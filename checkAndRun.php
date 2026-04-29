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
    $targetDate = getLatestTradingDateWithTWSE() ?? getLatestTradingDateWithFugle();

    if (checkIfDataPublished($pdo, $targetDate, 'daily_dashboard_results')) {
        echo '分析資料已存在, 結束整個任務';
        exit(1);
    }

    if (
        checkIfDataPublished($pdo, $targetDate, 'stock_history') &&
        checkIfDataPublished($pdo, $targetDate, 'stock_insti') &&
        checkIfDataPublished($pdo, $targetDate, 'stock_margin') &&
        checkIfDataPublished($pdo, $targetDate, 'stock_sbl_total') &&
        checkIfDataPublished($pdo, $targetDate, 'stock_sbl_sold')
    ) {
        echo '資料已到齊, 繼續下個分析任務';
        exit(0);
    } else {
        $SBLSoldData = getSBLSold($targetDate, $pdo);
        if (isset($SBLSoldData['status']) && $SBLSoldData['status'] == 'error') {
            echo '資料未到齊, 等待下次觸發';
            exit(1);
        } else {
            require 'updateAll.php';
            echo '資料庫更新完畢, 準備進入分析。';
            exit(0); // 返回 0 讓 GitHub Actions 執行下一步的 analyze.php
        }
    }
} catch (PDOException $e) {
    die("系統執行失敗：" . $e->getMessage());
}

function checkIfDataPublished($pdo, $date, $table)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE trade_date = ?");
    $stmt->execute([$date]);
    return (int)$stmt->fetchColumn() > 0;
}
