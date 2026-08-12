<?php
require_once("init.php");

// $a = getOpenInterest($pdo, $targetDate);
// echo json_encode($a);


$totalOI = getDataWithFinmind($pdo, [
    'dataset' => "TaiwanFuturesDaily",
    'data_id' => 'MTX',
    'start_date' => $targetDate,
    'end_date' => $targetDate
]);

echo json_encode($totalOI);