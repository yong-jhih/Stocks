<?php

require_once("init.php");

try {
    $stocksTSE = getStockProfileTSE($pdo);
    $stocksTPEx = getStockProfileTPEx($pdo);
    $stocksESM = getStockProfileESM($pdo);
    $stocksETF = getStockProfileETF($pdo);
    $stocks = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM];

    createJsonFile($pdo, 'stockProfileList', $stocks);
    createJsonFile($pdo, 'ETFProfileList', $stocksETF);
    echo "stocks:" . count($stocks) . "\n";
    echo "stocksETF:" . count($stocksETF) . "\n";
} catch (Throwable $e) {
    throw $e;
}
