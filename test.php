<?php
require_once("init.php");
$targetDate = '2026-08-13';
// updateMarketDailyData($pdo, $targetDate);

// $a = getInstiBuySell($pdo, $targetDate);
// echo "\n" . json_encode($a);


$institutionalBuySell = [];
// for ($i = 1; $i <= 10; $i++) {
$institutionalBuySell = getDataWithFinmind($pdo, [
    'dataset' => "TaiwanStockTotalInstitutionalInvestors",
    'start_date' => $targetDate,
    'end_date' => $targetDate,
]);
echo "\n" . json_encode($institutionalBuySell);



// if (!empty($institutionalBuySell)) {
//     foreach ($institutionalBuySell as $item) {
//         $institutional[$item['name']] = [
//             'buy' => round($item['buy'] / 1e8, 1),
//             'sell' => round($item['sell'] / 1e8, 1),
//             'total' => round(($item['buy'] - $item['sell']) / 1e8, 1)
//         ];
//     }
// }
// if (!empty($institutional)) {
//     break;
// } else {
//     if ($i <= 9) {
//         writeLog($pdo, 'getInstiBuySell', "第 {$i}/10 次抓取完成, 尚有缺漏資料, 60秒後重試", 'warning');
//         sleep(60);
//     } else {
//         writeLog($pdo, 'getInstiBuySell', "第 {$i}/10 次抓取完成, 尚有缺漏資料, 停止重試, 退出更新大盤資料", 'error');
//         exit(1);
//     }
// }
// }
