<?php

// 歷史資料更新
function updateAllHistory(PDO $pdo, string $targetDate): void
{
    $start_time = microtime(true);
    writeLog($pdo, 'updateAllHistory', '取得交易日期 [' . $targetDate . '] 開始更新盤後資料', 'start');
    insertHistory($pdo, $targetDate, getHistory($targetDate, $pdo));
    insertInsti($pdo, $targetDate, getInsti($targetDate, $pdo));
    insertMargin($pdo, $targetDate, getMargin($targetDate, $pdo));
    insertSBLTotal($pdo, $targetDate, getSBLTotal($targetDate, $pdo));
    insertSBLSold($pdo, $targetDate, getSBLSold($targetDate, $pdo));
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    writeLog($pdo, 'updateAllHistory', '更新盤後資料結束, 共耗時 ' . $execution_time . ' 秒', 'end');
}

function getHistory(string $date, PDO $pdo): ?array
{
    $url = "https://www.twse.com.tw/exchangeReport/MI_INDEX?response=json&type=ALLBUT0999&date=" . str_replace("-", "", $date);
    for ($i = 0; $i < 3; $i++) {
        $data = fetchUrl($pdo, $url);
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
        }
        writeLog($pdo, 'getHistory', "證交所回傳錯誤訊息：" . ($data['msg'] ?? '未知錯誤') . ", 準備執行第 " . ($i + 1) . " 次重試", 'warning');
    }
    writeLog($pdo, 'getHistory', '執行 3 次失敗,退出', 'error');
    return null;
}

function getInsti(string $date, PDO $pdo): ?array
{
    $url = "https://www.twse.com.tw/fund/T86?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    for ($i = 0; $i < 3; $i++) {
        $data = fetchUrl($pdo, $url);
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
        }
        writeLog($pdo, 'getInsti', "證交所回傳錯誤訊息：" . ($data['msg'] ?? '未知錯誤') . ", 準備執行第 " . ($i + 1) . " 次重試", 'warning');
    }
    writeLog($pdo, 'getInsti', '執行 3 次失敗,退出', 'error');
    return null;
}

function getMargin(string $date, PDO $pdo): ?array
{
    $url = "https://www.twse.com.tw/exchangeReport/MI_MARGN?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    for ($i = 0; $i < 3; $i++) {
        $data = fetchUrl($pdo, $url);
        if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['tables']) && is_array($data['tables'])) {
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
        }
        writeLog($pdo, 'getMargin', "證交所回傳錯誤訊息：" . ($data['msg'] ?? '未知錯誤') . ", 準備執行第 " . ($i + 1) . " 次重試", 'warning');
    }
    writeLog($pdo, 'getMargin', '執行 3 次失敗,退出', 'error');
    return null;
}
function getSBLTotal(string $date, PDO $pdo): ?array
{
    $url = "https://www.twse.com.tw/exchangeReport/TWT72U?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    for ($i = 0; $i < 3; $i++) {
        $data = fetchUrl($pdo, $url);
        if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data']) && is_array($data['data'])) {
            if (str_contains($data['title'], "證金營業處所借券餘額合計表")) {
                $stocks = [];
                foreach ($data['data'] as $row) {
                    if (preg_match('/^[1-9]\d{3}$/', $row[0]) && $row[8] == '集中市場') {
                        $stocks[] = $row;
                    }
                }
                return $stocks;
            }
        }
        writeLog($pdo, 'getSBLTotal', "證交所回傳錯誤訊息：" . ($data['msg'] ?? '未知錯誤') . ", 準備執行第 " . ($i + 1) . " 次重試", 'warning');
    }
    writeLog($pdo, 'getSBLTotal', '執行 3 次失敗,退出', 'error');
    return null;
}
function getSBLSold(string $date, PDO $pdo): ?array
{
    $url = "https://www.twse.com.tw/exchangeReport/TWT93U?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    for ($i = 0; $i < 3; $i++) {
        $data = fetchUrl($pdo, $url);
        if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data']) && is_array($data['data'])) {
            if (str_contains($data['title'], "信用額度總量管制餘額")) {
                $stocks = [];
                foreach ($data['data'] as $row) {
                    if (preg_match('/^[1-9]\d{3}$/', $row[0])) {
                        $stocks[] = $row;
                    }
                }
                return $stocks;
            }
        }
        writeLog($pdo, 'getSBLSold', "證交所回傳錯誤訊息：" . ($data['msg'] ?? '未知錯誤') . ", 準備執行第 " . ($i + 1) . " 次重試", 'warning');
    }
    writeLog($pdo, 'getSBLSold', '執行 3 次失敗,退出', 'error');
    return null;
}

function insertHistory(PDO $pdo, string $targetDate, array $historyData): void
{
    $start_time = microtime(true);
    $sql = "INSERT INTO stock_history 
            (trade_date, stock_id, stock_name, open_price, high_price, low_price, close_price, trade_volume, trade_value) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            stock_name = VALUES(stock_name),
            open_price = VALUES(open_price),
            high_price = VALUES(high_price),
            low_price = VALUES(low_price),
            close_price = VALUES(close_price),
            trade_volume = VALUES(trade_volume),
            trade_value = VALUES(trade_value)";
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
                (int)$clean($row[2]),   // 成交股數
                (int)$clean($row[4])    // 成交金額
            ]);
        }
        $pdo->commit();
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);
        writeLog($pdo, 'insertHistory', $targetDate . ' 上市個股日成交 更新完成,共新增 ' . count($historyData) . ' 筆,耗時 ' . $execution_time . ' 秒', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        writeLog($pdo, 'insertHistory', $targetDate . ' 上市個股日成交 寫入失敗：' . $e->getMessage(), 'error');
    }
}

