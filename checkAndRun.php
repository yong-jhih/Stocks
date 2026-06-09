<?php
require_once("init.php");

if (!isTradingDay($pdo, $targetDate) && isHoliday($pdo, $targetDate)) {
    echo '非交易日跳過';
    exit(0);
}

if (file_exists("data/" . $targetDate . "_filter.json")) {
    echo '分析資料已存在';
    exit(0);
}

$SBLSoldData = getSBLSold($targetDate, $pdo);
if (isset($SBLSoldData['status']) && $SBLSoldData['status'] == 'error') { // 未公布
    echo 'TWT93U 信用額度總量管制餘額 資料未到齊, 等待下次觸發';
    exit(0);
} else if ( // 已公布 檢查資料量 足夠 直接進行分析
    checkIfDataPublished($pdo, $targetDate, 'stock_history', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_insti', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_margin', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_total', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_sold', 500)
) {
    $start_time = microtime(true);
    writeLog($pdo, 'generateDailyDashboard', $targetDate . ' 資料數量正常, 開始進行分析及排行', 'start');

    $results = generateDailyDashboard($pdo, $targetDate);
    createJsonFile($pdo, $targetDate, 'filter', $results);
    renewCharts($pdo, $targetDate, 'filter', 'charts');

    $resultsTop = topPerformingGenerateDailyDashboard($pdo, $targetDate);
    createJsonFile($pdo, $targetDate, 'topPerforming', $resultsTop);
    renewCharts($pdo, $targetDate, 'topPerforming', 'topPerforming-charts');

    cleanData(20);
    lineNotification($pdo, getenv('LINE_TARGET'), '今日盤後篩選及評分排行已完成,請稍候佈署 - https://yong-jhih.github.io/Stocks/');

    callGAS($pdo, [
        'date' => $targetDate,
        'action' => 'triggersSelfSelect'
    ]);

    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    writeLog($pdo, 'generateDailyDashboard', $targetDate . ' 盤後篩選及評分排行已完成,共耗時 ' . $execution_time . ' 秒', 'end');
    updateSystemLog($pdo);
} else { // 已公布 資料量不足 則更新資料
    writeLog($pdo, 'updateAllHistory', $targetDate . ' 偵測 TWT93U 信用額度總量管制餘額 已公布, 準備進行更新', 'waitting');
    updateAllHistory($pdo, $targetDate);
    writeLog($pdo, 'updateAllHistory', $targetDate . ' 資料庫更新完畢, 等待下階段進入分析', 'waitting');
    updateSystemLog($pdo);
}
