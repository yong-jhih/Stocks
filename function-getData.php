<?php
function getLatestTradingDateWithTWSE() // return string "YYYY-MM-DD"
{
    $url = "https://www.twse.com.tw/exchangeReport/FMTQIK?response=json";
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK') {
        $rawDate = end($data['data'])[0];
        $cleanDate = str_replace('/', '', $rawDate);
        $convertedDate = convertTaiwanDateToWestern($cleanDate);
        if (!$convertedDate) return ["status" => "error", "msg" => "日期格式轉換失敗"];
        $latestDate = new DateTime($convertedDate);
        $today = new DateTime();
        $interval = $today->diff($latestDate);
        $daysDiff = $interval->days;
        $threshold = 10;
        if ($daysDiff > $threshold) return ["status" => "error", "msg" => "證交所資料異常：回傳日期 ($convertedDate) 與今日差距過大 ($daysDiff 天)"];
        return $convertedDate;
    } else {
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}

function getLatestTradingDateWithFugle($symbol = '2330') // return string "YYYY-MM-DD"
{
    $apiToken = getenv('FUGLE_TOKEN');
    if (!$apiToken) return ['status' => 'error', 'msg' => '找不到 Fugle Token 環境變數'];
    $url = "https://api.fugle.tw/marketdata/v1.0/stock/historical/stats/$symbol";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-API-KEY: $apiToken",
        "Accept: application/json"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['date'])) {
            return $data['date'];
        } else {
            return ['status' => 'error', 'msg' => '回傳格式異常'];
        }
    } else {
        return ['status' => 'error', 'msg' => "API 請求失敗，狀態碼：$httpCode"];
    }
}

function isHoliday($date) // return bool
{
    $url = "https://openapi.twse.com.tw/v1/holidaySchedule/holidaySchedule";
    $data = fetchUrl($url);
    $holiday = [];
    foreach ($data as $k => $v) {
        $holiday[] = convertTaiwanDateToWestern($v['Date']);
    }
    return in_array($date, $holiday);
}

function getHistory($date, $pdo) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/MI_INDEX?response=json&type=ALLBUT0999&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['tables'])) {
        foreach ($data['tables'] as $v) {
            if (str_contains($v['title'], "每日收盤行情") && is_array($v['data'])) {
                $stocks = [];
                foreach ($v['data'] as $v1) {
                    if (preg_match('/^[1-9]\d{3}$/', trim($v1[0]))) {
                        $stocks[] = $v1;
                    }
                }
                return $stocks;
            }
        }
        writeLog($pdo, $date . ' 上市個股日成交', "查詢不到每日收盤行情表", 'error');
        return ["status" => "error", "msg" => "查詢不到每日收盤行情表"];
    }
    writeLog($pdo, $date . ' 上市個股日成交', "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤'), 'error');
    return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
}

function getInsti($date, $pdo) // return array
{
    $url = "https://www.twse.com.tw/fund/T86?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data'])) {
        if (str_contains($data['title'], "三大法人買賣超日報")) {
            $stocks = [];
            foreach ($data['data'] as $v) {
                if (preg_match('/^[1-9]\d{3}$/', $v[0])) {
                    $stocks[] = $v;
                }
            }
            return $stocks;
        }
        writeLog($pdo, $date . ' 三大法人買賣超日報', "資料格式錯誤", 'error');
        return ["status" => "error", "msg" => "資料格式錯誤"];
    }
    writeLog($pdo, $date . ' 三大法人買賣超日報', "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤'), 'error');
    return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
}

