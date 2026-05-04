const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const url = 'https://www.ezmoney.com.tw/ETF/Fund/Info?fundCode=49YTW';

    // 啟動瀏覽器
    const browser = await puppeteer.launch({
        headless: "new",
        args: ['--no-sandbox', '--disable-setuid-sandbox'] // GitHub Actions 環境必需
    });

    const page = await browser.newPage();

    // 設置隨機 User-Agent 增加真實性
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

    try {
        console.log(`正在開啟網頁: ${url}`);
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });

        // 稍微等待一下確保 JavaScript 渲染完成
        await new Promise(r => setTimeout(r, 3000));

        // 取得渲染後的 HTML
        const html = await page.content();

        // 寫入暫存檔
        fs.writeFileSync('temp_source.html', html);
        console.log('成功儲存原始碼至 temp_source.html');

    } catch (error) {
        console.error('抓取失敗:', error);
        process.exit(1);
    } finally {
        await browser.close();
    }
})();