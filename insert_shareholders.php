<?php
require_once("init.php");

writeLog($pdo, 'insertTDCC', "取得本周最新交易日期 [ {$targetDate} ] 股東人數資料, 開始更新", 'end');
$start_time = microtime(true);
$content = file_get_contents('tdcc_result.json');
$results = json_decode($content, true);
$sql = "
    INSERT INTO stock_shareholder
    (
        trade_date,
        stock_id,
        shareholder_count,
        total_shares
    )
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        shareholder_count = VALUES(shareholder_count),
        total_shares = VALUES(total_shares)
";
$stmt = $pdo->prepare($sql);
try {
    $pdo->beginTransaction();
    foreach ($results as $row) {
        if (empty($row['stock_id'])) continue;
        if (isset($row['success']) && !$row['success']) continue;
        if (!isset($row['shareholder_count']) || !isset($row['total_shares'])) continue;
        $stmt->execute([
            $targetDate,
            $row['stock_id'],
            $row['shareholder_count'],
            $row['total_shares']
        ]);
    }
    $pdo->commit();
    unlink('tdcc_result.json');
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    writeLog($pdo, 'insertTDCC', "股東人數更新完成, 共耗時 {$execution_time} 秒", 'end');
    updateSystemLog($pdo);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    writeLog($pdo, 'insertTDCC', $e->getMessage(), 'error');
    updateSystemLog($pdo);
    exit(1);
}
