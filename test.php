<?php
require_once("init.php");

$date_array = [
    '2026-08-21',
    '2026-08-14',
    '2026-08-07',
    '2026-07-31',
    '2026-07-24',
    '2026-07-17',
    '2026-07-09',
    '2026-07-03',
    '2026-06-26',
    '2026-06-18',
    '2026-06-12',
    '2026-06-05',
    '2026-05-29',
    '2026-05-22',
    '2026-05-15',
    '2026-05-08',
    '2026-04-30',
    '2026-04-24',
    '2026-04-17',
    '2026-04-10',
    '2026-04-02',
    '2026-03-27',
    '2026-03-20',
    '2026-03-13',
    '2026-03-06',
    '2026-02-26',
    '2026-02-13',
    '2026-02-06',
    '2026-01-30',
    '2026-01-23',
    '2026-01-16',
    '2026-01-09',
    '2026-01-02',
    '2025-12-26',
    '2025-12-19',
    '2025-12-12',
    '2025-12-05',
    '2025-11-28',
    '2025-11-21',
    '2025-11-14',
    '2025-11-07',
    '2025-10-31',
    '2025-10-23',
    '2025-10-17',
    '2025-10-09',
    '2025-10-03',
    '2025-09-26',
    '2025-09-19',
    '2025-09-12',
    '2025-09-05'
];

$data = [];
$stock_id = "2330";
foreach ($date_array as $date) {
    $arg1 = escapeshellarg($stock_id);
    $arg2 = escapeshellarg(str_replace("-", "", $date));
    $rawOutput = shell_exec("node prefetch_TDCC.js {$arg1} {$arg2}");
    $data[$date] = json_decode($rawOutput, true);
    if ($date === '20260731') break;
}

createJsonFile($pdo, 'test', $data);

// try {
//     $pdo->beginTransaction();
//     $sql = "INSERT INTO stock_shareholder
//                 (trade_date, stock_id, shareholder_count, total_shares)
//                 VALUES (?, ?, ?, ?)
//                 ON DUPLICATE KEY UPDATE
//                 shareholder_count = VALUES(shareholder_count),
//                 total_shares = VALUES(total_shares)";
//     $stmt = $pdo->prepare($sql);
//     foreach ($data as $date => $row) {
//         $stmt->execute([
//             $date,
//             $stock_id,
//             (int)str_replace(",", "", $row[2]),
//             (int)str_replace(",", "", $row[3])
//         ]);
//     }
//     $pdo->commit();
// } catch (Throwable $e) {
//     $pdo->rollBack();
//     // throw new RuntimeException("{$targetDate} {$etf_id} 成分股資料新增失敗: " . $e->getMessage());
// }
