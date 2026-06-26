<?php

require_once("init.php");

$stocksTSE = getStockProfileTSE($pdo);
// $stocksTPEx = getStockProfileTPEx($pdo);
// $stocksESM = getStockProfileESM($pdo);


echo json_encode($stocksTSE['2330']);