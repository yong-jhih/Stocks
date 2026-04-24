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
                foreach ($v['data'] as $v1) {
                    if (preg_match('/^[1-9]\d{3}$/', trim($v1[0]))) {
                        $stocks[] = $v1;
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
            foreach ($data['data'] as $v) {
                if (preg_match('/^[1-9]\d{3}$/', $v[0])) {
                    $stocks[] = $v;
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
                foreach ($v['data'] as $row) {
                    if (preg_match('/^[1-9]\d{3}$/', $row[0])) {
                        $stocks[] = $row;
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
            foreach ($data['data'] as $row) {
                if (preg_match('/^[1-9]\d{3}$/', $row[0]) && $row[8] == '集中市場') {
                    $stocks[] = $row;
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
            foreach ($data['data'] as $row) {
                if (preg_match('/^[1-9]\d{3}$/', $row[0])) {
                    $stocks[] = $row;
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

function insertHistory($pdo, $targetDate, $historyData)
{
    if (!is_array($historyData) || isset($historyData['status'])) return;
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
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
    }
}

function insertInsti($pdo, $targetDate, $instiData)
{
    if (!is_array($instiData) || isset($instiData['status'])) return;

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
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
    }
}

function insertMargin($pdo, $targetDate, $marginData)
{
    if (!is_array($marginData) || isset($marginData['status'])) return;

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
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
    }
}
// 借券餘額 (TWT72U)
function insertSBLTotal($pdo, $targetDate, $SBLTotalData)
{
    if (!is_array($SBLTotalData) || isset($SBLTotalData['status'])) return;

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
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "失敗：" . $e->getMessage();
    }
}

// 借券賣出管制 (TWT93U)
function insertSBLSold($pdo, $targetDate, $SBLSoldData)
{
    if (!is_array($SBLSoldData) || isset($SBLSoldData['status'])) return;

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
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "失敗：" . $e->getMessage();
    }
}
