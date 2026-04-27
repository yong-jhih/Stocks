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
    $targetDate = getLatestTradingDateWithTWSE() ?? getLatestTradingDateWithFugle();
    if (is_array($targetDate)) {
        echo "通知： " . ($targetDate['msg'] ?? '無法取得交易日期') . "\n";
        exit;
    }
    insertHistory($pdo, $targetDate, getHistory($targetDate));
    insertInsti($pdo, $targetDate, getInsti($targetDate));
    insertMargin($pdo, $targetDate, getMargin($targetDate));
    insertSBLTotal($pdo, $targetDate, getSBLTotal($targetDate));
    insertSBLSold($pdo, $targetDate, getSBLSold($targetDate));
} catch (PDOException $e) {
    die("系統執行失敗：" . $e->getMessage());
}
