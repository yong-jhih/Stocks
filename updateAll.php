<?php
require_once("init.php");

if (is_array($targetDate)) {
    echo "通知： " . ($targetDate['msg'] ?? '無法取得交易日期') . "\n";
    writeLog($pdo, 'updateAll', "通知： " . ($targetDate['msg'] ?? '無法取得交易日期'), 'error');
    exit;
}
$start_time = microtime(true);
writeLog($pdo, 'updateAll', '取得交易日期 ' . $targetDate . ' 開始更新資料', 'start');
insertHistory($pdo, $targetDate, getHistory($targetDate, $pdo));
insertInsti($pdo, $targetDate, getInsti($targetDate, $pdo));
insertMargin($pdo, $targetDate, getMargin($targetDate, $pdo));
insertSBLTotal($pdo, $targetDate, getSBLTotal($targetDate, $pdo));
insertSBLSold($pdo, $targetDate, getSBLSold($targetDate, $pdo));
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
writeLog($pdo, 'updateAll', $targetDate . '更新資料結束,共耗時 ' . $execution_time . ' 秒', 'end');