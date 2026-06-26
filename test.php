<?php

require_once("init.php");

$stocksTSE = getStockProfileTSE($pdo);
$stocksTPEx = getStockProfileTPEx($pdo);
$stocksESM = getStockProfileESM($pdo);
// $stocksETF = getStockProfileETF($pdo);

// $result = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM, ...$stocksETF];
$stocks = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM];

// echo "上市共 " . count($stocksTSE) . " 檔\n";
// echo "上櫃共 " . count($stocksTPEx) . " 檔\n";
// echo "興櫃共 " . count($stocksESM) . " 檔\n";
// echo "ETF共 " . count($stocksETF) . " 檔\n";
// echo "共 " . count($result) . " 檔\n";

updateSubIndustryTest($pdo, $stocks);
