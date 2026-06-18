# UI 改版方案（路线 A:Blade 换皮,业务逻辑不变）

> 目标:为后台(Admin)和前台文章站(Public)做一套全新 UI,**不改动任何业务逻辑**——controller / service / 路由 / 校验 / 鉴权 / 既有测试全部保留,只替换表现层(Blade 模板 + Tailwind 样式 + Vite 资源)。

## 0. 硬约束(技术边界)

- **只新增/替换视图层,不删改业务逻辑**。controller 继续返回相同的视图数据契约(`pageTitle` / `activeMenu` / `adminSiteName` 及各页面变量),视图按这个契约渲染。
- 不改路由、不改 `route()` 目标、不改表单字段 `name`、不改 i18n 翻译 key、不改 `data-*` 行为钩子——这些是既有 Feature 测试和前端 JS 依赖的契约。
- 不引入新的后端依赖;前端沿用现有 Vite + Tailwind v4 资源链路。
- **功能必须 1:1 全部接通**:换皮只动外观,现有每个页面、每个入口、每个按钮/表单/筛选/操作都必须在新 UI 里原样存在且可用,**不允许遗漏或弱化任何功能**(详见下节强制验收标准)。

## ★ 功能 1:1 接通(强制验收标准,最高优先级)

这是本次改版**不可妥协**的第一要求:新 UI 必须把现有后台与前台的**全部功能原样接通**,只换外观、不丢能力。"好看但少了一个按钮/一个筛选/一个超管入口"= 不合格。

### 功能清单的权威来源
- **后台全部页面与动作以 `php artisan route:list`(admin 前缀)为准** —— 每一条路由都必须在新 UI 有可达的入口/控件,不能出现"页面在、但 UI 上点不到"的功能。
- **每个旧视图逐项对照**:其中的每一个 `route()` 链接/按钮、每一个 `<form>`(action + 全部字段 `name`)、筛选/搜索/排序/分页、批量操作、按权限(`admin.super`)或按状态显示的条件区块(例如分发的暂停/启用/重试/轮换密钥/显示密钥/下载站点包/同步设置/远端文章编辑删除,文章的审核/发布/回收站/还原/彻底删除等),以及 `data-*` 交互钩子 —— 在新视图里**全部要出现且行为一致**。

### 逐模块验收清单(每个模块迁移完才算 done)
- [ ] 该模块所有路由都有可达入口(逐条对照 `route:list`)
- [ ] 所有按钮/链接的 `route()` 目标一致
- [ ] 所有表单 action + 字段 `name` + `@csrf`/`@method` 一致
- [ ] 筛选、搜索、分页、批量操作全部保留
- [ ] 权限/状态相关的条件区块全部保留(超管专属项、按状态的操作)
- [ ] `data-*` 行为钩子与交互结果一致
- [ ] 该模块既有 Feature 测试全绿;旧测试没覆盖到的入口,**补测试**锁住

### 用测试作为 1:1 的安全网
- 既有 Feature 测试(断言文案、字段 `name`、路由)**只能加、不能删/弱化**;迁移后必须保持全绿——这是"功能没丢"的硬证据。
- 对测试未覆盖的页面/动作,迁移时**补 Feature 测试**(断言入口/控件存在 + 提交后行为正确),把"接通"固化进 CI。

## 1. 现状(决定方案的关键结构)

- **后台**:`resources/views/admin/` 下约 29 个模块(dashboard、tasks、articles、distribution、analytics、materials、ai-*、site-settings、knowledge-bases…),全部服务端渲染,`@extends('admin.layouts.app')` + `@section('content')`。
- **前台**:`resources/views/site/` + 主题根目录 **`resources/views/theme/`**;`App\Support\Site\SiteThemeCatalog` 扫描 `resource_path('views/theme')` 识别主题包,后台「网站设置」可切换主题(`config('geoflow.default_theme')`,默认 `toutiao-news-20260426`)。→ **前台换 UI = 新增一个主题包目录**,是系统设计好的扩展点。
- **资源链路**:Vite(`vite.config.js`)+ Tailwind v4(`@tailwindcss/vite`)+ vditor;`npm run build` / `npm run dev`。

## 2. 后台(Admin)改版

**目标形态:标准后台管理布局 —— 左侧固定「功能导航区」,右侧「操作区」(顶栏 + 主内容)。** 做法仍是:新建一套布局 + 组件,逐模块替换视图,controller 不动。

### 2.1 目标布局(标准 admin shell)

```
┌────────────────┬──────────────────────────────────────────────┐
│  Logo / 站名    │  顶栏: 面包屑/页标题 · 搜索 · 语言 · 通知 · 管理员 │
│ (sidebar 顶部)  ├──────────────────────────────────────────────┤
│                │                                              │
│ ▸ 仪表盘        │                                              │
│ ▸ 任务管理      │        操作区(主内容 @yield('content'))       │
│ ▸ 文章管理      │        · flash / 错误提示                      │
│ ▸ 分发管理      │        · 卡片 / 表格 / 表单 / 详情 …            │
│ ▸ 素材库        │                                              │
│ ▸ 数据分析      │                                              │
│ ▸ AI 配置       │                                              │
│ ▸ 网站设置      │                                              │
│ ▸ 系统 / 超管   │                                              │
│  (底部: 版本)   │                                              │
└────────────────┴──────────────────────────────────────────────┘
        左侧功能导航区(固定/可折叠)        右侧操作区(随内容滚动)
```

