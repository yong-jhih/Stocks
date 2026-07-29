<?php
require_once("init.php");
$targetDate = '2026-07-28';

// $tableTWSE = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
// $resultsTWSE = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTWSE);

// $tableTPEx = ['TPEx_stock_history', 'TPEx_stock_insti', 'TPEx_stock_margin', 'TPEx_stock_sbl_total', 'TPEx_stock_sbl_sold'];
// $resultsTPEx = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTPEx);
// $a = [...$resultsTWSE, ...$resultsTPEx];

// createJsonFile($pdo, $targetDate . '_test', $a);
// renewCharts($pdo, $targetDate, 'test', 'test-charts');

try {
    $etfid = ['00981A', '00403A', '00991A'];
    foreach ($etfid as $etf_id) {
        $start_time = microtime(true);
        writeLog($pdo, "update{$etf_id}", "取得交易日期 [{$targetDate}], 開始更新 {$etf_id} 成分股資料", 'start');
        $results = getComponent($targetDate, $etf_id);
        insertComponent($pdo, $targetDate, $etf_id, $results);
        $analyzeMultiPeriodChanges = analyzeMultiPeriodChanges($pdo, $targetDate, $etf_id);
        $analysis = $analyzeMultiPeriodChanges[0];
        $lineNotifyStr = $analyzeMultiPeriodChanges[1] . "\n";
        createJsonFile($pdo, $targetDate . "_componentOf{$etf_id}", $analysis, 'data');
        $stockIds = [];
        $a = json_decode(file_get_contents("data/{$targetDate}_componentOf{$etf_id}.json"), true);
        foreach ($a as $v) {
            $stockIds[] = $v['stock_id'];
        }
        $result = getEtfComponentChartData($pdo,  $etf_id,  $targetDate, $stockIds);
        createJsonFile($pdo, $targetDate . "_{$etf_id}-charts", $result);
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);
        writeLog($pdo, "update{$etf_id}", "{$etf_id} 成分股資料更新完成,共耗時{$execution_time} 秒", 'end');
    }
} catch (Throwable $e) {
    // if (str_contains($e->getMessage(), 'exceeding the allowed memory limit')) {
    //     writeLog($pdo, 'generateDailyDashboard', 'TiDB記憶體不足，5分鐘後重試', 'retry');
    //     callGAS([
    //         'date' => $targetDate,
    //         'action' => 'retry',
    //         'target' => 'CheckAndRun',
    //         'after' => 300
    //     ]);
    //     updateSystemLog($pdo);
    //     exit(0);
    // } else {
    // writeLog($pdo, 'checkAndRun', $e->getMessage(), 'error');
    // updateSystemLog($pdo);
    echo $e->getMessage();
    exit(1);
    // }
}
