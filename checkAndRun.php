<?php
require_once("init.php");

if (isHoliday($targetDate)) {
    echo '非交易日跳過';
    exit(0);
}
if (file_exists("data/" . $targetDate . "_filter.json")) {
    echo '分析資料已存在';
    exit(0);
}

$SBLSoldData = getSBLSold($targetDate, $pdo);
if (isset($SBLSoldData['status']) && $SBLSoldData['status'] == 'error') {
    echo '資料未到齊, 等待下次觸發';
    exit(0);
} else {
    echo '偵測 TWT93U 信用額度總量管制餘額 已公布, 準備進行更新。';
    require 'updateAll.php';
    echo '資料庫更新完畢, 準備進入分析。';
}

if (
    checkIfDataPublished($pdo, $targetDate, 'stock_history', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_insti', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_margin', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_total', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_sold', 500)
) {
    $results = generateDailyDashboard($pdo, $targetDate);
    createJsonFile($pdo, $targetDate, 'filter', $results);
    renewCharts($pdo, $targetDate, 'filter', 'charts');

    $resultsTop = topPerformingGenerateDailyDashboard($pdo, $targetDate);
    createJsonFile($pdo, $targetDate, 'topPerforming', $resultsTop);
    renewCharts($pdo, $targetDate, 'topPerforming', 'topPerforming-charts');

    cleanData(20);
    lineNotification($pdo, getenv('LINE_TARGET'), '今日盤後篩選及評分排行已完成,請稍候佈署 - https://yong-jhih.github.io/Stocks/');


    $gas_url = getenv('GAS_URL_TRIGGERS');
    $data = [
        'message'   => 'PHP 執行步驟已完成！',
        'sender'    => 'PHP Web Server',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    $json_data = json_encode($data);
    $ch = curl_init($gas_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_data),
        'Connection: close'
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        if (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
            echo 'GAS 執行完成（回應超時，但已成功觸發轉移）';
        } else {
            echo 'cURL 錯誤: ' . curl_error($ch);
        }
    } else {
        echo 'GAS 回應: ' . $response;
    }
    curl_close($ch);
}
