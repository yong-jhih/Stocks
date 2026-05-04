<?php
require_once("init.php");

$start_time = microtime(true);
$results = generateDailyDashboard($pdo, '2026-04-30');
// saveDailyDashboard($pdo, '2026-04-30', $results);
$a = createJsonFile('2026-04-30', 'test', $results);
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
writeLog($pdo, 'SelectAnalysis', '篩選分析結束,共耗時 ' . $execution_time . ' 秒', 'success');
