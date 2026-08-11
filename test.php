<?php
require_once("init.php");

$a = getOpenInterest($pdo, $targetDate);
echo json_encode($a);
