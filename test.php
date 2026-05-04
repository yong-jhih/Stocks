<?php
require_once("init.php");

$start_time = microtime(true);
$results = getComponentOf00981A($pdo, '2026-04-30');
echo $results;
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
writeLog($pdo, 'SelectAnalysis', '篩選分析結束,共耗時 ' . $execution_time . ' 秒', 'success');
