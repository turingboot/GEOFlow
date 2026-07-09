{{--
    平台产品落地页（自包含）：前台文章站关闭时的根路径首页，兼作 Google OAuth 应用首页。
    刻意不复用租户主题：内联样式、无外部资源依赖，任意开关状态都能稳定渲染。
--}}
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>{{ $companyName }} · GEO 优化平台</title>
    <meta name="description" content="{{ $companyName }} 是一个 GEO 优化平台：用 AI 规模化生产内容，并把 Google Search Console 的真实关键词表现回流到内容策略。">
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

        .hero { text-align:center; padding:56px 0 40px; }
        .eyebrow { display:inline-block; font-size:13px; color:var(--brand); background:var(--accent);
            border:1px solid var(--accent-line); border-radius:999px; padding:5px 14px; margin-bottom:20px; }
        .hero h1 { font-size:44px; line-height:1.15; margin:0 0 16px; letter-spacing:-.5px; }
        .hero p.lead { font-size:19px; color:var(--fg); max-width:680px; margin:0 auto 8px; }
        .hero p.lead-en { font-size:15px; color:var(--muted); max-width:640px; margin:0 auto 28px; }
        .cta-row { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }

        .grid { display:grid; grid-template-columns:repeat(2,1fr); gap:18px; margin:44px 0 8px; }
        .feat { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:24px; }
        .feat h3 { margin:0 0 8px; font-size:17px; }
        .feat p { margin:0; font-size:14.5px; color:var(--muted); }
        .feat .ic { font-size:22px; margin-bottom:10px; }

        .callout { background:var(--card); border:1px solid var(--line); border-radius:14px;
            padding:26px 28px; margin:34px 0; }
        .callout h2 { margin:0 0 8px; font-size:20px; }
        .callout p { margin:6px 0 0; color:var(--muted); font-size:14.5px; }
        .callout .pills { margin-top:14px; display:flex; gap:8px; flex-wrap:wrap; }
        .pill { font-size:12.5px; color:var(--brand); background:var(--accent); border:1px solid var(--accent-line);
            border-radius:999px; padding:4px 12px; }

        footer.foot { border-top:1px solid var(--line); margin-top:48px; padding:26px 0 48px;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
            color:var(--muted); font-size:13.5px; }
        footer.foot .links { display:flex; gap:18px; flex-wrap:wrap; }

        @media (max-width:640px) {
            .hero { padding:36px 0 28px; } .hero h1 { font-size:32px; }
            .hero p.lead { font-size:17px; } .grid { grid-template-columns:1fr; }
            .nav-links a.muted { display:none; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header class="nav">
        <div class="brand">{{ $companyName }}<span class="dot">.</span></div>
        <nav class="nav-links">
            <a class="muted" href="{{ route('site.legal.privacy') }}">隐私政策</a>
            <a class="muted" href="{{ route('site.legal.terms') }}">服务条款</a>
            <a class="btn btn-ghost" href="{{ route('admin.login') }}">登录</a>
        </nav>
    </header>

    <section class="hero">
        <span class="eyebrow">GEO 优化平台 · GEO Optimization Platform</span>
        <h1>{{ $companyName }}</h1>
        <p class="lead">用 AI 规模化生产 GEO 内容，并把 Google 搜索侧的真实关键词表现<b>回流</b>到你的内容生产与优化闭环。</p>
        <p class="lead-en">AI-scaled GEO content engineering, with real Google Search keyword signals fed back into your content strategy.</p>
        <div class="cta-row">
            <a class="btn btn-primary" href="{{ route('admin.login') }}">进入控制台</a>
            <a class="btn btn-ghost" href="{{ route('site.legal.privacy') }}">数据与隐私说明</a>
        </div>
    </section>

    <section class="grid">
        <div class="feat">
            <div class="ic">✍️</div>
            <h3>AI 内容工程</h3>
            <p>关键词库 / 标题库 → AI 生成 → 审核 → 发布的完整流水线，支持定时批量与多站点。</p>
        </div>
        <div class="feat">
            <div class="ic">📚</div>
            <h3>知识库 RAG</h3>
            <p>注入企业知识并做向量检索，生成更专业、更可信、更贴合品牌语境的内容。</p>
        </div>
        <div class="feat">
            <div class="ic">🌐</div>
            <h3>多站点分发</h3>
            <p>一键分发到 GeoFlow Agent 站点、WordPress、通用 HTTP API，覆盖多渠道曝光。</p>
        </div>
        <div class="feat">
            <div class="ic">📈</div>
            <h3>GSC 关键词回流</h3>
            <p>只读接入 Google Search Console，把搜索表现与收录情况回流，反哺内容优化决策。</p>
        </div>
    </section>

    <section class="callout">
        <h2>为什么要连接 Google Search Console？</h2>
        <p>连接后，{{ $companyName }} 仅以<b>只读</b>范围读取你已验证站点的搜索表现（点击、展示、点击率、排名）与收录状态，
            用于在你自己的控制台内做分析、并把关键词表现回流到内容策略。数据不用于广告、不出售、不用于训练模型。</p>
        <div class="pills">
            <span class="pill">webmasters.readonly · 只读</span>
            <span class="pill">遵守 Google API Limited Use</span>
            <span class="pill">可随时断开授权</span>
        </div>
        <p style="margin-top:14px;">详见 <a href="{{ route('site.legal.privacy') }}">隐私政策</a> 与 <a href="{{ route('site.legal.terms') }}">服务条款</a>。</p>
    </section>

    <footer class="foot">
        <div>© {{ $effectiveYear }} {{ $companyName }}</div>
        <div class="links">
            <a href="{{ route('admin.login') }}">登录</a>
            <a href="{{ route('site.legal.privacy') }}">隐私政策</a>
            <a href="{{ route('site.legal.terms') }}">服务条款</a>
            @if($contactEmail !== '')<a href="mailto:{{ $contactEmail }}">联系我们</a>@endif
        </div>
    </footer>
</div>
</body>
</html>
