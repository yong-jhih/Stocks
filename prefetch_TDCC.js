const puppeteer = require('puppeteer');

(async () => {
    const stock_id = process.argv[2];
    const url = 'https://www.tdcc.com.tw/portal/zh/smWeb/qryStock';
    // console.log(`正在開啟網頁: ${url}`);
    const browser = await puppeteer.launch({
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox'
        ]
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({
            width: 1440,
            height: 1000
        });

        // =====================================================
        // 1. 開啟 TDCC 頁面
        // =====================================================
        // console.log('正在載入頁面...');
        await page.goto(url, {
            waitUntil: 'networkidle2',
            timeout: 60000
        });
        // console.log('頁面載入完成');

        // =====================================================
        // 2. 等待查詢表單
        // =====================================================
        await page.waitForSelector('#StockNo', {
            timeout: 30000
        });

        // =====================================================
        // 3. 取得最新日期
        // =====================================================
        // const latestDate = await page.$eval(
        //     '#scaDate option:first-child',
        //     el => el.value
        // );
        // console.log(`最新資料日期: ${latestDate}`);
        const latestDate = process.argv[3];

        // =====================================================
        // 4. 確認日期選單
        // =====================================================
        await page.select('#scaDate', latestDate);

        // =====================================================
        // 5. 選擇「證券代號」
        // =====================================================
        // await page.check('#StockNo');

        // =====================================================
        // 6. 輸入 2330
        // =====================================================
        await page.click('#StockNo');
        await page.type('#StockNo', stock_id);
        // console.log('證券代號: 2330');

        // =====================================================
        // 7. 查詢
        // =====================================================
        // console.log('正在送出查詢...');
        await Promise.all([
            page.waitForNavigation({
                waitUntil: 'networkidle2',
                timeout: 60000
            }).catch(() => {
                // 某些情況可能不是傳統 navigation
            }),
            page.click('input[type="submit"]')
        ]);

        // =====================================================
        // 8. 等待結果
        // =====================================================
        await new Promise(resolve => setTimeout(resolve, 2000));
        // console.log('查詢完成');

        // =====================================================
        // 9. 印出目前頁面的所有 TABLE
        // =====================================================
        const tables = await page.evaluate(() => {
            const tableList = Array.from(
                document.querySelectorAll('table')
            );
            return tableList.map((table, tableIndex) => {
                const rows = Array.from(
                    table.querySelectorAll('tr')
                );
                return {
                    tableIndex,
                    rows: rows.map((row, rowIndex) => {
                        const cells = Array.from(
                            row.querySelectorAll('th, td')
                        );
                        return {
                            rowIndex,
                            cells: cells.map((cell, cellIndex) => ({
                                cellIndex,
                                tag: cell.tagName.toLowerCase(),
                                text: cell.innerText.trim()
                            }))
                        };
                    })
                };
            });
        });

        // =====================================================
        // 10. 顯示結果
        // =====================================================
        // console.log('\n');
        // console.log('=====================================================');
        // console.log('               TDCC 查詢結果 TABLE');
        // console.log('=====================================================');
        // console.log(`共找到 ${tables.length} 個 TABLE`);

        tables.forEach(table => {
            // console.log('\n');
            // console.log(`**************** TABLE #${table.tableIndex} ****************`);
            table.rows.forEach(row => {
                const values = row.cells.map(cell => cell.text);
                if (values[1] === '合　計') console.log(JSON.stringify(values));
            });
        });

        // =====================================================
        // 11. 另外把最終頁面上的純文字印出
        //     方便確認查詢是否真的成功
        // =====================================================
        const bodyText = await page.evaluate(() => {
            return document.body.innerText;
        });
        // console.log('\n');
        // console.log('=====================================================');
        // console.log('                 頁面文字');
        // console.log('=====================================================');
        // console.log(bodyText);
    } catch (error) {
        // console.error('\n=====================================================');
        // console.error('抓取失敗');
        // console.error('=====================================================');
        // console.error(error);
        console.log(JSON.stringify([]));
    } finally {
        await browser.close();
        // console.log('\n瀏覽器已關閉');
    }
})();