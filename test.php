<?php
require_once("init.php");
$targetDate = '2026-07-27';

$tableTWSE = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
$resultsTWSE = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTWSE);

$tableTPEx = ['TPEx_stock_history', 'TPEx_stock_insti', 'TPEx_stock_margin', 'TPEx_stock_sbl_total', 'TPEx_stock_sbl_sold'];
$resultsTPEx = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTPEx);
$a = array_merge($resultsTWSE, $resultsTPEx);

echo "TWSE:" . count($resultsTWSE) . "\n";
echo "TPEx:" . count($resultsTPEx) . "\n";
echo "Mix:" . count($a) . "\n";

// $resultsTop = topPerformingGenerateDailyDashboard($pdo, $targetDate, $table);
createJsonFile($pdo, $targetDate . '_test', $a);
// renewCharts($pdo, $targetDate, 'test', 'test-charts');
