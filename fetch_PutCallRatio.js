const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const dateStr = `${yyyy}/${mm}/${dd}`;
    const url = 'https://www.taifex.com.tw/cht/3/pcRatio';
    console.log(`正在開啟網頁: ${url}`);
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    await page.goto(url, { waitUntil: 'networkidle2' });
    await page.waitForSelector('table');
    const data = await page.evaluate(() => {
        const rows = Array.from(document.querySelectorAll('table tr'));
        const result = [];
        rows.forEach((row, idx) => {
            const cols = Array.from(row.querySelectorAll('td')).map(td => td.innerText.trim());
            if (cols.length === 0) return;
            if (idx === 0) return;
            result.push({
                date: cols[0],
                call_volume: cols[1],
                put_volume: cols[2],
                volume_pcr: cols[3],
                call_oi: cols[4],
                put_oi: cols[5],
                oi_pcr: cols[6]
            });
        });
        return result;
    });
    await browser.close();
    const outputFile = `pc_ratio_${yyyy}${mm}${dd}.json`;
    fs.writeFileSync(outputFile, JSON.stringify(data, null, 2), 'utf8');
    console.log(`成功產出 JSON：${outputFile}`);
})();