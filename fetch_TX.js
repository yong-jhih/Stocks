const puppeteer = require('puppeteer');
const fs = require('fs');
(async () => {
    // const today = new Date();
    // const yyyy = today.getFullYear();
    // const mm = String(today.getMonth() + 1).padStart(2, '0');
    // const dd = String(today.getDate()).padStart(2, '0');
    // const date = `${yyyy}/${mm}/${dd}`;
    const url = `https://openapi.taifex.com.tw/v1/MarketDataOfMajorInstitutionalTradersDetailsOfFuturesContractsBytheDate`;
    console.log(`正在開啟網頁: ${url}`);
    const res = await fetch(url);
    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }
    const json = await res.json();
    fs.writeFileSync('TX.json', JSON.stringify(json, null, 2), 'utf8');
    console.log('成功產出標準 JSON：TX.json');
})();