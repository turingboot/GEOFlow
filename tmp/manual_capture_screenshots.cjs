const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const base = process.env.GEOFLOW_BASE_URL || 'http://localhost:18080/geo_admin';
const outDir = path.resolve('D:/Project Files/GEOFlow/docs/manual-screenshots');

const pages = [
  {
    name: '07-首页-总览',
    url: '/dashboard',
    caption: '图：后台首页总览，进入系统后先看快捷入口和整体运行状态。',
    marks: [
      { text: '新建任务', label: '1' },
      { text: '快速开始', label: '2' },
      { text: 'GEO 内容主链路', label: '3' },
    ],
  },
  {
    name: '08-增长中心-总览',
    url: '/analytics',
    caption: '图：增长中心总览，用于查看访问、线索和内容效果数据。',
    marks: [
      { text: '刷新', label: '1' },
      { text: '表单管理', label: '2' },
      { text: '线索收件箱', label: '3' },
    ],
  },
  {
    name: '09-任务管理-列表',
    url: '/tasks',
    caption: '图：任务管理列表，可查看任务状态，也可以进入新建任务页面。',
    marks: [
      { text: '新建任务', label: '1' },
      { text: '任务', label: '2', nth: 1 },
      { text: '状态', label: '3' },
    ],
  },
  {
    name: '09-任务管理-新建基础信息',
    url: '/tasks/create',
    caption: '图：新建任务的基础信息区域，先设置任务名称、标题来源和关键词等基础内容。',
    marks: [
      { text: '任务名称', label: '1' },
      { text: '标题', label: '2' },
      { text: '关键词', label: '3' },
      { text: '生成数量', label: '4' },
    ],
  },
  {
    name: '09-任务管理-生成与发布设置',
    url: '/tasks/create',
    scroll: 720,
    caption: '图：任务的生成、审核和发布设置区域，决定文章如何生成以及生成后进入什么状态。',
    marks: [
      { text: 'AI 模型', label: '1' },
      { text: '提示词', label: '2' },
      { text: '知识库', label: '3' },
      { text: '发布', label: '4' },
    ],
  },
  {
    name: '10-分发管理-渠道列表',
    url: '/distribution',
    caption: '图：分发管理列表，用于查看和维护外部发布目标。',
    marks: [
      { text: '创建渠道', label: '1' },
      { text: '分发队列', label: '2' },
      { text: '状态', label: '3' },
    ],
  },
  {
    name: '10-分发管理-创建渠道',
    url: '/distribution/create',
    caption: '图：创建分发渠道页面，按目标站点类型填写连接信息。',
    marks: [
      { text: '渠道名称', label: '1' },
      { text: '渠道类型', label: '2' },
      { text: '目标', label: '3' },
      { text: '保存', label: '4' },
    ],
  },
  {
    name: '10-分发管理-分发队列',
    url: '/distribution/jobs',
    caption: '图：分发队列页面，用于查看文章同步到外部站点的执行结果。',
    marks: [
      { text: '状态', label: '1' },
      { text: '最后错误', label: '2' },
      { text: '操作', label: '3' },
    ],
  },
  {
    name: '11-内容管理-文章列表',
    url: '/articles',
    caption: '图：内容管理列表，可筛选、审核、编辑和发布文章。',
    marks: [
      { text: '新建文章', label: '1' },
      { text: '筛选', label: '2' },
      { text: '批量', label: '3' },
      { text: '状态', label: '4' },
    ],
  },
  {
    name: '11-内容管理-文章编辑',
    url: '/articles/create',
    caption: '图：文章编辑页面，用于维护标题、正文、分类、状态等内容。',
    marks: [
      { text: '标题', label: '1' },
      { text: '正文', label: '2' },
      { text: '发布状态', label: '3' },
      { text: '保存', label: '4' },
    ],
  },
  {
    name: '12-知识资产-总览',
    url: '/materials',
    caption: '图：知识资产总览，统一进入知识库、标题库、关键词库、图片库和作者管理。',
    marks: [
      { text: '知识库', label: '1' },
      { text: '标题库', label: '2' },
      { text: '关键词库', label: '3' },
      { text: '图片库', label: '4' },
      { text: '作者', label: '5' },
    ],
  },
  {
    name: '12-知识资产-创建知识库',
    url: '/knowledge-bases/create',
    caption: '图：创建知识库页面，先建立知识库，再上传企业资料。',
    marks: [
      { text: '知识库名称', label: '1' },
      { text: '描述', label: '2' },
      { text: '保存', label: '3' },
    ],
  },
  {
    name: '12-知识资产-关键词库',
    url: '/keyword-libraries',
    caption: '图：关键词库列表，用于维护任务和选题会用到的关键词素材。',
    marks: [
      { text: '新建', label: '1' },
      { text: '关键词', label: '2' },
      { text: '操作', label: '3' },
    ],
  },
  {
    name: '12-知识资产-标题库',
    url: '/title-libraries',
    caption: '图：标题库列表，用于维护文章生成时可以调用的标题素材。',
    marks: [
      { text: '新建', label: '1' },
      { text: '标题', label: '2' },
      { text: '操作', label: '3' },
    ],
  },
  {
    name: '12-知识资产-图片库',
    url: '/image-libraries',
    caption: '图：图片库列表，用于维护文章配图素材。',
    marks: [
      { text: '新建', label: '1' },
      { text: '图片', label: '2' },
      { text: '操作', label: '3' },
    ],
  },
  {
    name: '13-关键词趋势-列表',
    url: '/keyword-trends',
    caption: '图：关键词趋势列表，用于管理趋势来源和抓取结果。',
    marks: [
      { text: '创建', label: '1' },
      { text: '抓取', label: '2' },
      { text: '导入', label: '3' },
    ],
  },
  {
    name: '13-关键词趋势-创建来源',
    url: '/keyword-trends/create',
    caption: '图：创建趋势数据源页面，用于设置关键词来源和抓取规则。',
    marks: [
      { text: '名称', label: '1' },
      { text: '来源', label: '2' },
      { text: '关键词', label: '3' },
      { text: '保存', label: '4' },
    ],
  },
  {
    name: '14-谷歌搜录-总览',
    url: '/google-search-console',
    caption: '图：谷歌搜录总览，用于查看 Google Search Console 连接和站点数据。',
    marks: [
      { text: '连接', label: '1' },
      { text: '站点', label: '2' },
      { text: 'URL', label: '3' },
    ],
  },
  {
    name: '15-选题规划-列表',
    url: '/topic-plans',
    caption: '图：选题规划列表，用于查看已生成的选题计划。',
    marks: [
      { text: '创建', label: '1' },
      { text: '状态', label: '2' },
      { text: '操作', label: '3' },
    ],
  },
  {
    name: '15-选题规划-创建计划',
    url: '/topic-plans/create',
    caption: '图：创建选题计划页面，选择关键词、知识库和生成要求后生成候选选题。',
    marks: [
      { text: '计划名称', label: '1' },
      { text: '关键词', label: '2' },
      { text: '知识库', label: '3' },
      { text: '生成', label: '4' },
    ],
  },
  {
    name: '16-AI配置器-总览',
    url: '/ai-configurator',
    caption: '图：AI 配置器总览，用于进入模型、提示词和特殊提示词配置。',
    marks: [
      { text: '模型', label: '1' },
      { text: '提示词', label: '2' },
      { text: '特殊提示词', label: '3' },
    ],
  },
  {
    name: '16-AI配置器-模型配置',
    url: '/ai-models',
    caption: '图：AI 模型配置页面，用于查看已有模型、添加模型和测试连接。',
    marks: [
      { text: '新增', label: '1' },
      { text: '测试', label: '2' },
      { text: '默认向量模型', label: '3' },
      { text: '保存', label: '4' },
    ],
  },
  {
    name: '16-AI配置器-提示词',
    url: '/ai-prompts',
    caption: '图：提示词配置页面，用于维护文章生成时使用的写作要求。',
    marks: [
      { text: '新增', label: '1' },
      { text: '名称', label: '2' },
      { text: '内容', label: '3' },
      { text: '保存', label: '4' },
    ],
  },
  {
    name: '16-AI配置器-特殊提示词',
    url: '/ai-special-prompts',
    caption: '图：特殊提示词页面，用于维护关键词、描述等专项生成规则。',
    marks: [
      { text: '关键词', label: '1' },
      { text: '描述', label: '2' },
      { text: '保存', label: '3' },
    ],
  },
];

