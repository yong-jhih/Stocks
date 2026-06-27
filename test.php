<?php

require_once("init.php");

try {
    $stocksTSE = getStockProfileTSE($pdo);
    $stocksTPEx = getStockProfileTPEx($pdo);
    $stocksESM = getStockProfileESM($pdo);
    $stocksETF = getStockProfileETF($pdo);
    $stocks = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM];
    $stocksMix = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM, ...$stocksETF];
    $stocks_R = array_column($stocks, null, 'stock_id');
    createJsonFile($pdo, 'stockProfileList', $stocks_R);
    updateSubIndustry($pdo, $stocks);
} catch (Throwable $e) {
    throw $e;
}