function insertInsti(PDO $pdo, string $targetDate, array $instiData): void
{
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
        $execution_time = round($end_time - $start_time, 2);
        writeLog($pdo, 'insertInsti', $targetDate . ' 三大法人買賣超 更新完成,共新增 ' . count($instiData) . ' 筆,耗時 ' . $execution_time . ' 秒', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        writeLog($pdo, 'insertInsti', $targetDate . ' 三大法人買賣超 寫入失敗：' . $e->getMessage(), 'error');
    }
}

function insertMargin(PDO $pdo, string $targetDate, array $marginData): void
{
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
        $execution_time = round($end_time - $start_time, 2);
        writeLog($pdo, 'insertMargin', $targetDate . ' 融資融券彙總 更新完成,共新增 ' . count($marginData) . ' 筆,耗時 ' . $execution_time . ' 秒', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        writeLog($pdo, 'insertMargin', $targetDate . ' 融資融券彙總 寫入失敗：' . $e->getMessage(), 'error');
    }
}

function insertSBLTotal(PDO $pdo, string $targetDate, array $SBLTotalData): void
{
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
        writeLog($pdo, 'insertSBLTotal', $targetDate . ' 借券餘額 更新完成,共新增 ' . count($SBLTotalData) . ' 筆,耗時 ' .  $execution_time . ' 秒', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        writeLog($pdo, 'insertSBLTotal', $targetDate . ' 借券餘額 寫入失敗：' . $e->getMessage(), 'error');
    }
}

function insertSBLSold(PDO $pdo, string $targetDate, array $SBLSoldData): void
{
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
        $execution_time = round($end_time - $start_time, 2);
        writeLog($pdo, 'insertSBLSold', $targetDate . ' 借券賣出餘額 更新完成,共新增 ' . count($SBLSoldData) . ' 筆,耗時 ' .  $execution_time . ' 秒', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        writeLog($pdo, 'insertSBLSold', $targetDate . ' 借券賣出餘額 寫入失敗：' . $e->getMessage(), 'error');
    }
}

// 分析篩選
function generateDailyDashboard(PDO $pdo, string $targetDate): array
{
    $stocks = returnSqlFetch($pdo, $targetDate, [
        "vma20 > 700000",
        "((trade_volume > vma5 AND vma5 >= vma20) OR (trade_volume > vma20 * 1.3))",
        "yesterday_vol < vma5",
        "ABS(yesterday_close - yesterday_open) / NULLIF(yesterday_open, 0) < 0.03",
        "(yesterday_high - yesterday_low) / NULLIF(yesterday_low, 0) < 0.035",
        "yesterday_close > ma20",
        "((trade_volume < vma20 AND (insti_sum1 / NULLIF(vol_sum1, 0)) > 0.06) OR (trade_volume >= vma20 AND (insti_sum1 / NULLIF(vol_sum1, 0)) > 0.08))",
        "((high10 - low10) / NULLIF(low10, 0)) > 0.01",
        "((close_price - low10) / NULLIF(high10 - low10, 0)) BETWEEN 0.2 AND 0.9",
        "(insti_sum5 / NULLIF(vol_sum5, 0)) > 0.03"
    ]);
    $dashboardResults = outputModel($pdo, $stocks);
    writeLog($pdo, 'generateDailyDashboard', "[{$targetDate}] 篩選分析完成，共 " . count($dashboardResults) . " 檔", 'success');
    return $dashboardResults;
}

function selfSelectGenerateDailyDashboard(PDO $pdo, string $targetDate, array $code_array = []): array
{
    if (empty($code_array)) return [];
    $safeCodes = array_map(function ($code) use ($pdo) {
        return $pdo->quote($code);
    }, $code_array);
    $inClause = implode(",", $safeCodes);
    $stocks = returnSqlFetch($pdo, $targetDate, [
        "stock_id IN({$inClause})"
    ]);
    $dashboardResults = outputModel($pdo, $stocks);
    // writeLog($pdo, 'selfSelectGenerateDailyDashboard', "{$targetDate} 自選分析完成，共 " . count($dashboardResults) . " 檔", 'success');
    return $dashboardResults;
}

function topPerformingGenerateDailyDashboard(PDO $pdo, string $targetDate): array
{
    $stocks = returnSqlFetch($pdo, $targetDate, [
        "vma20 > 700000",
        "ma20 IS NOT NULL",
        "ma60 IS NOT NULL"
    ]);
    $dashboardResults = outputModel($pdo, $stocks);
    writeLog($pdo, 'topPerformingGenerateDailyDashboard', "[{$targetDate}] 排行分析完成，共 " . count($dashboardResults) . " 檔", 'success');
    return $dashboardResults;
}

