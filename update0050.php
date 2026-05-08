<?php
require_once("init.php");

if (isHoliday($targetDate)) {
    writeLog($pdo, 'update0050', '非交易日跳過', 'success');
    exit(0);
}

$start_time = microtime(true);
$results = getComponentOf0050_FromLocal($pdo, $targetDate);
insertComponentOf0050($pdo, $targetDate, $results);
$analysis = analyzeMultiPeriodChanges0050($pdo, $targetDate);
createJsonFile($pdo, $targetDate, 'componentOf0050', $analysis, $folder = 'data');
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
writeLog($pdo, 'update0050', $targetDate . ' 更新完成,共耗時 ' . $execution_time . ' 秒', 'success');
