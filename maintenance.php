<?php
require_once("init.php");

updateStockProfile($pdo);
writeLog($pdo, 'dbClean', '開始進行資料庫維護', 'start');
dbClean($pdo, 'stock_history', 'trade_date', 100);
dbClean($pdo, 'stock_insti', 'trade_date', 100);
dbClean($pdo, 'stock_margin', 'trade_date', 100);
dbClean($pdo, 'stock_sbl_total', 'trade_date', 100);
dbClean($pdo, 'stock_sbl_sold', 'trade_date', 100);
dbClean($pdo, '00981A_component', 'trade_date', 100);
dbClean($pdo, 'system_logs', 'log_time', 10);
writeLog($pdo, 'dbClean', '資料庫維護完成', 'end');

updateSystemLog($pdo);
