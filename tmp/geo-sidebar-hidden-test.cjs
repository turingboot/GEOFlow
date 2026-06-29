const { chromium } = require('playwright');

const baseUrl = 'http://127.0.0.1:18080';
const adminBase = `${baseUrl}/geo_admin`;
const articleTitle = 'Tenant GEO integration article';

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const messages = [];
  page.on('console', (message) => messages.push(`${message.type()}: ${message.text()}`));
  page.on('pageerror', (error) => messages.push(`pageerror: ${error.message}`));

  try {
    await page.goto(`${adminBase}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByLabel(/用户名|Username|账号|账户|登录用户名/i).fill('playwright_tenant');
    await page.getByLabel(/密码|Password/i).fill('password');
    await page.getByRole('button', { name: /登录|Login|Sign in/i }).click();
    await page.waitForURL(/geo_admin(?!\/login)/, { timeout: 15000 });

    const navGeoCount = await page.locator('aside nav a', { hasText: /GEO 评分|GEO Audit|Auditoria GEO/i }).count();
    if (navGeoCount !== 0) {
      throw new Error('侧边栏仍然显示 GEO 评分入口');
    }

    await page.goto(`${adminBase}/articles?geo_audit_status=risk`, { waitUntil: 'domcontentloaded' });
    await page.getByRole('link', { name: articleTitle }).click();
    await page.waitForURL(/\/geo_admin\/articles\/\d+\/edit/, { timeout: 15000 });
    await page.getByRole('link', { name: /查看详情/ }).click();
    await page.waitForURL(/\/geo_admin\/geo-audits\/\d+/, { timeout: 15000 });
    await page.waitForSelector('text=GEO', { timeout: 15000 });
    await page.screenshot({ path: 'tmp/geo-sidebar-hidden-detail.png', fullPage: true });

    console.log(JSON.stringify({
      ok: true,
      url: page.url(),
      consoleMessages: messages,
      screenshot: 'tmp/geo-sidebar-hidden-detail.png',
    }, null, 2));
  } catch (error) {
    await page.screenshot({ path: 'tmp/geo-sidebar-hidden-test-failed.png', fullPage: true }).catch(() => {});
    console.error(JSON.stringify({
      ok: false,
      error: error.message,
      url: page.url(),
      consoleMessages: messages,
      screenshot: 'tmp/geo-sidebar-hidden-test-failed.png',
    }, null, 2));
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
