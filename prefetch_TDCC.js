const puppeteer = require('puppeteer');

(async () => {
    const url = 'https://www.tdcc.com.tw/portal/zh/smWeb/qryStock';

    console.log(`正在開啟網頁: ${url}`);

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

        console.log('正在載入頁面...');

        await page.goto(url, {
            waitUntil: 'networkidle2',
            timeout: 60000
        });

        console.log('頁面載入完成');

        // -----------------------------------------------------
        // 先等主要查詢區塊出現
        // -----------------------------------------------------
        await page.waitForSelector('input', {
            timeout: 30000
        });

        // -----------------------------------------------------
        // 印出目前頁面上的 input / select
        // 方便確認 TDCC 實際欄位名稱
        // -----------------------------------------------------
        const formElements = await page.evaluate(() => {

            const inputs = Array.from(document.querySelectorAll('input')).map((el, index) => ({
                type: el.type,
                name: el.name,
                id: el.id,
                value: el.value,
                placeholder: el.placeholder,
                index
            }));

            const selects = Array.from(document.querySelectorAll('select')).map((el, index) => ({
                name: el.name,
                id: el.id,
                value: el.value,
                options: Array.from(el.options).map(option => ({
                    text: option.text.trim(),
                    value: option.value
                })),
                index
            }));

            return {
                inputs,
                selects
            };
        });

        console.log('\n================ INPUT =================');
        console.table(formElements.inputs);

        console.log('\n================ SELECT =================');
        console.dir(formElements.selects, {
            depth: null
        });

        // -----------------------------------------------------
        // 找日期下拉選單
        // -----------------------------------------------------
        const dateSelectInfo = await page.evaluate(() => {

            const selects = Array.from(document.querySelectorAll('select'));

            return selects.map((select, index) => ({
                index,
                name: select.name,
                id: select.id,
                options: Array.from(select.options).map(option => ({
                    text: option.text.trim(),
                    value: option.value
                }))
            }));
        });

        console.log('\n================ 日期選單 =================');

        dateSelectInfo.forEach(item => {
            console.log(`\nSELECT #${item.index}`);
            console.log(`name: ${item.name}`);
            console.log(`id: ${item.id}`);

            console.table(item.options);
        });

        // -----------------------------------------------------
        // 找「證券代號」input
        // -----------------------------------------------------
        const stockInputInfo = await page.evaluate(() => {

            const inputs = Array.from(document.querySelectorAll('input'));

            return inputs.map((input, index) => ({
                index,
                type: input.type,
                name: input.name,
                id: input.id,
                placeholder: input.placeholder,
                value: input.value
            }));
        });

        console.log('\n================ 證券代號 INPUT =================');
        console.table(stockInputInfo);

        // =====================================================
        // 下面先不直接假設欄位名稱
        // =====================================================
        //
        // 因為 TDCC 這個頁面的欄位可能會隨前端版本調整，
        // 我們第一步先把實際 DOM 結構確認清楚。
        //
        // 確認後再鎖定：
        //
        // 1. 最新日期 select
        // 2. 證券代號 input
        // 3. 查詢 button
        // 4. 結果 table
        //
        // =====================================================

        console.log('\n目前先完成頁面 DOM 偵測。');
        console.log('請確認上面 INPUT / SELECT 的輸出結果。');

    } catch (error) {

        console.error('\n抓取失敗：');
        console.error(error);

    } finally {

        await browser.close();

        console.log('\n瀏覽器已關閉');
    }

})();