<?php
require_once("init.php");

// $a = getOpenInterest($pdo, $targetDate);
// echo json_encode($a);
$targetDate = '2026-08-11';

$OI = getDataWithFinmind($pdo, [
    'dataset' => "TaiwanFuturesDaily",
    'data_id' => 'MTX',
    'start_date' => $targetDate,
    'end_date' => $targetDate
]);
$totalOI = 0;
if (!empty($OI)) {
    foreach ($OI['data'] as $v) {
        $totalOI += $v['open_interest'];
    }
}
echo $totalOI;
