<?php

function getLatestTradingDateWithTWSE(): ?string
{
    $url = "https://www.twse.com.tw/exchangeReport/FMTQIK?response=json";
    $data = fetchUrl($url);
    if (isset($data['stat']) && $data['stat'] === 'OK') {
        $rawDate = end($data['data'])[0];
        $cleanDate = str_replace('/', '', $rawDate);
        $convertedDate = convertTaiwanDateToWestern($cleanDate);
        if (!$convertedDate) {
            echo "getLatestTradingDateWithTWSE 日期格式轉換失敗\n";
            return null;
        }
        $latestDate = new DateTime($convertedDate);
        $today = new DateTime();
        $interval = $today->diff($latestDate);
        $daysDiff = $interval->days;
        $threshold = 10;
        if ($daysDiff > $threshold) {
            echo "getLatestTradingDateWithTWSE 證交所資料異常：回傳日期 ($convertedDate) 與今日差距過大 ($daysDiff 天)\n";
            return null;
        }
        return $convertedDate;
    } else {
        echo "getLatestTradingDateWithTWSE 證交所回傳錯誤訊息：" . ($data['stat'] ?? '未知錯誤') . "\n";
        return null;
    }
}

function getLatestTradingDateWithFugle(string $symbol = '2330'): ?string
{
    $apiToken = getenv('FUGLE_TOKEN');
    if (!$apiToken) {
        echo "getLatestTradingDateWithFugle 找不到 Fugle Token \n";
        return null;
    }
    $url = "https://api.fugle.tw/marketdata/v1.0/stock/historical/stats/$symbol";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-API-KEY: $apiToken",
        "Accept: application/json"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['date']) && $data['date'] != '') {
            return $data['date'];
        } else {
            echo "getLatestTradingDateWithFugle 回傳格式異常 \n";
            return null;
        }
    } else {
        echo "getLatestTradingDateWithFugle API 請求失敗，狀態碼：$httpCode \n";
        return null;
    }
}

function convertTaiwanDateToWestern(string $dateStr): ?string
{
    if (preg_match('/^(\d{3})(\d{2})(\d{2})$/', $dateStr, $matches)) {
        $taiwanYear = (int)$matches[1];
        $month = $matches[2];
        $day = $matches[3];
        $westernYear = $taiwanYear + 1911;
        $result = "{$westernYear}-{$month}-{$day}";
        if (checkdate((int)$month, (int)$day, $westernYear)) return $result;
    }
    return null;
}

function fetchUrl(string $url): array
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
        if ($response === FALSE) return ["status" => "error", "msg" => "錯誤：無法取得資料。請檢查網路連線或 API 網址。"];
        $result = json_decode($response, true) ?? ["status" => "error", "msg" => "JSON 解析失敗"];
        return $result;
    } catch (Exception $e) {
        return ["status" => "error", "msg" => "錯誤：{$e->getMessage()}"];
    }
}

function writeLog(PDO $pdo, string $type, string $content, string $result): void
{
    $sql = "INSERT INTO system_logs (log_time, log_type, content, result) VALUES (?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $currentTime = date('Y-m-d H:i:s');
        $stmt->execute([$currentTime, $type, $content, $result]);
    } catch (Exception $e) {
        echo "Critical Error: Unable to write to system_logs. {$e->getMessage()}";
        writeLog($pdo, 'writeLog', "Critical Error: Unable to write to system_logs. {$e->getMessage()}", 'error');
    }
}

function updateSystemLog(PDO $pdo, string $folder = 'data'): bool
{
    $sql = "SELECT * FROM system_logs ORDER BY id DESC LIMIT 800";
    $stmt = $pdo->query($sql);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0755, true)) return false;
    }
    $filePath = $folder . DIRECTORY_SEPARATOR . 'systemLog.json';
    $jsonString = json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents($filePath, $jsonString) !== false;
}

function checkIfDataPublished(PDO $pdo, string $date, string $table, int $count = 0): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE trade_date = ?");
    $stmt->execute([$date]);
    return (int)$stmt->fetchColumn() > $count;
}

