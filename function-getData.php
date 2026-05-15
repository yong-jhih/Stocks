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
                h.trade_date,
                h.stock_id,
                h.stock_name,
                h.open_price,
                h.close_price,
                h.trade_volume,
                h.high_price,
                h.low_price,
                AVG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING) as ma5,
                AVG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 10 PRECEDING AND 1 PRECEDING) as ma10,
                AVG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) as ma20,
                AVG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 60 PRECEDING AND 1 PRECEDING) as ma60,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING) as vma5,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 10 PRECEDING AND 1 PRECEDING) as vma10,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) as vma20,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 60 PRECEDING AND 1 PRECEDING) as vma60,
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
                LAG(i.trust_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_trust_buy_sell,
                LAG(i.foreign_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_foreign_buy_sell,
                LAG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_vol,
                LAG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_close,
                LAG(h.open_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_open,
                LAG(h.high_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_high,
                LAG(h.low_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_low
            FROM stock_history h
            LEFT JOIN stock_insti i ON h.stock_id = i.stock_id AND h.trade_date = i.trade_date
            LEFT JOIN stock_margin m ON h.stock_id = m.stock_id AND h.trade_date = m.trade_date
            LEFT JOIN stock_sbl_total st ON h.stock_id = st.stock_id AND h.trade_date = st.trade_date
            LEFT JOIN stock_sbl_sold ss ON h.stock_id = ss.stock_id AND h.trade_date = ss.trade_date
        ),
        StreakGrouping AS (
            SELECT *,
                LAG(ma5) OVER(PARTITION BY stock_id ORDER BY trade_date) as prev_ma5,
                LAG(ma10) OVER(PARTITION BY stock_id ORDER BY trade_date) as prev_ma10,
                LAG(ma20) OVER(PARTITION BY stock_id ORDER BY trade_date) as prev_ma20,
                LAG(ma60) OVER(PARTITION BY stock_id ORDER BY trade_date) as prev_ma60,
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
            open_price as `開盤價`,
            high_price as `高價`,
            low_price as `低價`,
            close_price as `收盤價`,
            yesterday_open as `昨日開盤價`,
            yesterday_high as `昨日高價`,
            yesterday_low as `昨日低價`,
            yesterday_close as `昨日收盤價`,
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
            foreign_buy_sell as `外資買賣超`,
            yesterday_foreign_buy_sell as `昨日外資買賣超`,
            ROUND(foreign_sum5/1000,0) as `外資5日累計`,
            ROUND(foreign_sum10/1000,0) as `外資10日累計`,
            ROUND(foreign_sum20/1000,0) as `外資20日累計`,
            trust_buy_sell as `投信買賣超`,
            yesterday_trust_buy_sell as `昨日投信買賣超`,
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
            ROUND(sbl_sold_balance/1000,0) as `借券賣出餘額`,
            ROUND(((ma5 - prev_ma5) / NULLIF(prev_ma5, 0)) * 100, 2) as `5日線斜率`,
            ROUND(((ma10 - prev_ma10) / NULLIF(prev_ma10, 0)) * 100, 2) as `10日線斜率`,
            ROUND(((ma20 - prev_ma20) / NULLIF(prev_ma20, 0)) * 100, 2) as `20日線斜率`,
            ROUND(((ma60 - prev_ma60) / NULLIF(prev_ma60, 0)) * 100, 2) as `60日線斜率`
        FROM ConsecutiveCalc
        WHERE trade_date = :targetDate
            AND vma20 > 700000
            AND (
                (trade_volume > vma5 AND vma5 >= vma20) 
                OR 
                (trade_volume > vma20 * 1.3)
            )
            AND yesterday_vol < vma5
            AND ABS(yesterday_close - yesterday_open) / NULLIF(yesterday_open, 0) < 0.03
            AND (yesterday_high - yesterday_low) / NULLIF(yesterday_low, 0) < 0.035
            AND yesterday_close > ma20
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
        $tag = [];
        if ($s['收盤價'] > $s['5日線'] && $s['5日線'] > $s['10日線'] && $s['10日線'] > $s['20日線'] && $s['5日線斜率'] > 0 && $s['10日線斜率'] > 0 && $s['20日線斜率'] > 0) $tag[] = "嚴格多頭";
        if ($s['收盤價'] > $s['5日線'] && $s['收盤價'] > $s['10日線'] && $s['收盤價'] > $s['20日線'] && $s['昨日收盤價'] < $s['20日線']) $tag[] = "三陽開泰";
        if ($s['收盤價'] / $s['昨日收盤價'] > 1.03 && $s['昨量比'] > 1.5) $tag[] = "價量齊揚";
        if ($s['昨量比'] < 0.7 && abs($s['收盤價'] / $s['昨日收盤價'] - 1) < 0.01) $tag[] = "量縮價穩";
        if ((float)$s['5日乖離率'] > 7) $tag[] = "高檔乖離";
        if ($s['20日線斜率'] > 0 && $s['60日線斜率'] <= 0) $tag[] = "生命線轉揚";
        if ((float)$s['20日集中度'] > 10) $tag[] = "法人鎖碼";
        if ($s['投信連買天數'] >= 3 && $s['投信5日累計'] > 0) $tag[] = "投信認養";
        $denominator = $s['5日均量'] * 5;
        $trust_ratio_5d = ($denominator == 0) ? null : ($s['投信5日累計'] / $denominator);
        if ($s['投信連買天數'] >= 5 && $trust_ratio_5d > 0.03) {
            $tag[] = "投信作帳股";
        }
        // if (floatval($s['10日振幅']) < 8 && $s['5日均量'] < $s['20日均量']) $tag[] = "整理末端";
        // if ($s['昨量比'] > 2 && $s['收盤價'] > $s['昨日高價'] && $s['收盤價'] < $s['高價'] * 0.97) $tag[] = "假突破";
        // if (($s['5日線'] - $s['10日線']) / $s['10日線'] > 0.03 && ($s['10日線'] - $s['20日線']) / $s['20日線'] > 0.03 && (float)$s['5日乖離率'] > 5) $tag[] = "均線發散";
        // if ($s['昨量比'] > 1.5 && $s['收盤價'] > $s['5日線'] && $s['收盤價'] > $s['昨日高價']) $tag[] = "爆量突破";
        // if ($s['昨量比'] > 2 && ($s['收盤價'] / $s['昨日收盤價']) > 1.04) $tag[] = "強勢突破";
        // if ($s['外資5日累計'] > 500 && $s['外資5日累計'] > abs($s['外資20日累計'])) $tag[] = "法人急買";
        // if ((float)$s['10日位階'] > 80 && abs($s['收盤價'] / $s['昨日收盤價'] - 1) < 0.02) $tag[] = "高檔震盪";
        // if ((float)$s['10日位階'] > 85 && $s['20日乖離率'] > 12) $tag[] = "短線過熱";
        // if ($s['外資買賣超'] > 0 && $s['投信買賣超'] < 0) $tag[] = "法人分歧";
        // if ($s['昨量比'] > 2 && abs($s['收盤價'] / $s['昨日收盤價'] - 1) < 0.01 && $s['收盤價'] < $s['昨日高價']) $tag[] = "爆量不漲";
        // if ($s['收盤價'] / $s['昨日收盤價'] < 0.97 && $s['昨量比'] > 1.5) $tag[] = "爆量長黑";
        // if ($s['收盤價'] < $s['20日線'] && $s['昨日收盤價'] >= $s['20日線']) $tag[] = "跌破生命線";
        // if ($s['外資5日累計'] < 0 && $s['投信5日累計'] < 0) $tag[] = "法人倒貨";
        // if ($s['融資5日累計'] > 0 && $s['收盤價'] < $s['10日線'] && $s['外資5日累計'] <= 0) $tag[] = "融資失控";
        // if ($s['券淨賣還5日累計'] > 0 && $s['券砸力'] > 3 && $s['收盤價'] < $s['5日線']) $tag[] = "空方加壓";
        // if ($s['昨量比'] < 0.8 && (float)$s['5日集中度'] > 5 && abs($s['收盤價'] / $s['昨日收盤價'] - 1) < 0.015) $tag[] = "洗盤吸籌";
        // if ($s['外資連買天數'] >= 3 && $s['昨量比'] < 1 && $s['收盤價'] > $s['20日線']) $tag[] = "偷偷吃貨";
        // if ((float)$s['20日集中度'] > 15 && $s['券補力'] > 3) $tag[] = "籌碼鎖死";
        // if ($s['券補力'] > 8 && $s['收盤價'] > $s['5日線']) $tag[] = "軋空預備";
        // if ($s['收盤價'] > $s['5日線'] && $s['5日線'] > $s['10日線'] && $s['昨量比'] > 1.8 && (float)$s['5日集中度'] > 8) $tag[] = "主升段啟動";
        // if ($s['收盤價'] > $s['20日線'] && $s['5日線斜率'] < $s['10日線斜率'] && $s['10日線斜率'] < $s['20日線斜率']) $tag[] = "多頭鈍化";
        // if ($s['昨量比'] > 2 && $s['收盤價'] / $s['昨日收盤價'] < 1.01 && $s['外資買賣超'] < 0) $tag[] = "爆量出貨";
        // if ($s['外資連買天數'] >= 5 && $s['外資買賣超'] < 0 && abs($s['外資買賣超']) > ($s['外資5日累計'] * 0.3)) $tag[] = "法人落跑";
        // if ($s['收盤價'] > $s['20日線'] && $s['5日線斜率'] > 0 && $s['20日線斜率'] < 0 && $s['昨量比'] > 1.5) $tag[] = "底部甦醒";
        // if ((float)$s['10日位階'] > 80 && $s['收盤價'] < $s['5日線'] && $s['外資買賣超'] < 0) $tag[] = "高檔轉弱";
        // if ($s['5日線斜率'] < 0 && $s['10日線斜率'] < 0 && $s['收盤價'] < $s['10日線']) $tag[] = "短線轉空";
        if ($s['投信連買天數'] == 1 && $s['昨日投信買賣超'] <= 0 && $s['投信買賣超'] > 100) $tag[] = "投信試單";
        if ($s['外資5日累計'] > abs($s['外資20日累計'] * 0.3) && $s['外資20日累計'] < 0) $tag[] = "外資回補";
        if ($s['外資連買天數'] > 0 && $s['投信連買天數'] > 0) $tag[] = "土洋合力";
        if ((float)$s['5日集中度'] > (float)$s['20日集中度']) $tag[] = "籌碼趨於集中";
        if ($s['券補力'] > 5) $tag[] = "潛在軋空";
        if ($s['融資'] < 0 && ($s['收盤價'] / $s['昨日收盤價']) >= 1) $tag[] = "主力換手";
        if ($s['融資'] > 0 && $s['券淨賣還'] > 0) $tag[] = "資券同增";
        if ($s['券淨賣還5日累計'] < 0) $tag[] = "借券回補";
        // if (in_array('爆量長黑', $tag)) $tag = array_diff($tag, ['主升段啟動', '均線發散']);
        // if (in_array('高檔震盪', $tag)) $tag = array_diff($tag, ['主升段啟動']);
        $prompt = "請幫我分析[" . $s['代碼'] . $s['股名'] . "]的產業別(使用證交所產業別分類)及佔營業收入20%以上相關的概念股標籤，請依格式回答不要多餘的內容及符號，格式嚴格限定:'XXX業-標籤1,標籤2,標籤3,...'。請搜尋最新的公開資訊觀測站或法人券商研究報告，以確保營收佔比數據的準確性。";
        $concept = callGeminiAI(getenv('GEMINI_TOKEN'), $prompt, 'gemini-3.1-flash-lite-preview');
        $dashboardResults[] = [
            'stock_id'   => $s['代碼'],
            'stock_name' => $s['股名'],
            'concept'    => $concept,
            'close'      => $s['收盤價'],
            'vol'      => $s['成交量'],
            'vol_ratio'  => $s['昨量比'],
            'rank10'     => $s['10日位階'],
            'amp10'     => $s['10日振幅'],
            'ma5'     => $s['5日線'],
            'ma10'     => $s['10日線'],
            'ma20'     => $s['20日線'],
            'vma5'     => $s['5日均量'],
            'vma10'     => $s['10日均量'],
            'vma20'     => $s['20日均量'],
            'bia5'     => $s['5日乖離率'],
            'bia10'     => $s['10日乖離率'],
            'bia20'     => $s['20日乖離率'],
            'con1'     => $s['1日集中度'],
            'con5'     => $s['5日集中度'],
            'con10'     => $s['10日集中度'],
            'con20'     => $s['20日集中度'],
            'margin_balance_diff' => $s['融資'],
            'margin_balance_diff_sum5' => $s['融資5日累計'],
            'margin_balance_diff_sum10' => $s['融資10日累計'],
            'margin_balance_diff_sum20' => $s['融資20日累計'],
            'margin_balance' => $s['融資餘額'],
            'foreign_sum5' => $s['外資5日累計'],
            'foreign_sum10' => $s['外資10日累計'],
            'foreign_sum20' => $s['外資20日累計'],
            'trust_sum5' => $s['投信5日累計'],
            'trust_sum10' => $s['投信10日累計'],
            'trust_sum20' => $s['投信20日累計'],
            'foreign_streak_days' => $s['外資連買天數'],
            'trust_streak_days' => $s['投信連買天數'],
            'foreign_buy_sell' => $s['外資買賣超'],
            'trust_buy_sell' => $s['投信買賣超'],
            'squeeze' => $s['券補力'],
            'bullet' => $s['券砸力'],
            'net_sbl' => $s['券淨賣還'],
            'net_sbl_sum5' => $s['券淨賣還5日累計'],
            'net_sbl_sum10' => $s['券淨賣還10日累計'],
            'net_sbl_sum20' => $s['券淨賣還20日累計'],
            'sbl_total' => $s['借券餘額'],
            'sbl_sold_balance' => $s['借券賣出餘額'],
            'tags' => implode(',', $tag)
        ];
    }
    writeLog($pdo, 'generateDailyDashboard', "$targetDate 分析完成，共篩選出 " . count($dashboardResults) . " 檔", 'success');
    return $dashboardResults;
}

function saveDailyDashboard($pdo, $targetDate, $dashboardResults)
{
    if (empty($dashboardResults)) {
        writeLog($pdo, 'SaveDashboard', "日期 {$targetDate} 無資料可供寫入", 'Warning');
        return;
    }
    $start_time = microtime(true);
    $sql = "INSERT INTO daily_dashboard_results (
                trade_date, stock_id, stock_name, concept, close_price, 
                vol_k, vol_ratio, rank10, amp10, ma5, ma10, ma20, 
                vma5, vma10, vma20, bia5, bia10, bia20, 
                con1, con5, con10, con20, 
                margin_balance_diff, margin_balance_diff_sum5, margin_balance_diff_sum10, margin_balance_diff_sum20, margin_balance, 
                foreign_sum5, foreign_sum10, foreign_sum20, trust_sum5, trust_sum10, trust_sum20, 
                foreign_streak_days, trust_streak_days, 
                squeeze, bullet, net_sbl, net_sbl_sum5, net_sbl_sum10, net_sbl_sum20, 
                sbl_total, sbl_sold_balance, action_tip, tags
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, 
                ?, ?, 
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?
            ) ON DUPLICATE KEY UPDATE 
                stock_name = VALUES(stock_name),
                concept = VALUES(concept),
                close_price = VALUES(close_price),
                vol_k = VALUES(vol_k),
                vol_ratio = VALUES(vol_ratio),
                rank10 = VALUES(rank10),
                amp10 = VALUES(amp10),
                ma5 = VALUES(ma5),
                ma10 = VALUES(ma10),
                ma20 = VALUES(ma20),
                vma5 = VALUES(vma5),
                vma10 = VALUES(vma10),
                vma20 = VALUES(vma20),
                bia5 = VALUES(bia5),
                bia10 = VALUES(bia10),
                bia20 = VALUES(bia20),
                con1 = VALUES(con1),
                con5 = VALUES(con5),
                con10 = VALUES(con10),
                con20 = VALUES(con20),
                margin_balance_diff = VALUES(margin_balance_diff),
                margin_balance_diff_sum5 = VALUES(margin_balance_diff_sum5),
                margin_balance_diff_sum10 = VALUES(margin_balance_diff_sum10),
                margin_balance_diff_sum20 = VALUES(margin_balance_diff_sum20),
                margin_balance = VALUES(margin_balance),
                foreign_sum5 = VALUES(foreign_sum5),
                foreign_sum10 = VALUES(foreign_sum10),
                foreign_sum20 = VALUES(foreign_sum20),
                trust_sum5 = VALUES(trust_sum5),
                trust_sum10 = VALUES(trust_sum10),
                trust_sum20 = VALUES(trust_sum20),
                foreign_streak_days = VALUES(foreign_streak_days),
                trust_streak_days = VALUES(trust_streak_days),
                squeeze = VALUES(squeeze),
                bullet = VALUES(bullet),
                net_sbl = VALUES(net_sbl),
                net_sbl_sum5 = VALUES(net_sbl_sum5),
                net_sbl_sum10 = VALUES(net_sbl_sum10),
                net_sbl_sum20 = VALUES(net_sbl_sum20),
                sbl_total = VALUES(sbl_total),
                sbl_sold_balance = VALUES(sbl_sold_balance),
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
                $row['vol'],
                $row['vol_ratio'],
                $row['rank10'],
                $row['amp10'],    // 新增
                $row['ma5'],      // 新增
                $row['ma10'],     // 新增
                $row['ma20'],     // 新增
                $row['vma5'],     // 新增
                $row['vma10'],    // 新增
                $row['vma20'],    // 新增
                $row['bia5'],     // 新增
                $row['bia10'],    // 新增
                $row['bia20'],    // 新增
                $row['con1'],     // 新增
                $row['con5'],     // 新增
                $row['con10'],    // 新增
                $row['con20'],    // 新增
                $row['margin_balance_diff'],      // 新增
                $row['margin_balance_diff_sum5'], // 新增
                $row['margin_balance_diff_sum10'], // 新增
                $row['margin_balance_diff_sum20'], // 新增
                $row['margin_balance'],           // 新增
                $row['foreign_sum5'],             // 新增
                $row['foreign_sum10'],            // 新增
                $row['foreign_sum20'],            // 新增
                $row['trust_sum5'],               // 新增
                $row['trust_sum10'],              // 新增
                $row['trust_sum20'],              // 新增
                $row['foreign_streak_days'],      // 新增
                $row['trust_streak_days'],        // 新增
                $row['squeeze'],
                $row['bullet'],
                $row['net_sbl'],                  // 新增
                $row['net_sbl_sum5'],             // 新增
                $row['net_sbl_sum10'],            // 新增
                $row['net_sbl_sum20'],            // 新增
                $row['sbl_total'],                // 新增
                $row['sbl_sold_balance'],         // 新增
                $row['action_tip'] ?? '',         // 預留欄位賦予空值
                $row['tags'] ?? ''                // 預留欄位賦予空值
            ]);
        }
        $pdo->commit();
        $execution_time = round(microtime(true) - $start_time, 2);
        $count = count($dashboardResults);
        writeLog($pdo, 'saveDailyDashboard', "{$targetDate} 分析結果存檔完成，共 {$count} 筆", 'success');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        writeLog($pdo, 'SaveDashboard', "寫入失敗：" . $e->getMessage(), 'Error');
        echo "Dashboard 存檔失敗：" . $e->getMessage();
    }
}

