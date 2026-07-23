<?php
require_once("init.php");



// $tableTWSE = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
// $resultsTWSE = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTWSE);

// $tableTPEx = ['TPEx_stock_history', 'TPEx_stock_insti', 'TPEx_stock_margin', 'TPEx_stock_sbl_total', 'TPEx_stock_sbl_sold'];
// $resultsTPEx = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTPEx);
// createJsonFile($pdo, $targetDate . '_filter', $results);
// renewCharts($pdo, $targetDate, 'filter', 'charts');
// $a = array_merge($resultsTWSE, $resultsTPEx);

// echo "TWSE:" . count($resultsTWSE) . "\n";
// echo "TPEx:" . count($resultsTPEx) . "\n";
// echo "Mix:" . count($a) . "\n";

// $resultsTop = topPerformingGenerateDailyDashboard($pdo, $targetDate, $table);
// createJsonFile($pdo, $targetDate . 'test', $a);
// renewCharts($pdo, $targetDate, 'topPerforming', 'topPerforming-charts');


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
    $start_time = microtime(true);
    $log = testRetry($pdo);
    if (!($log['log_type'] === 'generateDailyDashboard' && ($log['result'] === 'start' || $log['result'] === 'retry'))) {
      //  writeLog($pdo, 'generateDailyDashboard', "[{$targetDate}] 資料數量正常, 開始進行盤後篩選及評分排行", 'start');
    }

    try {
        $table = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
        $results = generateDailyDashboard($pdo, $targetDate, $table);
        createJsonFile($pdo, $targetDate . '_filter', $results);
        renewCharts($pdo, $targetDate, 'filter', 'charts');

        $resultsTop = topPerformingGenerateDailyDashboard($pdo, $targetDate, $table);
        createJsonFile($pdo, $targetDate . '_topPerforming', $resultsTop);
        renewCharts($pdo, $targetDate, 'topPerforming', 'topPerforming-charts');

        // lineNotification($pdo, getenv('LINE_TARGET'), $lineNotifyStr . '今日盤後篩選及評分排行已完成, 請稍候佈署 - https://yong-jhih.github.io/Stocks/');
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'exceeding the allowed memory limit')) {
            writeLog($pdo, 'generateDailyDashboard', 'TiDB記憶體不足，5分鐘後重試', 'retry');
            callGAS([
                'date' => $targetDate,
                'action' => 'retry',
                'target' => 'CheckAndRun',
                'after' => 300
            ]);
            updateSystemLog($pdo);
            exit(0);
        } else {
            writeLog($pdo, 'checkAndRun', $e->getMessage(), 'error');
            updateSystemLog($pdo);
            exit(1);
        }
    }
} else { // 已公布 資料量不足 則更新資料
    writeLog($pdo, 'updateAllHistory', "偵測 [{$targetDate}] TWT93U 信用額度總量管制餘額已公布, 準備進行更新歷史資料", 'waitting');
    updateAllHistory($pdo, $targetDate);
    updateAllTPExHistory($pdo, $targetDate);
    writeLog($pdo, 'updateAllHistory', '歷史資料更新完畢, 等待下階段進入分析', 'waitting');
    updateSystemLog($pdo);
       }
