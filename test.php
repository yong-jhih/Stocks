<?php
require_once("init.php");
$targetDate = '2026-08-11';

$openInterestTX = null;
$openInterestMTX = null;
$openInterestTMF = null;
$OI = null;
$return = [];
for ($i = 1; $i <= 10; $i++) {
    // if (empty($openInterestTX)) {
    //     $openInterestTX = getDataWithFinmind($pdo, [
    //         'dataset' => "TaiwanFuturesInstitutionalInvestors",
    //         'data_id' => 'TX',
    //         'start_date' => $targetDate,
    //         'end_date' => $targetDate
    //     ]);
    // }
    if (empty($openInterestMTX)) {
        $openInterestMTX = getDataWithFinmind($pdo, [
            'dataset' => "TaiwanFuturesInstitutionalInvestors",
            'data_id' => 'MTX',
            'start_date' => $targetDate,
            'end_date' => $targetDate
        ]);
    }
    // if (empty($openInterestTMF)) {
    //     $openInterestTMF = getDataWithFinmind($pdo, [
    //         'dataset' => "TaiwanFuturesInstitutionalInvestors",
    //         'data_id' => 'TMF',
    //         'start_date' => $targetDate,
    //         'end_date' => $targetDate
    //     ]);
    // }
    if (empty($OI)) {
        $OI = getDataWithFinmind($pdo, [
            'dataset' => "TaiwanFuturesDaily",
            'data_id' => 'MTX',
            'start_date' => $targetDate,
            'end_date' => $targetDate
        ]);
    }
    if (!empty($openInterestMTX) && !empty($OI)) {
        break;
    } else {
        if ($i <= 9) {
            writeLog($pdo, 'getOpenInterest', "第 {$i}/10 次抓取完成, 尚有缺漏資料, 60秒後重試", 'warning');
            sleep(60);
        } else {
            writeLog($pdo, 'getOpenInterest', "第 {$i}/10 次抓取完成, 尚有缺漏資料, 停止重試, 退出更新大盤資料", 'error');
            exit(1);
        }
    }
}
$totalOI = 0;
foreach ($OI['data'] as $v) {
    $totalOI += $v['open_interest'];
}
$totalNetLong = 0;
$totalNetShort = 0;
foreach ($openInterestMTX['data'] as $item) {
    $totalNetLong += $item['long_open_interest_balance_volume'];
    $totalNetShort += $item['short_open_interest_balance_volume'];
}
$net = $totalNetLong - $totalNetShort;

$MTXlongShortRatio = $net / $totalOI;

echo "\n";
echo $MTXlongShortRatio;
