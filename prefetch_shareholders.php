<?php
require_once("init.php");
$targetDate='2026-08-21';

$stocksMap = getStocksMap();
$stocks = [
    'targetDate' => str_replace("-", "", $targetDate),
    'stock_id_array' => []
];
foreach ($stocksMap as $stock_id => $stock) {
    if (in_array($stock['stock_type'], ["TSE", "TPEx"])) {
        $stocks['stock_id_array'][] = $stock_id;
    }
}
file_put_contents('tdcc_task.json', json_encode($stocks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$output = shell_exec("node prefetch_TDCC_batch.js " . escapeshellarg('tdcc_task.json'));
file_put_contents('tdcc_result.json', $output);