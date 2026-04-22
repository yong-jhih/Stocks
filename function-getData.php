<?php

function getLatestTradingDateWithTWSE() // return string "YYYY-MM-DD"
{
    $url = "https://www.twse.com.tw/exchangeReport/FMTQIK?response=json";
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK') {
        $date = end($data['data'])[0];
        return convertTaiwanDateToWestern($date);
    } else {
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}

function getHistory($date) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/MI_INDEX?response=json&type=ALLBUT0999&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['tables'])) {
        foreach ($data['tables'] as $v) {
            if (str_contains($v['title'], "每日收盤行情") && is_array($v['data'])) {
                $stocks = [];
                $fields = $v['fields'];
                foreach ($v['data'] as $v1) {
                    if (preg_match('/^[1-9]\d{3}$/', $v1[0])) {
                        $item = [];
                        foreach ($fields as $k => $field) {
                            $item[$field] = str_replace(',', '', $v1[$k]);
                        }
                        $stocks[] = $item;
                    }
                }
                return $stocks;
            }
        }
        return ["status" => "error", "msg" => "查詢不到每日收盤行情表"];
    }
    return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
}

function getInsti($date) // return array
{
    $url = "https://www.twse.com.tw/fund/T86?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data'])) {
        if (str_contains($data['title'], "三大法人買賣超日報")) {
            $stocks = [];
            $fields = $data['fields'];
            foreach ($data['data'] as $v) {
                if (preg_match('/^[1-9]\d{3}$/', $v[0])) {
                    $item = [];
                    foreach ($fields as $k => $field) {
                        $item[$field] = str_replace(',', '', $v[$k]);
                    }
                    $stocks[] = $item;
                }
            }
            return $stocks;
        }
        return ["status" => "error", "msg" => "資料格式錯誤"];
    }
    return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
}

function getMargin($date) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/MI_MARGN?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['tables'])) {
        foreach ($data['tables'] as $v) {
            if (str_contains($v['title'], "融資融券彙總") && is_array($v['data'])) {
                $stocks = [];
                $fields = $v['fields'];
                foreach ($v['data'] as $row) {
                    if (preg_match('/^[1-9]\d{3}$/', $row[0])) {
                        $item = [];
                        foreach ($fields as $index => $fieldName) {
                            $cleanValue = str_replace(',', '', $row[$index]);
                            $item[$fieldName] = $cleanValue;
                        }
                        $stocks[] = $item;
                    }
                }
                return $stocks;
            }
        }
        return ["status" => "error", "msg" => "查詢不到融資融券彙總表"];
    } else {
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}
function getSBLTotal($date) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/TWT72U?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data'])) {
        if (str_contains($data['title'], "證金營業處所借券餘額合計表")) {
            $stocks = [];
            $fields = $data['fields'];
            foreach ($data['data'] as $row) {
                if (preg_match('/^[1-9]\d{3}$/', $row[0]) && $row[8] == '集中市場') {
                    $item = [];
                    foreach ($fields as $index => $fieldName) {
                        $item[$fieldName] = str_replace(',', '', $row[$index]);
                    }
                    $stocks[] = $item;
                }
            }
            return $stocks;
        } else {
            return ["status" => "error", "msg" => "資料格式錯誤"];
        }
    } else {
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}
function getSBLSold($date) // return array
{
    $url = "https://www.twse.com.tw/exchangeReport/TWT93U?response=json&selectType=ALL&date=" . str_replace("-", "", $date);
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK' && isset($data['data'])) {
        if (str_contains($data['title'], "信用額度總量管制餘額")) {
            $stocks = [];
            $fields = $data['fields'];
            foreach ($data['data'] as $row) {
                if (preg_match('/^[1-9]\d{3}$/', $row[0])) {
                    $item = [];
                    foreach ($fields as $index => $fieldName) {
                        $item[$fieldName] = str_replace(',', '', $row[$index]);
                    }
                    $stocks[] = $item;
                }
            }
            return $stocks;
        } else {
            return ["status" => "error", "msg" => "資料格式錯誤"];
        }
    } else {
        return ["status" => "error", "msg" => "證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤')];
    }
}
