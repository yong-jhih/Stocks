<?php
require_once("init.php");
$targetDate = "2026-08-07";


// $params = [
//     'dataset' => "TaiwanFuturesInstitutionalInvestors",
//     'data_id' => "TX",
//     'start_date' => $targetDate,
//     'end_date' => $targetDate,
// ];
// $a = getDataWithFinmind($pdo, $params);

// $b = getTAIEX($pdo, $targetDate);
// echo json_encode($b);


// $SBLSoldData = getSBLSold($pdo, $targetDate);
// if (isset($SBLSoldData['status']) && $SBLSoldData['status'] == 'error' || empty($SBLSoldData)) { // 未公布
//     echo 'TWT93U 信用額度總量管制餘額 資料未到齊, 等待下次觸發';
//     exit(0);
// } else 
if ( // 已公布 檢查資料量 足夠 直接進行分析
    checkIfDataPublished($pdo, $targetDate, 'stock_history', 700) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_insti', 700) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_margin', 700) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_total', 700) &&
    checkIfDataPublished($pdo, $targetDate, 'stock_sbl_sold', 700) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_history', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_insti', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_margin', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_sbl_total', 500) &&
    checkIfDataPublished($pdo, $targetDate, 'TPEx_stock_sbl_sold', 500)
) {
    // $start_time = microtime(true);
    $lineNotifyStr = '';
    // $log = testRetry($pdo);
    // if (!($log['log_type'] === 'generateDailyDashboard' && ($log['result'] === 'start' || $log['result'] === 'retry'))) {
    //     writeLog($pdo, 'generateDailyDashboard', "[{$targetDate}] 資料數量正常, 開始進行盤後篩選及評分排行", 'start');
    // }

    try {
        // 篩選 排行
        $tableTWSE = ['stock_history', 'stock_insti', 'stock_margin', 'stock_sbl_total', 'stock_sbl_sold'];
        $tableTPEx = ['TPEx_stock_history', 'TPEx_stock_insti', 'TPEx_stock_margin', 'TPEx_stock_sbl_total', 'TPEx_stock_sbl_sold'];
        $resultsTWSE = generateDailyDashboard($pdo, $targetDate, $tableTWSE);
        $resultsTPEx = generateDailyDashboard($pdo, $targetDate, $tableTPEx);
        $resultsTopTWSE = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTWSE);
        $resultsTopTPEx = topPerformingGenerateDailyDashboard($pdo, $targetDate, $tableTPEx);
        $resultsMix = [...$resultsTWSE, ...$resultsTPEx];
        // createJsonFile($pdo, $targetDate . '_filter', $resultsMix);
        // renewCharts($pdo, $targetDate, 'filter', 'charts');
        $resultsTopMix = [...$resultsTopTWSE, ...$resultsTopTPEx];
        // createJsonFile($pdo, $targetDate . '_topPerforming', $resultsTopMix);
        // renewCharts($pdo, $targetDate, 'topPerforming', 'topPerforming-charts');
        // writeLog($pdo, 'generateDailyDashboard', "[{$targetDate}] 篩選分析完成，共 " . count($resultsMix) . " 檔", 'success');
        // writeLog($pdo, 'topPerformingGenerateDailyDashboard', "[{$targetDate}] 排行分析完成，共 " . count($resultsTopMix) . " 檔", 'success');
        // updateDateList($targetDate);
        // callGAS([
        //     'date' => $targetDate,
        //     'action' => 'triggersSelfSelect',
        //     'after' => 180
        // ]);
        // callGAS([
        //     'date' => $targetDate,
        //     'action' => 'addSelfSelect',
        //     'data' => $resultsMix
        // ]);
        // $end_time = microtime(true);
        // $execution_time = round($end_time - $start_time, 2);
        // writeLog($pdo, 'generateDailyDashboard', "[{$targetDate}] 盤後篩選及評分排行已完成, 共耗時 {$execution_time} 秒", 'end');
        // echo "\n" . "分析結束:" . $lineNotifyStr . "\n";

        // ETF
        $etfid = ['00981A', '00403A', '00991A'];
        foreach ($etfid as $etf_id) {
            // $start_time = microtime(true);
            // writeLog($pdo, "update{$etf_id}", "取得交易日期 [{$targetDate}], 開始更新 {$etf_id} 成分股資料", 'start');
            $analyzeMultiPeriodChanges = analyzeMultiPeriodChanges($pdo, $targetDate, $etf_id);
            $analysis = $analyzeMultiPeriodChanges[0];
            $lineNotifyStr .= $analyzeMultiPeriodChanges[1] . "\n";
            // createJsonFile($pdo, $targetDate . "_componentOf{$etf_id}", $analysis);
            $stockIds = [];
            $a = json_decode(file_get_contents("data/{$targetDate}_componentOf{$etf_id}.json"), true);
            foreach ($a as $v) {
                $stockIds[] = $v['stock_id'];
            }
            $result = getEtfComponentChartData($pdo,  $etf_id,  $targetDate, $stockIds);
            // createJsonFile($pdo, $targetDate . "_{$etf_id}-charts", $result);
            // $end_time = microtime(true);
            // $execution_time = round($end_time - $start_time, 2);
            // writeLog($pdo, "update{$etf_id}", "{$etf_id} 成分股資料更新完成, 共耗時 {$execution_time} 秒", 'end');
        }
        // echo "ETF結束:" . $lineNotifyStr . "\n";

        // 大盤法人買賣超
        $institutionalBuySell = getDataWithFinmind($pdo, [
            'dataset' => "TaiwanStockTotalInstitutionalInvestors",
            'start_date' => $targetDate,
            'end_date' => $targetDate,
        ])['data'];
        if (!empty($institutionalBuySell)) {
            $institutional = [];
            foreach ($institutionalBuySell as $item) {
                $institutional[$item['name']] = [
                    'buy' => round($item['buy'] / 1e8, 1),
                    'sell' => round($item['sell'] / 1e8, 1),
                    'total' => round(($item['buy'] - $item['sell']) / 1e8, 1)
                ];
            }
            $institutionalStr =
                "{$targetDate} 盤後\n" .
                "三大法人共 " . $institutional['total']['total'] . "億 (買進 " . $institutional['total']['buy'] . "億/賣出 " . $institutional['total']['sell'] . "億)\n" .
                "外資共 " . $institutional['Foreign_Investor']['total'] . "億 (買進 " . $institutional['Foreign_Investor']['buy'] . "億/賣出 " . $institutional['Foreign_Investor']['sell'] . "億)\n" .
                "投信共 " . $institutional['Investment_Trust']['total'] . "億 (買進 " . $institutional['Investment_Trust']['buy'] . "億/賣出 " . $institutional['Investment_Trust']['sell'] . "億)\n" .
                "自營商共 " . $institutional['Dealer_self']['total'] . "億 (買進 " . $institutional['Dealer_self']['buy'] . "億/賣出 " . $institutional['Dealer_self']['sell'] . "億)\n" .
                "自營商避險共 " . $institutional['Dealer_Hedging']['total'] . "億 (買進 " . $institutional['Dealer_Hedging']['buy'] . "億/賣出 " . $institutional['Dealer_Hedging']['sell'] . "億)\n\n";
            $lineNotifyStr = "{$institutionalStr}{$lineNotifyStr}";
        }
        // echo "大盤法人買賣超結束:" . $lineNotifyStr . "\n";

        echo "\n" . "{$lineNotifyStr} 今日盤後篩選及評分排行已完成, 請稍候佈署 - https://yong-jhih.github.io/Stocks/" . "\n";
        // updateSystemLog($pdo);
        // lineNotification($pdo, getenv('LINE_TARGET'), "{$lineNotifyStr} 今日盤後篩選及評分排行已完成, 請稍候佈署 - https://yong-jhih.github.io/Stocks/");
    } catch (Throwable $e) {
        writeLog($pdo, 'checkAndRun', $e->getMessage(), 'error');
        updateSystemLog($pdo);
        exit(1);
    }
} else { // 已公布 資料量不足 則更新資料
    // writeLog($pdo, 'updateAllHistory', "偵測 [{$targetDate}] TWT93U 信用額度總量管制餘額已公布, 準備進行更新歷史資料", 'waitting');
    // updateAllHistory($pdo, $targetDate);
    // updateAllTPExHistory($pdo, $targetDate);
    // writeLog($pdo, 'updateAllHistory', '歷史資料更新完畢, 等待下階段進入分析', 'waitting');
    // updateSystemLog($pdo);
}
