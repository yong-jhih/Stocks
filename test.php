<?php
// require_once("init.php");
$targetDate = '2026-08-17';
// updateMarketDailyData($pdo, $targetDate);

$pc = 0;
$a = json_decode(file_get_contents("pc_ratio.json"), true);
foreach ($a as $item) {
    if (strtotime($item['date']) === strtotime($targetDate)) {
        $pc = $item['oi_pcr'];
        break;
    }
}
echo $pc;
