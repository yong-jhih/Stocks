<?php
require_once("init.php");

try {
    $taiex = getTAIEX($pdo, $targetDate);
    $openInterest = getOpenInterest($pdo, $targetDate);
    $PutCallRatio = getPutCallRatio($targetDate);
    $instiBuySell = getInstiBuySell($pdo, $targetDate);
    $pdo->beginTransaction();
    $sql = "INSERT INTO market_daily (
                    trade_date, twii_close,
                    txf_foreign_long, txf_foreign_short, txf_foreign_net,
                    mxf_foreign_long, mxf_foreign_short, mxf_foreign_net,
                    tmf_foreign_long, tmf_foreign_short, tmf_foreign_net,
                    mxf_retail_ratio, txo_put_call_ratio,
                    insti_total_buy, insti_total_sell,
                    insti_foreign_buy, insti_foreign_sell,
                    insti_trust_buy, insti_trust_sell,
                    insti_dealer_buy, insti_dealer_sell,
                    insti_dealer_risk_buy, insti_dealer_risk_sell
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    twii_close = VALUES(twii_close),
                    txf_foreign_long = VALUES(txf_foreign_long),
                    txf_foreign_short = VALUES(txf_foreign_short),
                    txf_foreign_net = VALUES(txf_foreign_net),
                    mxf_foreign_long = VALUES(mxf_foreign_long),
                    mxf_foreign_short = VALUES(mxf_foreign_short),
                    mxf_foreign_net = VALUES(mxf_foreign_net),
                    tmf_foreign_long = VALUES(tmf_foreign_long),
                    tmf_foreign_short = VALUES(tmf_foreign_short),
                    tmf_foreign_net = VALUES(tmf_foreign_net),
                    mxf_retail_ratio = VALUES(mxf_retail_ratio),
                    txo_put_call_ratio = VALUES(txo_put_call_ratio),
                    insti_total_buy = VALUES(insti_total_buy),
                    insti_total_sell = VALUES(insti_total_sell),
                    insti_foreign_buy = VALUES(insti_foreign_buy),
                    insti_foreign_sell = VALUES(insti_foreign_sell),
                    insti_trust_buy = VALUES(insti_trust_buy),
                    insti_trust_sell = VALUES(insti_trust_sell),
                    insti_dealer_buy = VALUES(insti_dealer_buy),
                    insti_dealer_sell = VALUES(insti_dealer_sell),
                    insti_dealer_risk_buy = VALUES(insti_dealer_risk_buy),
                    insti_dealer_risk_sell = VALUES(insti_dealer_risk_sell)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $targetDate,
        $taiex,
        $openInterest['txf_foreign_long'] ?? null,
        $openInterest['txf_foreign_short'] ?? null,
        $openInterest['txf_foreign_net'] ?? null,
        $openInterest['mxf_foreign_long'] ?? null,
        $openInterest['mxf_foreign_short'] ?? null,
        $openInterest['mxf_foreign_net'] ?? null,
        $openInterest['tmf_foreign_long'] ?? null,
        $openInterest['tmf_foreign_short'] ?? null,
        $openInterest['tmf_foreign_net'] ?? null,
        $openInterest['mxf_retail_ratio'] ?? null,
        $PutCallRatio,
        $instiBuySell['insti_total_buy'] ?? null,
        $instiBuySell['insti_total_sell'] ?? null,
        $instiBuySell['insti_foreign_buy'] ?? null,
        $instiBuySell['insti_foreign_sell'] ?? null,
        $instiBuySell['insti_trust_buy'] ?? null,
        $instiBuySell['insti_trust_sell'] ?? null,
        $instiBuySell['insti_dealer_buy'] ?? null,
        $instiBuySell['insti_dealer_sell'] ?? null,
        $instiBuySell['insti_dealer_risk_buy'] ?? null,
        $instiBuySell['insti_dealer_risk_sell'] ?? null,
    ]);
    $pdo->commit();
    unlink("pc_ratio.json");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    writeLog($pdo, 'updateDailyMarket', "{$targetDate} 大盤資料新增失敗: " . $e->getMessage(), 'error');
    updateSystemLog($pdo);
    exit(1);
}
