const puppeteer = require('puppeteer');
const fs = require('fs');

// =====================================================
// 設定
// =====================================================
const TDCC_URL = 'https://www.tdcc.com.tw/portal/zh/smWeb/qryStock';

// 每檔查詢完成後等待時間
const QUERY_DELAY = 1200;

// 查詢結果變化等待 timeout
const RESULT_TIMEOUT = 15000;

// =====================================================
// 工具
// =====================================================
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function log(message) {
    // 使用 stderr，避免污染 PHP shell_exec 的 JSON output
    console.error(message);
}

function normalizeText(value) {
    return String(value || '')
        .replace(/\s+/g, '')
        .trim();
}

// =====================================================
// 取得最後一個資料結果 Table
// =====================================================
async function getResultTableText(page) {
    return await page.evaluate(() => {
        const tables = Array.from(document.querySelectorAll('table'));

        // 找出包含「持股/單位數分級」的 table
        const resultTable = tables.find(table => {
            const text = table.innerText || '';
            return text.includes('持股/單位數分級') &&
                text.includes('人數') &&
                text.includes('股數/單位數');
        });

        return resultTable ? resultTable.innerText.trim() : '';
    });
}

// =====================================================
// 取得合計列
// =====================================================
async function getTotalRow(page) {
    return await page.evaluate(() => {
        const tables = Array.from(document.querySelectorAll('table'));

        const resultTable = tables.find(table => {
            const text = table.innerText || '';

            return text.includes('持股/單位數分級') &&
                text.includes('人數') &&
                text.includes('股數/單位數');
        });

        if (!resultTable) {
            return null;
        }

        const rows = Array.from(resultTable.querySelectorAll('tr'));

        for (const row of rows) {
            const cols = Array.from(row.querySelectorAll('th, td'))
                .map(cell => cell.innerText.trim());

            // 尋找「合計」列
            if (cols.length >= 4 && cols.some(value => {
                const text = String(value)
                    .replace(/\s+/g, '')
                    .replace(/　/g, '');

                return text === '合計';
            })) {
                return cols;
            }
        }

        return null;
    });
}

// =====================================================
// 等待查詢結果更新
// =====================================================
async function waitForResultChange(page, oldTableText, timeout = RESULT_TIMEOUT) {

    await page.waitForFunction(
        oldText => {
            const tables = Array.from(document.querySelectorAll('table'));

            const resultTable = tables.find(table => {
                const text = table.innerText || '';

                return text.includes('持股/單位數分級') &&
                    text.includes('人數') &&
                    text.includes('股數/單位數');
            });

            if (!resultTable) {
                return false;
            }

            const newText = resultTable.innerText.trim();

            // Table 必須有內容
            if (!newText) {
                return false;
            }

            // 與查詢前內容不同才算完成
            return newText !== oldText;

        },
        {
            timeout
        },
        oldTableText
    );
}