function getMargin($date, $pdo) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/MI_MARGN?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['tables'])) {
        foreach ($data['tables'] as $v) {
            if (str_contains($v['title'], "融資融券彙總") && is_array($v['data'])) {
                $stocks = [];
                foreach ($v['data'] as $row) {
                    if (preg_match('/^[1-9]\d{3}$/', $row[0])) {
                        $stocks[] = $row;
                    }
                }
                return $stocks;
            }
        }
        writeLog($pdo, $date . ' 融資融券彙總', "查詢不到融資融券彙總表", 'error');
        return ["status" => "error", "msg" => "查詢不到融資融券彙總表"];
    } else {
        writeLog($pdo, $date . ' 融資融券彙總', "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤'), 'error');
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}
function getSBLTotal($date, $pdo) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/TWT72U?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data'])) {
        if (str_contains($data['title'], "證金營業處所借券餘額合計表")) {
            $stocks = [];
            foreach ($data['data'] as $row) {
                if (preg_match('/^[1-9]\d{3}$/', $row[0]) && $row[8] == '集中市場') {
                    $stocks[] = $row;
                }
            }
            return $stocks;
        } else {
            writeLog($pdo, $date . ' 證金營業處所借券餘額合計表', "資料格式錯誤", 'error');
            return ["status" => "error", "msg" => "資料格式錯誤"];
        }
    } else {
        writeLog($pdo, $date . ' 證金營業處所借券餘額合計表', "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤'), 'error');
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}
function getSBLSold($date, $pdo) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/TWT93U?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data'])) {
        if (str_contains($data['title'], "信用額度總量管制餘額")) {
            $stocks = [];
            foreach ($data['data'] as $row) {
                if (preg_match('/^[1-9]\d{3}$/', $row[0])) {
                    $stocks[] = $row;
                }
            }
            return $stocks;
        } else {
            writeLog($pdo, $date . ' 信用額度總量管制餘額', "資料格式錯誤", 'error');
            return ["status" => "error", "msg" => "資料格式錯誤"];
        }
    } else {
        writeLog($pdo, $date . ' 信用額度總量管制餘額', "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤'), 'error');
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}

function insertHistory($pdo, $targetDate, $historyData)
{
    if (!is_array($historyData) || isset($historyData['status'])) {
        writeLog($pdo, $targetDate . ' 上市個股日成交', "資料格式有誤或無資料", 'error');
        return;
    }
    $start_time = microtime(true);
    $sql = "INSERT INTO stock_history 
            (trade_date, stock_id, stock_name, open_price, high_price, low_price, close_price, trade_volume) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            stock_name = VALUES(stock_name),
            open_price = VALUES(open_price),
            high_price = VALUES(high_price),
            low_price = VALUES(low_price),
            close_price = VALUES(close_price),
            trade_volume = VALUES(trade_volume)";
    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction();
    try {
        foreach ($historyData as $row) {
            $clean = function ($v) {
                return str_replace(',', '', $v);
            };
            $stmt->execute([
                $targetDate,
                $row[0],
                $row[1],
                (float)$clean($row[5]), // 開盤
                (float)$clean($row[6]), // 最高
                (float)$clean($row[7]), // 最低
                (float)$clean($row[8]), // 收盤
                (int)$clean($row[2])    // 成交股數
            ]);
        }
        $pdo->commit();
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2); // 取小數點後兩位
        writeLog($pdo, 'insertHistory', $targetDate . '上市個股日成交更新完成,共新增 ' . count($historyData) . ' 筆,耗時 ' . $execution_time . ' 秒', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
        writeLog($pdo, '上市個股日成交', "寫入失敗：" . $e->getMessage(), 'error');
    }
}

function insertInsti($pdo, $targetDate, $instiData)
{
    if (!is_array($instiData) || isset($instiData['status'])) {
        writeLog($pdo, $targetDate . ' 三大法人買賣超', "資料格式有誤或無資料", 'error');
        return;
    }
    $start_time = microtime(true);
    $sql = "INSERT INTO stock_insti 
            (trade_date, stock_id, foreign_buy_sell, trust_buy_sell, dealer_buy_sell, total_buy_sell) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            foreign_buy_sell = VALUES(foreign_buy_sell),
            trust_buy_sell = VALUES(trust_buy_sell),
            dealer_buy_sell = VALUES(dealer_buy_sell),
            total_buy_sell = VALUES(total_buy_sell)";
    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction();
    try {
        foreach ($instiData as $row) {
            $clean = function ($v) {
                return str_replace(',', '', $v);
            };
            $stmt->execute([
                $targetDate,
                $row[0],
                (int)$clean($row[4]),  // 外資
                (int)$clean($row[10]), // 投信
                (int)$clean($row[11]), // 自營
                (int)$clean($row[18])  // 合計
            ]);
        }
        $pdo->commit();
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2); // 取小數點後兩位
        writeLog($pdo, 'insertInsti', $targetDate . '三大法人買賣超更新完成,共新增 ' . count($instiData) . ' 筆,耗時 ' . $execution_time . ' 秒', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
        writeLog($pdo, '三大法人買賣超', "寫入失敗：" . $e->getMessage(), 'error');
    }
}

function insertMargin($pdo, $targetDate, $marginData)
{
    if (!is_array($marginData) || isset($marginData['status'])) {
        writeLog($pdo, $targetDate . ' 融資融券彙總', "資料格式有誤或無資料", 'error');
        return;
    }
    $start_time = microtime(true);
    $sql = "INSERT INTO stock_margin 
            (trade_date, stock_id, margin_balance, margin_balance_diff, short_balance, short_balance_diff) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            margin_balance = VALUES(margin_balance),
            margin_balance_diff = VALUES(margin_balance_diff),
            short_balance = VALUES(short_balance),
            short_balance_diff = VALUES(short_balance_diff)";
    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction();
    try {
        foreach ($marginData as $row) {
            $clean = function ($v) {
                return (int)str_replace(',', '', $v);
            };
            $stmt->execute([
                $targetDate,
                $row[0],
                $clean($row[6]),                 // 融資餘額
                $clean($row[6]) - $clean($row[5]), // 融資增減
                $clean($row[12]),                // 融券餘額
                $clean($row[12]) - $clean($row[11]) // 融券增減
            ]);
        }
        $pdo->commit();
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2); // 取小數點後兩位
        writeLog($pdo, 'insertMargin', $targetDate . '融資融券彙總更新完成,共新增 ' . count($marginData) . ' 筆,耗時 ' . $execution_time . ' 秒', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
        writeLog($pdo, '融資融券彙總', "寫入失敗：" . $e->getMessage(), 'error');
    }
}

function insertSBLTotal($pdo, $targetDate, $SBLTotalData)
{
    if (!is_array($SBLTotalData) || isset($SBLTotalData['status'])) {
        writeLog($pdo, $targetDate . ' 借券餘額', "資料格式有誤或無資料", 'error');
        return;
    }
    $start_time = microtime(true);
    $sql = "INSERT INTO stock_sbl_total (trade_date, stock_id, sbl_balance) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE sbl_balance = VALUES(sbl_balance)";
    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction();
    try {
        foreach ($SBLTotalData as $row) {
            $stmt->execute([$targetDate, $row[0], (int)str_replace(',', '', $row[5])]);
        }
        $pdo->commit();
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2); // 取小數點後兩位
        writeLog($pdo, 'insertSBLTotal', $targetDate . '借券餘額更新完成,共新增 ' . count($SBLTotalData) . ' 筆,耗時 ' .  $execution_time . ' 秒', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "失敗：" . $e->getMessage();
        writeLog($pdo, '借券餘額', "寫入失敗：" . $e->getMessage(), 'error');
    }
}

function insertSBLSold($pdo, $targetDate, $SBLSoldData)
{
    if (!is_array($SBLSoldData) || isset($SBLSoldData['status'])) {
        writeLog($pdo, $targetDate . ' 借券賣出餘額', "資料格式有誤或無資料", 'error');
        return;
    }
    $start_time = microtime(true);
    $sql = "INSERT INTO stock_sbl_sold 
            (trade_date, stock_id, sbl_sold_balance, sbl_sold, sbl_return) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            sbl_sold_balance = VALUES(sbl_sold_balance),
            sbl_sold = VALUES(sbl_sold),
            sbl_return = VALUES(sbl_return)";
    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction();
    try {
        foreach ($SBLSoldData as $row) {
            $clean = function ($v) {
                return (int)str_replace(',', '', $v);
            };
            $stmt->execute([
                $targetDate,
                $row[0],
                $clean($row[12]), // 餘額
                $clean($row[9]),  // 賣出
                $clean($row[10])  // 還券
            ]);
        }
        $pdo->commit();
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2); // 取小數點後兩位
        writeLog($pdo, 'insertSBLSold', $targetDate . '借券賣出餘額更新完成,共新增 ' . count($SBLSoldData) . ' 筆,耗時 ' .  $execution_time . ' 秒', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "失敗：" . $e->getMessage();
        writeLog($pdo, '借券賣出餘額', "寫入失敗：" . $e->getMessage(), 'error');
    }
}
function generateDailyDashboard($pdo, $targetDate)
{
    $sql = "
    WITH BaseData AS (
        SELECT 
            h.trade_date, h.stock_id, h.stock_name, h.close_price, h.trade_volume, h.high_price, h.low_price,
            AVG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) as ma5,
            AVG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
            AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) as vma5,
            AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as vma20,
            MAX(h.high_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as high10,
            MIN(h.low_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as low10,
            SUM(i.total_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 0 PRECEDING AND CURRENT ROW) as insti_sum1,
            SUM(i.total_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) as insti_sum5,
            SUM(i.total_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as insti_sum10,
            SUM(i.total_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as insti_sum20,
            SUM(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 0 PRECEDING AND CURRENT ROW) as vol_sum1,
            SUM(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) as vol_sum5,
            SUM(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as vol_sum10,
            SUM(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as vol_sum20,
            m.margin_balance, m.margin_balance_diff, st.sbl_balance as sbl_total,
            ss.sbl_sold_balance, (ss.sbl_sold - ss.sbl_return) as net_sbl,
            i.trust_buy_sell, i.foreign_buy_sell,
            LAG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_vol,
            LAG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_close
        FROM stock_history h
        LEFT JOIN stock_insti i ON h.stock_id = i.stock_id AND h.trade_date = i.trade_date
        LEFT JOIN stock_margin m ON h.stock_id = m.stock_id AND h.trade_date = m.trade_date
        LEFT JOIN stock_sbl_total st ON h.stock_id = st.stock_id AND h.trade_date = st.trade_date
        LEFT JOIN stock_sbl_sold ss ON h.stock_id = ss.stock_id AND h.trade_date = ss.trade_date
    )
    SELECT * FROM BaseData 
    WHERE trade_date = :targetDate 
      AND vma20 > 700000 
      AND ((close_price - low10) / NULLIF(high10 - low10, 0)) BETWEEN 0.2 AND 0.9
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['targetDate' => $targetDate]);
    $rawStocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dashboardResults = [];
    foreach ($rawStocks as $s) {
        $tips = [];
        $tags = [];
        // --- 數值準備 ---
        $close = (float)$s['close_price'];
        $yClose = (float)$s['yesterday_close'];
        $vol = (int)$s['trade_volume'];
        $vma5 = (float)$s['vma5'];
        $vma20 = (float)$s['vma20'];
        $rank10 = (($s['close_price'] - $s['low10']) / max(1, ($s['high10'] - $s['low10']))) * 100;
        $volRatio = $s['yesterday_vol'] > 0 ? round($vol / $s['yesterday_vol'], 2) : 0;

        // 券力指標
        $squeeze = $vma20 > 0 ? round($s['sbl_sold_balance'] / ($vma20 / 1000), 1) : 0;
        $bullet = $vma5 > 0 ? round(($s['sbl_total'] - $s['sbl_sold_balance']) / ($vma5 / 1000), 1) : 0;

        // --- 標籤判定邏輯 (Tags) ---
        if ($s['margin_balance_diff'] < 0 && $s['trust_buy_sell'] > 0) $tags[] = "💎主力接散戶丟";
        if ($squeeze > 7 && $s['net_sbl'] < 0) $tags[] = "🔥高壓軋空";
        if ($bullet > 1.5) $tags[] = "💣法人備彈";

        // --- 提示判定邏輯 (Tips) ---
        if ($close > $yClose) {
            if ($squeeze > 8 && $s['net_sbl'] < 0) {
                $tips[] = "🚨強制軋空：法人被迫回補";
            } elseif ($volRatio > 1.5) {
                $tips[] = "🚀帶量突破：動能轉強";
            }
        }
        if ($rank10 < 40 && ($s['insti_sum1'] / max(1, $s['vol_sum1'])) > 0.05) {
            $tips[] = "✅低檔轉強：法人進場";
        }

        // --- AI 產業分析 ---
        // 建議在正式環境中，先檢查資料庫有沒有存過這檔股票的產業，沒有才呼叫 AI
        $concept = "搜尋中...";
        // $concept = callGeminiAI("請分析[{$s['stock_id']} {$s['stock_name']}]的產業概念...", 'gemini-1.5-flash');

        // --- 整合結果 ---
        $dashboardResults[] = [
            'stock_id'   => $s['stock_id'],
            'stock_name' => $s['stock_name'],
            'concept'    => $concept,
            'close'      => $close,
            'vol_k'      => round($vol / 1000, 0),
            'vol_ratio'  => $volRatio,
            'rank10'     => round($rank10, 1) . '%',
            'squeeze'    => $squeeze,
            'bullet'     => $bullet,
            'action_tip' => implode(" ", $tips) ?: "🔎震盪過濾",
            'tags'       => implode(",", $tags)
        ];
    }
    writeLog($pdo, 'Dashboard_Gen', "完成日期 $targetDate 分析，共篩選出 " . count($dashboardResults) . " 檔", 'Success');
    return $dashboardResults;
}

/**
 * 將分析後的 Dashboard 結果寫入資料庫
 * @param PDO $pdo 資料庫連線
 * @param string $targetDate 交易日期
 * @param array $dashboardResults generateDailyDashboard 回傳的陣列
 */
function saveDailyDashboard($pdo, $targetDate, $dashboardResults)
{
    if (empty($dashboardResults)) {
        writeLog($pdo, 'SaveDashboard', "日期 {$targetDate} 無資料可供寫入", 'Warning');
        return;
    }

    $start_time = microtime(true);

    $sql = "INSERT INTO daily_dashboard_results (
                trade_date, stock_id, stock_name, concept, close_price, 
                vol_k, vol_ratio, rank10, squeeze, bullet, action_tip, tags
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, ?
            ) ON DUPLICATE KEY UPDATE 
                stock_name = VALUES(stock_name),
                concept = VALUES(concept),
                close_price = VALUES(close_price),
                vol_k = VALUES(vol_k),
                vol_ratio = VALUES(vol_ratio),
                rank10 = VALUES(rank10),
                squeeze = VALUES(squeeze),
                bullet = VALUES(bullet),
                action_tip = VALUES(action_tip),
                tags = VALUES(tags)";

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);

        foreach ($dashboardResults as $row) {
            $stmt->execute([
                $targetDate,
                $row['stock_id'],
                $row['stock_name'],
                $row['concept'],
                $row['close'],
                $row['vol_k'],
                $row['vol_ratio'],
                $row['rank10'],
                $row['squeeze'],
                $row['bullet'],
                $row['action_tip'],
                $row['tags']
            ]);
        }

        $pdo->commit();

        $execution_time = round(microtime(true) - $start_time, 2);
        $count = count($dashboardResults);
        writeLog($pdo, 'SaveDashboard', "{$targetDate} 分析結果存檔完成，共 {$count} 筆，耗時 {$execution_time} 秒", 'Success');
    } catch (Exception $e) {
        $pdo->rollBack();
        writeLog($pdo, 'SaveDashboard', "寫入失敗：" . $e->getMessage(), 'Error');
        echo "Dashboard 存檔失敗：" . $e->getMessage();
    }
}

function testGenerateDailyDashboard($pdo, $targetDate)
{
    $sql = "
        WITH BaseData AS (
            SELECT 
                h.trade_date,
                h.stock_id,
                h.stock_name,
                h.close_price,
                h.trade_volume,
                h.high_price,
                h.low_price,
                AVG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING) as ma5,
                AVG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 10 PRECEDING AND 1 PRECEDING) as ma10,
                AVG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) as ma20,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING) as vma5,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 10 PRECEDING AND 1 PRECEDING) as vma10,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) as vma20,
                MAX(h.high_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as high10,
                MIN(h.low_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as low10,
                SUM(i.foreign_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) as foreign_sum5,
                SUM(i.foreign_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as foreign_sum10,
                SUM(i.foreign_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as foreign_sum20,
                SUM(i.trust_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) as trust_sum5,
                SUM(i.trust_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as trust_sum10,
                SUM(i.trust_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as trust_sum20,
                SUM(i.total_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 0 PRECEDING AND CURRENT ROW) as insti_sum1,
                SUM(i.total_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) as insti_sum5,
                SUM(i.total_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as insti_sum10,
                SUM(i.total_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as insti_sum20,
                SUM(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 0 PRECEDING AND CURRENT ROW) as vol_sum1,
                SUM(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) as vol_sum5,
                SUM(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as vol_sum10,
                SUM(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as vol_sum20,
                i.trust_buy_sell,
                i.foreign_buy_sell,
                m.margin_balance,
                m.margin_balance_diff,
                SUM(m.margin_balance_diff) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) as margin_balance_diff_sum5,
                SUM(m.margin_balance_diff) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as margin_balance_diff_sum10,
                SUM(m.margin_balance_diff) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as margin_balance_diff_sum20,
                st.sbl_balance as sbl_total,
                ss.sbl_sold_balance,
                (ss.sbl_sold - ss.sbl_return) as net_sbl,
                SUM(ss.sbl_sold - ss.sbl_return) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) as net_sbl_sum5,
                SUM(ss.sbl_sold - ss.sbl_return) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) as net_sbl_sum10,
                SUM(ss.sbl_sold - ss.sbl_return) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as net_sbl_sum20,
                LAG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_vol
            FROM stock_history h
            LEFT JOIN stock_insti i ON h.stock_id = i.stock_id AND h.trade_date = i.trade_date
            LEFT JOIN stock_margin m ON h.stock_id = m.stock_id AND h.trade_date = m.trade_date
            LEFT JOIN stock_sbl_total st ON h.stock_id = st.stock_id AND h.trade_date = st.trade_date
            LEFT JOIN stock_sbl_sold ss ON h.stock_id = ss.stock_id AND h.trade_date = ss.trade_date
        ),
        StreakGrouping AS (
            SELECT *,
                (ROW_NUMBER() OVER(PARTITION BY stock_id ORDER BY trade_date) - 
                 ROW_NUMBER() OVER(PARTITION BY stock_id, (CASE WHEN trust_buy_sell > 0 THEN 1 ELSE 0 END) ORDER BY trade_date)
                ) as t_grp,
                (ROW_NUMBER() OVER(PARTITION BY stock_id ORDER BY trade_date) - 
                 ROW_NUMBER() OVER(PARTITION BY stock_id, (CASE WHEN foreign_buy_sell > 0 THEN 1 ELSE 0 END) ORDER BY trade_date)
                ) as f_grp
            FROM BaseData
        ),
        ConsecutiveCalc AS (
            SELECT *,
                CASE WHEN trust_buy_sell > 0 THEN 
                    ROW_NUMBER() OVER(PARTITION BY stock_id, t_grp ORDER BY trade_date) 
                ELSE 0 END as trust_streak_days,
                CASE WHEN foreign_buy_sell > 0 THEN 
                    ROW_NUMBER() OVER(PARTITION BY stock_id, f_grp ORDER BY trade_date) 
                ELSE 0 END as foreign_streak_days
            FROM StreakGrouping
        )
        SELECT 
            stock_id as `代碼`,
            stock_name as `股名`,
            '待補' as `產業概念`,
            close_price as `收盤價`,
            ROUND(trade_volume / 1000, 0) as `成交量`,
            ROUND(trade_volume / NULLIF(yesterday_vol, 0), 2) as `昨量比`,
            CONCAT(ROUND(((close_price - low10) / NULLIF(high10 - low10, 0)) * 100, 2), '%') as `10日位階`,
            CONCAT(ROUND(((high10 - low10) / NULLIF(low10, 0)) * 100, 2), '%') as `10日振幅`,
            ROUND(ma5, 2) as `5日線`,
            ROUND(ma10, 2) as `10日線`,
            ROUND(ma20, 2) as `20日線`,
            ROUND(vma5 / 1000, 0) as `5日均量`,
            ROUND(vma10 / 1000, 0) as `10日均量`,
            ROUND(vma20 / 1000, 0) as `20日均量`,
            CONCAT(ROUND(((close_price - ma5) / NULLIF(ma5, 0)) * 100, 2), '%') as `5日乖離率`,
            CONCAT(ROUND(((close_price - ma10) / NULLIF(ma10, 0)) * 100, 2), '%') as `10日乖離率`,
            CONCAT(ROUND(((close_price - ma20) / NULLIF(ma20, 0)) * 100, 2), '%') as `20日乖離率`,
            CONCAT(ROUND((insti_sum1 / NULLIF(vol_sum1, 0)) * 100, 2), '%') as `1日集中度`,
            CONCAT(ROUND((insti_sum5 / NULLIF(vol_sum5, 0)) * 100, 2), '%') as `5日集中度`,
            CONCAT(ROUND((insti_sum10 / NULLIF(vol_sum10, 0)) * 100, 2), '%') as `10日集中度`,
            CONCAT(ROUND((insti_sum20 / NULLIF(vol_sum20, 0)) * 100, 2), '%') as `20日集中度`,
            margin_balance_diff as `融資`,
            margin_balance_diff_sum5 as `融資5日累計`,
            margin_balance_diff_sum10 as `融資10日累計`,
            margin_balance_diff_sum20 as `融資20日累計`,
            margin_balance as `融資餘額`,
            ROUND(foreign_sum5/1000,0) as `外資5日累計`,
            ROUND(foreign_sum10/1000,0) as `外資10日累計`,
            ROUND(foreign_sum20/1000,0) as `外資20日累計`,
            ROUND(trust_sum5/1000,0) as `投信5日累計`, 
            ROUND(trust_sum10/1000,0) as `投信10日累計`, 
            ROUND(trust_sum20/1000,0) as `投信20日累計`, 
            foreign_streak_days as `外資連買天數`,
            trust_streak_days as `投信連買天數`,
            ROUND(sbl_sold_balance/1000 / NULLIF(vma20 / 1000, 0), 1) as `券補力`,
            ROUND((sbl_total - sbl_sold_balance)/1000 / NULLIF(vma5 / 1000, 0), 1) as `券砸力`,
            ROUND(net_sbl/1000,0) as `券淨賣還`,
            ROUND(net_sbl_sum5/1000,0) as `券淨賣還5日累計`,
            ROUND(net_sbl_sum10/1000,0) as `券淨賣還10日累計`,
            ROUND(net_sbl_sum20/1000,0) as `券淨賣還20日累計`,
            ROUND(sbl_total/1000,0) as `借券餘額`,
            ROUND(sbl_sold_balance/1000,0) as `借券賣出餘額`
        FROM ConsecutiveCalc
        WHERE trade_date = :targetDate
            AND vma20 > 700000
            AND (
                (trade_volume > vma5 AND vma5 >= vma20) 
                OR 
                (trade_volume > vma20 * 1.3)
            )
            AND yesterday_vol < vma5
            AND (
                (trade_volume < vma20 AND (insti_sum1 / NULLIF(vol_sum1, 0)) > 0.06)
                OR
                (trade_volume >= vma20 AND (insti_sum1 / NULLIF(vol_sum1, 0)) > 0.08)
            )
            AND ((high10 - low10) / NULLIF(low10, 0)) > 0.01
            AND ((close_price - low10) / NULLIF(high10 - low10, 0)) BETWEEN 0.2 AND 0.9
            AND (insti_sum5 / NULLIF(vol_sum5, 0)) > 0.03;
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['targetDate' => $targetDate]);
    $rawStocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dashboardResults = [];
    foreach ($rawStocks as $s) {

        //     $tips = [];
        //     $tags = [];
        //     // --- 數值準備 ---
        //     $close = (float)$s['close_price'];
        //     $yClose = (float)$s['yesterday_close'];
        //     $vol = (int)$s['trade_volume'];
        //     $vma5 = (float)$s['vma5'];
        //     $vma20 = (float)$s['vma20'];
        //     $rank10 = (($s['close_price'] - $s['low10']) / max(1, ($s['high10'] - $s['low10']))) * 100;
        //     $volRatio = $s['yesterday_vol'] > 0 ? round($vol / $s['yesterday_vol'], 2) : 0;

        //     // 券力指標
        //     $squeeze = $vma20 > 0 ? round($s['sbl_sold_balance'] / ($vma20 / 1000), 1) : 0;
        //     $bullet = $vma5 > 0 ? round(($s['sbl_total'] - $s['sbl_sold_balance']) / ($vma5 / 1000), 1) : 0;

        //     // --- 標籤判定邏輯 (Tags) ---
        //     if ($s['margin_balance_diff'] < 0 && $s['trust_buy_sell'] > 0) $tags[] = "💎主力接散戶丟";
        //     if ($squeeze > 7 && $s['net_sbl'] < 0) $tags[] = "🔥高壓軋空";
        //     if ($bullet > 1.5) $tags[] = "💣法人備彈";

        //     // --- 提示判定邏輯 (Tips) ---
        //     if ($close > $yClose) {
        //         if ($squeeze > 8 && $s['net_sbl'] < 0) {
        //             $tips[] = "🚨強制軋空：法人被迫回補";
        //         } elseif ($volRatio > 1.5) {
        //             $tips[] = "🚀帶量突破：動能轉強";
        //         }
        //     }
        //     if ($rank10 < 40 && ($s['insti_sum1'] / max(1, $s['vol_sum1'])) > 0.05) {
        //         $tips[] = "✅低檔轉強：法人進場";
        //     }

        //     // --- AI 產業分析 ---
        //     // 建議在正式環境中，先檢查資料庫有沒有存過這檔股票的產業，沒有才呼叫 AI
        //     $concept = "搜尋中...";
        //     // $concept = callGeminiAI("請分析[{$s['stock_id']} {$s['stock_name']}]的產業概念...", 'gemini-1.5-flash');

        //     // --- 整合結果 ---
        $dashboardResults[] = [
            'stock_id'   => $s['代碼'],
            'stock_name' => $s['股名']
            // 'concept'    => $concept,
            // 'close'      => $close,
            // 'vol_k'      => round($vol / 1000, 0),
            // 'vol_ratio'  => $volRatio,
            // 'rank10'     => round($rank10, 1) . '%',
            // 'squeeze'    => $squeeze,
            // 'bullet'     => $bullet,
            // 'action_tip' => implode(" ", $tips) ?: "🔎震盪過濾",
            // 'tags'       => implode(",", $tags)
        ];
    }
    echo json_encode($dashboardResults);
    // writeLog($pdo, 'Dashboard_Gen', "完成日期 $targetDate 分析，共篩選出 " . count($dashboardResults) . " 檔", 'Success');
    // return $dashboardResults;
}

function testSaveDailyDashboard($pdo, $targetDate, $dashboardResults)
{
    if (empty($dashboardResults)) {
        writeLog($pdo, 'SaveDashboard', "日期 {$targetDate} 無資料可供寫入", 'Warning');
        return;
    }

    $start_time = microtime(true);

    $sql = "INSERT INTO daily_dashboard_results (
                trade_date, stock_id, stock_name, concept, close_price, 
                vol_k, vol_ratio, rank10, squeeze, bullet, action_tip, tags
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, ?
            ) ON DUPLICATE KEY UPDATE 
                stock_name = VALUES(stock_name),
                concept = VALUES(concept),
                close_price = VALUES(close_price),
                vol_k = VALUES(vol_k),
                vol_ratio = VALUES(vol_ratio),
                rank10 = VALUES(rank10),
                squeeze = VALUES(squeeze),
                bullet = VALUES(bullet),
                action_tip = VALUES(action_tip),
                tags = VALUES(tags)";

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);

        foreach ($dashboardResults as $row) {
            $stmt->execute([
                $targetDate,
                $row['stock_id'],
                $row['stock_name'],
                $row['concept'],
                $row['close'],
                $row['vol_k'],
                $row['vol_ratio'],
                $row['rank10'],
                $row['squeeze'],
                $row['bullet'],
                $row['action_tip'],
                $row['tags']
            ]);
        }

        $pdo->commit();

        $execution_time = round(microtime(true) - $start_time, 2);
        $count = count($dashboardResults);
        writeLog($pdo, 'SaveDashboard', "{$targetDate} 分析結果存檔完成，共 {$count} 筆，耗時 {$execution_time} 秒", 'Success');
    } catch (Exception $e) {
        $pdo->rollBack();
        writeLog($pdo, 'SaveDashboard', "寫入失敗：" . $e->getMessage(), 'Error');
        echo "Dashboard 存檔失敗：" . $e->getMessage();
    }
}