function returnSqlFetch(PDO $pdo, string $targetDate, array $where): array
{
    $cutoffDate = date('Y-m-d', strtotime($targetDate . ' - 200 days'));
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
                AVG(h.trade_volume) OVER w5  AS vma5, 
                AVG(h.trade_volume) OVER w10 AS vma10,
                AVG(h.trade_volume) OVER w20 AS vma20,
                AVG(h.trade_volume) OVER w60 AS vma60,

                -- 區間
                MAX(h.high_price) OVER r10 AS high10,
                MIN(h.low_price)  OVER r10 AS low10,
                MAX(h.high_price) OVER r20 AS high20,
                MIN(h.low_price)  OVER r20 AS low20,

                -- 法人
                COALESCE(i.foreign_buy_sell, 0) AS foreign_buy_sell,
                COALESCE(i.trust_buy_sell, 0)   AS trust_buy_sell,
                COALESCE(i.total_buy_sell, 0)   AS total_buy_sell,

                SUM(COALESCE(i.foreign_buy_sell,0)) OVER s5  AS foreign_sum5,
                SUM(COALESCE(i.foreign_buy_sell,0)) OVER s10 AS foreign_sum10,
                SUM(COALESCE(i.foreign_buy_sell,0)) OVER s20 AS foreign_sum20,
                SUM(COALESCE(i.foreign_buy_sell,0)) OVER s60 AS foreign_sum60,

                SUM(COALESCE(i.trust_buy_sell,0)) OVER s5  AS trust_sum5, 
                SUM(COALESCE(i.trust_buy_sell,0)) OVER s10 AS trust_sum10,
                SUM(COALESCE(i.trust_buy_sell,0)) OVER s20 AS trust_sum20,
                SUM(COALESCE(i.trust_buy_sell,0)) OVER s60 AS trust_sum60,

                COALESCE(i.total_buy_sell, 0) AS insti_sum1,              
                SUM(COALESCE(i.total_buy_sell,0)) OVER s5  AS insti_sum5, 
                SUM(COALESCE(i.total_buy_sell,0)) OVER s10 AS insti_sum10,
                SUM(COALESCE(i.total_buy_sell,0)) OVER s20 AS insti_sum20,
                SUM(COALESCE(i.total_buy_sell,0)) OVER s60 AS insti_sum60,

                h.trade_volume AS vol_sum1,               
                SUM(h.trade_volume) OVER s5  AS vol_sum5, 
                SUM(h.trade_volume) OVER s10 AS vol_sum10,
                SUM(h.trade_volume) OVER s20 AS vol_sum20,

                -- 融資
                COALESCE(m.margin_balance, 0)      AS margin_balance,
                COALESCE(m.margin_balance_diff, 0) AS margin_balance_diff,

                SUM(COALESCE(m.margin_balance_diff,0)) OVER s5  AS margin_balance_diff_sum5,
                SUM(COALESCE(m.margin_balance_diff,0)) OVER s10 AS margin_balance_diff_sum10,
                SUM(COALESCE(m.margin_balance_diff,0)) OVER s20 AS margin_balance_diff_sum20,
                SUM(COALESCE(m.margin_balance_diff,0)) OVER s60 AS margin_balance_diff_sum60,

                -- 借券
                COALESCE(st.sbl_balance, 0) AS sbl_total,
                COALESCE(ss.sbl_sold_balance, 0) AS sbl_sold_balance,
                (COALESCE(ss.sbl_sold,0) - COALESCE(ss.sbl_return,0)) AS net_sbl,

                SUM(COALESCE(ss.sbl_sold,0) - COALESCE(ss.sbl_return,0)) OVER s5  AS net_sbl_sum5,
                SUM(COALESCE(ss.sbl_sold,0) - COALESCE(ss.sbl_return,0)) OVER s10 AS net_sbl_sum10,
                SUM(COALESCE(ss.sbl_sold,0) - COALESCE(ss.sbl_return,0)) OVER s20 AS net_sbl_sum20,
                SUM(COALESCE(ss.sbl_sold,0) - COALESCE(ss.sbl_return,0)) OVER s60 AS net_sbl_sum60,

                -- 昨日
                LAG(h.open_price)   OVER lagw AS yesterday_open,
                LAG(h.high_price)   OVER lagw AS yesterday_high,
                LAG(h.low_price)    OVER lagw AS yesterday_low,
                LAG(h.close_price)  OVER lagw AS yesterday_close,
                LAG(h.trade_volume) OVER lagw AS yesterday_vol,

                LAG(i.foreign_buy_sell) OVER lagw AS yesterday_foreign_buy_sell,
                LAG(i.trust_buy_sell)   OVER lagw AS yesterday_trust_buy_sell

            FROM stock_history h
            LEFT JOIN stock_insti i      ON h.stock_id = i.stock_id AND h.trade_date = i.trade_date
            LEFT JOIN stock_margin m     ON h.stock_id = m.stock_id AND h.trade_date = m.trade_date
            LEFT JOIN stock_sbl_total st ON h.stock_id = st.stock_id AND h.trade_date = st.trade_date
            LEFT JOIN stock_sbl_sold ss  ON h.stock_id = ss.stock_id AND h.trade_date = ss.trade_date

            WINDOW
                lagw AS (PARTITION BY h.stock_id ORDER BY h.trade_date),
                w5   AS (PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING),
                w10  AS (PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 10 PRECEDING AND 1 PRECEDING),
                w20  AS (PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING),
                w60  AS (PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 60 PRECEDING AND 1 PRECEDING),
                r10  AS (PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW),
                r20  AS (PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW),
                s5   AS (PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 4 PRECEDING AND CURRENT ROW),
                s10  AS (PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 9 PRECEDING AND CURRENT ROW),
                s20  AS (PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW),
                s60  AS (PARTITION BY h.stock_id ORDER BY h.trade_date ROWS BETWEEN 59 PRECEDING AND CURRENT ROW)
        ),
        NumberedData AS (
            SELECT
                *,
                ROW_NUMBER() OVER(PARTITION BY stock_id ORDER BY trade_date) AS rn_all,
                ROW_NUMBER() OVER(PARTITION BY stock_id, (foreign_buy_sell > 0) ORDER BY trade_date) AS rn_foreign,
                ROW_NUMBER() OVER(PARTITION BY stock_id, (trust_buy_sell > 0) ORDER BY trade_date) AS rn_trust
            FROM BaseData
        ),
        FeatureData AS (
            SELECT
                *,
                LAG(ma5)  OVER(PARTITION BY stock_id ORDER BY trade_date) AS prev_ma5,
                LAG(ma10) OVER(PARTITION BY stock_id ORDER BY trade_date) AS prev_ma10,
                LAG(ma20) OVER(PARTITION BY stock_id ORDER BY trade_date) AS prev_ma20,
                LAG(ma60) OVER(PARTITION BY stock_id ORDER BY trade_date) AS prev_ma60,
                CASE WHEN foreign_buy_sell > 0 THEN ROW_NUMBER() OVER(PARTITION BY stock_id, (rn_all - rn_foreign) ORDER BY trade_date) ELSE 0 END AS foreign_streak_days,
                CASE WHEN trust_buy_sell > 0   THEN ROW_NUMBER() OVER(PARTITION BY stock_id, (rn_all - rn_trust)   ORDER BY trade_date) ELSE 0 END AS trust_streak_days
            FROM NumberedData
        )
        SELECT *
        FROM FeatureData
        WHERE trade_date >= :cutoffDate AND trade_date = :targetDatereplaceHere;";
    $replaceStr = "";
    foreach ($where as $whereStr) {
        $replaceStr .= " AND " . $whereStr;
    }
    $sql = str_replace('replaceHere', $replaceStr, $sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'cutoffDate' => $cutoffDate,
        'targetDate' => $targetDate
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function outputModel(PDO $pdo, array $sqlFetch): array
{
    $profile = [];
    $sqlProfile = "SELECT * FROM stock_profile";
    $stmtProfile = $pdo->prepare($sqlProfile);
    $stmtProfile->execute();
    foreach ($stmtProfile->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $profile[$row['stock_id']]['industry'] = $row['industry'];
    }
    $sqlSub_industry = "SELECT * FROM stock_sub_industry";
    $stmtSub_industry = $pdo->prepare($sqlSub_industry);
    $stmtSub_industry->execute();
    foreach ($stmtSub_industry->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $profile[$row['stock_id']]['sub_industry'][] = $row['sub_industry'];
    }
    $sqlConcept = "SELECT * FROM stock_concept";
    $stmtConcept = $pdo->prepare($sqlConcept);
    $stmtConcept->execute();
    foreach ($stmtConcept->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $profile[$row['stock_id']]['concept'][] = $row['concept'];
    }

    $dashboardResults = [];
    foreach ($sqlFetch as $s) {
        // =========================
        // Base Numbers
        // =========================
        $open  = (float)$s['open_price'];
        $high  = (float)$s['high_price'];
        $low  = (float)$s['low_price'];
        $close = (float)$s['close_price'];
        $yOpen  = (float)$s['yesterday_open'];
        $yHigh  = (float)$s['yesterday_high'];
        $yLow  = (float)$s['yesterday_low'];
        $yClose = (float)$s['yesterday_close'];
        $ma5  = (float)$s['ma5'];
        $ma10 = (float)$s['ma10'];
        $ma20 = (float)$s['ma20'];
        $ma60 = (float)$s['ma60'];
        $prevMa5  = (float)$s['prev_ma5'];
        $prevMa10 = (float)$s['prev_ma10'];
        $prevMa20 = (float)$s['prev_ma20'];
        $prevMa60 = (float)$s['prev_ma60'];
        $vma5  = (float)$s['vma5'];
        $vma10  = (float)$s['vma10'];
        $vma20 = (float)$s['vma20'];
        $vma60 = (float)$s['vma60'];
        $volRatio = ($s['yesterday_vol'] > 0) ? ($s['trade_volume'] / $s['yesterday_vol']) : 0;
        $rank10 = ($s['high10'] - $s['low10']) != 0 ? (($close - $s['low10']) / ($s['high10'] - $s['low10']) * 100) : 0;
        $amp10 = ($s['low10'] != 0) ? (($s['high10'] - $s['low10']) / $s['low10'] * 100) : 0;
        $bia5  = $ma5 ? (($close - $ma5) / $ma5 * 100) : 0;
        $bia10 = $ma10 ? (($close - $ma10) / $ma10 * 100) : 0;
        $bia20 = $ma20 ? (($close - $ma20) / $ma20 * 100) : 0;
        $bia60 = $ma60 ? (($close - $ma60) / $ma60 * 100) : 0;
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
        if ($close > $ma20 && $yClose <= $prevMa20) {
            $addSignal(
                'trend',
                true,
                '首次站上月線',
                12
            );
        } else if ($close > $ma20) {
            $addSignal(
                'trend',
                true,
                '站上月線',
                4
            );
        }

        if ($close > $ma60 && $yClose <= $prevMa60) {
            $addSignal(
                'trend',
                true,
                '首次站上季線',
                15
            );
        } else if ($close > $ma60) {
            $addSignal(
                'trend',
                true,
                '站上季線',
                6
            );
        }

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
        // 產業概念
        // =========================
        $industry = $profile[$s['stock_id']]['industry'] ?? '';
        $subIndustry = $profile[$s['stock_id']]['sub_industry'] ?? [];
        $concept = $profile[$s['stock_id']]['concept'] ?? [];

        // =========================
        // 輸出
        // =========================
        $dashboardResults[] = [
            'stock_id' => $s['stock_id'],
            'stock_name' => $s['stock_name'],
            'industry' => $industry,
            'subIndustry' => $subIndustry,
            'concept' => $concept,
            'score' => $finalScore,
            'rating' => $rating,
            'strategy_type' => $strategyType,
            'confidence' => $confidence,
            'trend_score' => round($trendScore),
            'momentum_score' => round($momentumScore),
            'chip_score' => round($chipScore),
            'structure_score' => round($structureScore),
            'risk_score' => round($riskScore),
            'close' => round($close, 2),
            'yesterday_high' => round($yHigh, 2),
            'yesterday_low' => round($yLow, 2),
            'vol' => round($s['trade_volume'] / 1000, 0),
            'vol_ratio' => round($volRatio, 2),
            'rank10' => round($rank10, 2),
            'amp10' => round($amp10, 2),
            'ma5' => round($ma5, 2),
            'ma10' => round($ma10, 2),
            'ma20' => round($ma20, 2),
            'ma60' => round($ma60, 2),
            'vma5' => round($vma5 / 1000, 0),
            'vma10' => round($s['vma10'] / 1000, 0),
            'vma20' => round($vma20 / 1000, 0),
            'bia5' => round($bia5, 2),
            'bia10' => round($bia10, 2),
            'bia20' => round($bia20, 2),
            'con1' => round($con1, 2),
            'con5' => round($con5, 2),
            'con10' => round($con10, 2),
            'con20' => round($con20, 2),
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
            'margin_balance' => (int)$s['margin_balance'],
            'margin_balance_diff' => (int)$s['margin_balance_diff'],
            'squeeze' => round($squeeze, 2),
            'bullet' => round($bullet, 2),
            'net_sbl' => round($s['net_sbl'] / 1000, 0),
            'net_sbl_sum5' => round($s['net_sbl_sum5'] / 1000, 0),
            'sbl_total' => round($s['sbl_total'] / 1000, 0),
            'sbl_sold_balance' => round($s['sbl_sold_balance'] / 1000, 0),
            'signals' => $signals,
            'tags' => $tags,
            'trigger_reasons' => $triggerReasons
        ];
    }

    // =========================
    // Sort by Score
    // =========================
    usort(
        $dashboardResults,
        fn($a, $b) => $b['score'] <=> $a['score']
    );
    return $dashboardResults;
}

function getStockAnalysisChart(PDO $pdo, string $stockId, string $targetDate, int $displayDays = 20): array
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

// 00981A
function getComponentOf00981A_FromLocal(PDO $pdo, string $targetDate): array
{
    $jsonFile = 'stock_data.json';
    if (file_exists($jsonFile)) {
        $jsonStr = file_get_contents($jsonFile);
        $data = json_decode($jsonStr, true);
        if ($data) {
            $details = null;
            foreach ($data as $item) {
                if ($item['AssetName'] === '股票') {
                    $details = $item['Details'];
                    $value = (int)$item['Value'];
                }
            }
            $totalAmount = 0;
            foreach ($details as $detail) {
                $itemDate = substr($detail['EditTime'], 0, 10);
                if ($itemDate !== $targetDate) {
                    throw new RuntimeException('00981A 資料未完全更新');
                }
                $totalAmount += (int)$detail['Amount'];
            }
            if (isset($value) && $value !== $totalAmount) {
                throw new RuntimeException('00981A 總市值不符');
            }
            return $details;
        }
    }
    throw new RuntimeException('查詢不到 00981A 成分股資料');
}

function insertComponentOf00981A(PDO $pdo, string $targetDate, array $data): void
{
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
        foreach ($data as $row) {
            $stmt->execute([
                $targetDate,
                $row['DetailCode'],
                $row['DetailName'],
                (int)$row['Share'],
                $row['NavRate']
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw new RuntimeException($targetDate . " 00981A 成分股資料新增失敗: " . $e->getMessage());
    }
}

function analyzeMultiPeriodChanges(PDO $pdo, string $targetDate): array
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
        if (!$rows) throw new RuntimeException('查詢不到 00981A 成分股歷史資料');
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
                'diff1'      => (int)$diff1,
                'diff5'      => (int)$item['diff5'],
                'diff10'     => (int)$item['diff10'],
                'diff20'     => (int)$item['diff20']
            ];
        }
        $notificationStr = "00981A成分股今日變動,請稍候佈署 - https://yong-jhih.github.io/Stocks/?page=00981A_component\n" . "增持共" . count($increase) . "檔\n" . "減持共" . count($decrease) . "檔\n" . "無變動共" . count($constant) . "檔\n";
        if (count($eliminate) > 0) $notificationStr .= "剔除共" . count($eliminate) . "檔:" . implode(',', $eliminate) . "\n";
        if (count($new) > 0) $notificationStr .= "新納入共" . count($new) . "檔:" . implode(',', $new) . "\n";
        return [$finalData, $notificationStr];
    } catch (Throwable $e) {
        throw new RuntimeException("Database Error: " . $e->getMessage());
    }
}

