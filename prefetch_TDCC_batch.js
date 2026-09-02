const puppeteer = require('puppeteer');
const fs = require('fs');

// =====================================================
// 設定
// =====================================================
const TDCC_URL = 'https://www.tdcc.com.tw/portal/zh/smWeb/qryStock';

// 每檔查詢後等待
const QUERY_DELAY_MIN = 1000;
const QUERY_DELAY_MAX = 1800;

// 等待結果更新 timeout
const RESULT_TIMEOUT = 15000;

// 單檔最多嘗試次數
const MAX_RETRY = 3;

// 每 N 檔主動重新載入 TDCC 頁面
const PAGE_REFRESH_INTERVAL = 300;

// 連續錯誤幾次後重啟 Browser
const MAX_CONSECUTIVE_ERRORS = 3;

// Browser 啟動 timeout
const PAGE_LOAD_TIMEOUT = 60000;

// =====================================================
// 工具
// =====================================================
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function randomDelay(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function log(message) {
    console.error(message);
}

// =====================================================
// 取得結果 Table 文字
// =====================================================
async function getResultTableText(page) {
    return await page.evaluate(() => {
        const tables = Array.from(document.querySelectorAll('table'));
        const resultTable = tables.find(table => {
            const text = table.innerText || '';
            return (
                text.includes('持股/單位數分級') &&
                text.includes('人數') &&
                text.includes('股數/單位數')
            );
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
            return (
                text.includes('持股/單位數分級') &&
                text.includes('人數') &&
                text.includes('股數/單位數')
            );
        });
        if (!resultTable) return null;
        const rows = Array.from(resultTable.querySelectorAll('tr'));
        for (const row of rows) {
            const cols = Array.from(row.querySelectorAll('th, td')).map(cell => cell.innerText.trim());
            const isTotal = cols.some(value => {
                const text = String(value).replace(/\s+/g, '').replace(/　/g, '');
                return text === '合計';
            });
            if (cols.length >= 4 && isTotal) return cols;
        }
        return null;
    });
}

// =====================================================
// 等待結果 Table 更新
// =====================================================
async function waitForResultChange(page, oldTableText, timeout = RESULT_TIMEOUT) {
    await page.waitForFunction(
        oldText => {
            const tables = Array.from(document.querySelectorAll('table'));
            const resultTable = tables.find(table => {
                const text = table.innerText || '';
                return (
                    text.includes('持股/單位數分級') &&
                    text.includes('人數') &&
                    text.includes('股數/單位數')
                );
            });
            if (!resultTable) return false;
            const newText = resultTable.innerText.trim();
            if (!newText) return false;
            return newText !== oldText;
        },
        { timeout }, oldTableText
    );
}

// =====================================================
// 建立 Browser
// =====================================================
async function createBrowser() {
    log('正在啟動 Browser...');
    const browser = await puppeteer.launch({
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage'
        ]
    });
    return browser;
}

// =====================================================
// 初始化 TDCC Page
// =====================================================
async function initializePage(browser, targetDate) {
    log(`正在開啟 TDCC: ${TDCC_URL}`);
    const pages = await browser.pages();
    // 關閉多餘 Page
    for (let i = 1; i < pages.length; i++) {
        try {
            await pages[i].close();
        } catch (e) { }
    }
    const page = pages[0];
    await page.setViewport({ width: 1440, height: 900 });
    page.setDefaultTimeout(20000);
    await page.goto(TDCC_URL, { waitUntil: 'networkidle2', timeout: PAGE_LOAD_TIMEOUT });
    log('TDCC 頁面載入完成');

    // ---------------------------------------------
    // 等待必要 DOM
    // ---------------------------------------------
    await page.waitForSelector('#scaDate', { timeout: 20000 });
    await page.waitForSelector('#StockNo', { timeout: 20000 });

    // ---------------------------------------------
    // 確認日期存在
    // ---------------------------------------------
    const dateExists = await page.evaluate(
        targetDate => {
            const select = document.querySelector('#scaDate');
            if (!select) return false;
            return Array.from(select.options).some(option => option.value === targetDate);
        }, targetDate
    );
    if (!dateExists) throw new Error(`TDCC 日期選單不存在資料日期: ${targetDate}`);
    await page.select('#scaDate', targetDate);

    // ---------------------------------------------
    // 確認 StockNo 查詢模式
    // ---------------------------------------------
    const radioExists = await page.$('#sqlStockNo');
    if (radioExists) {
        const checked = await page.$eval('#sqlStockNo', el => el.checked);
        if (!checked) await page.click('#sqlStockNo');
    }
    log(`TDCC 初始化完成 | 日期: ${targetDate}`);
    return page;
}

