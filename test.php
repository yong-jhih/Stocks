<?php

date_default_timezone_set('Asia/Taipei');
set_time_limit(0);

// require_once("config.php");
require_once("function-tools.php");
require_once("function-getData.php");
// function getPDOConnection()
// {
//     global $db_ip, $db_name, $db_user, $db_pass;
//     $dsn = "mysql:host=$db_ip;port=4000;dbname=$db_name;charset=utf8mb4";
//     $options = [
//         PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
//         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
//         PDO::MYSQL_ATTR_SSL_CA       => '/etc/ssl/certs/ca-certificates.crt',
//     ];
//     try {
//         return new PDO($dsn, $db_user, $db_pass, $options);
//     } catch (PDOException $e) {
//         error_log("Database Connection Failed: " . $e->getMessage());
//         die("系統執行失敗，請檢查資料庫連線。");
//     }
// }

// $pdo = getPDOConnection();
// $targetDate = getLatestTradingDateWithTWSE($pdo) ?? getLatestTradingDateWithFugle($pdo);




$gas_url = getenv('GAS_URL_TRIGGERS');
$data = [
    'message'   => 'PHP 執行步驟已完成！',
    'sender'    => 'PHP Web Server',
    'timestamp' => date('Y-m-d H:i:s')
];
$json_data = json_encode($data);
$ch = curl_init($gas_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
// ✨ 重點：強制 cURL 使用 HTTP/1.1 協議，避免 Google 302 導向時發生 HTTP/2 PROTOCOL_ERROR
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_data)
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'cURL 錯誤: ' . curl_error($ch);
} else {
    echo 'GAS 回應: ' . $response;
}

curl_close($ch);