// 產業概念
function getStockProfileTSE(PDO $pdo): array
{
    $stocksTSE = [];
    $industry = json_decode(file_get_contents('data/industry_code.json'), true);
    $url = "https://openapi.twse.com.tw/v1/opendata/t187ap03_L";
    for ($i = 1; $i <= 3; $i++) {
        $data = fetchUrl($pdo, $url);
        if (isset($data['status']) && $data['status'] === 'error') {
            $errorMsg = $data['msg'] ?? '未知錯誤';
            writeLog($pdo, 'getStockProfileTSE', "證交所回傳錯誤訊息：{$errorMsg}, 準備執行第 {$i} 次重試", 'warning');
            continue;
        }
        if (!is_array($data) || empty($data)) {
            writeLog($pdo, 'getStockProfileTSE', "證交所回傳資料格式異常或無資料, 準備執行第 {$i} 次重試", 'warning');
            continue;
        }
        foreach ($data as $v) {
            if (isset($v['公司代號'], $v['公司簡稱'])) {
                $stocksTSE[$v['公司代號']] = [
                    'stock_id' => $v['公司代號'],
                    'stock_name' => $v['公司簡稱'],
                    'stock_type' => 'TSE',
                    'industry'   => $industry[(string)($v['產業別'] ?? '')] ?? ''
                ];
            }
        }
        return $stocksTSE;
    }
    writeLog($pdo, 'getStockProfileTSE', "執行 3 次失敗,退出", 'error');
    throw new RuntimeException('取得上市公司基本資料, 執行 3 次失敗,退出');
}

