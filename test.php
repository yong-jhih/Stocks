<?php
require_once("init.php");
$targetDate = '2026-08-13';
// updateMarketDailyData($pdo, $targetDate);

$a = getInstiBuySell($pdo, $targetDate);
echo "\n" . json_encode($a);
