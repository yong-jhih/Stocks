<?php

require_once("init.php");

$stocksTSE = getStockProfileTSE($pdo);
// $stocksTPEx = getStockProfileTPEx($pdo);
// $stocksESM = getStockProfileESM($pdo);



// $industry = json_decode(file_get_contents('data/industry_code.json'), true);
// $industry = file_get_contents('data/industry_code.json');
// echo json_encode($industry['2330']);
// echo $industry['01'];