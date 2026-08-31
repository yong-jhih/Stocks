const puppeteer = require('puppeteer');
const fs = require('fs');

// =========================================================
// 基本設定
// =========================================================
const TDCC_URL = 'https://www.tdcc.com.tw/portal/zh/smWeb/qryStock';

function log(message) {
    console.error(message);
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function randomDelay(min = 800, max = 1500) {
    const ms = Math.floor(Math.random() * (max - min + 1)) + min;
    return sleep(ms);
}

// =========================================================
// 數字轉換
// =========================================================
function parseNumber(value) {
    if (value === null || value === undefined) {
        return 0;
    }

    return Number(
        String(value)
            .replace(/,/g, '')
            .replace(/\s/g, '')
    ) || 0;
}

// =========================================================
// 主程式
// =========================================================
(async () => {

    let browser = null;

    try {

        // -------------------------------------------------
        // 1. 取得 task JSON
        // -------------------------------------------------
        const taskFile = process.argv[2];

        if (!taskFile) {
            throw new Error('缺少 task JSON 檔案');
        }

        if (!fs.existsSync(taskFile)) {
            throw new Error(`找不到 task 檔案: ${taskFile}`);
        }

        const task = JSON.parse(
            fs.readFileSync(taskFile, 'utf8')
        );

        const targetDate = String(task.targetDate || '');
        const stockIds = Array.isArray(task.stock_id_array)
            ? task.stock_id_array
            : [];

        if (!targetDate) {
            throw new Error('task JSON 缺少 targetDate');
        }

        if (stockIds.length === 0) {
            throw new Error('task JSON 沒有 stock_id_array');
        }

        log('=====================================================');
        log('TDCC 批次查詢開始');
        log(`資料日期: ${targetDate}`);
        log(`股票數量: ${stockIds.length}`);
        log('=====================================================');

        // -------------------------------------------------
        // 2. 啟動 Browser
        // -------------------------------------------------
        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox'
            ]
        });

        const page = await browser.newPage();

        await page.setViewport({
            width: 1280,
            height: 900
        });

        // -------------------------------------------------
        // 3. 進入 TDCC
        // -------------------------------------------------
        log(`正在開啟 TDCC: ${TDCC_URL}`);

        await page.goto(TDCC_URL, {
            waitUntil: 'networkidle2',
            timeout: 60000
        });

        // 等待主要欄位
        await page.waitForSelector('#scaDate', {
            timeout: 30000
        });

        await page.waitForSelector('#StockNo', {
            timeout: 30000
        });

        log('TDCC 頁面載入完成');

        // -------------------------------------------------
        // 4. 確認目標日期是否存在
        // -------------------------------------------------
        const availableDates = await page.evaluate(() => {

            const select = document.querySelector('#scaDate');

            if (!select) {
                return [];
            }

            return Array.from(select.options)
                .map(option => option.value.trim())
                .filter(Boolean);
        });

        if (!availableDates.includes(targetDate)) {
            throw new Error(
                `TDCC 找不到指定日期 ${targetDate}`
            );
        }

        log(`確認資料日期存在: ${targetDate}`);

        // -------------------------------------------------
        // 5. 批次查詢
        // -------------------------------------------------
        const results = [];

        for (let i = 0; i < stockIds.length; i++) {

            const stockId = String(stockIds[i]).trim();

            if (!stockId) {
                continue;
            }

            log(
                `[${i + 1}/${stockIds.length}] 查詢 ${stockId}`
            );

            try {

                // -----------------------------------------
                // 5-1. 重新確認查詢頁面元素存在
                // -----------------------------------------
                await page.waitForSelector('#scaDate', {
                    timeout: 30000
                });

                await page.waitForSelector('#StockNo', {
                    timeout: 30000
                });

                // -----------------------------------------
                // 5-2. 設定資料日期
                // -----------------------------------------
                await page.select(
                    '#scaDate',
                    targetDate
                );

                // -----------------------------------------
                // 5-3. 確認使用「證券代號」
                // -----------------------------------------
                const stockRadio = await page.$('#sqlStockNo');

                if (stockRadio) {
                    await stockRadio.click();
                }

                // -----------------------------------------
                // 5-4. 清空並輸入股票代號
                // -----------------------------------------
                await page.$eval(
                    '#StockNo',
                    el => {
                        el.value = '';
                    }
                );

                await page.type(
                    '#StockNo',
                    stockId,
                    {
                        delay: 30
                    }
                );

                // -----------------------------------------
                // 5-5. 送出查詢
                // 找 submit button
                // -----------------------------------------
                const submitButton = await page.$(
                    'input[type="submit"]'
                );

                if (!submitButton) {
                    throw new Error('找不到查詢按鈕');
                }

                // 點擊後等待結果更新
                await Promise.all([
                    submitButton.click(),

                    page.waitForFunction(
                        () => {
                            const tables = document.querySelectorAll('table');

                            if (tables.length < 2) {
                                return false;
                            }

                            // 找尋包含「合　計」的 table
                            return Array.from(tables).some(table =>
                                table.innerText.includes('合　計') ||
                                table.innerText.includes('合計')
                            );
                        },
                        {
                            timeout: 30000
                        }
                    )
                ]);

                // -----------------------------------------
                // 5-6. 解析 TABLE
                // 只抓「合計」那一列
                // -----------------------------------------
                const result = await page.evaluate(() => {

                    const tables = Array.from(
                        document.querySelectorAll('table')
                    );

                    for (const table of tables) {

                        const rows = Array.from(
                            table.querySelectorAll('tr')
                        );

                        for (const row of rows) {

                            const cols = Array.from(
                                row.querySelectorAll('th, td')
                            )
                                .map(el => el.innerText.trim());

                            if (cols.length < 4) {
                                continue;
                            }

                            const levelName = String(cols[1] || '')
                                .replace(/\s/g, '');

                            if (
                                levelName === '合計' ||
                                levelName.includes('合計')
                            ) {
                                return {
                                    level: cols[0],
                                    name: cols[1],
                                    shareholder_count: cols[2],
                                    total_shares: cols[3],
                                    ratio: cols[4] || ''
                                };
                            }
                        }
                    }

                    return null;
                });

                // -----------------------------------------
                // 5-7. 驗證結果
                // -----------------------------------------
                if (!result) {
                    throw new Error(
                        `找不到 ${stockId} 的合計資料`
                    );
                }

                const shareholderCount = parseNumber(
                    result.shareholder_count
                );

                const totalShares = parseNumber(
                    result.total_shares
                );

                if (
                    shareholderCount <= 0 ||
                    totalShares <= 0
                ) {
                    throw new Error(
                        `資料異常 shareholder_count=${shareholderCount}, total_shares=${totalShares}`
                    );
                }

                results.push({
                    success: true,
                    stock_id: stockId,
                    trade_date: targetDate,
                    shareholder_count: shareholderCount,
                    total_shares: totalShares
                });

                log(
                    `✓ ${stockId} 成功 | 股東 ${shareholderCount.toLocaleString()} | 股數 ${totalShares.toLocaleString()}`
                );

            } catch (error) {

                log(
                    `✗ ${stockId} 失敗: ${error.message}`
                );

                results.push({
                    success: false,
                    stock_id: stockId,
                    trade_date: targetDate,
                    error: error.message
                });

                // 如果頁面發生異常，嘗試回到首頁
                try {

                    await page.goto(TDCC_URL, {
                        waitUntil: 'networkidle2',
                        timeout: 60000
                    });

                    await page.waitForSelector('#scaDate', {
                        timeout: 30000
                    });

                    await page.waitForSelector('#StockNo', {
                        timeout: 30000
                    });

                } catch (recoverError) {

                    log(
                        `頁面復原失敗: ${recoverError.message}`
                    );
                }
            }

            // ---------------------------------------------
            // 5-8. 查詢間隔
            // ---------------------------------------------
            if (i < stockIds.length - 1) {

                await randomDelay(800, 1500);
            }

            // 每 30 檔額外休息
            if (
                (i + 1) % 30 === 0 &&
                i < stockIds.length - 1
            ) {

                log('已完成 30 檔，暫停休息...');

                await randomDelay(5000, 10000);
            }
        }

        // -------------------------------------------------
        // 6. 關閉 Browser
        // -------------------------------------------------
        await browser.close();
        browser = null;

        // -------------------------------------------------
        // 7. stdout 只輸出 JSON
        // PHP json_decode($output, true) 才不會失敗
        // -------------------------------------------------
        process.stdout.write(
            JSON.stringify(results)
        );

    } catch (error) {

        log(`TDCC 批次程式錯誤: ${error.message}`);

        if (browser) {
            await browser.close();
        }

        // 即使整批失敗也輸出 JSON
        process.stdout.write(
            JSON.stringify({
                success: false,
                error: error.message
            })
        );

        process.exitCode = 1;
    }

})();