<?php
require_once("init.php");

// require_once("function-getData.php");


$targetDate = '2026-05-15';
$results = generateDailyDashboard($pdo, $targetDate);
createJsonFile($pdo, $targetDate, 'test', $results);
// renewCharts($pdo, $targetDate, 'test', 'test-charts');
