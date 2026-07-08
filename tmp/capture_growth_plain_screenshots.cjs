const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const base = 'http://localhost:18080/geo_admin';
const outDir = 'D:/Project Files/GEOFlow/docs/manual-screenshots';

const targets = [
  ['08-增长中心-总览.png', '/analytics'],
  ['08-增长中心-创建线索表单.png', '/lead-forms/create'],
  ['08-增长中心-表单管理.png', '/lead-forms'],
  ['08-增长中心-线索收件箱.png', '/leads'],
];

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 }, deviceScaleFactor: 1 });
  page.setDefaultTimeout(20000);

  await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="username"]', 'test');
  await page.fill('input[name="password"]', '123123123');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/geo_admin\/(dashboard|login)/);
  if (page.url().includes('/login')) throw new Error('login failed');

  for (const [fileName, route] of targets) {
    await page.goto(`${base}${route}`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('body');
    await page.waitForTimeout(900);
    await page.screenshot({ path: path.join(outDir, fileName), fullPage: false });
    console.log(fileName);
  }

  await browser.close();
})();
