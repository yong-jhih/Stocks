<?php

function convertTaiwanDateToWestern(PDO $pdo, string $dateStr): ?string
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
    writeLog($pdo, 'convertTaiwanDateToWestern', '民國年格式 轉換 西元年格式 失敗', 'error');
    return null;
}

function fetchUrl(string $url): array
{
    sleep(2);
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

function writeLog(PDO $pdo, string $type, string $content, string $result)
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

function checkIfDataPublished(PDO $pdo, string $date, string $table, int $count = 0): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE trade_date = ?");
    $stmt->execute([$date]);
    return (int)$stmt->fetchColumn() > $count;
}

function callGeminiAI(string $apikey, string $prompt = 'say hi', string $model = 'gemini-2.5-flash'): string
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

function updateDateList(string $date, string $folder = 'data')
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

function createJsonFile(PDO $pdo, string $date, string $name, array $data, string $folder = 'data'): ?string
{
    $safeName = preg_replace('/[^a-zA-Z0-9\-\_]/', '', $name);
    $safeDate = preg_replace('/[^0-9\-]/', '', $date);
    $fileName = "{$safeDate}_{$safeName}.json";
    $fullPath = $folder . DIRECTORY_SEPARATOR . $fileName;
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0755, true)) {
            writeLog($pdo, 'createJsonFile', "無法建立目錄: $folder", 'error');
            return null;
        }
    }
    $jsonString = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (file_put_contents($fullPath, $jsonString) !== false) {
        updateDateList($safeDate, $folder);
        return $fullPath;
    } else {
        writeLog($pdo, 'createJsonFile', "無法更新檔案: $fullPath", 'error');
        return null;
    }
}

function lineNotification(PDO $pdo, string $target, string $message = 'testLine')
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

function cleanData(int $days)
{
    $jsonPath = "data/dateList.json";
    if (file_exists($jsonPath)) {
        $dateArray = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($dateArray)) return;
        if (count($dateArray) <= $days) {
            echo "ℹ️ 目前資料量未超過 $days 筆，無需清理。<br>";
            return;
        }
        $dateArray = array_slice($dateArray, 0, $days);
        $newJson = json_encode($dateArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($jsonPath, $newJson)) {
            echo "✅ 成功清理 JSON！目前保留最新 $days 筆日期。<br>";
        } else {
            echo "❌ 寫入 JSON 失敗，請檢查權限。<br>";
            return;
        }
        $targetFiles = ["*_filter.json", "*_self-select.json", "*_componentOf00981A.json", "*_charts.json", "*_self-charts.json", "*_topPerforming.json", "*_topPerforming-charts.json"];
        foreach ($targetFiles as $pattern) {
            $allFiles = glob("data/" . $pattern);
            if ($allFiles) {
                foreach ($allFiles as $filePath) {
                    $basename = basename($filePath);
                    $fileDate = explode('_', $basename)[0];
                    if (!in_array($fileDate, $dateArray)) {
                        if (unlink($filePath)) {
                            echo "🗑️ 已刪除過期檔案: {$basename}<br>";
                        }
                    }
                }
            }
        }
    } else {
        echo "❌ 找不到 dateList.json";
    }
}

function dbClean(PDO $pdo, string $table, string $dateColumn, int $days): void
{
    $days = max(0, $days);
    $sql = "DELETE FROM `$table` WHERE `$dateColumn` < DATE_SUB(CURDATE(), INTERVAL $days DAY)";
    $pdo->exec($sql);
}

function renewCharts(PDO $pdo, string $targetDate, string $getCode, string $name): void
{
    $stockList = json_decode(file_get_contents("data/" . $targetDate . "_" . $getCode . ".json"), true);
    $allData = [
        'date' => $targetDate,
        'stocks' => []
    ];
    foreach ($stockList as $stock) {
        $data = getStockAnalysisChart($pdo, $stock['stock_id'], $targetDate);
        if ($data) {
            $allData['stocks'][$stock['stock_id']] = $data;
        }
    }
    createJsonFile($pdo, $targetDate, $name, $allData);
}

function callGAS(PDO $pdo, $data = []): void
{
    $gas_url = getenv('GAS_URL_TRIGGERS');
    $json_data = json_encode($data);
    $ch = curl_init($gas_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_data),
        'Connection: close'
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        if (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
            echo 'GAS 執行完成（回應超時，但已成功觸發轉移）';
            writeLog($pdo, 'callGAS', "發送請求成功:" . json_encode($data), 'success');
        } else {
            echo 'cURL 錯誤: ' . curl_error($ch);
            writeLog($pdo, 'callGAS', "發送請求失敗 cURL 錯誤:" . curl_error($ch), 'error');
        }
    } else {
        echo 'GAS 回應: ' . $response;
        writeLog($pdo, 'callGAS', 'GAS回應:' . $response, 'error');
    }
    curl_close($ch);
}
