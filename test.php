<?php

require_once("init.php");

try {
    $stocksTSE = getStockProfileTSE($pdo);
    $stocksTPEx = getStockProfileTPEx($pdo);
    $stocksESM = getStockProfileESM($pdo);
    $stocksETF = getStockProfileETF($pdo);
    $stocks = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM];

    $stocks_R = [];
    foreach ($stocks as $v) {
        $stocks_R[$v['stock_id']] = $v;
    }

    createJsonFile($pdo, 'stockProfileList', $stocks_R);
    createJsonFile($pdo, 'ETFProfileList', $stocksETF);
    echo "stocks:" . count($stocks) . "\n";
    echo "stocksETF:" . count($stocksETF) . "\n";
} catch (Throwable $e) {
    throw $e;
}
