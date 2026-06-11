<?php

date_default_timezone_set('Asia/Taipei');
set_time_limit(0);

$db_ip   = getenv('TiDB_Host');
$db_user = getenv('TiDB_User');
$db_pass = getenv('TIDB_PASS');
$db_name = getenv('TIDB_NAME');

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
        echo "Database Connection Failed: " . $e->getMessage();
        exit(1);
    }
}

$pdo = getPDOConnection();
$targetDate = getLatestTradingDateWithTWSE($pdo) ?? getLatestTradingDateWithFugle($pdo);
if (!$targetDate) {
    writeLog($pdo, 'init', "TWSE & Fugle 皆取得最新交易日期失敗, 退出程序", 'error');
    updateSystemLog($pdo);
    exit(1);
}