<?php

require_once("init.php");

// $url = "https://www.twse.com.tw/exchangeReport/MI_INDEX?response=json&type=ALLBUT0999&_=";
// for ($i = 0; $i < 3; $i++) {
//     $data = fetchUrl($pdo, $url);
//     if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['tables'])) {
//         foreach ($data['tables'] as $v) {
//             if (str_contains($v['title'], "每日收盤行情") && is_array($v['data'])) {
//                 $stocks = [];
//                 foreach ($v['data'] as $v1) {
//                     $stocks[$v1[0]] = ['stock_id' => $v1[0], 'stock_name' => $v1[1]];
//                 }
//                 if (count($stocks) > 1000) {
//                     createJsonFile($pdo, $targetDate, 'stockList', $stocks);
//                     if (file_exists("data/{$targetDate}_stockList.json")) {
//                         if (rename("data/{$targetDate}_stockList.json", "data/stockList.json")) {
//                             echo "檔案名稱已成功修改為：原檔名已成功更名為： stockList.json";
//                         } else {
//                             echo "檔案更名失敗。";
//                         }
//                     } else {
//                         echo "找不到原始檔案。";
//                     }
//                     exit;
//                 }
//             }
//         }
//     }
//     writeLog($pdo, 'getHistory', "證交所回傳錯誤訊息：" . ($data['msg'] ?? '未知錯誤') . ", 準備執行第 " . ($i + 1) . " 次重試", 'warning');
// }
// writeLog($pdo, 'getHistory', '執行 3 次失敗,退出', 'error');
// $a = json_decode(file_get_contents("data/_stockList.json"), true);
// echo "--------------------\n" . $a['0050']['stock_id'] . ":" . $a['0050']['stock_name'] . "\n--------------------\n";


$url = "https://www.tpex.org.tw/openapi/v1/tpex_mainboard_quotes";
for ($i = 0; $i < 3; $i++) {
    $data = fetchUrl($pdo, $url);
    $stocks = [];
    foreach ($data as $v) {
        $stocks[$v['SecuritiesCompanyCode']] = ['stock_id' => $v['SecuritiesCompanyCode'], 'stock_name' => $v['CompanyName']];
    }
    if (count($stocks) > 800) {
        createJsonFile($pdo, $targetDate, 'stockList', $stocks);
        if (file_exists("data/{$targetDate}_stockList.json")) {
            if (rename("data/{$targetDate}_stockList.json", "data/stockList.json")) {
                echo "檔案名稱已成功修改為：原檔名已成功更名為： stockList.json";
            } else {
                echo "檔案更名失敗。";
            }
        } else {
            echo "找不到原始檔案。";
        }
        exit;
    }
    writeLog($pdo, 'getHistory', "證交所回傳錯誤訊息：" . ($data['msg'] ?? '未知錯誤') . ", 準備執行第 " . ($i + 1) . " 次重試", 'warning');
}
writeLog($pdo, 'getHistory', '執行 3 次失敗,退出', 'error');





// https://openapi.twse.com.tw/v1/opendata/t187ap47_L 基金基本資料
// https://openapi.twse.com.tw/v1/opendata/t187ap03_L 上市公司基本資料
// https://www.tpex.org.tw/openapi/v1/mopsfin_t187ap03_O 上櫃公司基本資料