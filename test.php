<?php
require_once("init.php");

$date = ['2026-05-05', '2026-05-05'];
foreach ($date as $d) {
    if (
        checkIfDataPublished($pdo, $date, 'stock_history', 500) &&
        checkIfDataPublished($pdo, $date, 'stock_insti', 500) &&
        checkIfDataPublished($pdo, $date, 'stock_margin', 500) &&
        checkIfDataPublished($pdo, $date, 'stock_sbl_total', 500) &&
        checkIfDataPublished($pdo, $date, 'stock_sbl_sold', 500)
    ) {
        $results = selfSelectGenerateDailyDashboard($pdo, $d, [2449]);
        createJsonFile($pdo, $date, 'self-select', $results);
    }
}
