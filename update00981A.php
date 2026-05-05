<?php
require_once("init.php");

if (isHoliday($targetDate)) {
    writeLog($pdo, '00981A成分股抓取', '非交易日跳過', 'success');
    exit(0);
}

$start_time = microtime(true);
$results = getComponentOf00981A_FromLocal();
insertComponentOf00981A($pdo, $targetDate, $results);
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
writeLog($pdo, '00981A成分股抓取', '抓取完成,共耗時 ' . $execution_time . ' 秒', 'success');