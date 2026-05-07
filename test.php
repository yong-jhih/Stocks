<?php
require_once("init.php");

$date = ['2026-05-05'];
foreach ($date as $d) {
    if (
        checkIfDataPublished($pdo, $d, 'stock_history', 500) &&
        checkIfDataPublished($pdo, $d, 'stock_insti', 500) &&
        checkIfDataPublished($pdo, $d, 'stock_margin', 500) &&
        checkIfDataPublished($pdo, $d, 'stock_sbl_total', 500) &&
        checkIfDataPublished($pdo, $d, 'stock_sbl_sold', 500)
    ) {
        $results = selfSelectGenerateDailyDashboard($pdo, $d, [2449]);
        createJsonFile($pdo, $d, 'self-select', $results);
    }
}
