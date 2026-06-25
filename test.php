<?php

require_once("init.php");

$url = "https://www.twse.com.tw/exchangeReport/MI_INDEX?response=json&type=ALLBUT0999&_=";
for ($i = 0; $i < 3; $i++) {
    $data = fetchUrl($pdo, $url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['tables'])) {
        foreach ($data['tables'] as $v) {
            if (str_contains($v['title'], "每日收盤行情") && is_array($v['data'])) {
                $stocks = [];
                foreach ($v['data'] as $v1) {
                    $stocks[$v1[0]] = ['stock_id' => $v1[0], 'stock_name' => $v1[1]];
                }
                if (count($stocks) > 1000) {
                    createJsonFile($pdo, '', 'stockList', $stocks);
                    exit;
                }
            }
        }
    }
    writeLog($pdo, 'getHistory', "證交所回傳錯誤訊息：" . ($data['msg'] ?? '未知錯誤') . ", 準備執行第 " . ($i + 1) . " 次重試", 'warning');
}
writeLog($pdo, 'getHistory', '執行 3 次失敗,退出', 'error');