function getStockProfileTPEx(PDO $pdo): array
{
    $stocksOTC = [];
    $industry = json_decode(file_get_contents('data/industry_code.json'), true);
    $url = "https://www.tpex.org.tw/openapi/v1/mopsfin_t187ap03_O";
    for ($i = 1; $i <= 3; $i++) {
        $data = fetchUrl($pdo, $url);
        if (isset($data['status']) && $data['status'] === 'error') {
            $errorMsg = $data['msg'] ?? '未知錯誤';
            writeLog($pdo, 'getStockProfileTPEx', "證交所回傳錯誤訊息：{$errorMsg}, 準備執行第 {$i} 次重試", 'warning');
            continue;
        }
        if (!is_array($data) || empty($data)) {
            writeLog($pdo, 'getStockProfileTPEx', "證交所回傳資料格式異常或無資料, 準備執行第 {$i} 次重試", 'warning');
            continue;
        }
        foreach ($data as $v) {
            if (isset($v['SecuritiesCompanyCode'], $v['CompanyAbbreviation'])) {
                $stocksOTC[$v['SecuritiesCompanyCode']] = [
                    'stock_id' => $v['SecuritiesCompanyCode'],
                    'stock_name' => $v['CompanyAbbreviation'],
                    'stock_type' => 'TPEx',
                    'industry'   => $industry[(string)($v['SecuritiesIndustryCode'] ?? '')] ?? ''
                ];
            }
        }
        return $stocksOTC;
    }
    writeLog($pdo, 'getStockProfileTPEx', "執行 3 次失敗,退出", 'error');
    throw new RuntimeException('取得上櫃公司基本資料, 執行 3 次失敗,退出');
}

