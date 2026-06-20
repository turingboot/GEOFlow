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
    {{-- Precompiled admin Tailwind (Tavix-blue tokens baked in) — replaces the runtime Play CDN
         so styles apply on first paint with no FOUC / sidebar flicker. Rebuild with:
         npx tailwindcss@3.4.17 -c tailwind.admin.config.js -i resources/css/admin.tailwind.css -o public/assets/css/admin-tailwind.css --minify --}}
    <link rel="stylesheet" href="{{ asset('assets/css/admin-tailwind.css') }}?v={{ @filemtime(public_path('assets/css/admin-tailwind.css')) ?: config('geoflow.app_version', '2.0') }}">
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}?v={{ @filemtime(public_path('assets/css/admin.css')) ?: config('geoflow.app_version', '2.0') }}">
    @stack('styles')
</head>
<body class="admin-shell">
<div class="flex h-screen overflow-hidden">
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
        <main class="flex-1 overflow-y-auto px-4 py-6 sm:px-6 lg:px-8">
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
@vite('resources/js/app.js')
@stack('scripts')
</body>
</html>
