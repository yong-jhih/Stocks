<?php
require_once("init.php");
$targetDate = "2026-08-07";

try {
    $url = "https://openapi.twse.com.tw/v1/exchangeReport/FMTQIK";
    $data = fetchUrl($url);
    echo end($data)['TAIEX'];
} catch (Throwable $e) {
}