function getStockProfileESM(PDO $pdo): array
{
    $stocksESM = [];
    $industry = json_decode(file_get_contents('data/industry_code.json'), true);
    $url = "https://www.tpex.org.tw/openapi/v1/mopsfin_t187ap03_R";
    for ($i = 1; $i <= 3; $i++) {
        $data = fetchUrl($pdo, $url);
        if (isset($data['status']) && $data['status'] === 'error') {
            $errorMsg = $data['msg'] ?? '未知錯誤';
            writeLog($pdo, 'getStockProfileESM', "證交所回傳錯誤訊息：{$errorMsg}, 準備執行第 {$i} 次重試", 'warning');
            continue;
        }
        if (!is_array($data) || empty($data)) {
            writeLog($pdo, 'getStockProfileESM', "證交所回傳資料格式異常或無資料, 準備執行第 {$i} 次重試", 'warning');
            continue;
        }
        foreach ($data as $v) {
            if (isset($v['SecuritiesCompanyCode'], $v['CompanyAbbreviation'])) {
                $stocksESM[$v['SecuritiesCompanyCode']] = [
                    'stock_id' => $v['SecuritiesCompanyCode'],
                    'stock_name' => $v['CompanyAbbreviation'],
                    'stock_type' => 'ESM',
                    'industry'   => $industry[(string)($v['SecuritiesIndustryCode'] ?? '')] ?? ''
                ];
            }
        }
        return $stocksESM;
    }
    writeLog($pdo, 'getStockProfileESM', "執行 3 次失敗,退出", 'error');
    throw new RuntimeException('取得興櫃公司基本資料, 執行 3 次失敗,退出');
}

