<?php
set_time_limit(0);
require_once("config.php");
require_once("function-tools.php");
require_once("function-getData.php");

try {
    $dsn = "mysql:host=gateway01.ap-northeast-1.prod.aws.tidbcloud.com;port=4000;dbname=somethin_tools;charset=utf8mb4";
    $pdo = new PDO($dsn, 'CnY42RuyBGHYrNd.root', 'mRZDyCyqVQXX5Vdn', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    echo "正在測試網路連線至 $db_ip : 4000 ...\n";
    $fp = @fsockopen($db_ip, 4000, $errno, $errstr, 5);
    if (!$fp) {
        echo "網路連線失敗：$errstr ($errno)\n";
        echo "這代表 TiDB 的防火牆(IP Access List)沒有放行 GitHub 的連線。\n";
    } else {
        echo "網路連線成功！門開著。接下來準備嘗試帳號密碼登入...\n";
        fclose($fp);
    }

    $isCLI = (php_sapi_name() === 'cli');
    if ($isCLI || (isset($_POST['action']) && $_POST['action'] == 'updateAll')) {
        echo "開始執行台股資料更新任務...\n";
        // $targetDate = getLatestTradingDateWithTWSE();
        $targetDate = '2026-04-21';
        if (is_array($targetDate)) {
            echo "通知： " . ($targetDate['msg'] ?? '無法取得交易日期') . "\n";
            exit;
        }
        echo "目標日期：$targetDate\n";

        insertHistory($pdo, $targetDate, getHistory($targetDate));
        echo "1. 收盤行情處理完畢\n";

        insertInsti($pdo, $targetDate, getInsti($targetDate));
        echo "2. 三大法人資料處理完畢\n";

        insertMargin($pdo, $targetDate, getMargin($targetDate));
        echo "3. 融資融券資料處理完畢\n";

        insertSBLTotal($pdo, $targetDate, getSBLTotal($targetDate));
        echo "4. 借券餘額處理完畢\n";

        insertSBLSold($pdo, $targetDate, getSBLSold($targetDate));
        echo "5. 借券賣出處理完畢\n";

        echo "任務執行成功！\n";
    }
} catch (PDOException $e) {
    die("系統執行失敗：" . $e->getMessage());
}
