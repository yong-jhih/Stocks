<?php
require_once("init.php");
$targetDate = '2026-10-06';
if ( // 已公布 檢查資料量 足夠 直接進行分析
    checkIfDataPublished($pdo, $targetDate, 'stock_history', 700) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_insti', 700) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_margin', 700) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_total', 700) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_sold', 700) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_history', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_insti', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_margin', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_sbl_total', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_sbl_sold', 500)
) {
    try {
        // 篩選 排行
        $tableTWSE = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
        $tableTPEx = ['TPEx_stock_history', 'TPEx_stock_insti', 'TPEx_stock_margin', 'TPEx_stock_sbl_total', 'TPEx_stock_sbl_sold'];
        $resultsTWSE = generateDailyDashboard($pdo, $targetDate, $tableTWSE);
        $resultsTPEx = generateDailyDashboard($pdo, $targetDate, $tableTPEx);
        $resultsTopTWSE = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTWSE);
        $resultsTopTPEx = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTPEx);
        $resultsMix = [...$resultsTWSE, ...$resultsTPEx];
        createJsonFile($pdo, "{$targetDate}_filter", $resultsMix);
        renewCharts($pdo, $targetDate, 'filter', 'charts');
        $resultsTopMix = [...$resultsTopTWSE, ...$resultsTopTPEx];
        createJsonFile($pdo, "{$targetDate}_topPerforming", $resultsTopMix);
        renewCharts($pdo, $targetDate, 'topPerforming', 'topPerforming-charts');
        analyzeMarketTrend($pdo);
    } catch (Throwable $e) {
        writeLog($pdo, 'checkAndRun', $e->getMessage(), 'error');
        exit(1);
    }
}
