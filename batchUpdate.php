<?php
require_once("init.php");

$date_array = ['2026-02-11', '2026-02-23', '2026-02-24', '2026-02-25', '2026-02-26', '2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06'];
$start_time = microtime(true);
writeLog($pdo, 'batchUpdate', '開始進行批次更新, 共 ' . count($date_array) . ' 天', 'start');
foreach ($date_array as $date) {
    insertHistory($pdo, $date, getHistory($date, $pdo));
    insertInsti($pdo, $date, getInsti($date, $pdo));
    insertMargin($pdo, $date, getMargin($date, $pdo));
    insertSBLTotal($pdo, $date, getSBLTotal($date, $pdo));
    insertSBLSold($pdo, $date, getSBLSold($date, $pdo));
}
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
writeLog($pdo, 'batchUpdate', '批次更新結束,共耗時 ' . $execution_time . ' 秒', 'end');
