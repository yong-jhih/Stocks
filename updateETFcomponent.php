<?php
require_once("init.php");

$etfid = ['00981A', '00991A', '00403A'];
try {
    foreach ($etfid as $etf_id) {
        $results = getComponent($targetDate, $etf_id);
        insertComponent($pdo, $targetDate, $etf_id, $results);
    }
} catch (Throwable $e) {
    writeLog($pdo, 'updateETFcomponent', "更新ETF異常:" . $e->getMessage(), 'error');
    updateSystemLog($pdo);
    exit(1);
}
