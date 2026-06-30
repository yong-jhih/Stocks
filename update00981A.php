<?php
require_once("init.php");

$start_time = microtime(true);
writeLog($pdo, 'update00981A', '取得交易日期 [' . $targetDate . '], 開始更新 00981A 成分股資料', 'start');
try {
    $results = getComponentOf00981A_FromLocal($pdo, $targetDate);
    insertComponentOf00981A($pdo, $targetDate, $results);
    $analyzeMultiPeriodChanges = analyzeMultiPeriodChanges($pdo, $targetDate, '00981A');
    $analysis = $analyzeMultiPeriodChanges[0];
    $lineNotifyStr = $analyzeMultiPeriodChanges[1];
    createJsonFile($pdo, $targetDate . '_componentOf00981A', $analysis, 'data');
    updateDateList($targetDate);
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    writeLog($pdo, 'update00981A', '00981A 成分股資料更新完成,共耗時 ' . $execution_time . ' 秒', 'end');
    updateSystemLog($pdo);
    lineNotification($pdo, getenv('LINE_TARGET'), $lineNotifyStr);
} catch (Throwable $e) {
    writeLog($pdo, 'update00981A', $e->getMessage(), 'error');
    updateSystemLog($pdo);
    exit(1);
}
