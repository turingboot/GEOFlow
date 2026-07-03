const { chromium } = require('playwright');
const path = require('path');

(async () => {
  const outDir = path.resolve('tmp/membership-click-test');
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [];

  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      errors.push(`console: ${msg.text()}`);
    }
  });
  page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));

  await page.goto('http://127.0.0.1:18080/geo_admin/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'password');
  await Promise.all([
    page.waitForURL('**/geo_admin/dashboard', { timeout: 20000 }),
    page.click('button[type="submit"]'),
  ]);
  await page.screenshot({ path: path.join(outDir, '01-dashboard.png'), fullPage: true });

  await page.click('a[href$="/geo_admin/memberships"]');
  await page.waitForURL('**/geo_admin/memberships');
  await page.waitForLoadState('networkidle');
  await page.locator('h1').filter({ hasText: '会员管理' }).first().waitFor({ timeout: 10000 });
  await page.locator('table').first().waitFor({ timeout: 10000 });
  await page.screenshot({ path: path.join(outDir, '02-membership-management.png'), fullPage: true });

  await page.click('button[onclick="showMembershipPlanModal(null)"]');
  await page.waitForSelector('#membership-plan-modal:not(.hidden)');
  await page.fill('#plan_name', 'Click Test Plan');
  await page.fill('#plan_article_limit', '88');
  await page.fill('#plan_knowledge_limit', '6');
  await page.selectOption('#plan_is_active', '1');
  await page.screenshot({ path: path.join(outDir, '03-plan-modal.png'), fullPage: true });
  await page.click('#membership-plan-modal button[onclick="hideMembershipPlanModal()"]');
  await page.waitForFunction(() => document.querySelector('#membership-plan-modal')?.classList.contains('hidden'));

  await page.click('a[href$="/geo_admin/admin-users"]');
  await page.waitForURL('**/geo_admin/admin-users');
  await page.waitForLoadState('networkidle');
  await page.locator('button[onclick^="showAssignMembershipModal"]').first().waitFor({ timeout: 10000 });
  await page.screenshot({ path: path.join(outDir, '04-admin-users.png'), fullPage: true });

  await page.locator('button[onclick^="showAssignMembershipModal"]').first().click();
  await page.waitForSelector('#assign-membership-modal:not(.hidden)');
  await page.selectOption('#membership_period', 'quarter');
  await page.selectOption('#membership_effective_mode', 'renew');
  const previewStart = await page.locator('#membership_preview_start').innerText();
  const previewEnd = await page.locator('#membership_preview_end').innerText();
  if (previewStart === '-' || previewEnd === '-') {
    throw new Error('membership date preview did not update');
  }
  await page.screenshot({ path: path.join(outDir, '05-assign-membership-modal.png'), fullPage: true });
  await page.click('#assign-membership-modal button[onclick="hideAssignMembershipModal()"]');
  await page.waitForFunction(() => document.querySelector('#assign-membership-modal')?.classList.contains('hidden'));

  const tenantSelect = page.locator('form[action$="/geo_admin/tenant/switch"] select[name="tenant_id"]');
  await tenantSelect.waitFor({ timeout: 10000 });
  const tenantOptions = await tenantSelect.locator('option').evaluateAll((options) =>
    options.map((option) => option.value).filter((value) => value && value !== '0')
  );
  if (tenantOptions.length === 0) {
    throw new Error('no selectable tenant found for membership detail click test');
  }
  await Promise.all([
    page.waitForLoadState('networkidle'),
    tenantSelect.selectOption(tenantOptions[0]),
  ]);
  await page.locator('button[onclick="toggleUserMenu()"]').click();
  await page.waitForSelector('#user-menu:not(.hidden)');
  await page.screenshot({ path: path.join(outDir, '06-user-menu.png'), fullPage: true });
  await page.click('#user-menu a[href$="/geo_admin/membership"]');
  await page.waitForURL('**/geo_admin/membership');
  await page.waitForLoadState('networkidle');
  await page.locator('h1').filter({ hasText: '会员详情' }).first().waitFor({ timeout: 10000 });
  await page.locator('table').first().waitFor({ timeout: 10000 });
  await page.screenshot({ path: path.join(outDir, '07-membership-detail.png'), fullPage: true });

  console.log(JSON.stringify({ ok: true, finalUrl: page.url(), errors, screenshots: outDir, previewStart, previewEnd }, null, 2));
  await browser.close();
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
