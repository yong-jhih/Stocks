<?php

function convertTaiwanDateToWestern($dateStr) // return string "YYYY-MM-DD"
{
    if (preg_match('/^(\d{2,3})\/(\d{2})\/(\d{2})$/', $dateStr, $matches)) {
        $taiwanYear = (int)$matches[1];
        $month = $matches[2];
        $day = $matches[3];
        $westernYear = $taiwanYear + 1911;
        $result = "{$westernYear}-{$month}-{$day}";
        if (checkdate((int)$month, (int)$day, $westernYear)) {
            return $result;
        }
    }
    return null;
}

function fetchUrl($url) // return array
{
    sleep(5);
    $options = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n"
        ]
    ];
    try {
        $response = file_get_contents($url, false, stream_context_create($options));
        if ($response === FALSE) {
            return [
                "status" => "error",
                "msg" => "錯誤：無法取得資料。請檢查網路連線或 API 網址。"
            ];
        }
        return json_decode($response, true);
    } catch (Exception $e) {
        return [
            "status" => "error",
            "msg" => "錯誤：" . $e->getMessage()
        ];
    }
}
