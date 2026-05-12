<?php
require_once("init.php");

$result = getStockAnalysisChart($pdo, 2330, '2026-05-11', 20);
createJsonFile($pdo, '2026-05-11', 'analysisChart', $result);
