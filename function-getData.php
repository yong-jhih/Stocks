<?php

function getLatestTradingDateWithTWSE() // return string "YYYY-MM-DD"
{
    $url = "https://www.twse.com.tw/exchangeReport/FMTQIK?response=json";
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK') {
        $date = end($data['data'])[0];
        return convertTaiwanDateToWestern($date);
    } else {
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}

function getHistory($date) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/MI_INDEX?response=json&type=ALLBUT0999&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['tables'])) {
        foreach ($data['tables'] as $v) {
            if (str_contains($v['title'], "每日收盤行情") && is_array($v['data'])) {
                $stocks = [];
                $fields = $v['fields'];
                foreach ($v['data'] as $v1) {
                    if (preg_match('/^[1-9]\d{3}$/', $v1[0])) {
                        $item = [];
                        foreach ($fields as $k => $field) {
                            $item[$field] = str_replace(',', '', trim($v1[$k]));
                        }
                        $stocks[] = $item;
                    }
                }
                return $stocks;
            }
        }
        return ["status" => "error", "msg" => "查詢不到每日收盤行情表"];
    }
    return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
}

function getInsti($date) // return array
{
    $url = "https://www.twse.com.tw/fund/T86?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data'])) {
        if (str_contains($data['title'], "三大法人買賣超日報")) {
            $stocks = [];
            $fields = $data['fields'];
            foreach ($data['data'] as $v) {
                if (preg_match('/^[1-9]\d{3}$/', $v[0])) {
                    $item = [];
                    foreach ($fields as $k => $field) {
                        $item[$field] = str_replace(',', '', trim($v[$k]));
                    }
                    $stocks[] = $item;
                }
            }
            return $stocks;
        }
        return ["status" => "error", "msg" => "資料格式錯誤"];
    }
    return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
}

function getMargin($date) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/MI_MARGN?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['tables'])) {
        foreach ($data['tables'] as $v) {
            if (str_contains($v['title'], "融資融券彙總") && is_array($v['data'])) {
                $stocks = [];
                $fields = $v['fields'];
                foreach ($v['data'] as $row) {
                    if (preg_match('/^[1-9]\d{3}$/', $row[0])) {
                        $item = [];
                        foreach ($fields as $index => $fieldName) {
                            $cleanValue = str_replace(',', '', trim($row[$index]));
                            $item[$fieldName] = $cleanValue;
                        }
                        $stocks[] = $item;
                    }
                }
                return $stocks;
            }
        }
        return ["status" => "error", "msg" => "查詢不到融資融券彙總表"];
    } else {
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}
function getSBLTotal($date) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/TWT72U?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data'])) {
        if (str_contains($data['title'], "證金營業處所借券餘額合計表")) {
            $stocks = [];
            $fields = $data['fields'];
            foreach ($data['data'] as $row) {
                if (preg_match('/^[1-9]\d{3}$/', $row[0]) && $row[8] == '集中市場') {
                    $item = [];
                    foreach ($fields as $index => $fieldName) {
                        $item[$fieldName] = str_replace(',', '', trim($row[$index]));
                    }
                    $stocks[] = $item;
                }
            }
            return $stocks;
        } else {
            return ["status" => "error", "msg" => "資料格式錯誤"];
        }
    } else {
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}
function getSBLSold($date) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/TWT93U?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data'])) {
        if (str_contains($data['title'], "信用額度總量管制餘額")) {
            $stocks = [];
            $fields = $data['fields'];
            foreach ($data['data'] as $row) {
                if (preg_match('/^[1-9]\d{3}$/', $row[0])) {
                    $item = [];
                    foreach ($fields as $index => $fieldName) {
                        $item[$fieldName] = str_replace(',', '', trim($row[$index]));
                    }
                    $stocks[] = $item;
                }
            }
            return $stocks;
        } else {
            return ["status" => "error", "msg" => "資料格式錯誤"];
        }
    } else {
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}

