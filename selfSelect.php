<?php
require_once("init.php");

if (
    file_exists("data/" . $targetDate . "_self-select.json") &&
    file_exists("data/" . $targetDate . "_self-charts.json")
) {
    echo '分析資料已存在';
    exit(0);
}

$stockJson = getenv('STOCK_DATA');
$stocks = json_decode($stockJson, true);
$stockList = [];
foreach ($stocks as $stock) {
    $stockList[] = $stock['code'];
}
if (count($stockList) == 0) {
    echo '自選清單為空, 退出分析';
    exit(0);
}

if (
    checkIfDataPublished($pdo, $targetDate, 'stock_history', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_insti', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_margin', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_total', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_sold', 500)
) {
    echo '資料數量正常, 開始進行自選分析';
    try {
        $resultsSelf = selfSelectGenerateDailyDashboard($pdo, $targetDate, $stockList);
        createJsonFile($pdo, $targetDate . '_self-select', $resultsSelf);
        renewCharts($pdo, $targetDate, 'self-select', 'self-charts');
        callGAS([
            'date' => $targetDate,
            'action' => 'upload',
            'after' => 300
        ]);
        cleanData(20);
        updateSystemLog($pdo);
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'exceeding the allowed memory limit')) {
            writeLog($pdo, 'selfSelectGenerateDailyDashboard', 'TiDB記憶體不足，3分鐘後重試', 'retry');
            callGAS([
                'date' => $targetDate,
                'action' => 'retry',
                'target' => 'SelfSelect',
                'after' => 180
            ]);
            updateSystemLog($pdo);
            exit(0);
        } else {
            writeLog($pdo, 'selfSelectGenerateDailyDashboard', $e->getMessage(), 'error');
            updateSystemLog($pdo);
            exit(1);
        }
    }
} else {
    echo '資料數量不足, 請檢查資料更新狀態';
    exit(1);
}
