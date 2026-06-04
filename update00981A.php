<?php
require_once("init.php");

if (isHoliday($pdo, $targetDate)) {
    echo '非交易日跳過';
    exit(0);
}

$start_time = microtime(true);
writeLog($pdo, 'update00981A', $targetDate . ' 開始更新 00981A 成分股資料', 'start');
$results = getComponentOf00981A_FromLocal($pdo, $targetDate);
insertComponentOf00981A($pdo, $targetDate, $results);
$analysis = analyzeMultiPeriodChanges($pdo, $targetDate);
createJsonFile($pdo, $targetDate, 'componentOf00981A', $analysis, $folder = 'data');
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
writeLog($pdo, 'update00981A', $targetDate . ' 更新完成,共耗時 ' . $execution_time . ' 秒', 'end');
updateSystemLog($pdo);