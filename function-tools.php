<?php

function convertTaiwanDateToWestern($dateStr = '1150228')
{
    if (preg_match('/^(\d{3})(\d{2})(\d{2})$/', $dateStr, $matches)) {
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
            return ["status" => "error", "msg" => "錯誤：無法取得資料。請檢查網路連線或 API 網址。"];
        }
        return json_decode($response, true) ?? ["status" => "error", "msg" => "JSON 解析失敗"];
    } catch (Exception $e) {
        return ["status" => "error", "msg" => "錯誤：" . $e->getMessage()];
    }
}

function writeLog($pdo, $type, $content, $result)
{
    $sql = "INSERT INTO system_logs (log_time, log_type, content, result) 
            VALUES (?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $currentTime = date('Y-m-d H:i:s');
        $stmt->execute([$currentTime, $type, $content, $result]);
    } catch (Exception $e) {
        error_log("Critical Error: Unable to write to system_logs. " . $e->getMessage());
    }
}

function callGeminiAI($prompt = 'say hi', $model = 'gemini-2.5-flash')
{
    $apiKey = getenv('GEMINI_TOKEN');
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    try {
        $response = curl_exec($ch);
        if (curl_errno($ch)) return "連線失敗: " . curl_error($ch);
        $result = json_decode($response, true);
        curl_close($ch);
        if (isset($result['error'])) return "AI 診斷異常: " . $result['error']['message'];
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $ans = trim($result['candidates'][0]['content']['parts'][0]['text']);
            $cleanAns = preg_replace('/[*#]/u', '', $ans);
            return $cleanAns;
        }
        return "AI 無法識別內容";
    } catch (Exception $e) {
        return "程式執行失敗: " . $e->getMessage();
    }
}
