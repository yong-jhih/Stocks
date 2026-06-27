<?php

require_once("init.php");

try {
    $stocksTSE = getStockProfileTSE($pdo);
    $stocksTPEx = getStockProfileTPEx($pdo);
    $stocksESM = getStockProfileESM($pdo);
    $stocks = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM];
    updateConcept($pdo, $stocks);
} catch (Throwable $e) {
    throw $e;
}