async function annotate(page, marks) {
  await page.evaluate((items) => {
    document.querySelectorAll('[data-manual-annotation]').forEach((node) => node.remove());
    const style = document.createElement('style');
    style.setAttribute('data-manual-annotation', 'style');
    style.textContent = `
      [data-manual-secret] { filter: blur(8px) !important; }
      .manual-mark-box {
        position: absolute;
        border: 3px solid #dc2626;
        border-radius: 10px;
        box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.02);
        pointer-events: none;
        z-index: 2147483000;
      }
      .manual-mark-label {
        position: absolute;
        width: 30px;
        height: 30px;
        border-radius: 999px;
        background: #dc2626;
        color: #fff;
        font: 700 16px/30px Arial, sans-serif;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,.28);
        pointer-events: none;
        z-index: 2147483001;
      }
    `;
    document.head.appendChild(style);

    const secretSelectors = [
      'input[type="password"]',
      'input[name*="key" i]',
      'input[name*="secret" i]',
      'input[name*="token" i]',
      'textarea[name*="secret" i]',
      'textarea[name*="token" i]',
      'textarea[name*="credential" i]',
    ];
    secretSelectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((node) => node.setAttribute('data-manual-secret', '1'));
    });

    const visible = (el) => {
      const rect = el.getBoundingClientRect();
      const style = window.getComputedStyle(el);
      return rect.width > 12 && rect.height > 8 && style.visibility !== 'hidden' && style.display !== 'none';
    };
    const scoreElement = (el) => {
      const tag = el.tagName.toLowerCase();
      if (['button', 'a', 'label', 'input', 'select', 'textarea'].includes(tag)) return 0;
      if (el.closest('button,a,label')) return 1;
      return 2;
    };
    const inMainContent = (el) => {
      const rect = el.getBoundingClientRect();
      return rect.left >= 255 && rect.top >= 70 && rect.bottom <= window.innerHeight - 58;
    };
    const findByText = (text, nth = 0) => {
      const candidates = Array.from(document.querySelectorAll('button,a,label,span,div,h1,h2,h3,p,th,td'))
        .filter(visible)
        .filter(inMainContent)
        .filter((el) => (el.innerText || el.textContent || '').trim().includes(text))
        .sort((a, b) => scoreElement(a) - scoreElement(b));
      return candidates[nth] || candidates[0] || null;
    };
    const findElement = (item) => {
      if (item.selector) {
        const el = document.querySelector(item.selector);
        if (el && visible(el)) return el;
      }
      if (item.text) return findByText(item.text, item.nth || 0);
      return null;
    };

    items.forEach((item) => {
      const el = findElement(item);
      if (!el) return;
      const rect = el.getBoundingClientRect();
      const pad = 8;
      const top = rect.top + window.scrollY - pad;
      const left = rect.left + window.scrollX - pad;
      const width = rect.width + pad * 2;
      const height = rect.height + pad * 2;

      const box = document.createElement('div');
      box.setAttribute('data-manual-annotation', 'box');
      box.className = 'manual-mark-box';
      box.style.top = `${Math.max(0, top)}px`;
      box.style.left = `${Math.max(0, left)}px`;
      box.style.width = `${width}px`;
      box.style.height = `${height}px`;
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

async function main() {
  fs.mkdirSync(outDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({
    viewport: { width: 1440, height: 950 },
    deviceScaleFactor: 1,
  });
  page.setDefaultTimeout(20000);

  await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="username"]', process.env.GEOFLOW_USER || 'test');
  await page.fill('input[name="password"]', process.env.GEOFLOW_PASS || '123123123');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/geo_admin\/(dashboard|login)/, { timeout: 20000 });
  await page.waitForLoadState('domcontentloaded');

  if (page.url().includes('/login')) {
    throw new Error('Login failed; still on login page.');
  }

  const manifest = [];

  for (const item of pages) {
    const url = `${base}${item.url}`;
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 25000 });
      await page.waitForSelector('body', { timeout: 10000 });
      await page.waitForTimeout(900);
      await page.evaluate((scroll) => window.scrollTo(0, scroll || 0), item.scroll || 0);
      await page.waitForTimeout(350);
      await annotate(page, item.marks || []);
      await page.waitForTimeout(100);
      const file = path.join(outDir, `${item.name}.png`);
      await page.screenshot({ path: file, fullPage: false });
      manifest.push({ name: item.name, file, caption: item.caption, url: item.url });
      console.log('captured', item.name);
    } catch (error) {
      manifest.push({ name: item.name, error: error.message, caption: item.caption, url: item.url });
      console.log('failed', item.name, error.message);
    }
  }

  fs.writeFileSync(path.join(outDir, 'manifest.json'), JSON.stringify(manifest, null, 2), 'utf8');
  await browser.close();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
