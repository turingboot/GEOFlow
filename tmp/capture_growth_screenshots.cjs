const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const base = 'http://localhost:18080/geo_admin';
const outDir = 'D:/Project Files/GEOFlow/docs/manual-screenshots';

const targets = [
  {
    name: '08-增长中心-创建线索表单',
    url: '/lead-forms/create',
    marks: [
      { text: '表单名称', label: '1' },
      { text: '标识', label: '2' },
      { text: '字段', label: '3' },
      { text: '保存', label: '4' },
    ],
  },
  {
    name: '08-增长中心-表单管理',
    url: '/lead-forms',
    marks: [
      { text: '创建表单', label: '1' },
      { text: '状态', label: '2' },
      { text: '操作', label: '3' },
    ],
  },
  {
    name: '08-增长中心-线索收件箱',
    url: '/leads',
    marks: [
      { text: '状态', label: '1' },
      { text: '导出', label: '2' },
      { text: '线索', label: '3' },
    ],
  },
];

async function annotate(page, marks) {
  await page.evaluate((items) => {
    document.querySelectorAll('[data-manual-annotation]').forEach((node) => node.remove());
    const style = document.createElement('style');
    style.setAttribute('data-manual-annotation', 'style');
    style.textContent = `
      .manual-mark-box{position:absolute;border:3px solid #dc2626;border-radius:10px;pointer-events:none;z-index:2147483000}
      .manual-mark-label{position:absolute;width:30px;height:30px;border-radius:999px;background:#dc2626;color:white;font:700 16px/30px Arial,sans-serif;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.25);pointer-events:none;z-index:2147483001}
    `;
    document.head.appendChild(style);

    const visible = (el) => {
      const rect = el.getBoundingClientRect();
      const s = window.getComputedStyle(el);
      return rect.width > 8 && rect.height > 8 && s.display !== 'none' && s.visibility !== 'hidden';
    };
    const inMain = (el) => {
      const rect = el.getBoundingClientRect();
      return rect.left >= 255 && rect.top >= 70 && rect.bottom <= window.innerHeight - 58;
    };
    const find = (text) => {
      const selectors = 'button,a,label,span,div,h1,h2,h3,p,th,td,input,textarea,select';
      return Array.from(document.querySelectorAll(selectors))
        .filter(visible)
        .filter(inMain)
        .find((el) => (el.innerText || el.textContent || el.getAttribute('placeholder') || '').trim().includes(text));
    };

    items.forEach((item) => {
      const el = find(item.text);
      if (!el) return;
      const rect = el.getBoundingClientRect();
      const pad = 8;
      const top = rect.top + window.scrollY - pad;
      const left = rect.left + window.scrollX - pad;
      const box = document.createElement('div');
      box.setAttribute('data-manual-annotation', 'box');
      box.className = 'manual-mark-box';
      box.style.top = `${Math.max(0, top)}px`;
      box.style.left = `${Math.max(0, left)}px`;
      box.style.width = `${rect.width + pad * 2}px`;
      box.style.height = `${rect.height + pad * 2}px`;
      document.body.appendChild(box);
      const label = document.createElement('div');
      label.setAttribute('data-manual-annotation', 'label');
      label.className = 'manual-mark-label';
      label.textContent = item.label;
      label.style.top = `${Math.max(0, top - 16)}px`;
      label.style.left = `${Math.max(0, left - 16)}px`;
      document.body.appendChild(label);
    });
  }, marks);
}

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

  for (const target of targets) {
    await page.goto(`${base}${target.url}`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('body');
    await page.waitForTimeout(800);
    await annotate(page, target.marks);
    const file = path.join(outDir, `${target.name}.png`);
    await page.screenshot({ path: file, fullPage: false });
    console.log(file);
  }
  await browser.close();
})();
