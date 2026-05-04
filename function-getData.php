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
        $prompt = "請幫我分析[" . $s['代碼'] . $s['股名'] . "]的產業別(使用證交所產業別分類)及佔營業收入20%以上相關的概念股標籤，請依格式回答不要多餘的內容及符號，格式嚴格限定:'XXX業-標籤1,標籤2,標籤3,...'。請搜尋最新的公開資訊觀測站或法人券商研究報告，以確保營收佔比數據的準確性。";
        $concept = callGeminiAI(getenv('GEMINI_TOKEN'), $prompt, 'gemini-2.5-flash');
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
            'squeeze' => $s['券補力'],
            'bullet' => $s['券砸力'],
            'net_sbl' => $s['券淨賣還'],
            'net_sbl_sum5' => $s['券淨賣還5日累計'],
            'net_sbl_sum10' => $s['券淨賣還10日累計'],
            'net_sbl_sum20' => $s['券淨賣還20日累計'],
            'sbl_total' => $s['借券餘額'],
            'sbl_sold_balance' => $s['借券賣出餘額']
        ];
    }
    writeLog($pdo, 'Dashboard_Gen', "完成 $targetDate 分析，共篩選出 " . count($dashboardResults) . " 檔", 'Success');
    return $dashboardResults;
}

function saveDailyDashboard($pdo, $targetDate, $dashboardResults)
{
    if (empty($dashboardResults)) {
        writeLog($pdo, 'SaveDashboard', "日期 {$targetDate} 無資料可供寫入", 'Warning');
        return;
    }

    $start_time = microtime(true);

    // 1. 完整的 SQL 語句，包含所有新增的累計與技術指標欄位
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
            // 2. 嚴格對應 $dashboardResults 陣列中的 Key 進行參數綁定
            $stmt->execute([
                $targetDate,
                $row['stock_id'],
                $row['stock_name'],
                $row['concept'],
                $row['close'],
                $row['vol'],      // 修正：對應之前的 'vol'
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
        writeLog($pdo, 'SaveDashboard', "{$targetDate} 分析結果存檔完成，共 {$count} 筆，耗時 {$execution_time} 秒", 'Success');
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
    $tempFile = 'temp_source.html';
    if (!file_exists($tempFile)) {
        error_log("找不到暫存檔: $tempFile");
        return false;
    }
    $data = file_get_contents($tempFile);
    $parts = explode('<div id="DataAsset" data-content="', $data);
    if (count($parts) < 2) {
        error_log("HTML 格式不符，找不到 DataAsset");
        return false;
    }

    $subParts = trim(explode('" style="display:none;"></div>', explode('<div id="DataAssetDetailSchema" data-content="', $parts[1])[0])[0]);
    $subParts = str_replace("&quot;", "", $subParts);
    $a = explode("Details:", $subParts)[5];
    $a = explode(',{FundCode:49YTW,AssetCode:CASH,AssetName:現金,Sequence:1.0,MoneyType:NTD', $a)[0] . "]";
    $search = ["FundCode:49YTW,EtfKind:01015,", "Type:2,AssetCode:ST,", "MoneyType:NTD,", "Position: ,", "MTH:,", ",USD_EXRATE:1.00000000"];
    $abc =  str_replace($search, "", $a);

    $input = $abc;

    // 1. 提取核心陣列部分 (只保留 [{ ... }] 之間的內容)
    if (preg_match('/\[\s*\{.*\}\s*\]/s', $input, $matches)) {
        $cleanString = $matches[0];
    } else {
        die("找不到有效的陣列結構");
    }

    // 2. 補上 Key 的雙引號
    // 找尋所有以冒號結尾的單字，並包上引號
    $jsonReady = preg_replace('/(\b\w+\b):/', '"$1":', $cleanString);

    // 3. 補上 Value 的雙引號 (針對非數字、非布林、非空值的內容)
    // 邏輯：在冒號後面，如果不是以引號、數字、負號、中括號或大括號開頭的內容，全部補上引號
    $jsonReady = preg_replace('/:([^"\[\{0-9\-\.][^,\]\}]*)/', ':"$1"', $jsonReady);

    // 4. 清理可能產生的格式瑕疵
    $jsonReady = str_replace([': ,', ':,'], ':null', $jsonReady); // 處理空值
    $jsonReady = preg_replace('/,\s*([\]\}])/', '$1', $jsonReady); // 移除陣列末尾多餘的逗號

    // 5. 執行解析
    $dataArray = json_decode($jsonReady, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        // 成功轉換為 PHP 陣列，現在可以自由操作
        // 例如：過濾出您需要的欄位並轉回標準 JSON
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dataArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        // 如果還是失敗，輸出處理後的字串協助除錯
        echo "JSON 解析依然失敗: " . json_last_error_msg() . "\n";
        echo "錯誤位置附近的字串: " . substr($jsonReady, json_last_error(), 50);
    }
}
