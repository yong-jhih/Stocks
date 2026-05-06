<?php

function convertTaiwanDateToWestern($dateStr)
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
    sleep(3);
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
        echo "Critical Error: Unable to write to system_logs. " . $e->getMessage();
    }
}

function checkIfDataPublished($pdo, $date, $table)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE trade_date = ?");
    $stmt->execute([$date]);
    return (int)$stmt->fetchColumn() > 500;
}

function callGeminiAI($apikey, $prompt = 'say hi', $model = 'gemini-2.5-flash')
{
    if (!isset($apikey)) return 'api key 不存在';
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . $apikey;
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

function updateDateList($date, $folder = 'data')
{
    $listPath = $folder . DIRECTORY_SEPARATOR . 'dateList.json';
    $dateList = [];
    if (file_exists($listPath)) {
        $content = file_get_contents($listPath);
        $dateList = json_decode($content, true) ?: [];
    }
    if (!in_array($date, $dateList)) {
        $dateList[] = $date;
    }
    rsort($dateList);
    $jsonString = json_encode($dateList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents($listPath, $jsonString) !== false;
}

function createJsonFile($pdo, $date, $name, $data, $folder = 'data')
{
    $safeName = preg_replace('/[^a-zA-Z0-9\-\_]/', '', $name);
    $safeDate = preg_replace('/[^0-9\-]/', '', $date);
    $fileName = "{$safeDate}_{$safeName}.json";
    $fullPath = $folder . DIRECTORY_SEPARATOR . $fileName;
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0755, true)) {
            writeLog($pdo, 'createJsonFile', "無法建立目錄: $folder", 'error');
            return false;
        }
    }
    $jsonString = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (file_put_contents($fullPath, $jsonString) !== false) {
        updateDateList($safeDate, $folder);
        return $fullPath;
    } else {
        return false;
    }
}

function lineNotification($pdo, $target, $message = 'testLine')
{
    $channelAccessToken = getenv('LINE_CHANNEL_ACCESS_TOKEN');
    $url = 'https://api.line.me/v2/bot/message/push';
    $messageText = "系統通知：\n" . $message;
    $payload = [
        'to' => $target,
        'messages' => [
            [
                'type' => 'text',
                'text' => $messageText
            ]
        ]
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $channelAccessToken
    ]);
    $result = curl_exec($ch);
    $errno = curl_errno($ch);
    $error_msg = curl_error($ch);
    curl_close($ch);
    if ($errno) {
        writeLog($pdo, 'lineNotification', $error_msg, 'error');
        echo "cURL Error: " . $error_msg;
    }
}
