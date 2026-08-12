<?php
require_once("init.php");
$targetDate = '2026-08-11';

$a = getPutCallRatio($pdo, $targetDate);
echo "\n" . ($a);
