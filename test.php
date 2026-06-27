<?php

require_once("init.php");

try {
    $stocksTSE = getStockProfileTSE($pdo);
    $stocksTPEx = getStockProfileTPEx($pdo);
    $stocksESM = getStockProfileESM($pdo);
    $stocksETF = getStockProfileETF($pdo);
    $stocksMix = [...$stocksTSE, ...$stocksTPEx, ...$stocksESM, ...$stocksETF];
    updateIndustry($pdo, $stocksMix);

} catch (Throwable $e) {
    throw $e;
}