// =====================================================
// Page 健康檢查
// =====================================================
async function isPageHealthy(page) {
    try {
        if (!page) return false;
        if (page.isClosed()) return false;
        const scaDateExists = await page.$('#scaDate');
        const stockNoExists = await page.$('#StockNo');
        return !!(scaDateExists && stockNoExists);
    } catch (e) {
        return false;
    }
}

// =====================================================
// 重新建立 Page
// =====================================================
async function recoverPage(browser, page, targetDate) {
    log('');
    log('🔄 開始恢復 TDCC Page...');
    try {
        if (page && !page.isClosed()) {
            log('重新載入 TDCC 頁面...');
            await page.goto(TDCC_URL, { waitUntil: 'networkidle2', timeout: PAGE_LOAD_TIMEOUT });
            const healthy = await isPageHealthy(page);
            if (healthy) {
                await page.select('#scaDate', targetDate);
                const radioExists = await page.$('#sqlStockNo');
                if (radioExists) {
                    const checked = await page.$eval('#sqlStockNo', el => el.checked);
                    if (!checked) await page.click('#sqlStockNo');
                }
                log('✓ Page 恢復成功');
                return page;
            }
        }
    } catch (error) {
        log(`⚠ Page reload 失敗: ${error.message}`);
    }

    // ---------------------------------------------
    // Page 無法恢復
    // 建立新的 Page
    // ---------------------------------------------
    log('重新建立 TDCC Page...');
    try {
        if (page && !page.isClosed()) await page.close();
    } catch (e) { }
    return await initializePage(browser, targetDate);
}

// =====================================================
// 重啟 Browser
// =====================================================
async function restartBrowser(browser, targetDate) {
    log('');
    log('=====================================================');
    log('🔴 TDCC Browser 發生連續錯誤');
    log('🔄 正在重新啟動 Browser');
    log('=====================================================');
    try {
        if (browser) await browser.close();
    } catch (e) {
        log(`關閉舊 Browser 時發生錯誤: ${e.message}`);
    }
    await sleep(3000);
    const newBrowser = await createBrowser();
    const newPage = await initializePage(newBrowser, targetDate);
    log('✓ Browser 重新啟動完成');
    log('');
    return { browser: newBrowser, page: newPage };
}

