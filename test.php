<?php

require_once("init.php");
$targetDate = '2026-06-30';
$stockIds = [];
$a = json_decode(file_get_contents("data/{$targetDate}_componentOf00981A.json"), true);
foreach ($a as $v) {
    $stockIds[] = $v['stock_id'];
}
$result = getEtfComponentChartDataTest($pdo,  '00981A',  $targetDate, $stockIds);
createJsonFile($pdo, $targetDate . '_test', $result);
