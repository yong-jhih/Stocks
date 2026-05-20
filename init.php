<?php

date_default_timezone_set('Asia/Taipei');
set_time_limit(0);

require_once("config.php");
require_once("function-tools.php");
require_once("function-getData.php");
function getPDOConnection()
{
    global $db_ip, $db_name, $db_user, $db_pass;
    $dsn = "mysql:host=$db_ip;port=4000;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA       => '/etc/ssl/certs/ca-certificates.crt',
    ];
    try {
        return new PDO($dsn, $db_user, $db_pass, $options);
    } catch (PDOException $e) {
        error_log("Database Connection Failed: " . $e->getMessage());
        die("系統執行失敗，請檢查資料庫連線。");
    }
}

$pdo = getPDOConnection();
$targetDate = getLatestTradingDateWithTWSE($pdo) ?? getLatestTradingDateWithFugle($pdo);
