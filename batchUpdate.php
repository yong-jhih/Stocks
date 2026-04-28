<?php
set_time_limit(0);
require_once("config.php");
require_once("function-tools.php");
require_once("function-getData.php");

try {
    $dsn = "mysql:host=$db_ip;port=4000;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA       => '/etc/ssl/certs/ca-certificates.crt',
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    $date_array = ['2026-04-16'];
    $start_time = microtime(true);
    writeLog($pdo, 'batchUpdate', '開始進行批次更新', 'start');
    foreach ($date_array as $targetDate) {
        insertHistory($pdo, $targetDate, getHistory($targetDate, $pdo));
        insertInsti($pdo, $targetDate, getInsti($targetDate, $pdo));
        insertMargin($pdo, $targetDate, getMargin($targetDate, $pdo));
        insertSBLTotal($pdo, $targetDate, getSBLTotal($targetDate, $pdo));
        insertSBLSold($pdo, $targetDate, getSBLSold($targetDate, $pdo));
    }
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2); // 取小數點後兩位
    writeLog($pdo, 'batchUpdate', '批次更新結束,共耗時 ' . $execution_time . ' 秒', 'success');
} catch (PDOException $e) {
    die("系統執行失敗：" . $e->getMessage());
}
