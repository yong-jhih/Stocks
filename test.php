<?php
require_once("init.php");
lineNotification();
// $analysis = analyzeComponentChanges($pdo, '2026-05-05', '2026-05-04');
// if ($analysis) {
//     echo "### 新增持股 ###\n";
//     foreach ($analysis['added'] as $s) echo "{$s['stock_id']} {$s['stock_name']} (權重: {$s['new_weight']}%)\n";

//     echo "\n### 移除持股 ###\n";
//     foreach ($analysis['removed'] as $s) echo "{$s['stock_id']} {$s['stock_name']}\n";

//     echo "\n### 權重/股數變動 ###\n";
//     foreach ($analysis['changed'] as $s) {
//         $trend = $s['diff_weight'] > 0 ? "↑" : "↓";
//         echo "{$s['stock_id']} {$s['stock_name']}: {$s['old_weight']}% -> {$s['new_weight']}% ({$trend} {$s['diff_weight']}%)\n";
//     }
// }
