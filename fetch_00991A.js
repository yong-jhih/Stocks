const puppeteer = require('puppeteer');
const fs = require('fs');
(async () => {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const date = `${yyyy}/${mm}/${dd}`;
    const url = `https://www.fhtrust.com.tw/api/assets?fundID=ETF23&qDate=${date}`;
    console.log(url);
    const res = await fetch(url);
    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }
    const json = await res.json();
    fs.writeFileSync('etf_componet_00991A.json', JSON.stringify(json, null, 2), 'utf8');
    console.log('完成');
})();