<?php
require_once("init.php");
$targetDate = '2026-07-31';

// $tableTWSE = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
// $resultsTWSE = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTWSE);

// $tableTPEx = ['TPEx_stock_history', 'TPEx_stock_insti', 'TPEx_stock_margin', 'TPEx_stock_sbl_total', 'TPEx_stock_sbl_sold'];
// $resultsTPEx = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTPEx);
// $a = [...$resultsTWSE, ...$resultsTPEx];

// createJsonFile($pdo, $targetDate . '_test', $a);
// renewCharts($pdo, $targetDate, 'test', 'test-charts');

$tableTWSE = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
$tableTPEx = ['TPEx_stock_history', 'TPEx_stock_insti', 'TPEx_stock_margin', 'TPEx_stock_sbl_total', 'TPEx_stock_sbl_sold'];

$resultsTWSE = generateDailyDashboard($pdo, $targetDate, $tableTWSE);
$resultsTPEx = generateDailyDashboard($pdo, $targetDate, $tableTPEx);
$resultsMix = [...$resultsTWSE, ...$resultsTPEx];

$resultsTopTWSE = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTWSE);
$resultsTopTPEx = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTPEx);
$resultsTopMix = [...$resultsTopTWSE, ...$resultsTopTPEx];

createJsonFile($pdo, $targetDate . '_testA', $resultsMix);
renewCharts($pdo, $targetDate, 'testA', 'testA-charts');

createJsonFile($pdo, $targetDate . '_testB', $resultsTopMix);
renewCharts($pdo, $targetDate, 'testB', 'testB-charts');

writeLog($pdo, 'generateDailyDashboard', "[{$targetDate}] 篩選分析完成，共 " . count($resultsMix) . " 檔", 'success');
writeLog($pdo, 'topPerformingGenerateDailyDashboard', "[{$targetDate}] 排行分析完成，共 " . count($resultsTopMix) . " 檔", 'success');