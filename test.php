<?php
require_once("init.php");
$targetDate = '2026-07-21';
$etfId = '00981A';

try {
    $analyzeMultiPeriodChanges = analyzeMultiPeriodChanges($pdo, $targetDate, '00981A');
    $analysis = $analyzeMultiPeriodChanges[0];
    $stockIds = [];
    $a = json_decode(file_get_contents("data/{$targetDate}_componentOf00981A.json"), true);
    foreach ($a as $v) {
        $stockIds[] = $v['stock_id'];
    }

    $result = getEtfComponentChartData($pdo,  '00981A',  $targetDate, $stockIds);
    createJsonFile($pdo, $targetDate . '_00981A-charts_test', $result);
} catch (Throwable $e) {
    exit(1);
}
