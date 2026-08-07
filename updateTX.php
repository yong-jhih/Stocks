<?php
require_once("init.php");

try {
    $data = [];
    $prefutures = json_decode(file_get_contents("TX.json"), true);
    foreach ($prefutures as $item) {
        if ($item['Date'] !== str_replace("-", "", $targetDate)) throw new RuntimeException("三大法人-區分各期貨契約-依日期 資料未更新完全");
        if ($item['Item'] === "外資及陸資" && in_array($item['ContractCode'], ["臺股期貨", "小型臺指期貨", "微型臺指期貨"])) {
            $data[] = $item;
        }
    }
    
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
