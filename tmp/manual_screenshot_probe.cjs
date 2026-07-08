const { chromium } = require('playwright');

(async () => {
  const base = process.env.GEOFLOW_BASE_URL || 'http://localhost:18080/geo_admin';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  page.setDefaultTimeout(15000);

  await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="username"]', process.env.GEOFLOW_USER || 'test');
  await page.fill('input[name="password"]', process.env.GEOFLOW_PASS || '123123123');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  console.log('after_login', page.url());
  console.log('title', await page.title());
  console.log('body', (await page.locator('body').innerText()).slice(0, 500).replace(/\s+/g, ' '));

  const paths = [
    '/dashboard',
    '/analytics',
    '/tasks',
    '/tasks/create',
    '/distribution',
    '/distribution/create',
    '/distribution/jobs',
    '/articles',
    '/articles/create',
    '/materials',
    '/knowledge-bases/create',
    '/keyword-libraries',
    '/title-libraries',
    '/image-libraries',
    '/url-import',
    '/keyword-trends',
    '/keyword-trends/create',
    '/google-search-console',
    '/google-search-console/settings',
    '/topic-plans',
    '/topic-plans/create',
    '/ai-configurator',
    '/ai-prompts',
    '/ai-special-prompts',
  ];

  for (const path of paths) {
    const url = `${base}${path}`;
    await page.goto(url, { waitUntil: 'networkidle' });
    const statusText = (await page.locator('body').innerText()).slice(0, 220).replace(/\s+/g, ' ');
    console.log('PAGE', path, page.url(), statusText);
  }

  await browser.close();
})();
