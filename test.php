<?php
require_once("init.php");
$targetDate = '2026-07-30';

// $tableTWSE = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
// $resultsTWSE = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTWSE);

// $tableTPEx = ['TPEx_stock_history', 'TPEx_stock_insti', 'TPEx_stock_margin', 'TPEx_stock_sbl_total', 'TPEx_stock_sbl_sold'];
// $resultsTPEx = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTPEx);
// $a = [...$resultsTWSE, ...$resultsTPEx];

// createJsonFile($pdo, $targetDate . '_test', $a);
// renewCharts($pdo, $targetDate, 'test', 'test-charts');

// try {
//     $etfid = ['00981A', '00991A', '00403A'];
//     $lineNotifyStr = '';
//     foreach ($etfid as $etf_id) {
//         $start_time = microtime(true);
//         writeLog($pdo, "update{$etf_id}", "取得交易日期 [{$targetDate}], 開始更新 {$etf_id} 成分股資料", 'start');
//         $results = getComponent($targetDate, $etf_id);
//         insertComponent($pdo, $targetDate, $etf_id, $results);
//         $analyzeMultiPeriodChanges = analyzeMultiPeriodChanges($pdo, $targetDate, $etf_id);
//         $analysis = $analyzeMultiPeriodChanges[0];
//         $lineNotifyStr .= $analyzeMultiPeriodChanges[1] . "\n";
//         createJsonFile($pdo, $targetDate . "_componentOf{$etf_id}", $analysis);
//         $stockIds = [];
//         $a = json_decode(file_get_contents("data/{$targetDate}_componentOf{$etf_id}.json"), true);
//         foreach ($a as $v) {
//             $stockIds[] = $v['stock_id'];
//         }
//         $result = getEtfComponentChartData($pdo,  $etf_id,  $targetDate, $stockIds);
//         createJsonFile($pdo, $targetDate . "_{$etf_id}-charts", $result);
//         $end_time = microtime(true);
//         $execution_time = round($end_time - $start_time, 2);
//         writeLog($pdo, "update{$etf_id}", "{$etf_id}成分股資料更新完成,共耗時{$execution_time}秒", 'end');
//     }
// } catch (Throwable $e) {
//     echo $e->getMessage();
//     exit(1);
// }

// $targetDate = '2026-07-29';
// $a = getInstitutionalBuySellWithFinmind($pdo, $targetDate);
// if (!empty($a)) {
//     $institutional = [];
//     foreach ($a as $item) {
//         $institutional[$item['name']] = [
//             'buy' => round($item['buy'] / 1e8, 1),
//             'sell' => round($item['sell'] / 1e8, 1),
//             'total' => round(($item['buy'] - $item['sell']) / 1e8, 1)
//         ];
//     }
//     $institutionalStr =
//         "三大法人共 " . $institutional['total']['total'] . "億 (買進 " . $institutional['total']['buy'] . "億/賣出 " . $institutional['total']['sell'] . "億)\n" .
//         "外資共 " . $institutional['Foreign_Investor']['total'] . "億 (買進 " . $institutional['Foreign_Investor']['buy'] . "億/賣出 " . $institutional['Foreign_Investor']['sell'] . "億)\n" .
//         "投信共 " . $institutional['Investment_Trust']['total'] . "億 (買進 " . $institutional['Investment_Trust']['buy'] . "億/賣出 " . $institutional['Investment_Trust']['sell'] . "億)\n" .
//         "自營商共 " . $institutional['Dealer_self']['total'] . "億 (買進 " . $institutional['Dealer_self']['buy'] . "億/賣出 " . $institutional['Dealer_self']['sell'] . "億)\n" .
//         "自營商避險共 " . $institutional['Dealer_Hedging']['total'] . "億 (買進 " . $institutional['Dealer_Hedging']['buy'] . "億/賣出 " . $institutional['Dealer_Hedging']['sell'] . "億)\n";
//     echo $institutionalStr;
// }



$a = getInstitutionalBuySellWithFinmind($pdo, $targetDate);
$b = getDataWithFinmind($pdo, $targetDate, $targetDate, 'TaiwanStockTotalInstitutionalInvestors')['data'];


if ($a === $b) {
    echo 'all same';
}
