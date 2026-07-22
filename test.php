<?php
require_once("init.php");
$targetDate = '2026-07-21';
$etfId = '00981A';

try {
    $analyzeMultiPeriodChanges = analyzeMultiPeriodChanges($pdo, $targetDate, '00981A');
    $analysis = $analyzeMultiPeriodChanges[0];
    $stockIds = [];
    $a = json_decode(file_get_contents("data/{$targetDate}_componentOf00981A.json"), true);
    foreach ($a as $v) {
        $stockIds[] = $v['stock_id'];
    }

    // function getEtfComponentChartData(PDO $pdo, string $etfId, string $targetDate, array $stockIds): array
    // $result = getEtfComponentChartData_test($pdo,  '00981A',  $targetDate, $stockIds);

    if (empty($stockIds)) {
        return ['date' => $targetDate, 'stocks' => []];
    }

    // 步驟 1：先抓出該 ETF 在 $targetDate 之前（含）的最近 60 個實際交易日
    $dateSql = "
        SELECT DISTINCT trade_date 
        FROM etf_component 
        WHERE etf_id = ? AND trade_date <= ? 
        ORDER BY trade_date DESC 
        LIMIT 60
    ";
    $stmt = $pdo->prepare($dateSql);
    $stmt->execute([$etfId, $targetDate]);
    $recentDates = $stmt->fetchAll(PDO::FETCH_COLUMN); // 取得 [ 2026-06-30, 2026-06-29, ... ]

    // 如果連一個日期都沒有，直接回傳
    if (empty($recentDates)) {
        return ['date' => $targetDate, 'stocks' => []];
    }

    // 為了讓圖表從過去畫到現在，將日期反轉成正序（舊 -> 新）
    $recentDates = array_reverse($recentDates);

    // 步驟 2：利用剛才找出的精準 60 個交易日，去撈取股票資料
    $stockPlaceholders = implode(',', array_fill(0, count($stockIds), '?'));
    $datePlaceholders = implode(',', array_fill(0, count($recentDates), '?'));

    $sql = "
        SELECT
            ec.stock_id stock_id,
            ec.trade_date trade_date,
            ec.amount amount,
            sh.close_price close_price_twse,
            shx.close_price close_price_tpex
        FROM etf_component ec
        LEFT JOIN stock_history sh
            ON sh.trade_date = ec.trade_date
           AND sh.stock_id = ec.stock_id
        LEFT JOIN TPEx_stock_history shx
            ON shx.trade_date = ec.trade_date
           AND shx.stock_id = ec.stock_id
        WHERE ec.stock_id IN ($stockPlaceholders)
          AND ec.trade_date IN ($datePlaceholders)
          AND ec.etf_id = ?
        ORDER BY ec.trade_date ASC, ec.stock_id ASC
    ";

    $stmt = $pdo->prepare($sql);
    // 參數順序：所有 stockId -> 所有限定日期 -> etfId
    $params = array_merge($stockIds, $recentDates, [$etfId]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 將資料庫撈出來的結果，先整理成以 [stock_id][trade_date] 為 Key 的雙層陣列，方便等一下快速比對
    $dbData = [];
    foreach ($rows as $row) {
        $dbData[$row['stock_id']][$row['trade_date']] = $row;
    }

    // 步驟 3：開始建立標準的 60 天對齊結構
    $stocks = [];
    foreach ($stockIds as $stockId) {
        $stocks[$stockId] = [
            'stockId' => $stockId,
            'series' => []
        ];

        // 哪怕某天沒資料，也硬塞一個帶有 date、但 price 為 null 的物件，確保時間軸完美對齊
        foreach ($recentDates as $date) {
            if (isset($dbData[$stockId][$date])) {
                $row = $dbData[$stockId][$date];
                $stocks[$stockId]['series'][] = [
                    "date"   => $date,
                    "stock_id" => $stockId,
                    "price"  => (float)($row['close_price_twse'] ?? $row['close_price_tpex'] ?? null),
                    "amount" => $row['amount'] !== null ? $row['amount'] / 1000 : 0
                ];
            } else {
                // 如果這天該股票在資料庫沒紀錄（被剔除或尚未納入）
                $stocks[$stockId]['series'][] = [
                    "date"   => $date,
                    "stock_id" => $stockId,
                    "price"  => null,  // 填 null，前端圖表線段會自動斷開或不顯示
                    "amount" => 0
                ];
            }
        }
    }

    // return [
    //     'date' => $targetDate,
    //     'stocks' => $stocks
    // ];
    $result = [
        'date' => $targetDate,
        'stocks' => $stocks
    ];
    createJsonFile($pdo, $targetDate . '_00981A-charts_test', $result);
} catch (Throwable $e) {
    exit(1);
}
