<?php
require_once("init.php");

$stocks = getStockProfileWithTWSE($pdo);
updateConcept($pdo, $stocks);
