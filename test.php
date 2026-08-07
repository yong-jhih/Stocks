<?php
require_once("init.php");

$targetDate = "2026-08-06";
$a = getDataWithFinmind($pdo, $targetDate, $targetDate, "");
echo json_encode($a);