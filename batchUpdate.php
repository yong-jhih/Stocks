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
    $date_array = ['2026-04-17', '2026-04-16', '2026-04-15', '2026-04-14', '2026-04-13',];
    foreach ($date_array as $targetDate) {
        insertHistory($pdo, $targetDate, getHistory($targetDate, $pdo));
        insertInsti($pdo, $targetDate, getInsti($targetDate, $pdo));
        insertMargin($pdo, $targetDate, getMargin($targetDate, $pdo));
        insertSBLTotal($pdo, $targetDate, getSBLTotal($targetDate, $pdo));
        insertSBLSold($pdo, $targetDate, getSBLSold($targetDate, $pdo));
    }
} catch (PDOException $e) {
    die("系統執行失敗：" . $e->getMessage());
}