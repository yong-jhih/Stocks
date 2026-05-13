<?php
require_once("init.php");


$targetDate = '2026-05-12';
// $results = tetsGenerateDailyDashboard($pdo, $targetDate);
// createJsonFile($pdo, $targetDate, 'test', $results);
renewCharts($pdo, $targetDate, 'test', 'test-charts');
