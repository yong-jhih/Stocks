<?php
require_once("init.php");

$stockList = json_decode(file_get_contents("data/" . "2026-05-11" . "_filter.json"), true);
$allData = [
    'date' => "2026-05-11",
    'stocks' => []
];

foreach ($stockList as $stock) {
    $data = getStockAnalysisChart($pdo, $stock['stock_id'], "2026-05-11");
    if ($data) {
        $allData['stocks'][$stock['stock_id']] = $data;
    }
}
createJsonFile($pdo, "2026-05-11", 'chart', $allData);
