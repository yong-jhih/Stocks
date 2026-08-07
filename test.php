<?php
require_once("init.php");

$targetDate = "2026-08-06";
$a = getDataWithFinmind($pdo, $targetDate, $targetDate, "TaiwanFuturesInstitutionalInvestors");
echo json_encode($a);