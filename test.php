<?php

require_once("init.php");

try {
    $stocksTSE = getStockProfileTSE($pdo);
    $stocksTPEx = getStockProfileTPEx($pdo);
    $stocksESM = getStockProfileESM($pdo);
    $stocks = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM];
    updateSubIndustry($pdo, $stocks);
} catch (Throwable $e) {
    throw $e;
}