// =====================================================
// 主程式
// =====================================================
(async () => {

    let browser = null;

    try {

        // -------------------------------------------------
        // 讀取參數
        // -------------------------------------------------
        const taskFile = process.argv[2];

        if (!taskFile) {
            throw new Error('缺少 task JSON 檔案參數');
        }

        if (!fs.existsSync(taskFile)) {
            throw new Error(`找不到 task 檔案: ${taskFile}`);
        }

        const task = JSON.parse(
            fs.readFileSync(taskFile, 'utf8')
        );

        const targetDate = String(task.targetDate || '');
        const stockIds = Array.isArray(task.stock_id_array)
            ? task.stock_id_array.map(String)
            : [];

        if (!targetDate) {
            throw new Error('task.targetDate 不存在');
        }

        if (stockIds.length === 0) {
            throw new Error('stock_id_array 沒有股票');
        }

        log('');
        log('=====================================================');
        log('');
        log('               TDCC 批次查詢開始');
        log('');
        log(`資料日期: ${targetDate}`);
        log(`股票數量: ${stockIds.length}`);
        log('');
        log('=====================================================');
        log('');

        // -------------------------------------------------
        // 啟動 Browser
        // -------------------------------------------------
        log(`正在開啟 TDCC: ${TDCC_URL}`);

        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage'
            ]
        });

        const page = await browser.newPage();

        await page.setViewport({
            width: 1440,
            height: 900
        });

        await page.goto(TDCC_URL, {
            waitUntil: 'networkidle2',
            timeout: 60000
        });

        log('TDCC 頁面載入完成');

        // -------------------------------------------------
        // 等待必要 DOM
        // -------------------------------------------------
        await page.waitForSelector('#scaDate', {
            timeout: 15000
        });

        await page.waitForSelector('#StockNo', {
            timeout: 15000
        });

        // -------------------------------------------------
        // 設定日期
        // -------------------------------------------------
        const dateExists = await page.evaluate((targetDate) => {

            const select = document.querySelector('#scaDate');

            if (!select) {
                return false;
            }

            return Array.from(select.options)
                .some(option => option.value === targetDate);

        }, targetDate);

        if (!dateExists) {
            throw new Error(
                `TDCC 日期選單不存在資料日期: ${targetDate}`
            );
        }

        await page.select('#scaDate', targetDate);

        log(`確認資料日期存在: ${targetDate}`);
        log('');

        // -------------------------------------------------
        // 確認股票代號查詢 Radio
        // -------------------------------------------------
        const stockRadio = await page.$('#StockNo');

        if (stockRadio) {

            const checked = await page.$eval(
                '#StockNo',
                el => el.checked
            );

            if (!checked) {
                await page.click('#StockNo');
            }

        } else {

            // 如果 radio selector 不存在
            // 仍然可以繼續，因為某些版本可能預設 StockNo
            log('⚠ 找不到 #StockNo，使用預設查詢模式');

        }

        // -------------------------------------------------
        // 查詢結果
        // -------------------------------------------------
        const results = [];

        for (let i = 0; i < stockIds.length; i++) {

            const stockId = stockIds[i];

            log(`[${i + 1}/${stockIds.length}] 查詢 ${stockId}`);

            try {

                // -----------------------------------------
                // 記錄查詢前的 Table
                // -----------------------------------------
                const oldTableText = await getResultTableText(page);

                // -----------------------------------------
                // 確保日期正確
                // -----------------------------------------
                await page.select('#scaDate', targetDate);

                // -----------------------------------------
                // 確保 StockNo radio 已選取
                // -----------------------------------------
                const radioExists = await page.$('#StockNo');

                if (radioExists) {

                    const checked = await page.$eval(
                        '#StockNo',
                        el => el.checked
                    );

                    if (!checked) {
                        await page.click('#StockNo');
                    }
                }

                // -----------------------------------------
                // 清空股票代號
                // -----------------------------------------
                await page.click('#StockNo', {
                    clickCount: 3
                });

                await page.keyboard.press('Backspace');

                // -----------------------------------------
                // 輸入股票代號
                // -----------------------------------------
                await page.type(
                    '#StockNo',
                    stockId,
                    {
                        delay: 50
                    }
                );

                // 確認輸入值
                const inputValue = await page.$eval(
                    '#StockNo',
                    el => el.value
                );

                if (inputValue !== stockId) {
                    throw new Error(
                        `股票代號輸入失敗，預期 ${stockId}，實際 ${inputValue}`
                    );
                }

                // -----------------------------------------
                // 找查詢按鈕
                // -----------------------------------------
                const submitButton = await page.$('input[type="submit"]');

                if (!submitButton) {
                    throw new Error('找不到查詢按鈕');
                }

                // -----------------------------------------
                // 點擊查詢
                // -----------------------------------------
                await submitButton.click();

                // -----------------------------------------
                // 等待結果 Table 更新
                // -----------------------------------------
                await waitForResultChange(
                    page,
                    oldTableText,
                    RESULT_TIMEOUT
                );

                // -----------------------------------------
                // 額外等待 DOM 穩定
                // -----------------------------------------
                await sleep(800);

                // -----------------------------------------
                // 抓取合計列
                // -----------------------------------------
                const totalRow = await getTotalRow(page);

                if (!totalRow) {
                    throw new Error('找不到「合計」資料列');
                }

                if (totalRow.length < 4) {
                    throw new Error(
                        `合計列欄位數不足: ${JSON.stringify(totalRow)}`
                    );
                }

                /*
                 * TDCC 格式：
                 *
                 * [0] 序
                 * [1] 持股/單位數分級
                 * [2] 人數
                 * [3] 股數/單位數
                 * [4] 占集保庫存數比例
                 */

                const shareholderCount = parseInt(
                    String(totalRow[2]).replace(/,/g, ''),
                    10
                );

                const totalShares = parseInt(
                    String(totalRow[3]).replace(/,/g, ''),
                    10
                );

                // -----------------------------------------
                // 驗證資料
                // -----------------------------------------
                if (
                    !Number.isFinite(shareholderCount) ||
                    shareholderCount <= 0
                ) {
                    throw new Error(
                        `股東人數異常: ${totalRow[2]}`
                    );
                }

                if (
                    !Number.isFinite(totalShares) ||
                    totalShares <= 0
                ) {
                    throw new Error(
                        `股數異常: ${totalRow[3]}`
                    );
                }

                // -----------------------------------------
                // 成功結果
                // -----------------------------------------
                results.push({
                    success: true,
                    stock_id: stockId,
                    trade_date: targetDate,
                    shareholder_count: shareholderCount,
                    total_shares: totalShares
                });

                log(
                    `✓ ${stockId} 成功 | ` +
                    `股東 ${shareholderCount.toLocaleString()} | ` +
                    `股數 ${totalShares.toLocaleString()}`
                );

            } catch (error) {

                results.push({
                    success: false,
                    stock_id: stockId,
                    trade_date: targetDate,
                    error: error.message
                });

                log(
                    `✗ ${stockId} 失敗: ${error.message}`
                );
            }

            // -------------------------------------------------
            // 下一檔前等待
            // -------------------------------------------------
            if (i < stockIds.length - 1) {
                await sleep(QUERY_DELAY);
            }
        }

        // -------------------------------------------------
        // 關閉 Browser
        // -------------------------------------------------
        await browser.close();
        browser = null;

        // -------------------------------------------------
        // stdout 只輸出 JSON
        // PHP json_decode(shell_exec()) 才不會失敗
        // -------------------------------------------------
        process.stdout.write(
            JSON.stringify(results)
        );

    } catch (error) {

        if (browser) {
            await browser.close();
        }

        // 發生整體錯誤仍輸出 JSON
        process.stdout.write(
            JSON.stringify({
                success: false,
                error: error.message
            })
        );

        log(`批次程式錯誤: ${error.message}`);

        process.exitCode = 1;
    }

})();