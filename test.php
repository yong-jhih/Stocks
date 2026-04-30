<?php
require_once("init.php");

$start_time = microtime(true);
$results = testGenerateDailyDashboard($pdo, '2026-04-29');
// testSaveDailyDashboard($pdo, '2026-04-28', $results);
exit(0);
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
writeLog($pdo, 'SelectAnalysis', '篩選分析結束,共耗時 ' . $execution_time . ' 秒', 'success');
