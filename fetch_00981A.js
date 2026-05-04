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
        await page.waitForSelector('#DataAsset');
        const jsonData = await page.evaluate(() => {
            const element = document.querySelector('#DataAsset');
            if (!element) return null;
            const rawContent = element.getAttribute('data-content');
            try {
                const obj = eval(rawContent);
                return JSON.stringify(obj);
            } catch (e) {
                return null;
            }
        });
        if (jsonData) {
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