function lineNotification(PDO $pdo, string $target, string $message = 'testLine'): void
{
    $channelAccessToken = getenv('LINE_CHANNEL_ACCESS_TOKEN');
    $url = 'https://api.line.me/v2/bot/message/push';
    $messageText = "系統通知：\n{$message}";
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
        echo "cURL Error: {$error_msg}";
    }
}

function callGeminiAI(string $apikey, string $prompt = 'say hi', string $model = 'gemini-2.5-flash'): string
{
    if (!isset($apikey)) return 'api key 不存在';
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apikey}";
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
        return "程式執行失敗:{$e->getMessage()}";
    }
}

function callGAS($data = []): void
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
        if (curl_errno($ch) === CURLE_OPERATION_TIMEDOUT) {
            echo "GAS 執行完成 發送請求成功: " . json_encode($data);
        } else {
            echo "發送請求失敗 cURL 錯誤: " . curl_error($ch);
        }
    } else {
        echo "GAS 回應: {$response}";
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

function createJsonFile(PDO $pdo, string $filename, array $data, string $folder = 'data', bool $backup = true): ?string
{
    $fullPath = $folder . DIRECTORY_SEPARATOR . $filename . '.json';
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0755, true)) {
            writeLog($pdo, 'createJsonFile', "無法建立目錄: $folder", 'error');
            return null;
        }
    }
    if (file_exists($fullPath) && $backup) {
        $timestamp = date('Ymd_His');
        $backupFileName = "{$filename}_backup_{$timestamp}.json";
        $backupFullPath = $folder . DIRECTORY_SEPARATOR . $backupFileName;
        if (!rename($fullPath, $backupFullPath)) {
            writeLog($pdo, 'createJsonFile', "無法將舊檔案備份至: $backupFullPath", 'warning');
        }
    }
    $jsonString = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (file_put_contents($fullPath, $jsonString) !== false) {
        return $fullPath;
    } else {
        writeLog($pdo, 'createJsonFile', "無法更新檔案: $fullPath", 'error');
        return null;
    }
}

function cleanData(int $days): void
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
        $targetFiles = [
            "*_componentOf00991A.json",
            "*_00991A-charts.json",
            "*_componentOf00403A.json",
            "*_00403A-charts.json",
            "*_componentOf00981A.json",
            "*_00981A-charts.json",
            "*_filter.json",
            "*_charts.json",
            "*_self-select.json",
            "*_self-charts.json",
            "*_topPerforming.json",
            "*_topPerforming-charts.json"
        ];
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
    $sql = "DELETE FROM `$table` WHERE `$dateColumn` < DATE_SUB(CURDATE(), INTERVAL :days DAY)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        writeLog($pdo, 'dbClean', "{$table} 資料清理成功: 共刪除 " . $stmt->rowCount() . " 筆", 'success');
    } catch (PDOException $e) {
        writeLog($pdo, 'dbClean', "資料清理失敗: {$e->getMessage()}", 'error');
    }
}

function renewCharts(PDO $pdo, string $targetDate, string $getCode, string $name): void
{
    $stockList = json_decode(file_get_contents("data/{$targetDate}_{$getCode}.json"), true);
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
    createJsonFile($pdo, "{$targetDate}_{$name}", $allData);
}

function testRetry(PDO $pdo): array
{
    $sql = "SELECT * FROM system_logs ORDER BY id DESC LIMIT 1";
    return (array) $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
}

/**
 * 追蹤「策略組合」後續 N 個交易日的股價／評分表現，並累積寫入 data/comboTracking.json。
 *
 * 用法：在 $targetDate 的 topPerforming.json／filter.json 都已經產生之後呼叫。
 * 對 $trackDaysList 裡每個天數 N，往回找 dateList.json 裡「N 個交易日前」的日期（startDate），
 * 把 startDate 當天 topPerforming.json 裡每一檔股票的策略組合，拿 $targetDate（startDate 的
 * 第 N 個交易日後）的收盤價／綜合評分，計算出這段期間的漲跌幅跟評分變化，存成一筆紀錄。
 *
 * data/comboTracking.json 不在 cleanData() 的清理檔名規則裡，所以不會被每日清理流程刪除，
 * 會持續累積，長期下來就能用來驗證「哪種策略組合 × 均線策略」比較可靠。
 *
 * 注意：這個函式本身不做任何判斷或建議，只負責記錄資料，統計分析交給
 * summarizeComboTracking() 或你自己另外拉資料分析。
 */
