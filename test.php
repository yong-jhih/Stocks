<?php
require_once("init.php");
// updateMarketDailyData($pdo, $targetDate);

$a = analyzeMarketTrend($pdo);
echo "\n" . json_encode($a);
