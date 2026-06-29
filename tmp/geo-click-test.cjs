const { chromium } = require('playwright');

const baseUrl = 'http://127.0.0.1:18080';
const adminBase = `${baseUrl}/geo_admin`;
const articleTitle = 'Playwright GEO integration article';

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const messages = [];
  page.on('console', (message) => messages.push(`${message.type()}: ${message.text()}`));
  page.on('pageerror', (error) => messages.push(`pageerror: ${error.message}`));

  try {
    await page.goto(`${adminBase}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByLabel(/用户名|Username|账号|账户|登录用户名/i).fill('playwright_geo');
    await page.getByLabel(/密码|Password/i).fill('password');
    await page.getByRole('button', { name: /登录|Login|Sign in/i }).click();
    await page.waitForURL(/geo_admin(?!\/login)/, { timeout: 15000 });

    await page.goto(`${adminBase}/articles?geo_audit_status=risk`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('text=GEO', { timeout: 15000 });
    await page.waitForSelector(`text=${articleTitle}`, { timeout: 15000 });
    await page.waitForSelector('text=58', { timeout: 15000 });
    await page.screenshot({ path: 'tmp/geo-articles-list.png', fullPage: true });

    await page.getByRole('link', { name: articleTitle }).click();
    await page.waitForURL(/\/geo_admin\/articles\/\d+\/edit/, { timeout: 15000 });
    await page.waitForSelector('text=不参与审核流', { timeout: 15000 });
    await page.waitForSelector('text=重新评分', { timeout: 15000 });
    await page.waitForSelector('text=AI 一键优化', { timeout: 15000 });
    await page.waitForSelector('text=Add summary, FAQ, opening answer, conclusion, and internal links.', { timeout: 15000 });
    await page.screenshot({ path: 'tmp/geo-article-edit.png', fullPage: true });

    await page.getByRole('button', { name: /重新评分/ }).click();
    await page.waitForURL(/\/geo_admin\/articles\/\d+\/edit/, { timeout: 15000 });
    await page.waitForSelector('text=GEO', { timeout: 15000 });
    await page.screenshot({ path: 'tmp/geo-after-reaudit.png', fullPage: true });

    console.log(JSON.stringify({
      ok: true,
      url: page.url(),
      consoleMessages: messages,
      screenshots: [
        'tmp/geo-articles-list.png',
        'tmp/geo-article-edit.png',
        'tmp/geo-after-reaudit.png',
      ],
    }, null, 2));
  } catch (error) {
    await page.screenshot({ path: 'tmp/geo-click-test-failed.png', fullPage: true }).catch(() => {});
    console.error(JSON.stringify({
      ok: false,
      error: error.message,
      url: page.url(),
      consoleMessages: messages,
      screenshot: 'tmp/geo-click-test-failed.png',
    }, null, 2));
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
