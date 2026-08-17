<?php
require_once("init.php");
// $targetDate = '2026-08-17';
// updateMarketDailyData($pdo, $targetDate);


// $a = json_decode(file_get_contents("pc_ratio.json"), true);
// foreach ($a as $item) {
//     if ($item['date'] === str_replace("-", "/", $targetDate)) {
//         echo $item['oi_pcr'];
//         break;
//     }
// }


$a = getPutCallRatio($pdo, $targetDate);
echo "PutCallRatio:{$a}";