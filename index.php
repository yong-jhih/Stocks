<?php
set_time_limit(0);
require_once("function-tools.php");
require_once("function-getData.php");

try {
    
} catch (PDOException $e) {
    die("系統執行失敗：" . $e->getMessage());
}