function getStockProfileETF(PDO $pdo): array
{
    $stocksETF = [];
    $url = "https://openapi.twse.com.tw/v1/opendata/t187ap47_L";
    for ($i = 1; $i <= 3; $i++) {
        $data = fetchUrl($pdo, $url);
        if (isset($data['status']) && $data['status'] === 'error') {
            $errorMsg = $data['msg'] ?? '未知錯誤';
            writeLog($pdo, 'getStockProfileETF', "證交所回傳錯誤訊息：{$errorMsg}, 準備執行第 {$i} 次重試", 'warning');
            continue;
        }
        if (!is_array($data) || empty($data)) {
            writeLog($pdo, 'getStockProfileETF', "證交所回傳資料格式異常或無資料, 準備執行第 {$i} 次重試", 'warning');
            continue;
        }
        foreach ($data as $v) {
            if (isset($v['基金代號'], $v['基金簡稱'])) {
                $stocksETF[$v['基金代號']] = [
                    'stock_id' => $v['基金代號'],
                    'stock_name' => $v['基金簡稱'],
                    'stock_type' => 'ETF',
                    'industry'   => ''
                ];
            }
        }
        return $stocksETF;
    }
    writeLog($pdo, 'getStockProfileETF', "執行 3 次失敗,退出", 'error');
    throw new RuntimeException('取得上市ETF基本資料, 執行 3 次失敗,退出');
}

function updateIndustry(PDO $pdo, array $stocks): void
{
    $pdo->beginTransaction();
    try {
        foreach (array_chunk($stocks, 500) as $chunk) {
            $values = [];
            $params = [];
            foreach ($chunk as $row) {
                $values[] = "(?, ?, ?, ?)";
                $params[] = $row['stock_id'];
                $params[] = $row['stock_name'];
                $params[] = $row['stock_type'];
                $params[] = $row['industry'];
            }
            $sql = "
                INSERT INTO stock_profile
                (stock_id, stock_name, stock_type, industry)
                VALUES " . implode(',', $values) . "
                ON DUPLICATE KEY UPDATE
                    stock_name = VALUES(stock_name),
                    stock_type = VALUES(stock_type),
                    industry   = VALUES(industry)
            ";
            $pdo->prepare($sql)->execute($params);
        }
        $pdo->commit();
        writeLog($pdo, 'updateIndustry', '產業別 更新完成,共更新 ' . count($stocks) . ' 筆', 'success');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw new RuntimeException("[updateIndustry] " . $e->getMessage(), 0, $e);
    }
}

