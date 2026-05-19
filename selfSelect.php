<?php
require_once("init.php");

if (isHoliday($targetDate)) {
    echo '非交易日跳過';
    exit(0);
}
if (file_exists("data/" . $targetDate . "_self-select.json")) {
    echo '分析資料已存在';
    exit(0);
}

$SBLSoldData = getSBLSold($targetDate, $pdo);
if (isset($SBLSoldData['status']) && $SBLSoldData['status'] == 'error') {
    echo '資料未到齊, 等待下次觸發';
    exit(0);
}

if (
    checkIfDataPublished($pdo, $targetDate, 'stock_history', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_insti', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_margin', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_total', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_sold', 500)
) {
    $stockJson = getenv('STOCK_DATA');
    $stocks = json_decode($stockJson, true);
    $stockList = [];
    foreach ($stocks as $stock) {
        $stockList[] = $stock['code'];
    }
    $resultsSelf = selfSelectGenerateDailyDashboard($pdo, $targetDate, $stockList);
    createJsonFile($pdo, $targetDate, 'self-select', $resultsSelf);
    renewCharts($pdo, $targetDate, 'self-select', 'self-charts');
} else {
    exit(0);
}
