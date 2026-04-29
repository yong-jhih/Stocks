<?php

date_default_timezone_set('Asia/Taipei');
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
    echo checkIfDataPublished($pdo, '2026-04-28', 'stock_history');
} catch (PDOException $e) {
    die("系統執行失敗：" . $e->getMessage());
}
function checkIfDataPublished($pdo, $date, $table)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ? WHERE trade_date = ?");
    $stmt->execute([$table, $date]);
    return $stmt->fetchColumn() > 0;
}