function trackComboPerformance(PDO $pdo, string $targetDate, array $trackDaysList = [1, 2, 5]): void
{
    $dateListPath = "data/dateList.json";
    if (!file_exists($dateListPath)) {
        writeLog($pdo, 'trackComboPerformance', "找不到 dateList.json，略過追蹤", 'warning');
        return;
    }
    $dateList = json_decode(file_get_contents($dateListPath), true);
    if (!is_array($dateList)) {
        writeLog($pdo, 'trackComboPerformance', "dateList.json 格式錯誤，略過追蹤", 'warning');
        return;
    }
    // dateList.json 是新到舊排序（rsort），targetDate 的 index 加上 N 就是 N 個交易日前的 index
    $targetIndex = array_search($targetDate, $dateList);
    if ($targetIndex === false) {
        writeLog($pdo, 'trackComboPerformance', "[{$targetDate}] 不在 dateList.json 內，略過追蹤", 'warning');
        return;
    }

    $endPath = "data/{$targetDate}_topPerforming.json";
    if (!file_exists($endPath)) {
        writeLog($pdo, 'trackComboPerformance', "[{$targetDate}] 找不到 topPerforming.json，略過追蹤", 'warning');
        return;
    }
    $endData = json_decode(file_get_contents($endPath), true);
    if (!is_array($endData)) return;
    $endMap = [];
    foreach ($endData as $s) {
        $endMap[$s['stock_id']] = $s;
    }

    $trackingPath = "data/comboTracking.json";
    $tracking = [];
    if (file_exists($trackingPath)) {
        $tracking = json_decode(file_get_contents($trackingPath), true) ?: [];
    }

    foreach ($trackDaysList as $n) {
        $startIndex = $targetIndex + $n;
        if (!isset($dateList[$startIndex])) {
            continue; // 累積的交易日還不夠 N 天，之後資料變多自然就會補上
        }
        $startDate = $dateList[$startIndex];

        $startPath = "data/{$startDate}_topPerforming.json";
        if (!file_exists($startPath)) continue;
        $startData = json_decode(file_get_contents($startPath), true);
        if (!is_array($startData)) continue;

        // startDate 當天是否出現在盤後篩選精選名單(filter.json)，供之後分析比對用
        $filterIds = [];
        $filterPath = "data/{$startDate}_filter.json";
        if (file_exists($filterPath)) {
            $filterData = json_decode(file_get_contents($filterPath), true);
            if (is_array($filterData)) {
                foreach ($filterData as $f) {
                    $filterIds[$f['stock_id']] = true;
                }
            }
        }

        // 先移除這個 (start_date, end_date, trading_days) 組合已經寫過的舊紀錄，避免重複執行時累加重複資料
        $tracking = array_values(array_filter($tracking, function ($r) use ($startDate, $targetDate, $n) {
            return !($r['start_date'] === $startDate && $r['end_date'] === $targetDate && $r['trading_days'] === $n);
        }));

        foreach ($startData as $s0) {
            $sid = $s0['stock_id'];
            if (!isset($endMap[$sid])) continue; // 這段期間下市、下櫃或不在榜內，跳過
            $s2 = $endMap[$sid];
            if (!is_numeric($s0['close'] ?? null) || $s0['close'] == 0) continue;

            $priceChgPct = round(($s2['close'] - $s0['close']) / $s0['close'] * 100, 2);
            $scoreChg = $s2['score'] - $s0['score'];

            $tracking[] = [
                'start_date' => $startDate,
                'end_date' => $targetDate,
                'trading_days' => $n,
                'stock_id' => $sid,
                'stock_name' => $s0['stock_name'],
                'strategy_type' => $s0['strategy_type'],
                'ma_strategy' => $s0['ma_strategy'],
                'matched_combos' => $s0['matched_combos'],
                'rating' => $s0['rating'],
                'in_filter' => isset($filterIds[$sid]),
                'price_start' => $s0['close'],
                'price_end' => $s2['close'],
                'price_chg_pct' => $priceChgPct,
                'score_start' => $s0['score'],
                'score_end' => $s2['score'],
                'score_chg' => $scoreChg,
                // === 2026-10-28 新增：自選評分「方案A」(7/10) 回測用的原始欄位 ===
                // 存原始數值而非算好的判定結果，日後要調整門檻或條件組合時不必重新累積資料，
                // 直接用這些欄位重算即可。對應 checkAndRun.php 自選評分的 6 個舊條件。
                'net_sbl' => $s0['net_sbl'] ?? null,
                'net_sbl_sum5' => $s0['net_sbl_sum5'] ?? null,
                'foreign_streak_days' => $s0['foreign_streak_days'] ?? null,
                'trust_streak_days' => $s0['trust_streak_days'] ?? null,
                'margin_balance' => $s0['margin_balance'] ?? null,
                'margin_balance_diff' => $s0['margin_balance_diff'] ?? null,
                'margin_balance_diff_sum5' => $s0['margin_balance_diff_sum5'] ?? null,
                'sbl_sold_balance' => $s0['sbl_sold_balance'] ?? null,
                'vma5' => $s0['vma5'] ?? null,
                'vma20' => $s0['vma20'] ?? null,
                'bia5' => $s0['bia5'] ?? null,
                'bia10' => $s0['bia10'] ?? null,
                'bia20' => $s0['bia20'] ?? null
            ];
        }
    }

    $jsonString = json_encode($tracking, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (file_put_contents($trackingPath, $jsonString) === false) {
        writeLog($pdo, 'trackComboPerformance', "寫入 comboTracking.json 失敗", 'error');
    } else {
        writeLog($pdo, 'trackComboPerformance', "[{$targetDate}] 組合追蹤資料已更新，累積共 " . count($tracking) . " 筆", 'success');
    }
}

/**
 * 讀取 data/comboTracking.json，依「策略分類 × 均線策略」分組統計平均漲跌幅／上漲率／評分變化。
 * 純粹是個報表工具，方便你或我快速檢視目前累積的追蹤資料，不寫入任何東西。
 *
 * @param int $trackDays 只統計這個天數窗口的紀錄（對應 trackComboPerformance 的 $trackDaysList）
 * @param bool $onlyInFilter 若為 true，只統計當初有出現在 filter.json（盤後篩選精選名單）裡的股票
 * @param int $minSamples 樣本數低於這個門檻的分組不顯示，避免小樣本雜訊
 * @return array 依平均漲跌幅由高到低排序的分組統計
 */
function summarizeComboTracking(int $trackDays = 2, bool $onlyInFilter = false, int $minSamples = 5): array
{
    $trackingPath = "data/comboTracking.json";
    if (!file_exists($trackingPath)) return [];
    $tracking = json_decode(file_get_contents($trackingPath), true);
    if (!is_array($tracking)) return [];

    $groups = [];
    foreach ($tracking as $r) {
        if ($r['trading_days'] !== $trackDays) continue;
        if ($onlyInFilter && empty($r['in_filter'])) continue;
        $key = $r['strategy_type'] . '|' . $r['ma_strategy'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'strategy_type' => $r['strategy_type'],
                'ma_strategy' => $r['ma_strategy'],
                'n' => 0,
                'up_count' => 0,
                'price_chg_sum' => 0,
                'score_chg_sum' => 0
            ];
        }
        $groups[$key]['n']++;
        if ($r['price_chg_pct'] > 0) $groups[$key]['up_count']++;
        $groups[$key]['price_chg_sum'] += $r['price_chg_pct'];
        $groups[$key]['score_chg_sum'] += $r['score_chg'];
    }

    $result = [];
    foreach ($groups as $g) {
        if ($g['n'] < $minSamples) continue;
        $result[] = [
            'strategy_type' => $g['strategy_type'],
            'ma_strategy' => $g['ma_strategy'],
            'n' => $g['n'],
            'up_rate' => round($g['up_count'] / $g['n'] * 100, 1),
            'avg_price_chg_pct' => round($g['price_chg_sum'] / $g['n'], 2),
            'avg_score_chg' => round($g['score_chg_sum'] / $g['n'], 1)
        ];
    }
    usort($result, fn ($a, $b) => $b['avg_price_chg_pct'] <=> $a['avg_price_chg_pct']);
    return $result;
}
