<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.login.title') }} — {{ $adminSiteName }}</title>
    <script src="{{ asset('js/tailwindcss.play-cdn.js') }}"></script>
    <script>
        tailwind.config = { theme: { extend: {
            colors: {
                blue: { 50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81', 950: '#1e1b4b' },
                gray: { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a', 950: '#020617' },
            },
            fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', 'sans-serif'] },
            borderRadius: { DEFAULT: '0.5rem', md: '0.625rem', lg: '0.75rem', xl: '1rem', '2xl': '1.25rem' },
        } } };
    </script>
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    <style>
        body {
            background:
                radial-gradient(900px circle at 15% 8%, rgba(79, 70, 229, 0.30), transparent 45%),
                radial-gradient(720px circle at 85% 92%, rgba(99, 102, 241, 0.18), transparent 45%),
                linear-gradient(180deg, #0f172a 0%, #020617 100%);
            min-height: 100vh;
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
        }
        .login-form {
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 30px 70px rgba(2, 6, 23, 0.45);
        }
        .login-badge {
            background: linear-gradient(180deg, #6366f1 0%, #4338ca 100%);
        }
        .initial-admin-hint {
            background: linear-gradient(180deg, rgba(238, 242, 255, 0.98) 0%, rgba(255, 255, 255, 0.94) 100%);
        }
    </style>
</head>
<body class="overflow-hidden">
<div class="fixed right-4 top-4 z-50">
    <select onchange="window.location.href=this.value" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-600 shadow-sm">
        @foreach (\App\Support\AdminWeb::supportedLocales() as $localeCode => $localeLabel)
            <option value="{{ route('admin.locale.switch', ['locale' => $localeCode]) }}" @selected(app()->getLocale() === $localeCode)>
                {{ $localeLabel }}
            </option>
        @endforeach
    </select>
</div>
<div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4">
    <div class="rounded-2xl p-8 login-form">
        <div class="text-center mb-8">
            <div class="login-badge w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="shield-check" class="w-8 h-8 text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ __('admin.login.title') }}</h1>
            <p class="text-gray-600">{{ __('admin.login.subtitle', ['site_name' => $adminSiteName]) }}</p>
        </div>
        @if (session('message'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                {{ session('message') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                {{ $errors->first() }}
            </div>
        @endif
        @if (!empty($initialAdminHint['enabled']))
            <div id="initial-admin-hint" class="initial-admin-hint mb-6 hidden rounded-xl border border-blue-200 p-4 text-sm text-gray-700 shadow-sm" data-storage-key="{{ $initialAdminHint['storage_key'] ?? 'geoflow.initial-admin-hint' }}">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white">
                        <i data-lucide="key-round" class="h-5 w-5"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900">{{ __('admin.login.first_login_hint_title') }}</p>
                                <p class="mt-1 leading-6 text-gray-600">{{ __('admin.login.first_login_hint_intro') }}</p>
                            </div>
                            <button type="button" data-dismiss-initial-admin-hint class="rounded-md p-1 text-gray-400 hover:bg-white hover:text-gray-600" aria-label="{{ __('admin.login.first_login_dismiss') }}">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>
                        <div class="mt-3 grid gap-2 rounded-lg border border-blue-100 bg-white/80 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-gray-500">{{ __('admin.login.first_login_username') }}</span>
                                <code class="rounded bg-gray-100 px-2 py-1 font-mono text-xs text-gray-900">{{ $initialAdminHint['username'] ?? '' }}</code>
                            </div>
                            @if (($initialAdminHint['mode'] ?? '') === 'known')
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-gray-500">{{ __('admin.login.first_login_password') }}</span>
                                    <code class="rounded bg-gray-100 px-2 py-1 font-mono text-xs text-gray-900">{{ $initialAdminHint['password'] ?? '' }}</code>
                                </div>
                            @else
                                <div class="rounded-lg bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">
                                    {{ __('admin.login.first_login_password_from_log') }}
                                </div>
                            @endif
                        </div>
                        <p class="mt-3 text-xs leading-5 text-red-600">{{ __('admin.login.first_login_security') }}</p>
                    </div>
                </div>
            </div>
        @endif
        <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-6">
            @csrf
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">{{ __('admin.login.username') }}</label>
                <input type="text" id="username" name="username" required value="{{ old('username') }}"
                       class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="{{ __('admin.login.username_placeholder') }}" autocomplete="username">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">{{ __('admin.login.password') }}</label>
                <input type="password" id="password" name="password" required
                       class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="{{ __('admin.login.password_placeholder') }}" autocomplete="current-password">
            </div>
            <input type="hidden" name="remember" value="0">
            <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-white/70 px-3 py-3 text-sm text-gray-600">
                <span class="flex items-center gap-2">
                    <input type="checkbox" name="remember" value="1" checked class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span>{{ __('admin.login.remember_30_days') }}</span>
                </span>
                <span class="text-xs text-gray-400">{{ __('admin.login.remember_30_days_hint') }}</span>
            </label>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg">
                {{ __('admin.login.submit') }}
            </button>
        </form>
    </div>
    <div class="text-center mt-6">
        <a href="{{ url('/') }}" class="text-slate-300 hover:text-white text-sm">{{ __('admin.login.back_home') }}</a>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const initialHint = document.getElementById('initial-admin-hint');
        if (initialHint) {
            const storageKey = initialHint.getAttribute('data-storage-key') || 'geoflow.initial-admin-hint';
            let shouldShow = true;

            try {
                shouldShow = window.localStorage.getItem(storageKey) !== 'seen';
                if (shouldShow) {
                    window.localStorage.setItem(storageKey, 'seen');
                }
            } catch (error) {
                shouldShow = true;
            }

            if (shouldShow) {
                initialHint.classList.remove('hidden');
            }

            initialHint.querySelector('[data-dismiss-initial-admin-hint]')?.addEventListener('click', function () {
                initialHint.classList.add('hidden');
                try {
                    window.localStorage.setItem(storageKey, 'seen');
                } catch (error) {}
            });
        }

        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
</script>
</body>
</html>