// =====================================================
// 查詢單一股票
// =====================================================
async function queryStock(page, stockId, targetDate) {
    // ---------------------------------------------
    // Page 健康檢查
    // ---------------------------------------------
    const healthy = await isPageHealthy(page);
    if (!healthy) throw new Error('TDCC Page 不健康，必要 DOM 不存在');

    // ---------------------------------------------
    // 記錄舊結果
    // ---------------------------------------------
    const oldTableText = await getResultTableText(page);

    // ---------------------------------------------
    // 日期
    // ---------------------------------------------
    await page.select('#scaDate', targetDate);

    // ---------------------------------------------
    // 股票代號查詢模式
    // ---------------------------------------------
    const radioExists = await page.$('#sqlStockNo');
    if (radioExists) {
        const checked = await page.$eval('#sqlStockNo', el => el.checked);
        if (!checked) await page.click('#sqlStockNo');
    }

    // ---------------------------------------------
    // 清空股票代號
    // ---------------------------------------------
    await page.click('#StockNo', { clickCount: 3 });
    await page.keyboard.press('Backspace');

    // ---------------------------------------------
    // 輸入股票代號
    // ---------------------------------------------
    await page.type('#StockNo', stockId, { delay: 50 });

    // ---------------------------------------------
    // 驗證輸入
    // ---------------------------------------------
    const inputValue = await page.$eval('#StockNo', el => el.value);
    if (inputValue !== stockId) throw new Error(`股票代號輸入失敗，預期 ${stockId}，實際 ${inputValue}`);

    // ---------------------------------------------
    // 查詢
    // ---------------------------------------------
    const submitButton = await page.$('input[type="submit"]');
    if (!submitButton) throw new Error('找不到查詢按鈕');
    await submitButton.click();

    // ---------------------------------------------
    // 等待結果更新
    // ---------------------------------------------
    await waitForResultChange(page, oldTableText, RESULT_TIMEOUT);

    // 額外等待 render 完成
    await sleep(800);
    // ---------------------------------------------
    // 取得合計
    // ---------------------------------------------
    const totalRow = await getTotalRow(page);
    if (!totalRow) throw new Error('找不到「合計」資料列');
    if (totalRow.length < 4) throw new Error(`合計列欄位數不足: ${JSON.stringify(totalRow)}`);

    // TDCC:
    // [0] 序
    // [1] 持股/單位數分級
    // [2] 人數
    // [3] 股數
    // [4] 比例
    const shareholderCount = parseInt(String(totalRow[2]).replace(/,/g, ''), 10);
    const totalShares = parseInt(String(totalRow[3]).replace(/,/g, ''), 10);

    // ---------------------------------------------
    // 驗證資料
    // ---------------------------------------------
    if (!Number.isFinite(shareholderCount) || shareholderCount <= 0) throw new Error(`股東人數異常: ${totalRow[2]}`);

    if (!Number.isFinite(totalShares) || totalShares <= 0) throw new Error(`股數異常: ${totalRow[3]}`);
    return { shareholder_count: shareholderCount, total_shares: totalShares };
}

