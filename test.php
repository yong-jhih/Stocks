<?php
// require_once("init.php");


date_default_timezone_set('Asia/Taipei');
set_time_limit(0);

require_once("function-tools.php");
require_once("function-getData.php");

// $url = "https://www.moneydj.com/Z/ZH/ZHA/ZHA.djhtm";
// $ch = curl_init();
// curl_setopt($ch, CURLOPT_URL, $url);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// $response = curl_exec($ch);
// $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// curl_close($ch);
// if ($httpCode === 200) {
//     $code = [];
//     $b = explode(".djhtm?a=", explode("/z/js/SD.djjs", explode("oMainTable", $response)[1])[0]);
//     foreach ($b as $k => $v) {
//         if ($k == 0) continue;
//         $code[] = substr($v, 0, 7);
//     }
//     $a = array_values(array_unique($code));

// $  $response;
//     $data = json_decode($response, true);
//     foreach ($data as $k => $stock) {
//         if ($stock['公司代號']) continue;
//         $stocks[] = [
//             'stock_id' => $stock['公司代號'] ?? '',
//             'stock_name' => $stock['公司簡稱'] ?? '',
//             'industry' => $industry[(string)$stock['產業別']] ?? ''
//         ];
//     }
// }

// foreach ($a as $v) {
//     $url = "https://www.moneydj.com/Z/ZH/ZHA/ZHA.djhtm?a=" . $v;
// }

$url = "https://www.moneydj.com/z/zh/zha/zh00.djhtm?a=C020021";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode === 200) {
    $a = explode("</td></tr></table>", explode("oScrollMenu", $response)[1])[0];
    $b = explode("javascript:Link2Stk('AS", $a);

    // $code = [];
    // $c = explode("/z/js/SD.djjs", explode("oScrollMenu", $response)[1])[0];
    foreach ($b as $k => $v) {
        if ($k == 0) continue;
        echo $v . "\n\n";
    }
    // $a = array_values(array_unique($code));
    // echo json_encode($c);
}
