<?php
require_once("init.php");
// updateMarketDailyData($pdo, $targetDate);

$targetDate = '2026-08-17';
$PutCallRatio = getPutCallRatio($pdo, $targetDate);
echo $PutCallRatio;