function insertHistory($db_ip, $db_name, $db_user, $db_pass, $historyData)
{
    $dsn = "mysql:host=$db_ip;dbname=$db_name;charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        die("資料庫連線失敗：" . $e->getMessage());
    }
    $targetDate = getLatestTradingDateWithTWSE();
    if (is_array($targetDate)) die($targetDate['msg']);
    echo "處理日期：{$targetDate}\n";

    // --- 1. 每日收盤行情 ---
    // $historyData = getHistory($targetDate);
    if (is_array($historyData) && !isset($historyData['status'])) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO stock_history (trade_date, stock_id, stock_name, open_price, high_price, low_price, close_price, trade_volume) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($historyData as $row) {
            // 使用你 Function 裡已經處理好的 key 或是直接判斷
            $stmt->execute([
                $targetDate,
                $row['證券代號'] ?? $row['股票代號'] ?? '',
                $row['證券名稱'] ?? '',
                (float)($row['開盤價'] ?? 0),
                (float)($row['最高價'] ?? 0),
                (float)($row['最低價'] ?? 0),
                (float)($row['收盤價'] ?? 0),
                (int)($row['成交股數'] ?? 0)
            ]);
        }
        echo "1. 收盤行情處理完畢。\n";
    }

    // --- 2. 三大法人 ---
    // $instiData = getInsti($targetDate);
    // if (is_array($instiData) && !isset($instiData['status'])) {
    //     $stmt = $pdo->prepare("INSERT IGNORE INTO stock_insti (trade_date, stock_id, foreign_buy_sell, trust_buy_sell, dealer_buy_sell, total_buy_sell) VALUES (?, ?, ?, ?, ?, ?)");
    //     foreach ($instiData as $row) {
    //         $stmt->execute([
    //             $targetDate,
    //             $row['證券代號'] ?? '',
    //             (int)($row['外資買賣超股數'] ?? 0),
    //             (int)($row['投信買賣超股數'] ?? 0),
    //             (int)($row['自營商買賣超股數'] ?? 0),
    //             (int)($row['三大法人買賣超股數'] ?? 0)
    //         ]);
    //     }
    //     echo "2. 三大法人處理完畢。\n";
    // }

    // // --- 3. 融資融券 ---
    // $marginData = getMargin($targetDate);
    // if (is_array($marginData) && !isset($marginData['status'])) {
    //     $stmt = $pdo->prepare("INSERT IGNORE INTO stock_margin (trade_date, stock_id, margin_balance, short_balance) VALUES (?, ?, ?, ?)");
    //     foreach ($marginData as $row) {
    //         $stmt->execute([
    //             $targetDate,
    //             $row['股票代號'] ?? '',
    //             (int)($row['融資今日餘額'] ?? 0),
    //             (int)($row['融券今日餘額'] ?? 0)
    //         ]);
    //     }
    //     echo "3. 融資融券處理完畢。\n";
    // }

    // // --- 4. 借券餘額 (TWT72U) ---
    // $sblTotalData = getSBLTotal($targetDate);
    // if (is_array($sblTotalData) && !isset($sblTotalData['status'])) {
    //     $stmt = $pdo->prepare("INSERT IGNORE INTO stock_sbl_total (trade_date, stock_id, sbl_balance) VALUES (?, ?, ?)");
    //     foreach ($sblTotalData as $row) {
    //         $stmt->execute([
    //             $targetDate,
    //             $row['股票代碼'] ?? '',
    //             (int)($row['本日餘額'] ?? 0)
    //         ]);
    //     }
    //     echo "4. 借券餘額處理完畢。\n";
    // }

    // // --- 5. 借券賣出額度 (TWT93U) ---
    // $sblSoldData = getSBLSold($targetDate);
    // if (is_array($sblSoldData) && !isset($sblSoldData['status'])) {
    //     $stmt = $pdo->prepare("INSERT IGNORE INTO stock_sbl_sold (trade_date, stock_id, sbl_sold_balance) VALUES (?, ?, ?)");
    //     foreach ($sblSoldData as $row) {
    //         // 注意：TWT93U 的欄位名稱常有微調，加入多重判斷
    //         $val = $row['借券賣出本日餘額'] ?? $row['本日可借券賣出限額'] ?? 0;
    //         $stmt->execute([
    //             $targetDate,
    //             $row['證券代號'] ?? $row['股票代號'] ?? '',
    //             (int)$val
    //         ]);
    //     }
    //     echo "5. 借券賣出額度處理完畢。\n";
    // }
}
