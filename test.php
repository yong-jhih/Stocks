<?php
require_once("init.php");
$targetDate = "2026-08-10";


$taiex = getDataWithFinmind($pdo, [
    'dataset' => "TaiwanVariousIndicators5Seconds",
    'date' => $targetDate,
])['data'];

$a = '';
$url = "https://openapi.twse.com.tw/v1/exchangeReport/FMTQIK";
for ($i = 0; $i < 3; $i++) {
    $response = fetchUrl($url);
    if (isset($response['stat']) && $response['stat'] === 'error') {
        continue;
    } else {
        foreach ($response as $v) {
            if (convertTaiwanDateToWestern($v['Date']) === str_replace("-", "", $targetDate)) {
                $a = $v['TAIEX'];
            }
        }
    }
}


echo json_encode($a) . "\n";
echo json_encode($taiex);
