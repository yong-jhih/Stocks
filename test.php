<?php
// require_once("init.php");

$start_time = microtime(true);
$results = getComponentOf00981A_FromLocal();
echo $results;
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);
// writeLog($pdo, 'SelectAnalysis', '篩選分析結束,共耗時 ' . $execution_time . ' 秒', 'success');


function getComponentOf00981A()
{
    $url = "https://www.ezmoney.com.tw/ETF/Fund/Info?fundCode=49YTW";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $response;
}

function fetchUrlWithCurl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 模擬 Chrome 瀏覽器
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
    // 加入來源頁面 (Referer)，這對繞過防護很重要
    curl_setopt($ch, CURLOPT_REFERER, 'https://www.google.com/');
    // 處理跳轉
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    // 略過 SSL 檢查 (若遇到證書問題時)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
