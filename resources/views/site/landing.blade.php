{{--
    平台产品落地页（自包含，纯英文，官网布局）：前台文章站关闭时的根路径首页，兼作 Google OAuth 应用首页。
    需满足 Google「应用首页」验收：准确标识品牌、充分描述功能、透明说明数据用途、
    含隐私政策链接（footer + 数据专区 + hero，与同意屏幕逐字一致）、无需登录即可访问。
    文案与上一版逐字一致，仅布局与样式改为官网风格。
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
            --bg:#ffffff; --bg-alt:#f7f8fa; --fg:#1f2329; --muted:#6b7280; --card:#ffffff; --line:#e5e7eb;
            --brand:#2563eb; --brand-fg:#fff; --accent:#eff6ff; --accent-line:#bfdbfe;
            --hero-a:#eff4ff; --hero-b:#ffffff; --footer-bg:#0f1115; --footer-fg:#c7cedb; --footer-muted:#8b94a3; }
        @media (prefers-color-scheme: dark) { :root {
            --bg:#0f1115; --bg-alt:#141821; --fg:#e7ebf1; --muted:#9aa4b2; --card:#171a21; --line:#2a2f3a;
            --brand:#3b82f6; --accent:#141c2e; --accent-line:#29406a;
            --hero-a:#151b2b; --hero-b:#0f1115; --footer-bg:#0a0c11; --footer-fg:#c7cedb; --footer-muted:#7a8493; } }
        * { box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body { margin:0; background:var(--bg); color:var(--fg); line-height:1.7;
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            -webkit-font-smoothing:antialiased; }
        a { color:var(--brand); text-decoration:none; }
        a:hover { text-decoration:underline; }
        .container { max-width:1080px; margin:0 auto; padding:0 24px; }
        .brand { font-weight:800; font-size:20px; letter-spacing:.2px; color:var(--fg); }
        .brand .dot { color:var(--brand); }

        .btn { display:inline-block; border-radius:10px; padding:11px 22px; font-size:15px; font-weight:600; }
        .btn-primary { background:var(--brand); color:var(--brand-fg); }
        .btn-primary:hover { text-decoration:none; opacity:.92; }
        .btn-ghost { border:1px solid var(--line); color:var(--fg); background:transparent; }
        .btn-ghost:hover { text-decoration:none; border-color:var(--brand); }

        /* Sticky header */
        .site-header { position:sticky; top:0; z-index:20; background:color-mix(in srgb, var(--bg) 82%, transparent);
            backdrop-filter:saturate(160%) blur(10px); border-bottom:1px solid var(--line); }
        .header-inner { display:flex; align-items:center; justify-content:space-between; height:64px; }
        .nav { display:flex; align-items:center; gap:26px; font-size:14.5px; }
        .nav a.link { color:var(--muted); }
        .nav a.link:hover { color:var(--fg); text-decoration:none; }

        /* Hero */
        .hero { background:linear-gradient(180deg, var(--hero-a), var(--hero-b)); border-bottom:1px solid var(--line);
            text-align:center; padding:76px 0 64px; }
        .eyebrow { display:inline-block; font-size:13px; color:var(--brand); background:var(--accent);
            border:1px solid var(--accent-line); border-radius:999px; padding:6px 15px; margin-bottom:22px; }
        .hero h1 { font-size:52px; line-height:1.08; margin:0 0 18px; letter-spacing:-1px; }
        .hero p.lead { font-size:19px; color:var(--fg); max-width:760px; margin:0 auto 12px; }
        .hero p.purpose { font-size:16px; color:var(--muted); max-width:720px; margin:0 auto 30px; }
        .cta-row { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }

        /* Sections */
        section.section { padding:64px 0; }
        section.section-alt { background:var(--bg-alt); border-top:1px solid var(--line); border-bottom:1px solid var(--line); }
        .section h2 { font-size:28px; letter-spacing:-.4px; margin:0 0 16px; }
        .section > .container > p { font-size:16px; margin:10px 0; max-width:840px; }

        .grid { display:grid; grid-template-columns:repeat(2,1fr); gap:20px; margin-top:32px; }
        .feat { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:24px;
            transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
        .feat:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(2,8,20,.08); border-color:var(--accent-line); }
        .feat .ic { display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px;
            border-radius:12px; background:var(--accent); border:1px solid var(--accent-line); font-size:22px; margin-bottom:14px; }
        .feat h3 { margin:0 0 6px; font-size:17px; }
        .feat p { margin:0; font-size:14.5px; color:var(--muted); }

        /* Data-use card */
        .data { background:var(--card); border:1px solid var(--accent-line); border-radius:18px; padding:34px 36px;
            box-shadow:0 8px 30px rgba(2,8,20,.05); }
        .data h2 { margin:0 0 8px; }
        .data p { font-size:15px; margin:10px 0; }
        .data ul { margin:14px 0; padding-left:20px; }
        .data li { font-size:14.5px; margin:6px 0; }
        .pills { margin-top:18px; display:flex; gap:8px; flex-wrap:wrap; }
        .pill { font-size:12.5px; color:var(--brand); background:var(--accent); border:1px solid var(--accent-line);
            border-radius:999px; padding:5px 13px; }
        .privacy-link { margin-top:18px; font-size:15px; }

        /* Footer */
        .site-footer { background:var(--footer-bg); color:var(--footer-fg); padding:52px 0 40px; }
        .footer-grid { display:grid; grid-template-columns:1.6fr 1fr 1fr; gap:32px; }
        .site-footer .brand { color:#fff; }
        .footer-brand .copyright { margin-top:14px; color:var(--footer-muted); font-size:13px; }
        .footer-col h4 { font-size:12px; letter-spacing:.08em; text-transform:uppercase; color:var(--footer-muted);
            margin:0 0 14px; font-weight:700; }
        .footer-col a { display:block; color:var(--footer-fg); font-size:14.5px; margin:9px 0; }
        .footer-col a:hover { color:#fff; text-decoration:none; }

        @media (max-width:760px) {
            .hero { padding:52px 0 44px; } .hero h1 { font-size:36px; }
            .hero p.lead { font-size:17px; } .grid { grid-template-columns:1fr; }
            .section { padding:48px 0; } .section h2 { font-size:23px; } .data { padding:26px 22px; }
            .nav a.link { display:none; } .footer-grid { grid-template-columns:1fr 1fr; }
            .footer-brand { grid-column:1 / -1; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <div class="brand">{{ $companyName }}<span class="dot">.</span></div>
            <nav class="nav">
                <a class="link" href="#about">What it does</a>
                <a class="link" href="#data">Data use</a>
                <a class="btn btn-ghost" href="{{ route('admin.login') }}">Sign in</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <span class="eyebrow">GEO Optimization Platform</span>
                <h1>{{ $companyName }}</h1>
                <p class="lead">{{ $companyName }} is a web application for creating website content at scale with AI and measuring how that content performs in Google Search.</p>
                <p class="purpose"><b>The purpose of {{ $companyName }}</b> is to help businesses and content teams turn real Google Search Console data — the keywords and pages that actually bring traffic — into better, higher-performing content, all in one dashboard.</p>
                <div class="cta-row">
                    <a class="btn btn-primary" href="{{ route('admin.login') }}">Open console</a>
                    <a class="btn btn-ghost" href="{{ route('site.legal.privacy') }}">Privacy &amp; data</a>
                </div>
            </div>
        </section>

        <section id="about" class="section">
            <div class="container">
                <h2>What {{ $companyName }} is for</h2>
                <p><b>The purpose of {{ $companyName }} is to help businesses and content teams plan, generate, review and publish website content at scale, and then improve that content using real search-performance data from Google Search Console.</b> The workflow covers keyword and topic planning, AI-assisted content generation, human review, and multi-site publishing to websites you own.</p>
                <p>To close the loop, you can optionally connect your own Google Search Console account. {{ $companyName }} then reads — on a strictly <b>read-only</b> basis — your verified sites, search performance (clicks, impressions, CTR, average position) and indexing status, and shows them in your own dashboard. This lets you see which keywords bring real traffic and feed that back into your content optimization. We request no write access, and this data is shown only to you. The platform is operated by {{ $companyName }} and hosted on this domain, <b>{{ $siteUrl !== '' ? $siteUrl : url('/') }}</b>.</p>

                <div class="grid">
                    <div class="feat"><div class="ic">✍️</div><h3>AI content engineering</h3><p>An end-to-end pipeline: keyword & title libraries → AI generation → human review → publishing.</p></div>
                    <div class="feat"><div class="ic">📚</div><h3>Knowledge-base RAG</h3><p>Inject enterprise knowledge with vector retrieval to produce more professional, trustworthy content.</p></div>
                    <div class="feat"><div class="ic">🌐</div><h3>Multi-site distribution</h3><p>Publish in one click to GeoFlow Agent sites, WordPress, and generic HTTP APIs.</p></div>
                    <div class="feat"><div class="ic">📈</div><h3>GSC keyword reflow</h3><p>Read-only Search Console access feeds search performance back into content optimization.</p></div>
                </div>
            </div>
        </section>

        <section id="data" class="section section-alt">
            <div class="container">
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
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div class="footer-brand">
                <div class="brand">{{ $companyName }}<span class="dot">.</span></div>
                <div class="copyright">© {{ $effectiveYear }} {{ $companyName }} · {{ $siteUrl !== '' ? $siteUrl : url('/') }}</div>
            </div>
            <div class="footer-col">
                <h4>Product</h4>
                <a href="{{ route('admin.login') }}">Open console</a>
                <a href="{{ route('admin.login') }}">Sign in</a>
            </div>
            <div class="footer-col">
                <h4>Legal</h4>
                <a href="{{ route('site.legal.privacy') }}">Privacy Policy</a>
                <a href="{{ route('site.legal.terms') }}">Terms of Service</a>
                @if($contactEmail !== '')<a href="mailto:{{ $contactEmail }}">Contact</a>@endif
            </div>
        </div>
    </footer>
</body>
</html>