- **左侧功能导航区(sidebar,固定列)**:顶部站名/Logo;中部按业务分组的菜单(对齐现有 `activeMenu` 取值:dashboard、tasks、articles、distribution、materials、analytics、ai-*、site-settings、system-updates…);当前页高亮**由 controller 传入的 `activeMenu` 驱动(契约不变)**;超管专属项(管理员、活动日志、API Token)按 `admin.super` 权限显示;底部放版本号/退出。桌面端固定常显,窄屏收起为抽屉。
- **右侧操作区**:
  - **顶栏**:页标题(`pageTitle`)/面包屑、语言切换、通知入口、管理员菜单(退出等);窄屏左侧放汉堡按钮切换 sidebar。
  - **主内容**:统一的 `@yield('content')` 容器 + 统一的 flash/错误提示位,页面只管往里填卡片/表格/表单。

### 2.2 实现步骤

1. **新布局与公共件**
   - 重写 `resources/views/admin/layouts/app.blade.php` 为上面的「左导航 + 右操作区」骨架(可并存一个 `app-v2`,灰度切换);把 sidebar、顶栏、flash、分页、面包屑拆到 `resources/views/admin/partials/`。
   - sidebar 菜单集中在一个 partial 里按 `activeMenu` 高亮,避免每页重复;权限可见性沿用现有 guard/`admin.super` 判断。
   - 约定一套 Tailwind 组件类(按钮 / 表单控件 / 卡片 / 表格 / 徽章 / 弹窗),集中在局部件或 Blade 组件中。
2. **增量迁移视图**
   - 各页 `@extends('admin.layouts.app')` 与 `@section('content')` **保持不变** → controller 零改动,仅页面内部结构/样式适配新操作区。
   - 建议顺序:`dashboard → tasks → articles → distribution → materials → analytics → 其余`。
   - 每个视图只改外层结构与样式,**保留**:`@section('content')` 用到的变量名、`route()` 目标、表单字段 `name`/`id`、`@csrf`/`@method`、`data-*` 钩子、`__()` 文案 key。
3. **交互增强(可选,不动后端)**
   - 侧栏折叠、移动端抽屉、下拉/tab/模态等用 **Alpine.js**(或 htmx)做轻量增强,**不需要改 API**。现有原生 JS 切换(如分发表单的 `data-channel-type-panel` / `data-shopify-auth`)可平滑替换为 Alpine。
4. **资源**
   - 新增 CSS/JS 入口纳入 `vite.config.js`;构建用 `npm run build`,开发用 `npm run dev` / `composer run dev`。

## 3. 前台文章站(Public)改版

**做法:新增一个主题包,不动 `Site\*` 控制器。**

1. 在 `resources/views/theme/` 下新建主题目录(对照现有主题包结构:首页 / 归档 / 分类 / 文章详情 / 公共布局 / 资源)。
2. 按 `SiteThemeCatalog` 期望的结构与元信息组织该目录,使其能在后台主题列表中被识别。
3. 后台「网站设置 → 主题」切换到新主题(或设 `GEOFLOW_DEFAULT_THEME` / `config('geoflow.default_theme')`)。
4. 前台路由、locale 中间件、视图日志、SEO 输出逻辑全部不变。

> 注:GEOFlow Agent 目标站点包有独立的前台模板(分发渠道用),与本站主题是两套;如需同步改版,另行评估,不在本方案范围内默认包含。

## 4. 测试护栏(避免"换皮换崩测试")

现有 Feature 测试大量依赖**文案 + 路由 + 裸 HTML 属性**,换皮时必须保留,否则测试会红:

- 保留 `__('...')` 翻译 key 与其输出文案(测试用 `assertSee(__('admin.xxx'))`)。
- 保留表单字段 `name`(测试有 `assertSee('name="wordpress_username"', false)` 这类裸断言)。
- 保留 `route()` 目标与 method、`@csrf`。
- 保留按渠道类型/模式切换的 `data-*` 钩子语义(如 `data-channel-type-panel`、`data-shopify-auth`)。
- 每迁移一个模块,**立即**跑该模块的 Feature 测试(`php artisan test --compact tests/Feature/AdminXxxPageTest.php`)回归。

## 5. 分阶段交付建议

1. **阶段 0:脚手架** —— 新布局 + 组件约定 + 1 个样板模块(dashboard 或 distribution),跑通构建与该模块测试。
2. **阶段 1:后台主链路** —— tasks / articles / distribution / materials。
3. **阶段 2:后台其余模块** —— analytics / ai-* / site-settings / knowledge-bases / 系统类页面。
4. **阶段 3:前台新主题包** —— 新建并切换主题。
5. 每阶段结束:`vendor/bin/pint --dirty --format agent`(若动到少量 PHP)+ 相关 Feature 测试回归 + `npm run build`。

## 6. 不做什么 / 为什么不用路线 B(SPA)

- 本方案**不**改 controller/service/路由/鉴权/数据库;**不**引入前端框架重写。
- 路线 B(Vue/React SPA 走 `/api/v1`)只对已有 API 的 `tasks/articles/materials/catalog/jobs` 满足"逻辑不变";分发、分析、AI 配置、网站设置、知识库、管理员、API Token、系统更新、主题复制、URL 导入等**都没有对应 API**,需新增大量接口 + Sanctum SPA 鉴权 = 新增后端逻辑,**违反"其他逻辑不变"的约束**,故不采用。若将来明确要前后端解耦,再单独立项补全 API。

## 7. 影响面清单(便于评审)

- **改动**:`resources/views/admin/**`、`resources/views/admin/layouts/**`、`resources/views/admin/partials/**`、`resources/views/theme/<新主题>/**`、`resources/css|js/**`、`vite.config.js`(新增入口)。
- **不改**:`app/Http/Controllers/**`、`app/Services/**`、`routes/**`、`app/Http/Middleware/**`、`lang/**`(翻译 key 不变;如需新增文案才追加)、`database/**`、既有测试(只在必要时新增)。
