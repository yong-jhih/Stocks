<?php
require_once("init.php");
$targetDate = '2026-07-09';




$table = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
$results = generateDailyDashboard($pdo, $targetDate, $table);
createJsonFile($pdo, $targetDate . '_test', $results);
renewCharts($pdo, $targetDate, 'test', 'charts');
