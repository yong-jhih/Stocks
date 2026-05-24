<?php
require_once("init.php");

$industry = [
    '01' => '水泥工業',
    '02' => '食品工業',
    '03' => '塑膠工業',
    '04' => '紡織纖維',
    '05' => '電機機械',
    '06' => '電器電纜',
    '08' => '玻璃陶瓷',
    '09' => '造紙工業',
    '10' => '鋼鐵工業',
    '11' => '橡膠工業',
    '12' => '汽車工業',
    '14' => '建材營造',
    '15' => '航運業',
    '16' => '觀光餐旅',
    '17' => '金融保險',
    '18' => '貿易百貨',
    '19' => '綜合',
    '20' => '其他',
    '21' => '化學工業',
    '22' => '生技醫療業',
    '23' => '油電燃氣業',
    '24' => '半導體業',
    '25' => '電腦及週邊設備業',
    '26' => '光電業',
    '27' => '通信網路業',
    '28' => '電子零組件業',
    '29' => '電子通路業',
    '30' => '資訊服務業',
    '31' => '其他電子業',
    '35' => '綠能環保',
    '36' => '數位雲端',
    '37' => '運動休閒',
    '38' => '居家生活'
];
$url = "https://openapi.twse.com.tw/v1/opendata/t187ap03_L";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$stocks = [];
if ($httpCode === 200) {
    $data = json_decode($response, true);
    foreach ($data as $k => $stock) {
        $stocks[] = [
            'stock_id' => $stock['公司代號'],
            'stock_name' => $stock['公司簡稱'],
            'industry' => $industry[(string)$stock['產業別']] ?? ''
        ];
    }
}

$sql = "INSERT INTO stock_profile 
            (stock_id, stock_name, industry) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            stock_id = VALUES(stock_id),
            stock_name = VALUES(stock_name),
            industry = VALUES(industry)";
$stmt = $pdo->prepare($sql);
$pdo->beginTransaction();
try {
    foreach ($stocks as $row) {
        $stmt->execute([
            $row[0],
            $row[1],
            (string)($row[3])
        ]);
    }
    $pdo->commit();
    writeLog($pdo, 'stock_profile', '更新完成,共新增 ' . count($stocks) . ' 筆', 'success');
} catch (Exception $e) {
    $pdo->rollBack();
    echo "寫入失敗：" . $e->getMessage();
    writeLog($pdo, 'stock_profile', "寫入失敗：" . $e->getMessage(), 'error');
}
