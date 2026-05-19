<?php
require_once("init.php");

// require_once("function-getData.php");


$targetDate = '2026-05-18';


$results = generateDailyDashboard($pdo, $targetDate);
createJsonFile($pdo, $targetDate, 'filter', $results);
renewCharts($pdo, $targetDate, 'filter', 'charts');

$resultsSelf = selfSelectGenerateDailyDashboard($pdo, $targetDate, [3264, 2449, 3665, 3017, 2368, 2330, 1590, 6412, 2363, 2383, 8210]);
createJsonFile($pdo, $targetDate, 'self-select', $resultsSelf);
renewCharts($pdo, $targetDate, 'self-select', 'self-charts');

$resultsTop = topPerformingGenerateDailyDashboard($pdo, $targetDate);
createJsonFile($pdo, $targetDate, 'topPerforming', $resultsTop);
renewCharts($pdo, $targetDate, 'topPerforming', 'topPerforming-charts');
