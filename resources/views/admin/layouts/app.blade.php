@php
    $adminBrandName = \App\Support\AdminWeb::siteName();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@isset($pageTitle){{ $pageTitle }} — @endisset{{ $adminBrandName }}</title>
    <script src="{{ asset('js/tailwindcss.play-cdn.js') }}"></script>
    <script>
        /* 全新后台设计令牌:主色 indigo、中性 slate、Inter 字体、圆润圆角、软阴影。
           运行时覆盖 Play CDN 主题,使既有工具类(bg-blue-600/text-gray-900/...)整体换肤,不改任何 Blade。 */
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        blue: { 50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81', 950: '#1e1b4b' },
                        gray: { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a', 950: '#020617' },
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', 'sans-serif'],
                    },
                    borderRadius: { DEFAULT: '0.5rem', md: '0.625rem', lg: '0.75rem', xl: '1rem', '2xl': '1.25rem' },
                    boxShadow: {
                        sm: '0 1px 2px 0 rgb(15 23 42 / 0.04)',
                        DEFAULT: '0 1px 3px 0 rgb(15 23 42 / 0.06), 0 1px 2px -1px rgb(15 23 42 / 0.06)',
                        md: '0 6px 16px -6px rgb(15 23 42 / 0.12)',
                        lg: '0 12px 28px -10px rgb(15 23 42 / 0.16)',
                    },
                },
            },
        };
    </script>
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
    @stack('styles')
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">
    @include('admin.partials.sidebar', [
        'adminBrandName' => $adminBrandName,
        'activeMenu' => $activeMenu ?? '',
    ])
    <div class="flex min-w-0 flex-1 flex-col">
        @include('admin.partials.topbar', [
            'adminBrandName' => $adminBrandName,
            'adminSiteName' => $adminSiteName ?? $adminBrandName,
            'pageTitle' => $pageTitle ?? '',
        ])
        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto w-full max-w-[1600px]">
                @if (session('message'))
                    <div class="admin-flash-alert mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                        <span class="block sm:inline">{{ session('message') }}</span>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="admin-flash-alert mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        @foreach ($errors->all() as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
        @include('admin.partials.footer')
    </div>
</div>
@include('admin.partials.welcome-modal')
@stack('scripts')
</body>
</html>
