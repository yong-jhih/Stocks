<?php
date_default_timezone_set('Asia/Taipei');
set_time_limit(0);

require_once("function-tools.php");
require_once("function-getData.php");

$jsonFile = 'stock_data.json';
if (file_exists($jsonFile)) {
    $jsonStr = file_get_contents($jsonFile);
    $data = json_decode($jsonStr, true);
    if ($data) {
        foreach ($data as $item) {
            if ($item['AssetName'] === '股票') {
                $details = $item['Details'];
                $value = $item['Value'];
            }
        }

        echo 'val=' . $value . "\n";

        $todayStr = '2026-05-07';
        $isAllUpdated = true;
        $totalAmount = 0;
        foreach ($details as $detail) {
            $itemDate = substr($detail['EditTime'], 0, 10);
            if ($itemDate !== $todayStr) {
                $isAllUpdated = false;
                break;
            }
            $totalAmount += (int)$detail['Amount'];
        }

        echo 'totalAmount=' . $totalAmount . "\n";
        // if ($totalAmount > 1.01 * $value || $totalAmount < 0.99 * $value) return null;
        // if (!$isAllUpdated) return null;
        // return $details;
    }
    // return null;
}
