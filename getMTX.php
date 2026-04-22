<?php
set_time_limit(0);
// // 證交所營業日期跟加權指收盤
for ($i = 0; $i < 3; $i++) {
    if ($i == 0) {
        $search_date[] = date('Ym01', time());
    } elseif ($i == 1) {
        $search_date[] = date('Ym01', strtotime('last month'));
    } elseif ($i > 1) {
        $search_date[] = date('Ym01', strtotime('last month ' . (1 - $i) . ' month'));
    }
}
$content = [];
$data = [];
$TWII = [];
foreach ($search_date as $k => $date) {
    $ch = curl_init();
    $option = [
        CURLOPT_URL => 'https://www.twse.com.tw/rwd/zh/TAIEX/MI_5MINS_HIST?date=' . $date,
        CURLOPT_RETURNTRANSFER => true
    ];
    curl_setopt_array($ch, $option);
    $content[$k] = json_decode(curl_exec($ch));
    curl_close($ch);
    $data = array_merge($data, $content[$k]->data);
}
foreach ($data as $v) {
    $roc_to_year = (int)explode('/', $v[0])[0] + 1911;
    $date_str = implode('/', [$roc_to_year, explode('/', $v[0])[1], explode('/', $v[0])[2]]);
    $TWII[$date_str] = (float)str_replace(',', '', $v[4]);
}
ksort($TWII);

foreach ($TWII as $k => $v) {
    $dates_to_scrape[] = $k;
}

