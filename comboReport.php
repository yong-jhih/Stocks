<?php

/**
 * comboReport.php
 *
 * 讀取 data/comboTracking.json（由 checkAndRun.php 每天自動呼叫 trackComboPerformance() 累積），
 * 印出「策略分類 × 均線策略」的後續股價表現統計報表。
 *
 * 只讀本機的 data/comboTracking.json，不連 DB、不打 API，可以隨時手動執行：
 *   php comboReport.php
 * 或透過瀏覽器開啟。
 *
 * 可用參數（網址列加 ?days=5&filter=1&min=5，或命令列 php comboReport.php 5 1 5）：
 *   days   追蹤天數窗口，對應 trackComboPerformance() 的 1/2/5，預設 2
 *   filter 是否只看當初有出現在 filter.json（盤後篩選精選名單）的股票，1=是，預設 0
 *   min    最小樣本數門檻，低於這個數字的分組不顯示，預設 5
 */
require_once("function-tools.php");

if (php_sapi_name() === 'cli') {
    $trackDays = isset($argv[1]) ? (int)$argv[1] : 2;
    $onlyInFilter = isset($argv[2]) ? (bool)$argv[2] : false;
    $minSamples = isset($argv[3]) ? (int)$argv[3] : 5;
} else {
    $trackDays = isset($_GET['days']) ? (int)$_GET['days'] : 2;
    $onlyInFilter = !empty($_GET['filter']);
    $minSamples = isset($_GET['min']) ? (int)$_GET['min'] : 5;
}

$summary = summarizeComboTracking($trackDays, $onlyInFilter, $minSamples);

$totalRecords = 0;
$trackingPath = "data/comboTracking.json";
if (file_exists($trackingPath)) {
    $all = json_decode(file_get_contents($trackingPath), true);
    if (is_array($all)) {
        $totalRecords = count(array_filter($all, function ($r) use ($trackDays, $onlyInFilter) {
            if ($r['trading_days'] !== $trackDays) return false;
            if ($onlyInFilter && empty($r['in_filter'])) return false;
            return true;
        }));
    }
}

$isCli = php_sapi_name() === 'cli';
$nl = $isCli ? "\n" : "<br>\n";

echo "====================================" . $nl;
echo "策略組合追蹤報表" . $nl;
echo "追蹤天數窗口：{$trackDays} 個交易日" . $nl;
echo "資料範圍：" . ($onlyInFilter ? "僅限盤後篩選精選名單 (filter.json)" : "全部 topPerforming 名單") . $nl;
echo "最小樣本數門檻：{$minSamples}" . $nl;
echo "符合條件總筆數：{$totalRecords}" . $nl;
echo "====================================" . $nl;

if (empty($summary)) {
    echo "目前沒有符合條件的分組資料（可能是累積天數還不夠，或樣本數都低於門檻）。" . $nl;
    exit(0);
}

if (!$isCli) echo "<pre>";
printf("%-10s %-10s %6s %8s %10s %10s" . $nl, "策略分類", "均線策略", "N", "上漲率", "平均漲跌%", "平均評分Δ");
foreach ($summary as $row) {
    printf(
        "%-10s %-10s %6d %7.1f%% %9.2f%% %10.1f" . $nl,
        $row['strategy_type'],
        $row['ma_strategy'],
        $row['n'],
        $row['up_rate'],
        $row['avg_price_chg_pct'],
        $row['avg_score_chg']
    );
}
if (!$isCli) echo "</pre>";
