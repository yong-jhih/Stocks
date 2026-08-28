<?php
require_once("init.php");

$targetDate = '2026-08-14';
// process.php

$stock_id = "2449";
$date = str_replace("-", "", $targetDate);
$arg1 = escapeshellarg($stock_id);
$arg2 = escapeshellarg($date);

// 執行 node 腳本並取得 console.log 的輸出
$rawOutput = shell_exec("node prefetch_TDCC.js {$arg1} {$arg2}");
echo $rawOutput;
// 解析 JSON
// $data = json_decode($rawOutput, true);

// if ($data && $data['status'] === 'success') {
//     echo "成功接收到 " . count($data['items']) . " 筆資料！\n";
//     foreach ($data['items'] as $item) {
//         echo "- ID: {$item['id']}, Name: {$item['name']}\n";
//     }
// } else {
//     echo "資料擷取失敗\n";
//     exit(1);
// }
