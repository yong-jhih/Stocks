<?php
require_once("init.php");
$targetDate = '2026-09-03';

$params = [
    'data_id' => "2330",
    'start_date' => $targetDate,
    'end_date' => $targetDate,
];

$params['token'] = getenv('FINMIND_TOKEN');
$apiUrl = "https://api.finmindtrade.com/api/v4/taiwan_stock_trading_daily_report_secid_agg?" . http_build_query($params);
try {
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) throw new RuntimeException("錯誤 無法取得 Finmind {$params['dataset']}");
    if ($httpCode !== 200) throw new RuntimeException("http {$httpCode} 無法取得 Finmind {$params['dataset']}");
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException('JSON Error: ' . json_last_error_msg());
    echo json_encode($result);
} catch (Throwable $e) {
    writeLog($pdo, $params['dataset'], $e->getMessage(), 'Warnning');
    echo "empty";
}
