<?php
require_once("init.php");
$targetDate = '2026-07-22';


// $tableTWSE = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
// $resultsTWSE = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTWSE);

// $tableTPEx = ['TPEx_stock_history', 'TPEx_stock_insti', 'TPEx_stock_margin', 'TPEx_stock_sbl_total', 'TPEx_stock_sbl_sold'];
// $resultsTPEx = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTPEx);
// createJsonFile($pdo, $targetDate . '_filter', $results);
// renewCharts($pdo, $targetDate, 'filter', 'charts');
// $a = array_merge($resultsTWSE, $resultsTPEx);

// echo "TWSE:" . count($resultsTWSE) . "\n";
// echo "TPEx:" . count($resultsTPEx) . "\n";
// echo "Mix:" . count($a) . "\n";

// $resultsTop = topPerformingGenerateDailyDashboard($pdo, $targetDate, $table);
// createJsonFile($pdo, $targetDate . 'test', $a);
// renewCharts($pdo, $targetDate, 'topPerforming', 'topPerforming-charts');


        $table = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
        $resultsTop = topPerformingGenerateDailyDashboard($pdo, $targetDate, $table);
        createJsonFile($pdo, $targetDate . '_topPerforming', $resultsTop);
        renewCharts($pdo, $targetDate, 'topPerforming', 'topPerforming-charts');