// 小臺指未沖銷契約量 MTXOI
function get_taifex_mtx_data_limited(array $dates, int $max_concurrency = 5): array
{
    $url = "https://www.taifex.com.tw/cht/3/futDailyMarketReport";
    $results = [];

    $mh = curl_multi_init();
    $handle_map = [];
    $date_queue = array_reverse($dates);
    $active_handles = 0;
    while (count($date_queue) > 0 && $active_handles < $max_concurrency) {
        $date = array_pop($date_queue);
        $postData = [
            'queryType' => '2',
            'marketCode' => '0',
            'commodity_id' => 'MTX',
            'queryDate' => $date,
            'MarketCode' => '0',
            'commodity_idt' => 'MTX',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36");
        curl_multi_add_handle($mh, $ch);
        $key = spl_object_hash($ch);
        $handle_map[$key] = [
            'handle' => $ch,
            'date'   => $date,
        ];
        $active_handles++;
    }
    do {
        $mrc = curl_multi_exec($mh, $running);
        while ($done = curl_multi_info_read($mh)) {
            $ch_done = $done['handle'];
            $ch_key = spl_object_hash($ch_done);
            $item = $handle_map[$ch_key] ?? null;

            if ($item) {
                $date = $item['date'];
                $error = curl_error($ch_done);
                $response = curl_multi_getcontent($ch_done);
                if ($error || $response === null || empty($response)) {
                    $results[$date] = ['error' => 'cURL Error or Empty Response: ' . ($error ?: 'Response is empty/null.')];
                } else {
                    $MTXOI = null;
                    try {
                        if (isset(explode('小計:', $response)[1])) {
                            $segment = explode('小計:', $response)[1];
                            if (isset(explode('<TD align=right style="background-color:#ecf2f9" class="12bk">', $segment)[1])) {
                                $value_segment = explode('<TD align=right style="background-color:#ecf2f9" class="12bk">', $segment)[1];
                                $value_string = trim(explode(' ', trim($value_segment))[0]);
                                $MTXOI = (int)trim($value_string);
                            }
                        }
                        $results[$date] =  $MTXOI;
                    } catch (\Throwable $e) {
                        $results[$date] = ['error' => 'Parse Error: ' . $e->getMessage()];
                    }
                }
                curl_multi_remove_handle($mh, $ch_done);
                curl_close($ch_done);
                unset($handle_map[$ch_key]);
                $active_handles--;
            }
            if (count($date_queue) > 0) {
                $date_next = array_pop($date_queue);

                $postData = [
                    'queryDate' => $date_next,
                    'queryType' => '2',
                    'commodity_id' => 'MTX',
                    'MarketCode' => '0',
                    'commodity_idt' => 'MTX'
                ];
                $ch_next = curl_init();
                curl_setopt($ch_next, CURLOPT_URL, $url);
                curl_setopt($ch_next, CURLOPT_POST, true);
                curl_setopt($ch_next, CURLOPT_POSTFIELDS, http_build_query($postData));
                curl_setopt($ch_next, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_next, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch_next, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36");

                curl_multi_add_handle($mh, $ch_next);
                $key_next = spl_object_hash($ch_next);
                $handle_map[$key_next] = [
                    'handle' => $ch_next,
                    'date'   => $date_next,
                ];
                $active_handles++;
            }
        }

        if ($running > 0 || count($date_queue) > 0) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running > 0 || count($date_queue) > 0);
    curl_multi_close($mh);
    ksort($results);

    return $results;
}
$max_concurrency_limit = 60;
$MTXOI = get_taifex_mtx_data_limited($dates_to_scrape, $max_concurrency_limit);

// 小臺指主力未沖銷多空 MTXmain
function get_taifex_mtx_data_main(array $dates, int $max_concurrency = 5): array
{
    $url = "https://www.taifex.com.tw/cht/3/futContractsDate";
    $results = [];

    $mh = curl_multi_init();
    $handle_map = [];
    $date_queue = array_reverse($dates);
    $active_handles = 0;
    while (count($date_queue) > 0 && $active_handles < $max_concurrency) {
        $date = array_pop($date_queue);
        $postData = [
            'queryType' => '1',
            'doQuery' => '1',
            'commodityId' => 'MXF',
            'queryDate' => $date,
            'button' => '送出查詢',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36");
        curl_multi_add_handle($mh, $ch);
        $key = spl_object_hash($ch);
        $handle_map[$key] = [
            'handle' => $ch,
            'date'   => $date,
        ];
        $active_handles++;
    }
    do {
        $mrc = curl_multi_exec($mh, $running);
        while ($done = curl_multi_info_read($mh)) {
            $ch_done = $done['handle'];
            $ch_key = spl_object_hash($ch_done);
            $item = $handle_map[$ch_key] ?? null;

            if ($item) {
                $date = $item['date'];
                $error = curl_error($ch_done);
                $response = curl_multi_getcontent($ch_done);
                if ($error || $response === null || empty($response)) {
                    $results[$date] = ['error' => 'cURL Error or Empty Response: ' . ($error ?: 'Response is empty/null.')];
                } else {
                    $MTXOI = null;
                    try {
                        if (isset(explode('期貨合計', $response)[1])) {
                            $segment = explode('</span></B>', explode('</TR>', explode('期貨合計', $response)[1])[0]);
                            $save = (int)str_replace(',', '', trim(explode('<span class="blue"">', $segment[3])[1]));
                            $drop = (int)str_replace(',', '', trim(explode('<span class="blue">', $segment[4])[1]));
                        } else {
                            $save = 0;
                            $drop = 0;
                        }
                        $results[$date]['save'] = $save;
                        $results[$date]['drop'] = $drop;
                    } catch (\Throwable $e) {
                        $results[$date] = ['error' => 'Parse Error: ' . $e->getMessage()];
                    }
                }
                curl_multi_remove_handle($mh, $ch_done);
                curl_close($ch_done);
                unset($handle_map[$ch_key]);
                $active_handles--;
            }
            if (count($date_queue) > 0) {
                $date_next = array_pop($date_queue);
                $postData = [
                    'doQuery' => '1',
                    'commodityId' => 'MXF',
                    'button' => '送出查詢',
                    'queryDate' => $date_next,
                    'queryType' => '1',
                ];
                $ch_next = curl_init();
                curl_setopt($ch_next, CURLOPT_URL, $url);
                curl_setopt($ch_next, CURLOPT_POST, true);
                curl_setopt($ch_next, CURLOPT_POSTFIELDS, http_build_query($postData));
                curl_setopt($ch_next, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_next, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch_next, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36");

                curl_multi_add_handle($mh, $ch_next);
                $key_next = spl_object_hash($ch_next);
                $handle_map[$key_next] = [
                    'handle' => $ch_next,
                    'date'   => $date_next,
                ];
                $active_handles++;
            }
        }

        if ($running > 0 || count($date_queue) > 0) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running > 0 || count($date_queue) > 0);
    curl_multi_close($mh);
    ksort($results);

    return $results;
}
$max_concurrency_limit = 60;
$MTXmain = get_taifex_mtx_data_main($dates_to_scrape, $max_concurrency_limit);

// 合併計算輸出資料
$exponent = [];
foreach ($dates_to_scrape as $date) {
    if ($MTXmain[$date]['save'] != 0 && $MTXmain[$date]['drop'] != 0) {
        $exponent[$date]['percentage'] = (($MTXOI[$date] - $MTXmain[$date]['save']) - ($MTXOI[$date] - $MTXmain[$date]['drop'])) / $MTXOI[$date];
    } else {
        $exponent[$date]['percentage'] = 0;
    }
    $exponent[$date]['TWII'] = $TWII[$date];
}
echo json_encode($exponent);
