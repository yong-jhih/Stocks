<?php
require_once("init.php");

$stocks = getStockProfileWithTWSE($pdo);
updateSubIndustry($pdo, $stocks);
