<?php
require_once("init.php");

if (isHoliday($targetDate)) {
    echo '非交易日跳過';
    exit(0);
}
if (checkIfDataPublished($pdo, $targetDate, 'daily_dashboard_results')) {
    echo '分析資料已存在';
    exit(0);
}

$SBLSoldData = getSBLSold($targetDate, $pdo);
if (isset($SBLSoldData['status']) && $SBLSoldData['status'] == 'error') {
    echo '資料未到齊, 等待下次觸發';
    exit(0);
} else {
    echo '偵測 TWT93U 信用額度總量管制餘額 已公布, 準備進行更新。';
    require 'updateAll.php';
    echo '資料庫更新完畢, 準備進入分析。';
}

if (
    checkIfDataPublished($pdo, $targetDate, 'stock_history') &&
    checkIfDataPublished($pdo, $targetDate, 'stock_insti') &&
    checkIfDataPublished($pdo, $targetDate, 'stock_margin') &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_total') &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_sold')
) {
    $results = generateDailyDashboard($pdo, $targetDate);
    createJsonFile($pdo, $targetDate, 'filter', $results);
    saveDailyDashboard($pdo, $targetDate, $results);
}
