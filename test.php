<?php
require_once("init.php");

$a = updateMarketDailyData($pdo, $targetDate);
echo json_encode($a);