function getComponentOf00981A_FromLocal()
{
    $jsonFile = 'stock_data.json';
    if (file_exists($jsonFile)) {
        $jsonStr = file_get_contents($jsonFile);
        $data = json_decode($jsonStr, true);
        if ($data) {
            foreach ($data as $item) {
                if ($item['AssetName'] === '股票') {
                    $details = $item['Details'];
                    $value = (int)$item['Value'];
                }
            }

            $todayStr = date('Y-m-d');
            $isAllUpdated = true;
            $totalAmount = 0;
            foreach ($details as $detail) {
                $itemDate = substr($detail['EditTime'], 0, 10);
                if ($itemDate !== $todayStr) {
                    $isAllUpdated = false;
                    break;
                }
                $totalAmount += (int)$detail['Amount'];
            }
            if (isset($value) && $value !== $totalAmount) return null;
            if (!$isAllUpdated) return null;
            return $details;
        }
        return null;
    }
}

function insertComponentOf00981A($pdo, $targetDate, $Data)
{
    if (!is_array($Data) || empty($Data)) {
        writeLog($pdo, $targetDate . ' 00981A成分股抓取', "資料格式有誤或無資料", 'error');
        exit(1);
    }
    try {
        $sql = "INSERT INTO 00981A_component 
                (trade_date, stock_id, stock_name, amount, weight) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                stock_name = VALUES(stock_name),
                amount = VALUES(amount),
                weight = VALUES(weight)";
        $stmt = $pdo->prepare($sql);
        $pdo->beginTransaction();
        foreach ($Data as $row) {
            $stmt->execute([
                $targetDate,
                $row['DetailCode'],
                $row['DetailName'],
                (int)$row['Share'],
                $row['NavRate']
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        writeLog($pdo, $targetDate . ' 00981A成分股抓取', "抓取失敗: " . $e->getMessage(), 'error');
        exit(1);
    }
}

function analyzeMultiPeriodChanges($pdo, $targetDate)
{
    try {
        $intervals = [1, 5, 10, 20];
        $compareDates = [];
        foreach ($intervals as $days) {
            $dateSql = "SELECT DISTINCT trade_date FROM 00981A_component 
                        WHERE trade_date < :targetDate ORDER BY trade_date DESC LIMIT :offset, 1";
            $dateStmt = $pdo->prepare($dateSql);
            $dateStmt->bindValue(':targetDate', $targetDate, PDO::PARAM_STR);
            $dateStmt->bindValue(':offset', (int)($days - 1), PDO::PARAM_INT);
            $dateStmt->execute();
            $found = $dateStmt->fetchColumn();
            $compareDates[$days] = $found ?: '1900-01-01';
        }
        $sql = "
            SELECT 
                all_ids.stock_id,
                MAX(COALESCE(curr.stock_name, d1.stock_name, d5.stock_name, d10.stock_name, d20.stock_name)) as stock_name,
                MAX(IFNULL(curr.amount, 0)) as amount,
                MAX(IFNULL(curr.weight, 0)) as weight,
                MAX(IFNULL(d1.amount, 0)) as prev_amount,
                (MAX(IFNULL(curr.amount, 0)) - MAX(IFNULL(d1.amount, 0))) as diff1,
                (MAX(IFNULL(curr.amount, 0)) - MAX(IFNULL(d5.amount, 0))) as diff5,
                (MAX(IFNULL(curr.amount, 0)) - MAX(IFNULL(d10.amount, 0))) as diff10,
                (MAX(IFNULL(curr.amount, 0)) - MAX(IFNULL(d20.amount, 0))) as diff20
            FROM (
                SELECT stock_id FROM 00981A_component WHERE trade_date = :targetDate
                UNION SELECT stock_id FROM 00981A_component WHERE trade_date = :d1
                UNION SELECT stock_id FROM 00981A_component WHERE trade_date = :d5
                UNION SELECT stock_id FROM 00981A_component WHERE trade_date = :d10
                UNION SELECT stock_id FROM 00981A_component WHERE trade_date = :d20
            ) all_ids
            LEFT JOIN 00981A_component curr ON all_ids.stock_id = curr.stock_id AND curr.trade_date = :targetDate
            LEFT JOIN 00981A_component d1 ON all_ids.stock_id = d1.stock_id AND d1.trade_date = :d1
            LEFT JOIN 00981A_component d5 ON all_ids.stock_id = d5.stock_id AND d5.trade_date = :d5
            LEFT JOIN 00981A_component d10 ON all_ids.stock_id = d10.stock_id AND d10.trade_date = :d10
            LEFT JOIN 00981A_component d20 ON all_ids.stock_id = d20.stock_id AND d20.trade_date = :d20
            GROUP BY all_ids.stock_id
            ORDER BY weight DESC, amount DESC, all_ids.stock_id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':targetDate', $targetDate);
        $stmt->bindValue(':d1', $compareDates[1]);
        $stmt->bindValue(':d5', $compareDates[5]);
        $stmt->bindValue(':d10', $compareDates[10]);
        $stmt->bindValue(':d20', $compareDates[20]);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return [];
        $finalData = [];
        $new = [];
        $eliminate = [];
        $increase = [];
        $decrease = [];
        $constant = [];
        foreach ($rows as $item) {
            $currAmount = (int)$item['amount'];
            $prevAmount = (int)$item['prev_amount'];
            $diff1 = (int)$item['diff1'];
            if ($prevAmount == 0 && $currAmount > 0) {
                $note = "新增";
                $new[] = (string)$item['stock_id'] . (string)$item['stock_name'];
            } elseif ($prevAmount > 0 && $currAmount == 0) {
                $note = "剔除";
                $eliminate[] = (string)$item['stock_id'] . (string)$item['stock_name'];
            } elseif ($diff1 > 0) {
                $note = "增持";
                $increase[] = (string)$item['stock_id'] . (string)$item['stock_name'];
            } elseif ($diff1 < 0) {
                $note = "減持";
                $decrease[] = (string)$item['stock_id'] . (string)$item['stock_name'];
            } else {
                $note = "無變動";
                $constant[] = (string)$item['stock_id'] . (string)$item['stock_name'];
            }
            $finalData[] = [
                'stock_id'   => (string)$item['stock_id'],
                'stock_name' => (string)$item['stock_name'],
                'note'       => $note,
                'amount'     => $currAmount,
                'weight'     => (float)$item['weight'],
                'diff1'      => $diff1,
                'diff5'      => (int)$item['diff5'],
                'diff10'     => (int)$item['diff10'],
                'diff20'     => (int)$item['diff20']
            ];
        }
        $notificationStr = "00981A成分股今日變動(資料累積中,先看短期就好) - https://yong-jhih.github.io/Stocks/00981A_component\n" . "增持共" . count($increase) . "檔\n" . "減持共" . count($decrease) . "檔\n" . "無變動共" . count($constant) . "檔\n";
        if (count($eliminate) > 0) $notificationStr .= "剔除共" . count($eliminate) . "檔:" . implode(',', $eliminate) . "\n";
        if (count($new) > 0) $notificationStr .= "新納入共" . count($new) . "檔:" . implode(',', $new) . "\n";
        lineNotification($pdo, getenv('LINE_TARGET'), $notificationStr);
        return $finalData;
    } catch (PDOException $e) {
        writeLog($pdo, 'analyzeMultiPeriodChanges', "Database Error: " . $e->getMessage(), 'error');
        return null;
    }
}

function selfSelectGenerateDailyDashboard($pdo, $targetDate, $code_array = [])
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
                AVG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 60 PRECEDING AND 1 PRECEDING) as ma60,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING) as vma5,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 10 PRECEDING AND 1 PRECEDING) as vma10,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) as vma20,
                AVG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 60 PRECEDING AND 1 PRECEDING) as vma60,
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
                LAG(i.trust_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_trust_buy_sell,
                LAG(i.foreign_buy_sell) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_foreign_buy_sell,
                LAG(h.trade_volume) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_vol,
                LAG(h.close_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_close,
                LAG(h.open_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_open,
                LAG(h.high_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_high,
                LAG(h.low_price) OVER(PARTITION BY h.stock_id ORDER BY h.trade_date) as yesterday_low
            FROM stock_history h
            LEFT JOIN stock_insti i ON h.stock_id = i.stock_id AND h.trade_date = i.trade_date
            LEFT JOIN stock_margin m ON h.stock_id = m.stock_id AND h.trade_date = m.trade_date
            LEFT JOIN stock_sbl_total st ON h.stock_id = st.stock_id AND h.trade_date = st.trade_date
            LEFT JOIN stock_sbl_sold ss ON h.stock_id = ss.stock_id AND h.trade_date = ss.trade_date
        ),
        StreakGrouping AS (
            SELECT *,
                LAG(ma5) OVER(PARTITION BY stock_id ORDER BY trade_date) as prev_ma5,
                LAG(ma10) OVER(PARTITION BY stock_id ORDER BY trade_date) as prev_ma10,
                LAG(ma20) OVER(PARTITION BY stock_id ORDER BY trade_date) as prev_ma20,
                LAG(ma60) OVER(PARTITION BY stock_id ORDER BY trade_date) as prev_ma60,
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
            yesterday_open as `昨日開盤價`,
            yesterday_high as `昨日高價`,
            yesterday_low as `昨日低價`,
            yesterday_close as `昨日收盤價`,
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
            foreign_buy_sell as `外資買賣超`,
            yesterday_foreign_buy_sell as `昨日外資買賣超`,
            ROUND(foreign_sum5/1000,0) as `外資5日累計`,
            ROUND(foreign_sum10/1000,0) as `外資10日累計`,
            ROUND(foreign_sum20/1000,0) as `外資20日累計`,
            trust_buy_sell as `投信買賣超`,
            yesterday_trust_buy_sell as `昨日投信買賣超`,
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
            ROUND(sbl_sold_balance/1000,0) as `借券賣出餘額`,
            ROUND(((ma5 - prev_ma5) / NULLIF(prev_ma5, 0)) * 100, 2) as `5日線斜率`,
            ROUND(((ma10 - prev_ma10) / NULLIF(prev_ma10, 0)) * 100, 2) as `10日線斜率`,
            ROUND(((ma20 - prev_ma20) / NULLIF(prev_ma20, 0)) * 100, 2) as `20日線斜率`,
            ROUND(((ma60 - prev_ma60) / NULLIF(prev_ma60, 0)) * 100, 2) as `60日線斜率`
        FROM ConsecutiveCalc
        WHERE trade_date = :targetDate
            AND stock_id IN(" . implode(",", $code_array) . ")
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['targetDate' => $targetDate]);
    $rawStocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dashboardResults = [];
    foreach ($rawStocks as $s) {
        $tag = [];
        if ($s['收盤價'] > $s['5日線'] && $s['5日線'] > $s['10日線'] && $s['10日線'] > $s['20日線'] && $s['5日線斜率'] > 0 && $s['10日線斜率'] > 0 && $s['20日線斜率'] > 0) $tag[] = "嚴格多頭";
        if ($s['收盤價'] > $s['5日線'] && $s['收盤價'] > $s['10日線'] && $s['收盤價'] > $s['20日線'] && $s['昨日收盤價'] < $s['20日線']) $tag[] = "三陽開泰";
        if ($s['收盤價'] / $s['昨日收盤價'] > 1.03 && $s['昨量比'] > 1.5) $tag[] = "價量齊揚";
        if ($s['昨量比'] < 0.7 && abs($s['收盤價'] / $s['昨日收盤價'] - 1) < 0.01) $tag[] = "量縮價穩";
        if ((float)$s['5日乖離率'] > 7) $tag[] = "高檔乖離";
        if ($s['20日線斜率'] > 0 && $s['60日線斜率'] <= 0) $tag[] = "生命線轉揚";
        if ((float)$s['20日集中度'] > 10) $tag[] = "法人鎖碼";
        if ($s['投信連買天數'] >= 3 && $s['投信5日累計'] > 0) $tag[] = "投信認養";
        $denominator = $s['5日均量'] * 5;
        $trust_ratio_5d = ($denominator == 0) ? null : ($s['投信5日累計'] / $denominator);
        if ($s['投信連買天數'] >= 5 && $trust_ratio_5d > 0.03) {
            $tag[] = "投信作帳股";
        }
        if ($s['投信連買天數'] == 1 && $s['昨日投信買賣超'] <= 0 && $s['投信買賣超'] > 100) $tag[] = "投信試單";
        if ($s['外資連買天數'] >= 1 && $s['外資5日累計'] > $s['外資20日累計']) $tag[] = "外資回補";
        if ($s['外資連買天數'] > 0 && $s['投信連買天數'] > 0) $tag[] = "土洋合力";
        if ((float)$s['5日集中度'] > (float)$s['20日集中度']) $tag[] = "籌碼趨於集中";
        if ($s['券補力'] > 5) $tag[] = "潛在軋空";
        if ($s['融資'] < 0 && ($s['收盤價'] / $s['昨日收盤價']) >= 1) $tag[] = "主力換手";
        if ($s['融資'] > 0 && $s['券淨賣還'] > 0) $tag[] = "資券同增";
        if ($s['券淨賣還5日累計'] < 0) $tag[] = "借券回補";

        $dashboardResults[] = [
            'stock_id'   => $s['代碼'],
            'stock_name' => $s['股名'],
            'concept'    => "",
            'close'      => $s['收盤價'],
            'vol'      => $s['成交量'],
            'vol_ratio'  => $s['昨量比'],
            'rank10'     => $s['10日位階'],
            'amp10'     => $s['10日振幅'],
            'ma5'     => $s['5日線'],
            'ma10'     => $s['10日線'],
            'ma20'     => $s['20日線'],
            'vma5'     => $s['5日均量'],
            'vma10'     => $s['10日均量'],
            'vma20'     => $s['20日均量'],
            'bia5'     => $s['5日乖離率'],
            'bia10'     => $s['10日乖離率'],
            'bia20'     => $s['20日乖離率'],
            'con1'     => $s['1日集中度'],
            'con5'     => $s['5日集中度'],
            'con10'     => $s['10日集中度'],
            'con20'     => $s['20日集中度'],
            'margin_balance_diff' => $s['融資'],
            'margin_balance_diff_sum5' => $s['融資5日累計'],
            'margin_balance_diff_sum10' => $s['融資10日累計'],
            'margin_balance_diff_sum20' => $s['融資20日累計'],
            'margin_balance' => $s['融資餘額'],
            'foreign_sum5' => $s['外資5日累計'],
            'foreign_sum10' => $s['外資10日累計'],
            'foreign_sum20' => $s['外資20日累計'],
            'trust_sum5' => $s['投信5日累計'],
            'trust_sum10' => $s['投信10日累計'],
            'trust_sum20' => $s['投信20日累計'],
            'foreign_streak_days' => $s['外資連買天數'],
            'trust_streak_days' => $s['投信連買天數'],
            'squeeze' => $s['券補力'],
            'bullet' => $s['券砸力'],
            'net_sbl' => $s['券淨賣還'],
            'net_sbl_sum5' => $s['券淨賣還5日累計'],
            'net_sbl_sum10' => $s['券淨賣還10日累計'],
            'net_sbl_sum20' => $s['券淨賣還20日累計'],
            'sbl_total' => $s['借券餘額'],
            'sbl_sold_balance' => $s['借券賣出餘額'],
            'tags' => implode(',', $tag)
        ];
    }
    writeLog($pdo, 'selfSelectGenerateDailyDashboard', "$targetDate 自選分析完成，共 " . count($dashboardResults) . " 檔", 'success');
    return $dashboardResults;
}

function getStockAnalysisChart($pdo, $stockId, $targetDate, $displayDays = 20)
{
    $fetchLimit = $displayDays + 10;
    $sql = "
        SELECT 
            h.trade_date,
            h.close_price,
            i.total_buy_sell as inst_diff,
            m.margin_balance,
            s.sbl_sold,
            s.sbl_return
        FROM stock_history h
        LEFT JOIN stock_insti i ON h.trade_date = i.trade_date AND h.stock_id = i.stock_id
        LEFT JOIN stock_margin m ON h.trade_date = m.trade_date AND h.stock_id = m.stock_id
        LEFT JOIN stock_sbl_sold s ON h.trade_date = s.trade_date AND h.stock_id = s.stock_id
        WHERE h.stock_id = :stockId AND h.trade_date <= :targetDate
        ORDER BY h.trade_date DESC
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':stockId', $stockId);
    $stmt->bindValue(':targetDate', $targetDate);
    $stmt->bindValue(':limit', (int)$fetchLimit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    $count = count($rows);
    $results = [];
    for ($i = 0; $i < $count; $i++) {
        if ($i < ($count - $displayDays)) continue;
        $curr = $rows[$i];
        $prev = $rows[$i - 1] ?? $curr;

        // --- 1. 法人 (Institutional) ---
        $instDiff = round(($curr['inst_diff'] ?? 0) / 1000); // 今日張數
        $instCum5 = 0;
        for ($j = max(0, $i - 4); $j <= $i; $j++) {
            $instCum5 += ($rows[$j]['inst_diff'] ?? 0);
        }
        $instCum5 = round($instCum5 / 1000);

        // --- 2. 融資 (Margin) ---
        $marginToday = $curr['margin_balance'] ?? 0;
        $marginPrev = $prev['margin_balance'] ?? $marginToday;
        $marginDiff = round(($marginToday - $marginPrev) / 1000); // 今日增減張數

        $refMargin5 = $rows[max(0, $i - 5)]['margin_balance'] ?? $marginToday;
        $marginCum5 = round(($marginToday - $refMargin5) / 1000);

        // --- 3. 借券賣出 (SBL) ---
        $sblNetDiff = ($curr['sbl_sold'] ?? 0) - ($curr['sbl_return'] ?? 0);
        $sblNetDiffIdx = round($sblNetDiff / 1000); // 今日淨張數

        $sblNet5 = 0;
        for ($k = max(0, $i - 4); $k <= $i; $k++) {
            $sblNet5 += (($rows[$k]['sbl_sold'] ?? 0) - ($rows[$k]['sbl_return'] ?? 0));
        }
        $sblNet5 = round($sblNet5 / 1000);

        // --- 4. 組合資料 ---
        $results[] = [
            'date'  => date('m/d', strtotime($curr['trade_date'])),
            'price' => (float)$curr['close_price'],
            // 柱狀圖用 (Bars)
            'bar_inst'   => $instDiff,
            'bar_margin' => $marginDiff,
            'bar_sbl'    => $sblNetDiffIdx,
            // 折線圖用 (Lines)
            'line_inst5'   => $instCum5,
            'line_margin5' => $marginCum5,
            'line_sbl5'    => $sblNet5
        ];
    }
    return ['stockId' => $stockId, 'series'  => $results];
}

function tetsGenerateDailyDashboard(PDO $pdo, string $targetDate): array
{
    $sql = "
    WITH BaseData AS (
        SELECT
            h.trade_date,
            h.stock_id,
            h.stock_name,
            h.open_price,
            h.high_price,
            h.low_price,
            h.close_price,
            h.trade_volume,

            -- 均線
            AVG(h.close_price) OVER w5  AS ma5,
            AVG(h.close_price) OVER w10 AS ma10,
            AVG(h.close_price) OVER w20 AS ma20,
            AVG(h.close_price) OVER w60 AS ma60,

            -- 均量
            AVG(h.trade_volume) OVER vw5  AS vma5,
            AVG(h.trade_volume) OVER vw10 AS vma10,
            AVG(h.trade_volume) OVER vw20 AS vma20,
            AVG(h.trade_volume) OVER vw60 AS vma60,

            -- 區間
            MAX(h.high_price) OVER r10 AS high10,
            MIN(h.low_price)  OVER r10 AS low10,

            -- 法人
            COALESCE(i.foreign_buy_sell, 0) AS foreign_buy_sell,
            COALESCE(i.trust_buy_sell, 0)   AS trust_buy_sell,
            COALESCE(i.total_buy_sell, 0)   AS total_buy_sell,

            SUM(COALESCE(i.foreign_buy_sell,0)) OVER s5  AS foreign_sum5,
            SUM(COALESCE(i.foreign_buy_sell,0)) OVER s10 AS foreign_sum10,
            SUM(COALESCE(i.foreign_buy_sell,0)) OVER s20 AS foreign_sum20,

            SUM(COALESCE(i.trust_buy_sell,0)) OVER ts5  AS trust_sum5,
            SUM(COALESCE(i.trust_buy_sell,0)) OVER ts10 AS trust_sum10,
            SUM(COALESCE(i.trust_buy_sell,0)) OVER ts20 AS trust_sum20,

            SUM(COALESCE(i.total_buy_sell,0)) OVER is1  AS insti_sum1,
            SUM(COALESCE(i.total_buy_sell,0)) OVER is5  AS insti_sum5,
            SUM(COALESCE(i.total_buy_sell,0)) OVER is10 AS insti_sum10,
            SUM(COALESCE(i.total_buy_sell,0)) OVER is20 AS insti_sum20,

            SUM(h.trade_volume) OVER vs1  AS vol_sum1,
            SUM(h.trade_volume) OVER vs5  AS vol_sum5,
            SUM(h.trade_volume) OVER vs10 AS vol_sum10,
            SUM(h.trade_volume) OVER vs20 AS vol_sum20,

            -- 融資
            COALESCE(m.margin_balance, 0)      AS margin_balance,
            COALESCE(m.margin_balance_diff, 0) AS margin_balance_diff,

            SUM(COALESCE(m.margin_balance_diff,0)) OVER ms5  AS margin_balance_diff_sum5,
            SUM(COALESCE(m.margin_balance_diff,0)) OVER ms10 AS margin_balance_diff_sum10,
            SUM(COALESCE(m.margin_balance_diff,0)) OVER ms20 AS margin_balance_diff_sum20,

            -- 借券
            COALESCE(st.sbl_balance, 0) AS sbl_total,
            COALESCE(ss.sbl_sold_balance, 0) AS sbl_sold_balance,
            (COALESCE(ss.sbl_sold,0) - COALESCE(ss.sbl_return,0)) AS net_sbl,

            SUM(COALESCE(ss.sbl_sold,0) - COALESCE(ss.sbl_return,0)) OVER ns5  AS net_sbl_sum5,
            SUM(COALESCE(ss.sbl_sold,0) - COALESCE(ss.sbl_return,0)) OVER ns10 AS net_sbl_sum10,
            SUM(COALESCE(ss.sbl_sold,0) - COALESCE(ss.sbl_return,0)) OVER ns20 AS net_sbl_sum20,

            -- 昨日
            LAG(h.open_price)   OVER lagw AS yesterday_open,
            LAG(h.high_price)   OVER lagw AS yesterday_high,
            LAG(h.low_price)    OVER lagw AS yesterday_low,
            LAG(h.close_price)  OVER lagw AS yesterday_close,
            LAG(h.trade_volume) OVER lagw AS yesterday_vol,

            LAG(i.foreign_buy_sell) OVER lagw AS yesterday_foreign_buy_sell,
            LAG(i.trust_buy_sell)   OVER lagw AS yesterday_trust_buy_sell

        FROM stock_history h

        LEFT JOIN stock_insti i
            ON h.stock_id = i.stock_id
            AND h.trade_date = i.trade_date

        LEFT JOIN stock_margin m
            ON h.stock_id = m.stock_id
            AND h.trade_date = m.trade_date

        LEFT JOIN stock_sbl_total st
            ON h.stock_id = st.stock_id
            AND h.trade_date = st.trade_date

        LEFT JOIN stock_sbl_sold ss
            ON h.stock_id = ss.stock_id
            AND h.trade_date = ss.trade_date

        WINDOW
            lagw AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
            ),

            w5  AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING
            ),

            w10 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 10 PRECEDING AND 1 PRECEDING
            ),

            w20 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING
            ),

            w60 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 60 PRECEDING AND 1 PRECEDING
            ),

            vw5  AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING
            ),

            vw10 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 10 PRECEDING AND 1 PRECEDING
            ),

            vw20 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING
            ),

            vw60 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 60 PRECEDING AND 1 PRECEDING
            ),

            r10 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 9 PRECEDING AND CURRENT ROW
            ),

            s5 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 4 PRECEDING AND CURRENT ROW
            ),

            s10 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 9 PRECEDING AND CURRENT ROW
            ),

            s20 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 19 PRECEDING AND CURRENT ROW
            ),

            ts5 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 4 PRECEDING AND CURRENT ROW
            ),

            ts10 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 9 PRECEDING AND CURRENT ROW
            ),

            ts20 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 19 PRECEDING AND CURRENT ROW
            ),

            is1 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN CURRENT ROW AND CURRENT ROW
            ),

            is5 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 4 PRECEDING AND CURRENT ROW
            ),

            is10 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 9 PRECEDING AND CURRENT ROW
            ),

            is20 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 19 PRECEDING AND CURRENT ROW
            ),

            vs1 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN CURRENT ROW AND CURRENT ROW
            ),

            vs5 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 4 PRECEDING AND CURRENT ROW
            ),

            vs10 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 9 PRECEDING AND CURRENT ROW
            ),

            vs20 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 19 PRECEDING AND CURRENT ROW
            ),

            ms5 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 4 PRECEDING AND CURRENT ROW
            ),

            ms10 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 9 PRECEDING AND CURRENT ROW
            ),

            ms20 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 19 PRECEDING AND CURRENT ROW
            ),

            ns5 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 4 PRECEDING AND CURRENT ROW
            ),

            ns10 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 9 PRECEDING AND CURRENT ROW
            ),

            ns20 AS (
                PARTITION BY h.stock_id
                ORDER BY h.trade_date
                ROWS BETWEEN 19 PRECEDING AND CURRENT ROW
            )
    ),
    NumberedData AS (
        SELECT
            *,

            ROW_NUMBER() OVER(
                PARTITION BY stock_id
                ORDER BY trade_date
            ) AS rn_all,

            ROW_NUMBER() OVER(
                PARTITION BY stock_id, (foreign_buy_sell > 0)
                ORDER BY trade_date
            ) AS rn_foreign,

            ROW_NUMBER() OVER(
                PARTITION BY stock_id, (trust_buy_sell > 0)
                ORDER BY trade_date
            ) AS rn_trust

        FROM BaseData
    ),

    FeatureData AS (
        SELECT
            *,

            LAG(ma5) OVER(
                PARTITION BY stock_id
                ORDER BY trade_date
            ) AS prev_ma5,

            LAG(ma10) OVER(
                PARTITION BY stock_id
                ORDER BY trade_date
            ) AS prev_ma10,

            LAG(ma20) OVER(
                PARTITION BY stock_id
                ORDER BY trade_date
            ) AS prev_ma20,

            CASE
                WHEN foreign_buy_sell > 0 THEN
                    ROW_NUMBER() OVER(
                        PARTITION BY stock_id, (rn_all - rn_foreign)
                        ORDER BY trade_date
                    )
                ELSE 0
            END AS foreign_streak_days,

            CASE
                WHEN trust_buy_sell > 0 THEN
                    ROW_NUMBER() OVER(
                        PARTITION BY stock_id, (rn_all - rn_trust)
                        ORDER BY trade_date
                    )
                ELSE 0
            END AS trust_streak_days

        FROM NumberedData
    )

    SELECT *
    FROM FeatureData
    WHERE trade_date = :targetDate
        AND vma20 > 700000
        AND ma20 IS NOT NULL
        AND ma60 IS NOT NULL
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'targetDate' => $targetDate
    ]);

    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dashboardResults = [];
    foreach ($stocks as $s) {
        // =========================
        // Base Numbers
        // =========================
        $close = (float)$s['close_price'];
        $open  = (float)$s['open_price'];
        $high  = (float)$s['high_price'];
        $low  = (float)$s['low_price'];
        $yClose = (float)$s['yesterday_close'];
        $yOpen  = (float)$s['yesterday_open'];
        $yHigh  = (float)$s['yesterday_high'];
        $ma5  = (float)$s['ma5'];
        $ma10 = (float)$s['ma10'];
        $ma20 = (float)$s['ma20'];
        $ma60 = (float)$s['ma60'];
        $prevMa5  = (float)$s['prev_ma5'];
        $prevMa10 = (float)$s['prev_ma10'];
        $prevMa20 = (float)$s['prev_ma20'];
        $vma5  = (float)$s['vma5'];
        $vma20 = (float)$s['vma20'];
        $volRatio = ($s['yesterday_vol'] > 0) ? ($s['trade_volume'] / $s['yesterday_vol']) : 0;
        $rank10 = ($s['high10'] - $s['low10']) != 0 ? (($close - $s['low10']) / ($s['high10'] - $s['low10']) * 100) : 0;
        $amp10 = ($s['low10'] != 0) ? (($s['high10'] - $s['low10']) / $s['low10'] * 100) : 0;
        $bia5  = $ma5 ? (($close - $ma5) / $ma5 * 100) : 0;
        $bia10 = $ma10 ? (($close - $ma10) / $ma10 * 100) : 0;
        $bia20 = $ma20 ? (($close - $ma20) / $ma20 * 100) : 0;
        $con1 = $s['vol_sum1'] ? ($s['insti_sum1'] / $s['vol_sum1'] * 100) : 0;
        $con5 = $s['vol_sum5'] ? ($s['insti_sum5'] / $s['vol_sum5'] * 100) : 0;
        $con10 = $s['vol_sum10'] ? ($s['insti_sum10'] / $s['vol_sum10'] * 100) : 0;
        $con20 = $s['vol_sum20'] ? ($s['insti_sum20'] / $s['vol_sum20'] * 100) : 0;
        $squeeze = $vma20 ? ($s['sbl_sold_balance'] / $vma20) : 0;
        $bullet = $vma20 ? (($s['sbl_total'] - $s['sbl_sold_balance']) / $vma20) : 0;

        // =========================
        // Signal Containers
        // =========================
        $signals = [
            'trend' => [],
            'momentum' => [],
            'chip' => [],
            'risk' => [],
            'structure' => []
        ];
        $addSignal = function (
            string $group,
            bool $condition,
            string $tag,
            int $score
        ) use (&$signals): void {
            if ($condition) $signals[$group][$tag] = $score;
        };

        // =========================
        // Trend
        // =========================
        $addSignal(
            'trend',
            $close > $ma5 &&
                $ma5 > $ma10 &&
                $ma10 > $ma20,
            '多頭排列',
            15
        );
        $addSignal(
            'trend',
            $ma5 > $prevMa5 &&
                $ma10 > $prevMa10 &&
                $ma20 > $prevMa20,
            '均線上彎',
            10
        );
        $addSignal(
            'trend',
            $close > $ma60,
            '站上季線',
            8
        );

        // =========================
        // Momentum
        // =========================
        $addSignal(
            'momentum',
            $volRatio > 1.5 &&
                $close > $yHigh,
            '爆量突破',
            20
        );
        $addSignal(
            'momentum',
            ($close / max($yClose, 0.01)) > 1.03 &&
                $volRatio > 1.3,
            '價量齊揚',
            15
        );

        // =========================
        // Chip
        // =========================
        $addSignal(
            'chip',
            $con5 > 15,
            '法人集中',
            15
        );
        $addSignal(
            'chip',
            $s['foreign_streak_days'] >= 3,
            '外資連買',
            10
        );
        $addSignal(
            'chip',
            $s['trust_streak_days'] >= 3,
            '投信連買',
            12
        );
        $addSignal(
            'chip',
            $s['foreign_streak_days'] > 0 &&
                $s['trust_streak_days'] > 0,
            '土洋合力',
            15
        );
        $addSignal(
            'chip',
            $s['margin_balance_diff'] < 0 &&
                $close >= $yClose,
            '融資減肥',
            6
        );

        // =========================
        // Structure
        // =========================
        $addSignal(
            'structure',
            $amp10 < 8 &&
                $vma5 < $vma20,
            '整理末端',
            8
        );
        $addSignal(
            'structure',
            $rank10 < 30,
            '低檔區',
            6
        );
        $addSignal(
            'structure',
            $close > $ma20 &&
                $volRatio < 0.9 &&
                $close >= $yClose,
            '量縮抗跌',
            10
        );

        // =========================
        // Market State
        // 不加分，只做分類
        // =========================
        $marketStates = [];

        if (
            $close > $ma5 &&
            $ma5 > $ma10 &&
            $volRatio > 1.8 &&
            $con5 > 8
        ) {
            $marketStates[] = '主升段';
        }

        if (
            $amp10 < 12 &&
            $vma5 < $vma20 &&
            $close > $ma20 &&
            $con5 > 5
        ) {
            $marketStates[] = '發動前夕';
        }

        // =========================
        // Risk 同類只觸發最嚴重
        // =========================
        // ---- 過熱類 ----
        if ($rank10 > 90 && $bia20 > 15) {
            $addSignal(
                'risk',
                true,
                '極度過熱',
                -35
            );
        } elseif ($rank10 > 85 && $bia20 > 12) {
            $addSignal(
                'risk',
                true,
                '短線過熱',
                -18
            );
        } elseif ($bia20 > 18) {
            $addSignal(
                'risk',
                true,
                '乖離過大',
                -12
            );
        }

        // ---- 出貨類 ----
        if (
            $volRatio > 2.5 &&
            ($close / max($yClose, 0.01)) < 1.01
        ) {
            $addSignal(
                'risk',
                true,
                '爆量滯漲',
                -30
            );
        } elseif (
            $volRatio > 2 &&
            (
                ($high - max($close, $open))
                / max(($high - $low), 0.01)
            ) > 0.45
        ) {
            $addSignal(
                'risk',
                true,
                '高檔出貨',
                -25
            );
        } elseif (
            $high > $yHigh &&
            $close < $yHigh
        ) {
            $addSignal(
                'risk',
                true,
                '假突破',
                -20
            );
        }

        // ---- 趨勢轉弱類 ----
        if (
            $close < $ma20 &&
            $s['trade_volume'] < $vma20
        ) {
            $addSignal(
                'risk',
                true,
                '量縮走弱',
                -20
            );
        } elseif ($ma20 < $prevMa20) {
            $addSignal(
                'risk',
                true,
                '月線轉弱',
                -18
            );
        } elseif ($close < $ma20) {
            $addSignal(
                'risk',
                true,
                '跌破月線',
                -12
            );
        }

        // ---- 籌碼轉弱 ----
        $addSignal(
            'risk',
            $s['foreign_buy_sell'] < 0 &&
                $s['trust_buy_sell'] < 0,
            '法人同步轉賣',
            -18
        );
        $addSignal(
            'risk',
            $s['foreign_sum5'] < 0 &&
                $s['trust_sum5'] < 0,
            '法人倒貨',
            -22
        );

        // =========================
        // Category Scores
        // =========================
        $trendScore = min(
            35,
            array_sum($signals['trend'])
        );

        $momentumScore = min(
            35,
            array_sum($signals['momentum'])
        );

        $chipScore = min(
            40,
            array_sum($signals['chip'])
        );

        $structureScore = min(
            20,
            array_sum($signals['structure'])
        );

        $riskScore = array_sum($signals['risk']);

        // =========================
        // Risk Multiplier
        // =========================
        $riskMultiplier = 1.0;
        if ($riskScore <= -20) {
            $riskMultiplier = 0.9;
        }
        if ($riskScore <= -40) {
            $riskMultiplier = 0.75;
        }
        if ($riskScore <= -60) {
            $riskMultiplier = 0.6;
        }

        // =========================
        // Final Score
        // =========================
        $rawScore =
            ($trendScore * 1.0) +
            ($momentumScore * 1.1) +
            ($chipScore * 1.2) +
            ($structureScore * 0.8);

        $finalScore = ($rawScore * $riskMultiplier);

        // Normalize
        $finalScore = max(
            0,
            min(100, round($finalScore))
        );

        // =========================
        // Rating
        // =========================
        $rating = match (true) {
            $finalScore >= 80 => 'S',
            $finalScore >= 65 => 'A',
            $finalScore >= 50 => 'B',
            $finalScore >= 35 => 'C',
            default => 'D'
        };

        // =========================
        // Strategy Type
        // =========================
        $strategyType = $marketStates[0] ?? '觀察';
        if (
            $trendScore >= 20 &&
            $momentumScore >= 20 &&
            $chipScore >= 20
        ) {
            $strategyType = '主升段';
        } elseif (
            $chipScore >= 25 &&
            $momentumScore < 15
        ) {
            $strategyType = '籌碼潛伏';
        } elseif (
            $momentumScore >= 25 &&
            $trendScore < 15
        ) {
            $strategyType = '短線轉強';
        } elseif (
            $riskScore <= -25
        ) {
            $strategyType = '高風險';
        }

        // =========================
        // Confidence
        // =========================
        $positiveGroups = 0;
        foreach (
            [
                $trendScore,
                $momentumScore,
                $chipScore
            ] as $v
        ) {

            if ($v >= 15) {
                $positiveGroups++;
            }
        }
        $confidence = round(max(0, min(1, (($positiveGroups / 3) * $riskMultiplier))), 2);

        // =========================
        // Flatten Tags
        // =========================
        $tags = [];
        foreach ($signals as $group => $groupSignals) {
            foreach ($groupSignals as $tag => $score) {
                $tags[] = $tag;
            }
        }

        // =========================
        // Trigger Reasons
        // =========================
        $triggerReasons = [];
        if ($s['foreign_streak_days'] >= 3) {
            $triggerReasons[] =
                '外資連買 ' . $s['foreign_streak_days'] . ' 日';
        }
        if ($volRatio > 1.5) {
            $triggerReasons[] =
                '成交量放大 ' . round($volRatio, 2) . ' 倍';
        }
        if ($close > $yHigh) {
            $triggerReasons[] = '突破前高';
        }
        if ($con20 > 10) {
            $triggerReasons[] =
                '法人持股集中度提升';
        }
        // =========================
        // Concept
        // =========================
        $concept = '';

        // =========================
        // Output      稍微過濾下,只保留高分
        // =========================
        if ($rating == 'S' || $rating == 'A') {
            $dashboardResults[] = [
                'stock_id' => $s['stock_id'],
                'stock_name' => $s['stock_name'],
                'concept' => $concept,
                'score' => $finalScore,
                'rating' => $rating,
                'strategy_type' => $strategyType,
                'confidence' => $confidence,
                // Score Breakdown
                'trend_score' => round($trendScore),
                'momentum_score' => round($momentumScore),
                'chip_score' => round($chipScore),
                'structure_score' => round($structureScore),
                'risk_score' => round($riskScore),
                // Price
                'close' => round($close, 2),
                // Volume
                'vol' => round($s['trade_volume'] / 1000, 0),
                'vol_ratio' => round($volRatio, 2),
                // Structure
                'rank10' => round($rank10, 2),
                'amp10' => round($amp10, 2),
                // MA
                'ma5' => round($ma5, 2),
                'ma10' => round($ma10, 2),
                'ma20' => round($ma20, 2),
                'ma60' => round($ma60, 2),
                // Volume MA
                'vma5' => round($vma5 / 1000, 0),
                'vma10' => round($s['vma10'] / 1000, 0),
                'vma20' => round($vma20 / 1000, 0),
                // Bias
                'bia5' => round($bia5, 2),
                'bia10' => round($bia10, 2),
                'bia20' => round($bia20, 2),
                // Chip Ratios
                'con1' => round($con1, 2),
                'con5' => round($con5, 2),
                'con10' => round($con10, 2),
                'con20' => round($con20, 2),
                // Institution
                'foreign_buy_sell' => $s['foreign_buy_sell'],
                'trust_buy_sell' => $s['trust_buy_sell'],
                'foreign_sum5' => round($s['foreign_sum5'] / 1000, 0),
                'foreign_sum10' => round($s['foreign_sum10'] / 1000, 0),
                'foreign_sum20' => round($s['foreign_sum20'] / 1000, 0),
                'trust_sum5' => round($s['trust_sum5'] / 1000, 0),
                'trust_sum10' => round($s['trust_sum10'] / 1000, 0),
                'trust_sum20' => round($s['trust_sum20'] / 1000, 0),
                'foreign_streak_days' => (int)$s['foreign_streak_days'],
                'trust_streak_days' => (int)$s['trust_streak_days'],
                // Margin
                'margin_balance' => (int)$s['margin_balance'],
                'margin_balance_diff' => (int)$s['margin_balance_diff'],
                // SBL
                'squeeze' => round($squeeze, 2),
                'bullet' => round($bullet, 2),
                'net_sbl' => round($s['net_sbl'] / 1000, 0),
                'sbl_total' => round($s['sbl_total'] / 1000, 0),
                'sbl_sold_balance' => round($s['sbl_sold_balance'] / 1000, 0),
                // Signals
                'signals' => $signals,
                // Flat Tags
                'tags' => $tags,
                // Trigger
                'trigger_reasons' => $triggerReasons
            ];
        }
    }

    // =========================
    // Sort by Score
    // =========================
    usort(
        $dashboardResults,
        fn($a, $b) => $b['score'] <=> $a['score']
    );
    writeLog(
        $pdo,
        'generateDailyDashboard',
        "{$targetDate} 分析完成，共篩選出 " .
            count($dashboardResults) .
            " 檔",
        'success'
    );
    return $dashboardResults;
}
