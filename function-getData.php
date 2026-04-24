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
                    if (preg_match('/^[1-9]\d{3}$/', trim($v1[0]))) {
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
    if (!is_array($historyData) || isset($historyData['status'])) {
        echo "資料有誤，跳過寫入。\n";
        return;
    }

    $requiredFields = ['證券代號', '開盤價', '最高價', '最低價', '收盤價'];
    foreach ($requiredFields as $f) {
        if (!isset($historyData[0][$f])) {
            echo "錯誤：格式已變更，找不到欄位 {$f}\n";
            return;
        }
    }

    echo "正在處理 {$targetDate} 的行情資料...\n";

    $sql = "INSERT IGNORE INTO stock_history 
            (trade_date, stock_id, stock_name, open_price, high_price, low_price, close_price, trade_volume) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction(); // 開啟事務

    try {
        foreach ($historyData as $row) {
            $open  = is_numeric($row['開盤價']) ? $row['開盤價'] : 0;
            $high  = is_numeric($row['最高價']) ? $row['最高價'] : 0;
            $low   = is_numeric($row['最低價']) ? $row['最低價'] : 0;
            $close = is_numeric($row['收盤價']) ? $row['收盤價'] : 0;
            $stmt->execute([
                $targetDate,
                $row['證券代號'] ?? '',
                $row['證券名稱'] ?? '',
                (float)$open,
                (float)$high,
                (float)$low,
                (float)$close,
                (int)($row['成交股數'] ?? 0)
            ]);
        }
        $pdo->commit();
        echo "1. 收盤行情處理完畢。\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
    }
}

function insertInsti($pdo, $targetDate, $instiData)
{
    if (!is_array($instiData) || isset($instiData['status'])) {
        echo "資料有誤，跳過寫入。\n";
        return;
    }

    echo "正在處理 {$targetDate} 的行情資料...\n";

    $sql = "INSERT IGNORE INTO stock_insti 
            (trade_date, stock_id, foreign_buy_sell, trust_buy_sell, dealer_buy_sell, total_buy_sell) 
            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction(); // 開啟事務

    try {
        foreach ($instiData as $row) {
            $foreign_buy_sell  = is_numeric($row['外陸資買賣超股數(不含外資自營商)']) ? $row['外陸資買賣超股數(不含外資自營商)'] : 0;
            $trust_buy_sell  = is_numeric($row['投信買賣超股數']) ? $row['投信買賣超股數'] : 0;
            $dealar_buy_sell   = is_numeric($row['自營商買賣超股數']) ? $row['自營商買賣超股數'] : 0;
            $total_buy_sell = is_numeric($row['三大法人買賣超股數']) ? $row['三大法人買賣超股數'] : 0;
            $stmt->execute([
                $targetDate,
                $row['證券代號'] ?? '',
                (int)$foreign_buy_sell,
                (int)$trust_buy_sell,
                (int)$dealar_buy_sell,
                (int)$total_buy_sell
            ]);
        }
        $pdo->commit();
        echo "2. 三大法人買賣超處理完畢。\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
    }
}

function insertMargin($pdo, $targetDate, $marginData)
{
    if (!is_array($marginData) || isset($marginData['status'])) {
        echo "資料有誤，跳過寫入。\n";
        return;
    }

    echo "正在處理 {$targetDate} 的行情資料...\n";

    $sql = "INSERT IGNORE INTO stock_margin 
            (trade_date, stock_id, margin_balance, margin_balance_diff, short_balance, short_balance_diff) 
            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction(); // 開啟事務

    try {
        foreach ($marginData as $row) {
            $margin_balance  = is_numeric(str_replace(',', '', $row[6])) ? str_replace(',', '', $row[6]) : 0;
            $margin_balance_diff  = is_numeric(str_replace(',', '', $row[6]) - str_replace(',', '', $row[5])) ? str_replace(',', '', $row[6]) - str_replace(',', '', $row[5]) : 0;
            $short_balance   = is_numeric(str_replace(',', '', $row[12])) ? str_replace(',', '', $row[12]) : 0;
            $short_balance_diff = is_numeric(str_replace(',', '', $row[12]) - str_replace(',', '', $row[11])) ? str_replace(',', '', $row[12]) - str_replace(',', '', $row[11]) : 0;
            $stmt->execute([
                $targetDate,
                $row[0] ?? '',
                (int)$margin_balance,
                (int)$margin_balance_diff,
                (int)$short_balance,
                (int)$short_balance_diff
            ]);
        }
        $pdo->commit();
        echo "3. 融資融券彙總處理完畢。\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
    }
}

function insertSBLTotal($pdo, $targetDate, $SBLTotalData)
{
    if (!is_array($SBLTotalData) || isset($SBLTotalData['status'])) {
        echo "資料有誤，跳過寫入。\n";
        return;
    }

    echo "正在處理 {$targetDate} 的借券餘額資料...\n";

    $sql = "INSERT IGNORE INTO stock_sbl_total 
            (trade_date, stock_id, sbl_balance) 
            VALUES (?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction(); // 開啟事務

    try {
        foreach ($SBLTotalData as $row) {
            $sbl_total  = is_numeric(str_replace(',', '', $row[5])) ? str_replace(',', '', $row[5]) : 0;
            $stmt->execute([
                $targetDate,
                $row[0] ?? '',
                (int)$sbl_total,
            ]);
        }
        $pdo->commit();
        echo "4. 借券餘額處理完畢。\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
    }
}

function insertSBLSold($pdo, $targetDate, $SBLSoldData)
{
    if (!is_array($SBLSoldData) || isset($SBLSoldData['status'])) {
        echo "資料有誤，跳過寫入。\n";
        return;
    }

    echo "正在處理 {$targetDate} 的借券賣還資料...\n";

    $sql = "INSERT IGNORE INTO stock_sbl_sold 
            (trade_date, stock_id, sbl_sold_balance, sbl_sold, sbl_return) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction(); // 開啟事務

    try {
        foreach ($SBLSoldData as $row) {
            $sbl_sold_balance  = is_numeric(str_replace(',', '', $row[12])) ? str_replace(',', '', $row[12]) : 0;
            $sbl_sold  = is_numeric(str_replace(',', '', $row[9])) ? str_replace(',', '', $row[9]) : 0;
            $sbl_return   = is_numeric(str_replace(',', '', $row[10])) ? str_replace(',', '', $row[10]) : 0;
            $stmt->execute([
                $targetDate,
                $row[0] ?? '',
                (int)$sbl_sold_balance,
                (int)$sbl_sold,
                (int)$sbl_return
            ]);
        }
        $pdo->commit();
        echo "5. 信用額度總量管制餘額表處理完畢。\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "寫入失敗：" . $e->getMessage();
    }
}
