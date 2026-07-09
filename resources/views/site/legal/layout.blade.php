{{--
    平台级法律页面布局（自包含）。
    刻意不复用租户主题 layout：内联样式、无外部资源依赖，保证在任意租户/前台开关状态下都能稳定渲染，
    供 Google OAuth 应用审核抓取。
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>@yield('title') · {{ $companyName }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f7f8fa;
            color: #1f2329;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC",
                "Hiragino Sans GB", "Microsoft YaHei", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.75;
            -webkit-font-smoothing: antialiased;
        }
        .legal-wrap { max-width: 820px; margin: 0 auto; padding: 40px 20px 72px; }
        .legal-top { margin-bottom: 28px; }
        .legal-top a { color: #2563eb; text-decoration: none; font-size: 14px; }
        .legal-top a:hover { text-decoration: underline; }
        .legal-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 40px 44px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        h1 { font-size: 28px; line-height: 1.3; margin: 0 0 6px; }
        .legal-meta { color: #6b7280; font-size: 13px; margin: 0 0 28px; }
        h2 { font-size: 19px; margin: 34px 0 10px; padding-top: 6px; }
        h3 { font-size: 15px; margin: 20px 0 6px; color: #374151; }
        p, li { font-size: 15px; }
        ul { padding-left: 22px; margin: 8px 0; }
        li { margin: 4px 0; }
        a { color: #2563eb; word-break: break-word; }
        code, .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px; background: #f1f3f5; padding: 1px 5px; border-radius: 4px;
        }
        .en { color: #4b5563; }
        .callout {
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;
            padding: 14px 18px; margin: 14px 0;
        }
        .legal-footer { margin-top: 40px; color: #6b7280; font-size: 13px; text-align: center; }
        hr { border: none; border-top: 1px solid #e5e7eb; margin: 20px 0; }
        @media (max-width: 640px) { .legal-card { padding: 26px 20px; } h1 { font-size: 23px; } }
        @media (prefers-color-scheme: dark) {
            body { background: #0f1115; color: #e5e7eb; }
            .legal-card { background: #171a21; border-color: #2a2f3a; box-shadow: none; }
            h3 { color: #cbd5e1; }
            .legal-meta, .en, .legal-footer { color: #9aa4b2; }
            code, .mono { background: #232733; }
            .callout { background: #172033; border-color: #29406a; }
            hr { border-top-color: #2a2f3a; }
        }
    </style>
</head>
<body>
    <div class="legal-wrap">
        <div class="legal-top">
            <a href="{{ $siteUrl !== '' ? $siteUrl : url('/') }}">← {{ $companyName }}</a>
        </div>
        <article class="legal-card">
            @yield('content')
        </article>
        <div class="legal-footer">
            © {{ strlen($effectiveDate) >= 4 ? substr($effectiveDate, 0, 4) : $effectiveDate }} {{ $companyName }}
        </div>
    </div>
</body>
</html>
