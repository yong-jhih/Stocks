// const puppeteer = require('puppeteer');
// const fs = require('fs');

// (async () => {
//     const url = 'https://www.ezmoney.com.tw/ETF/Fund/Info?fundCode=49YTW';

//     // 啟動瀏覽器
//     const browser = await puppeteer.launch({
//         headless: "new",
//         args: ['--no-sandbox', '--disable-setuid-sandbox'] // GitHub Actions 環境必需
//     });

//     const page = await browser.newPage();

//     // 設置隨機 User-Agent 增加真實性
//     await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

//     try {
//         console.log(`正在開啟網頁: ${url}`);
//         await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });

//         // 稍微等待一下確保 JavaScript 渲染完成
//         await new Promise(r => setTimeout(r, 3000));

//         // 取得渲染後的 HTML
//         const html = await page.content();

//         // 寫入暫存檔
//         fs.writeFileSync('temp_source.html', html);
//         console.log('成功儲存原始碼至 temp_source.html');

//     } catch (error) {
//         console.error('抓取失敗:', error);
//         process.exit(1);
//     } finally {
//         await browser.close();
//     }
// })();

const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const url = 'https://www.ezmoney.com.tw/ETF/Fund/Info?fundCode=49YTW';

    const browser = await puppeteer.launch({
        headless: "new",
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

    try {
        console.log(`正在開啟網頁: ${url}`);
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });

        // 等待目標 div 出現，確保資料已載入
        await page.waitForSelector('#DataAsset');

        // 在瀏覽器環境中直接解析與格式化
        const jsonData = await page.evaluate(() => {
            const element = document.querySelector('#DataAsset');
            if (!element) return null;

            const rawContent = element.getAttribute('data-content');

            try {
                // 關鍵點：在瀏覽器內利用 eval 或是 Function 將 JS Object 字串轉為真正的 JS 物件
                // 然後直接轉成標準 JSON 字串
                const obj = eval(rawContent);
                return JSON.stringify(obj);
            } catch (e) {
                return null;
            }
        });

        if (jsonData) {
            // 直接儲存為標準 JSON 檔
            fs.writeFileSync('stock_data.json', jsonData);
            console.log('成功產出標準 JSON：stock_data.json');
        } else {
            console.error('找不到資料或解析失敗');
        }

    } catch (error) {
        console.error('抓取失敗:', error);
        process.exit(1);
    } finally {
        await browser.close();
    }
})();