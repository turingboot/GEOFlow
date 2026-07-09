{{--
    平台产品落地页（自包含）：前台文章站关闭时的根路径首页，兼作 Google OAuth 应用首页。
    需满足 Google「应用首页」验收：准确标识品牌、充分描述功能、透明说明数据用途、
    含隐私政策链接（与同意屏幕逐字一致）、无需登录即可访问。
    刻意不复用租户主题：内联样式、无外部资源依赖，任意开关状态都能稳定渲染。
--}}
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>{{ $companyName }} · GEO 优化平台 / GEO Optimization Platform</title>
    <meta name="description" content="{{ $companyName }} is a GEO (Generative Engine Optimization) content-engineering and search-monitoring platform. It produces content at scale with AI and feeds real Google Search Console keyword signals back into your content strategy.">
    <style>
        :root { color-scheme: light dark;
            --bg:#f7f8fa; --fg:#1f2329; --muted:#6b7280; --card:#fff; --line:#e5e7eb;
            --brand:#2563eb; --brand-fg:#fff; --accent:#eff6ff; --accent-line:#bfdbfe; }
        @media (prefers-color-scheme: dark) { :root {
            --bg:#0f1115; --fg:#e7ebf1; --muted:#9aa4b2; --card:#171a21; --line:#2a2f3a;
            --brand:#3b82f6; --accent:#141c2e; --accent-line:#29406a; } }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--fg); line-height:1.7;
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei",Roboto,Helvetica,Arial,sans-serif;
            -webkit-font-smoothing:antialiased; }
        a { color:var(--brand); text-decoration:none; }
        a:hover { text-decoration:underline; }
        .wrap { max-width:1000px; margin:0 auto; padding:0 20px; }
        .en { color:var(--muted); }

        header.nav { display:flex; align-items:center; justify-content:space-between; padding:20px 0; }
        .brand { font-weight:700; font-size:19px; letter-spacing:.2px; }
        .brand .dot { color:var(--brand); }
        .nav-links { display:flex; gap:20px; align-items:center; font-size:14px; }
        .nav-links a.muted { color:var(--muted); }

        .btn { display:inline-block; border-radius:10px; padding:11px 22px; font-size:15px; font-weight:600; }
        .btn-primary { background:var(--brand); color:var(--brand-fg); }
        .btn-primary:hover { text-decoration:none; opacity:.92; }
        .btn-ghost { border:1px solid var(--line); color:var(--fg); }
        .btn-ghost:hover { text-decoration:none; border-color:var(--brand); }

        .hero { text-align:center; padding:52px 0 20px; }
        .eyebrow { display:inline-block; font-size:13px; color:var(--brand); background:var(--accent);
            border:1px solid var(--accent-line); border-radius:999px; padding:5px 14px; margin-bottom:20px; }
        .hero h1 { font-size:44px; line-height:1.15; margin:0 0 16px; letter-spacing:-.5px; }
        .hero p.lead { font-size:18px; color:var(--fg); max-width:720px; margin:0 auto 8px; }
        .hero p.lead-en { font-size:15px; max-width:680px; margin:0 auto 26px; }
        .cta-row { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }

        section.block { margin:40px 0; }
        section.block h2 { font-size:22px; margin:0 0 12px; }
        section.block > p { font-size:15.5px; margin:8px 0; }

        .grid { display:grid; grid-template-columns:repeat(2,1fr); gap:18px; margin-top:20px; }
        .feat { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:22px; }
        .feat h3 { margin:0 0 6px; font-size:16.5px; }
        .feat p { margin:0; font-size:14px; color:var(--muted); }
        .feat .ic { font-size:22px; margin-bottom:8px; }

        .data { background:var(--card); border:1px solid var(--accent-line); border-radius:16px; padding:28px 30px; }
        .data h2 { margin:0 0 6px; }
        .data ul { margin:12px 0; padding-left:20px; }
        .data li { font-size:14.5px; margin:6px 0; }
        .data li .en { display:block; }
        .pills { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
        .pill { font-size:12.5px; color:var(--brand); background:var(--accent); border:1px solid var(--accent-line);
            border-radius:999px; padding:4px 12px; }
        .privacy-link { margin-top:16px; font-size:15px; }

        footer.foot { border-top:1px solid var(--line); margin-top:44px; padding:26px 0 48px;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
            color:var(--muted); font-size:13.5px; }
        footer.foot .links { display:flex; gap:18px; flex-wrap:wrap; }

        @media (max-width:640px) {
            .hero { padding:32px 0 12px; } .hero h1 { font-size:32px; }
            .hero p.lead { font-size:16.5px; } .grid { grid-template-columns:1fr; }
            .nav-links a.muted { display:none; } .data { padding:22px 20px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header class="nav">
        <div class="brand">{{ $companyName }}<span class="dot">.</span></div>
        <nav class="nav-links">
            <a class="muted" href="{{ route('site.legal.privacy') }}">隐私政策 Privacy</a>
            <a class="muted" href="{{ route('site.legal.terms') }}">服务条款 Terms</a>
            <a class="btn btn-ghost" href="{{ route('admin.login') }}">登录 Sign in</a>
        </nav>
    </header>

    <section class="hero">
        <span class="eyebrow">GEO 优化平台 · GEO Optimization Platform</span>
        <h1>{{ $companyName }}</h1>
        <p class="lead">{{ $companyName }} 是一个 GEO（生成式引擎优化）内容工程与搜索监控平台：用 AI 规模化生产内容，并把 Google Search Console 的真实关键词表现<b>回流</b>到你的内容策略。</p>
        <p class="lead-en">{{ $companyName }} is a GEO (Generative Engine Optimization) content-engineering and search-monitoring platform. It produces content at scale with AI and feeds real Google Search Console keyword signals back into your content strategy.</p>
        <div class="cta-row">
            <a class="btn btn-primary" href="{{ route('admin.login') }}">进入控制台 · Open console</a>
            <a class="btn btn-ghost" href="{{ route('site.legal.privacy') }}">隐私与数据 · Privacy &amp; data</a>
        </div>
    </section>

    <section class="block">
        <h2>关于本应用 · About {{ $companyName }}</h2>
        <p>{{ $companyName }} 面向企业与内容团队，提供从关键词/选题、AI 内容生成、人工审核到多站点发布的完整流水线；并可选连接你自己的 Google Search Console 账号，监控搜索表现与收录、辅助内容优化决策。平台由 {{ $companyName }} 运营，托管于本域名 <b>{{ $siteUrl !== '' ? $siteUrl : url('/') }}</b>。</p>
        <p class="en">{{ $companyName }} serves businesses and content teams with an end-to-end pipeline — keyword & topic planning, AI content generation, human review, and multi-site publishing. You may optionally connect your own Google Search Console account to monitor search performance and indexing and inform content optimization. The platform is operated by {{ $companyName }} and hosted on this domain, <b>{{ $siteUrl !== '' ? $siteUrl : url('/') }}</b>.</p>

        <div class="grid">
            <div class="feat"><div class="ic">✍️</div><h3>AI 内容工程 · AI content engineering</h3><p>关键词/标题库 → AI 生成 → 审核 → 发布的完整流水线。 Keyword/title libraries → AI generation → review → publish.</p></div>
            <div class="feat"><div class="ic">📚</div><h3>知识库 RAG · Knowledge-base RAG</h3><p>注入企业知识并向量检索，生成更专业可信的内容。 Inject enterprise knowledge via vector retrieval for trustworthy output.</p></div>
            <div class="feat"><div class="ic">🌐</div><h3>多站点分发 · Multi-site distribution</h3><p>一键分发到 GeoFlow Agent 站点、WordPress、通用 HTTP API。 Publish to GeoFlow Agent sites, WordPress and generic HTTP APIs.</p></div>
            <div class="feat"><div class="ic">📈</div><h3>GSC 关键词回流 · GSC keyword reflow</h3><p>只读接入 Search Console，把搜索表现回流到内容优化。 Read-only Search Console access feeding metrics back into optimization.</p></div>
        </div>
    </section>

    <section class="block">
        <div class="data">
            <h2>本应用如何使用 Google 用户数据 · How {{ $companyName }} uses Google user data</h2>
            <p>仅当你主动授权时，{{ $companyName }} 以<b>只读</b>范围（<code>webmasters.readonly</code>）访问你在 Google Search Console 中已验证的以下数据：</p>
            <p class="en">Only after your explicit consent, {{ $companyName }} accesses the following data from your verified Google Search Console properties, on a <b>read-only</b> basis (<code>webmasters.readonly</code>):</p>
            <ul>
                <li>已验证站点列表 <span class="en">Your verified site list</span></li>
                <li>搜索表现：点击量、展示量、点击率、平均排名 <span class="en">Search performance: clicks, impressions, CTR, average position</span></li>
                <li>网址收录 / 编入索引状态 <span class="en">URL indexing / coverage status</span></li>
            </ul>
            <p><b>用途：</b>这些数据仅用于在你自己的控制台内展示与分析，并把关键词表现回流到你的内容优化决策。<b>不</b>用于广告、<b>不</b>出售、<b>不</b>用于训练机器学习/AI 模型；除为提供本服务所必需外不与第三方共享。你可随时在后台断开连接或在 Google 账号中撤销授权。</p>
            <p class="en"><b>Purpose:</b> this data is used only to display and analyze your own metrics inside your dashboard and to feed keyword performance back into your content decisions. It is <b>not</b> used for advertising, <b>not</b> sold, and <b>not</b> used to train ML/AI models; it is not shared with third parties except as required to provide the service. You may disconnect or revoke access at any time.</p>
            <p>{{ $companyName }} 对从 Google API 获取信息的使用遵守 <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a> 的 <b>Limited Use</b> 要求。<span class="en">{{ $companyName }}'s use of information received from Google APIs adheres to the Google API Services User Data Policy, including the Limited Use requirements.</span></p>
            <div class="pills">
                <span class="pill">webmasters.readonly · 只读</span>
                <span class="pill">Limited Use</span>
                <span class="pill">可随时断开 · Revocable</span>
            </div>
            <p class="privacy-link">完整说明见 <a href="{{ route('site.legal.privacy') }}"><b>隐私政策 · Privacy Policy</b></a>，及 <a href="{{ route('site.legal.terms') }}">服务条款 · Terms of Service</a>。</p>
        </div>
    </section>

    <footer class="foot">
        <div>© {{ $effectiveYear }} {{ $companyName }} · {{ $siteUrl !== '' ? $siteUrl : url('/') }}</div>
        <div class="links">
            <a href="{{ route('admin.login') }}">登录 Sign in</a>
            <a href="{{ route('site.legal.privacy') }}">隐私政策 Privacy</a>
            <a href="{{ route('site.legal.terms') }}">服务条款 Terms</a>
            @if($contactEmail !== '')<a href="mailto:{{ $contactEmail }}">联系我们 Contact</a>@endif
        </div>
    </footer>
</div>
</body>
</html>
