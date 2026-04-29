<?php

date_default_timezone_set('Asia/Taipei');
set_time_limit(0);
require_once("function-tools.php");
require_once("function-getData.php");

try {

    $ans = callGeminiAI('', '說明下井字遊戲', 'gemini-2.5-flash');
    echo $ans;
} catch (PDOException $e) {
    die("系統執行失敗：" . $e->getMessage());
}
