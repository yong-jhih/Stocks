<?php
require_once("init.php");

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
        if (!isset($row['shareholder_count']) || !isset($row['total_shares'])) continue;
        $stmt->execute([
            $targetDate,
            $row['stock_id'],
            $row['shareholder_count'],
            $row['total_shares']
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    writeLog($pdo, 'insertTDCC', $e->getMessage(), 'error');
    exit(1);
}
