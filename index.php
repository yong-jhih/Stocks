<?php
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
    echo "資料庫連線成功。\n";

    // 1. 取得最新交易日期
    // $targetDate = getLatestTradingDateWithTWSE();
    $targetDate = '2026-04-21';
    if (is_array($targetDate)) {
        die($targetDate['msg']);
    }
    echo "處理日期：{$targetDate}\n---\n";
    // insertHistory($pdo, $targetDate, getHistory($targetDate));
    // insertInsti($pdo, $targetDate, getInsti($targetDate));
    // insertMargin($pdo, $targetDate, getMargin($targetDate));;
    // insertSBLTotal($pdo, $targetDate, getSBLTotal($targetDate));
    // insertSBLSold($pdo, $targetDate, getSBLSold($targetDate));

    echo "\n所有程序執行完畢。\n";
} catch (PDOException $e) {
    die("系統執行失敗：" . $e->getMessage());
}