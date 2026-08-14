<?php
require_once("init.php");
// updateMarketDailyData($pdo, $targetDate);

$targetDate = '2026-08-14';
$stocksMap = getStocksMap();
$stocks = [];
$date = str_replace("-", "", $targetDate);
$url = "https://www.twse.com.tw/rwd/zh/fund/TWT43U?date={$date}&response=json";
$data = fetchUrl($url);
if (isset($data['stat']) && $data['stat'] === 'OK') {
    foreach ($data['data'] as $stock) {
        if (isset($stocksMap[$stock[0]]) && in_array($stocksMap[$stock[0]]['stock_type'], ['TSE', 'TPEx'])) {
            $stocks[] = $stock;
        }
    }
} else {
    echo "getLatestTradingDateWithTWSE 證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤') . "\n";
    return null;
}

echo count($stocks) . "\n";
echo json_encode($stocks[20]);
