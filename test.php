<?php
require_once("init.php");
$targetDate = "2026-08-07";

try {
    $params = [
        'dataset' => "TaiwanFuturesInstitutionalInvestors",
        'data_id' => "TX",
        'start_date' => $targetDate,
        'end_date' => $targetDate,
    ];
    $a = getDataWithFinmind($pdo, $params);
    $b = getTAIEX($targetDate);
    echo $b . "\n";
    echo json_encode($a);
} catch (Throwable $e) {
    // callGAS([
    //     'date' => $targetDate,
    //     'action' => 'retry',
    //     'target' => 'GetETF',
    //     'after' => 600
    // ]);
    // writeLog($pdo, 'updateETFcomponent', "更新ETF異常:" . $e->getMessage(), 'error');
    // updateSystemLog($pdo);
    // $currentHi = (int)date('Hi');
    // if ($currentHi >= 2000 && $currentHi <= 2030) exit(1);
}
