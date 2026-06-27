<?php

require_once("init.php");

try {
    $stocksTSE = getStockProfileTSE($pdo);
    $stocksTPEx = getStockProfileTPEx($pdo);
    $stocksESM = getStockProfileESM($pdo);
    $stocks = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM];
    $stocks_R = array_column($stocks, null, 'stock_id');
    createJsonFile($pdo, 'stockProfileList', $stocks_R);
    updateSubIndustry($pdo, $stocks);
    updateConcept($pdo, $stocks);
} catch (Throwable $e) {
    throw $e;
}
