<?php
$start_time = microtime(true);
require_once("init.php");

$targetDate = '2026-08-21';
$stocksMap = getStocksMap();
$stocks = [
    'targetDate' => $targetDate,
    'stock_id_array' => []
];
foreach ($stocksMap as $stock_id => $stock) {
    if (in_array($stock['stock_type'], ["TSE", "TPEx"])) {
        $stocks['stock_id_array'][] = $stock_id;
        // $arg1 = escapeshellarg($stock_id);
        // $arg2 = escapeshellarg(str_replace("-", "", $targetDate));
        // $rawOutput = json_decode(shell_exec("node prefetch_TDCC.js {$arg1} {$arg2}"));
        // try {
        //     $pdo->beginTransaction();
        //     $sql = "INSERT INTO stock_shareholder
        //         (trade_date, stock_id, shareholder_count, total_shares)
        //         VALUES (?, ?, ?, ?)
        //         ON DUPLICATE KEY UPDATE
        //         shareholder_count = VALUES(shareholder_count),
        //         total_shares = VALUES(total_shares)";
        //     $stmt = $pdo->prepare($sql);
        //     $stmt->execute([
        //         $targetDate,
        //         $stock_id,
        //         (int)str_replace(",", "", $rawOutput[2]),
        //         (int)str_replace(",", "", $rawOutput[3])
        //     ]);
        //     $pdo->commit();
        // } catch (Throwable $e) {
        //     $pdo->rollBack();
        // }
    }
}
$count = count($stocks['stock_id_array']);
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
echo "執行時間:{$execution_time} 秒\n";
echo "檔數:{$count} 檔\n";
