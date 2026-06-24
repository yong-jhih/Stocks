<?php
require_once("init.php");

// updateStockProfile($pdo);
dbClean($pdo, 'stock_history', 'trade_date', 100);
dbClean($pdo, 'stock_insti', 'trade_date', 100);
dbClean($pdo, 'stock_margin', 'trade_date', 100);
dbClean($pdo, 'stock_sbl_total', 'trade_date', 100);
dbClean($pdo, 'stock_sbl_sold', 'trade_date', 100);
dbClean($pdo, '00981A_component', 'trade_date', 100);
dbClean($pdo, 'system_logs', 'log_time', 10);

updateSystemLog($pdo);
