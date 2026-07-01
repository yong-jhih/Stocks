<?php

require_once("init.php");

$stockIds = [];
$a = json_decode(file_get_contents('data/2026-06-30_componentOf00981A.json.json'), true);
foreach ($a as $v) {
    $stockIds[] = $v['stock_id'];
}
$result = getEtfComponentChartData($pdo,  '00981A',  $targetDate, $stockIds);
createJsonFile($pdo, $targetDate . '_test', $result);