function updateSubIndustry(PDO $pdo, array $stocks): void
{
    $stockList = array_column($stocks, 'stock_id');
    if (empty($stockList)) return;

    // 1. 先刪除舊資料（這部分可以保持一次性處理，效率較高）
    try {
        $placeholders = implode(',', array_fill(0, count($stockList), '?'));
        $sqlDelete = "DELETE FROM stock_sub_industry WHERE stock_id IN ($placeholders)";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute($stockList);
    } catch (Throwable $e) {
        throw new RuntimeException("[updateSubIndustry Delete Error] " . $e->getMessage(), 0, $e);
    }

    // 2. 設定併發數量 (例如每次同時抓 15 檔，避免被對方網站封鎖)
    $concurrency = 15;
    $stockChunks = array_chunk($stocks, $concurrency, true);
    $totalInsertCount = 0;
    foreach ($stockChunks as $chunk) {
        $mh = curl_multi_init();
        $handlers = [];
        foreach ($chunk as $stockId => $stock) {
            $url = "https://ic.tpex.org.tw/company_chain.php?stk_code=" . $stock['stock_id'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_multi_add_handle($mh, $ch);
            $handlers[$stock['stock_id']] = $ch;
        }
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        // 3. 解析與寫入資料 (每一批完成就寫入，並用小交易包起來)
        $pdo->beginTransaction();
        try {
            $allValues = [];
            $allParams = [];
            foreach ($handlers as $stockId => $ch) {
                $html = curl_multi_getcontent($ch);
                $curlError = curl_error($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                if ($html === false || trim($html) == '') {
                    writeLog($pdo, 'updateSubIndustry', "【次產業】代號 {$stockId} 抓取失敗或為空：{$curlError}", 'warning');
                    continue;
                }

                // 解析 HTML
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                @$dom->loadHTML($html); // 加 @ 隱藏 HTML5 標籤不規範的警告
                $xpath = new DOMXPath($dom);
                $nodes = $xpath->query('//h4[a[contains(@href,"introduce.php")]]');

                $subIndustries = [];
                foreach ($nodes as $node) {
                    $text = html_entity_decode(trim($node->textContent));
                    $parts = explode('>', $text);
                    if (count($parts) < 2) continue;
                    $subIndustry = trim(end($parts));
                    if ($subIndustry != '') {
                        $subIndustries[] = $subIndustry;
                    }
                }
                $subIndustries = array_values(array_unique($subIndustries));
                if (!empty($subIndustries)) {
                    $values = [];
                    $params = [];
                    // foreach ($subIndustries as $subIndustry) {
                    //     $values[] = "(?, ?)";
                    //     $params[] = $stockId;
                    //     $params[] = $subIndustry;
                    // }
                    // $sqlInsert = "INSERT INTO stock_sub_industry (stock_id, sub_industry) VALUES " . implode(',', $values);
                    // $stmtInsert = $pdo->prepare($sqlInsert);
                    // $stmtInsert->execute($params);

                    foreach ($subIndustries as $subIndustry) {
                        $allValues[] = "(?, ?)";
                        $allParams[] = $stockId;
                        $allParams[] = $subIndustry;
                    }
                    // $totalInsertCount += $stmtInsert->rowCount();
                }
            }
            if (!empty($allValues)) {
                $sql = "INSERT INTO stock_sub_industry (stock_id, sub_industry) VALUES " . implode(',', $allValues);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($allParams);
                $totalInsertCount += $stmt->rowCount();
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            writeLog($pdo, 'updateSubIndustry', "批次寫入失敗: " . $e->getMessage(), 'error');
        }
        curl_multi_close($mh);
        usleep(200000);
    }
    writeLog($pdo, 'updateSubIndustry', '次產業 更新完成,共更新 ' . $totalInsertCount . ' 筆', 'success');
}
function updateConcept(PDO $pdo, array $stocks): void
{
    $stockList = array_filter(array_column($stocks, 'stock_id'));
    if (empty($stockList)) return;

    $pdo->beginTransaction();
    try {
        // 先刪除舊資料
        $sqlDelConcept = "DELETE FROM stock_concept WHERE stock_id IN (" . implode(',', $stockList) . ")";
        $stmtDelConcept = $pdo->prepare($sqlDelConcept);
        $stmtDelConcept->execute();

        // 取得概念
        $url = "https://www.moneydj.com/Z/ZG/ZGE/ZGE_E_E.djhtm";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $html = curl_exec($ch);
        curl_close($ch);

        if ($html === false) {
            throw new RuntimeException("無法取得 MoneyDJ 分類主頁面");
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//select[@name="M1"]/option');

        $result = [];
        foreach ($nodes as $node) {
            $value = trim($node->getAttribute('value'));
            $name = str_replace("概念股", "", trim($node->textContent));
            if ($value !== '') {
                $result[] = [
                    'concept_id' => $value,
                    'concept_name' => $name,
                ];
            }
        }

        $totalInsertCount = 0;
        $stockMap = array_flip($stockList);
        foreach ($result as $k => $v) {
            $url = "https://www.moneydj.com/z/zg/zge_" . $v['concept_id'] . "_1.djhtm";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $html = curl_exec($ch);
            curl_close($ch);

            if ($html === false) {
                throw new RuntimeException("無法取得分類成分股，分類：{$v['concept_name']}");
            }

            $c = [];
            $a = explode("GenLink2stk('AS", $html);
            foreach ($a as $i => $b) {
                if ($i == 0) continue;
                $c[] = substr($b, 0, 4);
            }
            $c = array_values(array_unique($c));

            $values = [];
            $params = [];
            foreach ($c as $stock_id) {
                if (!isset($stockMap[$stock_id])) continue;

                $values[] = "(?, ?)";
                $params[] = $stock_id;
                $params[] = $v['concept_name'];
            }

            if (!empty($values)) {
                $sql = "INSERT IGNORE INTO stock_concept (stock_id, concept) VALUES " . implode(',', $values);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $totalInsertCount += $stmt->rowCount();
            }

            if ($k > 0 && $k % 10 == 0) sleep(1);
        }
        $pdo->commit();
        writeLog($pdo, 'updateConcept', '概念股 更新完成,共更新 ' . $totalInsertCount . ' 筆', 'success');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw new RuntimeException("[updateConcept] " . $e->getMessage(), 0, $e);
    }
}

function updateStockProfile(PDO $pdo): void
{
    $start_time = microtime(true);
    writeLog($pdo, 'updateStockProfile', '開始更新基本資料及產業別及次產業概念', 'start');
    try {
        $stocksTSE = getStockProfileTSE($pdo);
        $stocksTPEx = getStockProfileTPEx($pdo);
        $stocksESM = getStockProfileESM($pdo);
        $stocksETF = getStockProfileETF($pdo);
        $stocks = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM];
        $stocksMix = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM, ...$stocksETF];
        $stocks_R = [];
        foreach ($stocks as $v) {
            $stocks_R[$v['stock_id']] = $v;
        }
        createJsonFile($pdo, 'stockProfileList', $stocks_R);
        createJsonFile($pdo, 'ETFProfileList', $stocksETF);
        updateIndustry($pdo, $stocksMix);
        updateSubIndustry($pdo, $stocks);
        updateConcept($pdo, $stocks);
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);
        writeLog($pdo, 'updateStockProfile', '產業別及次產業概念更新完成,共耗時 ' . $execution_time . ' 秒', 'end');
    } catch (Throwable $e) {
        writeLog($pdo, 'updateStockProfile', "產業別及次產業概念更新失敗，原因：" . $e->getMessage(), 'error');
        throw $e;
    }
}
