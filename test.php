<?php
require_once("init.php");
updateStockProfile($pdo);

// date_default_timezone_set('Asia/Taipei');
// set_time_limit(0);

// require_once("function-tools.php");
// require_once("function-getData.php");


// $industry = [
//     '01' => '水泥工業',
//     '02' => '食品工業',
//     '03' => '塑膠工業',
//     '04' => '紡織纖維',
//     '05' => '電機機械',
//     '06' => '電器電纜',
//     '08' => '玻璃陶瓷',
//     '09' => '造紙工業',
//     '10' => '鋼鐵工業',
//     '11' => '橡膠工業',
//     '12' => '汽車工業',
//     '14' => '建材營造',
//     '15' => '航運業',
//     '16' => '觀光餐旅',
//     '17' => '金融保險',
//     '18' => '貿易百貨',
//     '19' => '綜合',
//     '20' => '其他',
//     '21' => '化學工業',
//     '22' => '生技醫療業',
//     '23' => '油電燃氣業',
//     '24' => '半導體業',
//     '25' => '電腦及週邊設備業',
//     '26' => '光電業',
//     '27' => '通信網路業',
//     '28' => '電子零組件業',
//     '29' => '電子通路業',
//     '30' => '資訊服務業',
//     '31' => '其他電子業',
//     '35' => '綠能環保',
//     '36' => '數位雲端',
//     '37' => '運動休閒',
//     '38' => '居家生活'
// ];
// $url = "https://openapi.twse.com.tw/v1/opendata/t187ap03_L";
// try {
//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, $url);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     $response = curl_exec($ch);
//     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//     curl_close($ch);

//     $stocks = [];
//     if ($httpCode === 200) {
//         $data = json_decode($response, true);
//         foreach ($data as $k => $stock) {
//             if ($stock['公司代號'] == '') continue;

//             $url = "https://ic.tpex.org.tw/company_chain.php?stk_code=" . $stock['公司代號'];
//             $ch = curl_init();
//             curl_setopt($ch, CURLOPT_URL, $url);
//             curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//             $html = curl_exec($ch);
//             libxml_use_internal_errors(true);
//             $dom = new DOMDocument();
//             $dom->loadHTML($html);
//             $xpath = new DOMXPath($dom);
//             $nodes = $xpath->query('//h4[a[contains(@href,"introduce.php")]]');
//             $result = [];
//             foreach ($nodes as $node) {
//                 $text = html_entity_decode($node->textContent);
//                 $parts = explode('>', $text);
//                 if (count($parts) >= 2) {
//                     $subIndustry = trim(end($parts));
//                     $result[] = $subIndustry;
//                 }
//             }

//             $stocks[] = [
//                 'stock_id' => $stock['公司代號'] ?? '',
//                 'stock_name' => $stock['公司簡稱'] ?? '',
//                 'industry' => $industry[(string)$stock['產業別']] ?? ''
//             ];
//         }
//     }
// } catch (Exception $e) {
//     echo "取得 t187ap03_L 失敗：" . $e->getMessage();
// }
