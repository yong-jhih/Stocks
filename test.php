<?php
require_once("init.php");
$targetDate = '2026-07-09';

if ( // 已公布 檢查資料量 足夠 直接進行分析
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_history', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_insti', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_margin', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_sbl_total', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_sbl_sold', 500)
) {
    echo '上櫃資料到齊';
} else { // 已公布 資料量不足 則更新資料
    // writeLog($pdo, 'updateAllHistory', "偵測 [{$targetDate}] TWT93U 信用額度總量管制餘額已公布, 準備進行更新歷史資料", 'waitting');
    updateAllTPExHistory($pdo, $targetDate);
    // writeLog($pdo, 'updateAllHistory', '歷史資料更新完畢, 等待下階段進入分析', 'waitting');
    // updateSystemLog($pdo);
}
