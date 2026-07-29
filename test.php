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
    // $etfid = ['00981A', '00403A', '00991A'];
    // foreach ($etfid as $etf_id) {
    //     $start_time = microtime(true);
    // writeLog($pdo, "update{$etf_id}", "取得交易日期 [{$targetDate}], 開始更新 {$etf_id} 成分股資料", 'start');
    //     $results = getComponent($targetDate, $etf_id);
    //     insertComponent($pdo, $targetDate, $etf_id, $results);
    //     $analyzeMultiPeriodChanges = analyzeMultiPeriodChanges($pdo, $targetDate, $etf_id);
    //     $analysis = $analyzeMultiPeriodChanges[0];
    //     $lineNotifyStr = $analyzeMultiPeriodChanges[1] . "\n";
    //     createJsonFile($pdo, $targetDate . "_componentOf{$etf_id}", $analysis, 'data');
    //     $stockIds = [];
    //     $a = json_decode(file_get_contents("data/{$targetDate}_componentOf{$etf_id}.json"), true);
    //     foreach ($a as $v) {
    //         $stockIds[] = $v['stock_id'];
    //     }
    //     $result = getEtfComponentChartData($pdo,  $etf_id,  $targetDate, $stockIds);
    //     createJsonFile($pdo, $targetDate . "_{$etf_id}-charts", $result);
    //     $end_time = microtime(true);
    //     $execution_time = round($end_time - $start_time, 2);
    // writeLog($pdo, "update{$etf_id}", "{$etf_id} 成分股資料更新完成,共耗時{$execution_time} 秒", 'end');
    // }

    // 併00981A執行
    $start_time = microtime(true);
    // writeLog($pdo, 'update00981A', "取得交易日期 [{$targetDate}], 開始更新 00981A 成分股資料", 'start');
    $results = getComponentOf00981A_FromLocal($targetDate);
    insertComponentOf00981A($pdo, $targetDate, $results);
    $analyzeMultiPeriodChanges = analyzeMultiPeriodChanges($pdo, $targetDate, '00981A');
    $analysis = $analyzeMultiPeriodChanges[0];
    $lineNotifyStr = $analyzeMultiPeriodChanges[1] . "\n";
    createJsonFile($pdo, $targetDate . '_componentOf00981A', $analysis, 'data');
    $stockIds = [];
    $a = json_decode(file_get_contents("data/{$targetDate}_componentOf00981A.json"), true);
    foreach ($a as $v) {
        $stockIds[] = $v['stock_id'];
    }
    $result = getEtfComponentChartData($pdo,  '00981A',  $targetDate, $stockIds);
    createJsonFile($pdo, $targetDate . '_00981A-charts', $result);
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    // writeLog($pdo, 'update00981A', '00981A 成分股資料更新完成,共耗時 ' . $execution_time . ' 秒', 'end');

    // 併00403A執行
    $start_time = microtime(true);
    // writeLog($pdo, 'update00403A', "取得交易日期 [{$targetDate}], 開始更新 00403A 成分股資料", 'start');
    $results = getComponentOf00403A_FromLocal($targetDate);
    insertComponentOf00403A($pdo, $targetDate, $results);
    $analyzeMultiPeriodChanges = analyzeMultiPeriodChanges($pdo, $targetDate, '00403A');
    $analysis = $analyzeMultiPeriodChanges[0];
    $lineNotifyStr .= $analyzeMultiPeriodChanges[1] . "\n";
    createJsonFile($pdo, $targetDate . '_componentOf00403A', $analysis, 'data');
    $stockIds = [];
    $a = json_decode(file_get_contents("data/{$targetDate}_componentOf00403A.json"), true);
    foreach ($a as $v) {
        $stockIds[] = $v['stock_id'];
    }
    $result = getEtfComponentChartData($pdo,  '00403A',  $targetDate, $stockIds);
    createJsonFile($pdo, $targetDate . '_00403A-charts', $result);
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    // writeLog($pdo, 'update00403A', '00403A 成分股資料更新完成,共耗時 ' . $execution_time . ' 秒', 'end');

    // 併00991A執行
    $start_time = microtime(true);
    // writeLog($pdo, 'update00991A', "取得交易日期 [{$targetDate}], 開始更新 00991A 成分股資料", 'start');
    $results = getComponentOf00991A_FromLocal($targetDate);
    insertComponentOf00991A($pdo, $targetDate, $results);
    $analyzeMultiPeriodChanges = analyzeMultiPeriodChanges($pdo, $targetDate, '00991A');
    $analysis = $analyzeMultiPeriodChanges[0];
    $lineNotifyStr .= $analyzeMultiPeriodChanges[1] . "\n";
    createJsonFile($pdo, $targetDate . '_componentOf00991A', $analysis, 'data');
    $stockIds = [];
    $a = json_decode(file_get_contents("data/{$targetDate}_componentOf00991A.json"), true);
    foreach ($a as $v) {
        $stockIds[] = $v['stock_id'];
    }
    $result = getEtfComponentChartData($pdo,  '00991A',  $targetDate, $stockIds);
    createJsonFile($pdo, $targetDate . '_00991A-charts', $result);
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    // writeLog($pdo, 'update00991A', '00991A 成分股資料更新完成,共耗時 ' . $execution_time . ' 秒', 'end');
} catch (Throwable $e) {
    echo $e->getMessage();
    exit(1);
}
