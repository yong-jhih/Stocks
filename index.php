<?php
set_time_limit(0);
// require_once("Auth.php");
require_once("config.php");
require_once("function-tools.php");
require_once("function-getData.php");

try {
    $dsn = "mysql:host=$db_ip;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    $targetDate = getLatestTradingDateWithTWSE();
    if (is_array($targetDate)) {
        die($targetDate['msg']);
    }

    // insertHistory($pdo, $targetDate, getHistory($targetDate));
    // insertInsti($pdo, $targetDate, getInsti($targetDate));
    // insertMargin($pdo, $targetDate, getMargin($targetDate));
    // insertSBLTotal($pdo, $targetDate, getSBLTotal($targetDate));
    // insertSBLSold($pdo, $targetDate, getSBLSold($targetDate));
    // }
} catch (PDOException $e) {
    die("系統執行失敗：" . $e->getMessage());
}
