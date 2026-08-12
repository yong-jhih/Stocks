<?php
require_once("init.php");
$targetDate = '2026-08-11';

$a = updateMarketDailyData($pdo, $targetDate);
echo json_encode($a);
