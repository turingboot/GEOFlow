{{--
    平台产品落地页（自包含，纯英文）：前台文章站关闭时的根路径首页，兼作 Google OAuth 应用首页。
    需满足 Google「应用首页」验收：准确标识品牌、充分描述功能、透明说明数据用途、
    含隐私政策链接（与同意屏幕逐字一致）、无需登录即可访问。
    刻意不复用租户主题：内联样式、无外部资源依赖，任意开关状态都能稳定渲染。
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>{{ $companyName }} · GEO Optimization Platform</title>
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
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
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

        .hero { text-align:center; padding:52px 0 20px; }
        .eyebrow { display:inline-block; font-size:13px; color:var(--brand); background:var(--accent);
            border:1px solid var(--accent-line); border-radius:999px; padding:5px 14px; margin-bottom:20px; }
        .hero h1 { font-size:46px; line-height:1.12; margin:0 0 16px; letter-spacing:-.5px; }
        .hero p.lead { font-size:18px; color:var(--fg); max-width:730px; margin:0 auto 10px; }
        .hero p.purpose { font-size:16px; color:var(--muted); max-width:700px; margin:0 auto 26px; }
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
        .pills { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
        .pill { font-size:12.5px; color:var(--brand); background:var(--accent); border:1px solid var(--accent-line);
            border-radius:999px; padding:4px 12px; }
        .privacy-link { margin-top:16px; font-size:15px; }

        footer.foot { border-top:1px solid var(--line); margin-top:44px; padding:26px 0 48px;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
            color:var(--muted); font-size:13.5px; }
        footer.foot .links { display:flex; gap:18px; flex-wrap:wrap; }

        @media (max-width:640px) {
            .hero { padding:32px 0 12px; } .hero h1 { font-size:33px; }
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
            <a class="muted" href="{{ route('site.legal.privacy') }}">Privacy</a>
            <a class="muted" href="{{ route('site.legal.terms') }}">Terms</a>
            <a class="btn btn-ghost" href="{{ route('admin.login') }}">Sign in</a>
        </nav>
    </header>

    <section class="hero">
        <span class="eyebrow">GEO Optimization Platform</span>
        <h1>{{ $companyName }}</h1>
        <p class="lead">{{ $companyName }} is a web application for creating website content at scale with AI and measuring how that content performs in Google Search.</p>
        <p class="purpose"><b>The purpose of {{ $companyName }}</b> is to help businesses and content teams turn real Google Search Console data — the keywords and pages that actually bring traffic — into better, higher-performing content, all in one dashboard.</p>
        <div class="cta-row">
            <a class="btn btn-primary" href="{{ route('admin.login') }}">Open console</a>
            <a class="btn btn-ghost" href="{{ route('site.legal.privacy') }}">Privacy &amp; data</a>
        </div>
    </section>

    <section class="block">
        <h2>What {{ $companyName }} is for</h2>
        <p><b>The purpose of {{ $companyName }} is to help businesses and content teams plan, generate, review and publish website content at scale, and then improve that content using real search-performance data from Google Search Console.</b> The workflow covers keyword and topic planning, AI-assisted content generation, human review, and multi-site publishing to websites you own.</p>
        <p>To close the loop, you can optionally connect your own Google Search Console account. {{ $companyName }} then reads — on a strictly <b>read-only</b> basis — your verified sites, search performance (clicks, impressions, CTR, average position) and indexing status, and shows them in your own dashboard. This lets you see which keywords bring real traffic and feed that back into your content optimization. We request no write access, and this data is shown only to you. The platform is operated by {{ $companyName }} and hosted on this domain, <b>{{ $siteUrl !== '' ? $siteUrl : url('/') }}</b>.</p>

        <div class="grid">
            <div class="feat"><div class="ic">✍️</div><h3>AI content engineering</h3><p>An end-to-end pipeline: keyword & title libraries → AI generation → human review → publishing.</p></div>
            <div class="feat"><div class="ic">📚</div><h3>Knowledge-base RAG</h3><p>Inject enterprise knowledge with vector retrieval to produce more professional, trustworthy content.</p></div>
            <div class="feat"><div class="ic">🌐</div><h3>Multi-site distribution</h3><p>Publish in one click to GeoFlow Agent sites, WordPress, and generic HTTP APIs.</p></div>
            <div class="feat"><div class="ic">📈</div><h3>GSC keyword reflow</h3><p>Read-only Search Console access feeds search performance back into content optimization.</p></div>
        </div>
    </section>

    <section class="block">
        <div class="data">
            <h2>How {{ $companyName }} uses Google user data</h2>
            <p>Only after your explicit consent, {{ $companyName }} accesses the following data from your verified Google Search Console properties, on a <b>read-only</b> basis (<code>webmasters.readonly</code>):</p>
            <ul>
                <li>Your list of verified sites</li>
                <li>Search performance: clicks, impressions, CTR, and average position</li>
                <li>URL indexing / coverage status</li>
            </ul>
            <p><b>Purpose:</b> this data is used only to display and analyze your own metrics inside your dashboard, and to feed keyword performance back into your content decisions. It is <b>not</b> used for advertising, <b>not</b> sold, and <b>not</b> used to train machine-learning or AI models. It is not shared with third parties except as required to provide the service. You can disconnect or revoke access at any time.</p>
            <p>{{ $companyName }}'s use of information received from Google APIs adheres to the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the <b>Limited Use</b> requirements.</p>
            <div class="pills">
                <span class="pill">webmasters.readonly · read-only</span>
                <span class="pill">Limited Use</span>
                <span class="pill">Revocable anytime</span>
            </div>
            <p class="privacy-link">Read the full <a href="{{ route('site.legal.privacy') }}"><b>Privacy Policy</b></a> and <a href="{{ route('site.legal.terms') }}">Terms of Service</a>.</p>
        </div>
    </section>

    <footer class="foot">
        <div>© {{ $effectiveYear }} {{ $companyName }} · {{ $siteUrl !== '' ? $siteUrl : url('/') }}</div>
        <div class="links">
            <a href="{{ route('admin.login') }}">Sign in</a>
            <a href="{{ route('site.legal.privacy') }}">Privacy Policy</a>
            <a href="{{ route('site.legal.terms') }}">Terms of Service</a>
            @if($contactEmail !== '')<a href="mailto:{{ $contactEmail }}">Contact</a>@endif
        </div>
    </footer>
</div>
</body>
</html>
