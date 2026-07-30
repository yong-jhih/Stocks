<?php
require_once("init.php");
// $targetDate = '2026-07-29';

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


// FinMind API URL (自行填寫)
$url = "https://api.finmindtrade.com/api/v4/data";

// FinMind Token (自行填寫)
$token = getenv('FINMIND_TOKEN');

// API 參數
$params = [
    'dataset' => 'TaiwanStockTotalInstitutionalInvestors',
    'data_id' => '2330',
    'start_date' => '2026-07-01',
    'end_date' => '2026-07-29',
    'token' => $token
];

// 組成完整 URL
$apiUrl = $url . '?' . http_build_query($params);

// 初始化 cURL
$ch = curl_init($apiUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ]
]);

// 執行請求
$response = curl_exec($ch);

// 檢查錯誤
if (curl_errno($ch)) {
    die('cURL Error: ' . curl_error($ch));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// HTTP 狀態檢查
if ($httpCode !== 200) {
    die("HTTP Error: {$httpCode}");
}

// JSON 解析
$result = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON Error: ' . json_last_error_msg());
}

// 顯示結果
echo '<pre>';
print_r($result);
echo '</pre>';
