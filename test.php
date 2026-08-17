<?php
require_once("init.php");
// updateMarketDailyData($pdo, $targetDate);

$targetDate = '2026-08-17';

$PutCallRatio = null;
for ($i = 1; $i <= 10; $i++) {
    if ($PutCallRatio) {
        $url = 'https://www.taifex.com.tw/cht/3/pcRatio';
        $postData = http_build_query([
            'queryStartDate' => str_replace("-", "/", $targetDate),
            'queryEndDate'   => str_replace("-", "/", $targetDate)
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 200) {
            echo "321";
            $html = $response;
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            $xpath = new DOMXPath($doc);
            $tables = $xpath->query('//table[contains(@class, "table_f")]');
            if ($tables->length > 0) {
                $tableNode = $tables->item(0);
                $rows = $xpath->query('.//tr', $tableNode);
                $result = [];
                foreach ($rows as $row) {
                    $cols = $xpath->query('.//td', $row);
                    if ($cols->length >= 7) {
                        $result[] = [
                            'date'          => trim($cols->item(0)->nodeValue), // 日期
                            'put_volume'    => trim($cols->item(1)->nodeValue), // 賣權成交量
                            'call_volume'   => trim($cols->item(2)->nodeValue), // 買權成交量
                            'volume_ratio'  => trim($cols->item(3)->nodeValue), // 買賣權成交量比率%
                            'put_oi'        => trim($cols->item(4)->nodeValue), // 賣權未平倉量
                            'call_oi'       => trim($cols->item(5)->nodeValue), // 買權未平倉量
                            'oi_ratio'      => trim($cols->item(6)->nodeValue), // 買賣權未平倉量比率%
                        ];
                    }
                }
                echo json_encode($result);
                if (isset($result[0]) && $result[0]['date'] == str_replace("-", "/", $targetDate)) {
                    echo (float)$result[0]['oi_ratio'];
                    $PutCallRatio = (float)$result[0]['oi_ratio'];
                }
            }
        }
    }
    if (!empty($PutCallRatio)) {
        break;
    } else {
        if ($i <= 9) {
            writeLog($pdo, 'getPutCallRatio', "第 {$i}/10 次抓取完成, 尚有缺漏資料, 60秒後重試", 'warning');
            sleep(60);
        } else {
            writeLog($pdo, 'getPutCallRatio', "第 {$i}/10 次抓取完成, 尚有缺漏資料, 停止重試, 退出更新基本資料", 'error');
            exit(1);
        }
    }
}
echo $PutCallRatio;
