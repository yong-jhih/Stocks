<?php
require_once("init.php");

if (
    file_exists("data/" . $targetDate . "_filter.json") &&
    file_exists("data/" . $targetDate . "_charts.json") &&
    file_exists("data/" . $targetDate . "_topPerforming.json") &&
    file_exists("data/" . $targetDate . "_topPerforming-charts.json")
) {
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
    $log = testRetry($pdo);
    if (!($log['log_type'] === 'generateDailyDashboard' && ($log['result'] === 'start' || $log['result'] === 'retry'))) {
        writeLog($pdo, 'generateDailyDashboard', "[{$targetDate}] 資料數量正常, 開始進行盤後篩選及評分排行", 'start');
    }

    try {
        $results = generateDailyDashboard($pdo, $targetDate);
        createJsonFile($pdo, $targetDate, 'filter', $results);
        renewCharts($pdo, $targetDate, 'filter', 'charts');

        $resultsTop = topPerformingGenerateDailyDashboard($pdo, $targetDate);
        createJsonFile($pdo, $targetDate, 'topPerforming', $resultsTop);
        renewCharts($pdo, $targetDate, 'topPerforming', 'topPerforming-charts');

        callGAS($pdo, [
            'date' => $targetDate,
            'action' => 'triggersSelfSelect'
        ]);

        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);
        writeLog($pdo, 'generateDailyDashboard', "[{$targetDate}] 盤後篩選及評分排行已完成, 共耗時 {$execution_time} 秒", 'end');
        updateSystemLog($pdo);
        lineNotification($pdo, getenv('LINE_TARGET'), '今日盤後篩選及評分排行已完成, 請稍候佈署 - https://yong-jhih.github.io/Stocks/');
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'exceeding the allowed memory limit')) {
            writeLog($pdo, 'generateDailyDashboard', 'TiDB記憶體不足，5分鐘後重試', 'retry');
            callGAS($pdo, [
                'date' => $targetDate,
                'action' => 'retry',
                'target' => 'CheckAndRun',
                'after' => 300
            ]);
            updateSystemLog($pdo);
            exit(0);
        } else {
            writeLog($pdo, 'generateDailyDashboard', $e->getMessage(), 'error');
            updateSystemLog($pdo);
            exit(1);
        }
    }
} else { // 已公布 資料量不足 則更新資料
    writeLog($pdo, 'updateAllHistory', "偵測 [{$targetDate}] TWT93U 信用額度總量管制餘額已公布, 準備進行更新歷史資料", 'waitting');
    updateAllHistory($pdo, $targetDate);
    writeLog($pdo, 'updateAllHistory', '歷史資料更新完畢, 等待下階段進入分析', 'waitting');
    updateSystemLog($pdo);
}