// =====================================================
// 主程式
// =====================================================
(async () => {
    let browser = null;
    let page = null;
    try {
        // ---------------------------------------------
        // 讀取 Task
        // ---------------------------------------------
        const taskFile = process.argv[2];
        if (!taskFile) throw new Error('缺少 task JSON 檔案參數');
        if (!fs.existsSync(taskFile)) throw new Error(`找不到 task 檔案: ${taskFile}`);
        const task = JSON.parse(fs.readFileSync(taskFile, 'utf8'));
        const targetDate = String(task.targetDate || '');
        const stockIds = Array.isArray(task.stock_id_array) ? task.stock_id_array.map(String) : [];
        if (!targetDate) throw new Error('task.targetDate 不存在');
        if (stockIds.length === 0) throw new Error('stock_id_array 沒有股票');

        // ---------------------------------------------
        // 顯示資訊
        // ---------------------------------------------
        log('');
        log('=====================================================');
        log('');
        log('               TDCC 批次查詢開始');
        log('');
        log(`資料日期: ${targetDate}`);
        log(`股票數量: ${stockIds.length}`);
        log(`每 ${PAGE_REFRESH_INTERVAL} 檔自動重新載入頁面`);
        log(`單檔最多 Retry: ${MAX_RETRY} 次`);
        log('');
        log('=====================================================');
        log('');

        // ---------------------------------------------
        // 初始化 Browser / Page
        // ---------------------------------------------
        browser = await createBrowser();
        page = await initializePage(browser, targetDate);
        const results = [];
        let consecutiveErrors = 0;

        // ---------------------------------------------
        // 開始批次
        // ---------------------------------------------
        for (let i = 0; i < stockIds.length; i++) {
            const stockId = stockIds[i];
            // =============================================
            // 定期重新載入 Page
            // =============================================
            if (i > 0 && i % PAGE_REFRESH_INTERVAL === 0) {
                log('');
                log('-----------------------------------------------------');
                log(`🔄 已完成 ${i} 檔，主動重新載入 TDCC`);
                log('-----------------------------------------------------');
                try {
                    page = await recoverPage(browser, page, targetDate);
                    await sleep(2000);
                } catch (error) {
                    log(`⚠ Page refresh 失敗: ${error.message}`);
                    const restarted = await restartBrowser(browser, targetDate);
                    browser = restarted.browser;
                    page = restarted.page;
                }
                log('');
            }

            // =============================================
            // 顯示進度
            // =============================================
            log(`[${i + 1}/${stockIds.length}] 查詢 ${stockId}`);
            let success = false;
            let lastError = null;

            // =============================================
            // 單檔 Retry
            // =============================================
            for (let retry = 1; retry <= MAX_RETRY; retry++) {
                try {
                    // -------------------------------------
                    // 如果不是第一次
                    // 先恢復 Page
                    // -------------------------------------
                    if (retry > 1) {
                        log(`  ↻ Retry ${retry}/${MAX_RETRY}`);
                        await sleep(2000 * retry);
                        page = await recoverPage(browser, page, targetDate);
                    }

                    // -------------------------------------
                    // 查詢
                    // -------------------------------------
                    const data = await queryStock(page, stockId, targetDate);
                    results.push({
                        success: true,
                        stock_id: stockId,
                        trade_date: targetDate,
                        shareholder_count: data.shareholder_count,
                        total_shares: data.total_shares
                    });
                    log(
                        `✓ ${stockId} 成功 | ` +
                        `股東 ${data.shareholder_count.toLocaleString()} | ` +
                        `股數 ${data.total_shares.toLocaleString()}`
                    );
                    success = true;
                    consecutiveErrors = 0;
                    break;
                } catch (error) {
                    lastError = error;
                    log(`  ✗ 第 ${retry} 次失敗: ${error.message}`);
                }
            }

            // =============================================
            // 最終失敗
            // =============================================
            if (!success) {
                consecutiveErrors++;
                results.push({
                    success: false,
                    stock_id: stockId,
                    trade_date: targetDate,
                    error: lastError ? lastError.message : '未知錯誤'
                });
                log(`✗ ${stockId} 最終失敗`);

                // -----------------------------------------
                // 連續錯誤
                // 重啟 Browser
                // -----------------------------------------
                if (consecutiveErrors >= MAX_CONSECUTIVE_ERRORS) {
                    log('');
                    log(`⚠ 已連續 ${consecutiveErrors} 檔失敗`);
                    try {
                        const restarted = await restartBrowser(browser, targetDate);
                        browser = restarted.browser;
                        page = restarted.page;
                        consecutiveErrors = 0;
                    } catch (restartError) {
                        log(`🔴 Browser 重啟失敗: ${restartError.message}`);
                    }
                }
            }

            // =============================================
            // 下一檔等待
            // =============================================
            if (i < stockIds.length - 1) {
                const delay = randomDelay(QUERY_DELAY_MIN, QUERY_DELAY_MAX);
                await sleep(delay);
            }
        }

        // ---------------------------------------------
        // 關閉 Browser
        // ---------------------------------------------
        if (browser) {
            await browser.close();
            browser = null;
        }

        // ---------------------------------------------
        // stdout JSON
        // ---------------------------------------------
        process.stdout.write(JSON.stringify(results));
    } catch (error) {
        try {
            if (browser) await browser.close();
        } catch (e) { }
        process.stdout.write(JSON.stringify({ success: false, error: error.message }));
        log(`批次程式錯誤: ${error.message}`);
        process.exitCode = 1;
    }
})();