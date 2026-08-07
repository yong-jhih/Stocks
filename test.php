<?php
require_once("init.php");

function updateTX(PDO $pdo, string $targetDate): void
{
    $start_time = microtime(true);
    writeLog($pdo, 'updateAllHistory', "取得交易日期 [{$targetDate}] 開始更新上市盤後資料", 'start');
    try {
        $historyData = null;
        for ($i = 1; $i <= 10; $i++) {
            if (empty($historyData)) $historyData = getHistory($pdo, $targetDate);
            if (!empty($historyData) && !empty($instiData) && !empty($marginData) && !empty($SBLTotalData) && !empty($SBLSoldData)) {
                break;
            } else {
                if ($i <= 9) {
                    writeLog($pdo, 'updateAllHistory', "第 {$i}/10 次抓取完成, 尚有缺漏資料, 60秒後重試", 'warning');
                    sleep(60);
                } else {
                    writeLog($pdo, 'updateAllHistory', "第 {$i}/10 次抓取完成, 尚有缺漏資料, 停止重試, 直接寫入現有資料", 'warning');
                }
            }
        }
        if (!checkIfDataPublished($pdo, $targetDate, 'stock_history', 700) && !empty($historyData)) insertHistory($pdo, $targetDate, $historyData);
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);
        writeLog($pdo, 'updateAllHistory', "更新上市盤後資料結束, 共耗時   {$execution_time}   秒", 'end');
    } catch (Throwable $e) {
        writeLog($pdo, 'updateAllHistory', "上市歷史資料更新失敗，原因：{$e->getMessage()}", 'error');
        throw new RuntimeException("上市歷史資料更新失敗，原因：{$e->getMessage()}");
    }
}
function getTX(PDO $pdo): ?array
{
    $url = "https://openapi.taifex.com.tw/v1/MarketDataOfMajorInstitutionalTradersDetailsOfFuturesContractsBytheDate";
    for ($i = 1; $i <= 3; $i++) {
        $data = fetchUrl($url);
        if (!empty($data) && isset($data['status']) && $data['status'] != 'error') {
            return $data;
        }
    }
    writeLog($pdo, 'getTX', '取得 區分各期貨契約 執行 3 次失敗,跳過', 'warning');
    return null;
}

function insertTX(PDO $pdo, string $targetDate, array $historyData): void
{
    $start_time = microtime(true);
    $sql = "INSERT INTO stock_history 
            (trade_date, stock_id, open_price, high_price, low_price, close_price, trade_volume, trade_value) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
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
    } catch (Throwable $e) {
        $pdo->rollBack();
        writeLog($pdo, 'insertHistory', $targetDate . ' 上市個股日成交 寫入失敗：' . $e->getMessage(), 'error');
    }
}


