<?php
require_once("init.php");

$targetDate = "2026-08-06";
$params = [
    'dataset' => "TaiwanFuturesInstitutionalInvestors",
    'data_id' => "TX",
    'start_date' => $targetDate,
    'end_date' => $targetDate,
];
$a = getDataWithFinmind($pdo, $params);
echo json_encode($a);
