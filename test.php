<?php
require_once("init.php");


$targetDate = '2026-05-14';
$results = testgenerateDailyDashboard($pdo, $targetDate);
createJsonFile($pdo, $targetDate, 'test', $results);
renewCharts($pdo, $targetDate, 'test', 'test-charts');
