<?php
$start_time = microtime(true);
require_once("init.php");

$targetDate = '2026-08-21';
$stocksMap = getStocksMap();
$stocks = [
    'targetDate' => str_replace("-", "", $targetDate),
    'stock_id_array' => []
];
$i = 0;
foreach ($stocksMap as $stock_id => $stock) {
    if (in_array($stock['stock_type'], ["TSE", "TPEx"])) {
        $stocks['stock_id_array'][] = $stock_id;
        $i++;
    }
    if ($i > 5) break;
}
$taskFile = 'tdcc_task.json';
file_put_contents(
    $taskFile,
    json_encode(
        $stocks,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    )
);
$output = shell_exec(
    "node prefetch_TDCC_batch.js " .
        escapeshellarg($taskFile)
);
$results = json_decode($output, true);
$count = count($results);

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
    throw $e;
}

// echo "================ 結果 ================\n";
// print_r($results);
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
echo "\n執行時間: {$execution_time} 秒\n";
echo "執行檔數: {$count} 檔\n";
