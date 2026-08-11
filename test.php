<?php
require_once("init.php");
$targetDate = "2026-08-10";

$a = getOpenInterest($pdo, $targetDate);
echo json_encode($a);
