@extends('admin.layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ __('admin.gsc.page_title') }}</h1>
                <p class="admin-hero-sub">{{ __('admin.gsc.page_subtitle') }}</p>
            </div>
            <div class="admin-hero-actions">
                @if ($isSuperAdmin)
                    <a href="{{ route('admin.google-search-console.settings') }}" class="admin-btn admin-btn-secondary">
                        <i data-lucide="settings" class="h-4 w-4"></i>{{ __('admin.gsc.button.settings') }}
                    </a>
                @endif
                <a href="{{ route('admin.google-search-console.service-account') }}" class="admin-btn admin-btn-secondary">
                    <i data-lucide="shield-check" class="h-4 w-4"></i>{{ __('admin.gsc.button.add_service_account') }}
                </a>
                @if ($oauthConfigured)
                    <a href="{{ route('admin.google-search-console.connect') }}" class="admin-btn admin-btn-primary">
                        <i data-lucide="link" class="h-4 w-4"></i>{{ __('admin.gsc.button.connect') }}
                    </a>
                @endif
            </div>
        </div>

        @unless ($oauthConfigured)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ __('admin.gsc.notice.oauth_not_configured') }}
                @if ($isSuperAdmin)
                    <a href="{{ route('admin.google-search-console.settings') }}" class="font-semibold underline">{{ __('admin.gsc.button.settings') }}</a>
                @endif
            </div>
        @endunless

        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div class="admin-vstat grad-indigo">
                <span class="admin-vstat-icon"><i data-lucide="link" class="h-5 w-5"></i></span>
                <div class="min-w-0"><div class="admin-vstat-label">{{ __('admin.gsc.stats.connections') }}</div><div class="admin-vstat-value">{{ $stats['connections'] }}</div></div>
            </div>
            <div class="admin-vstat grad-sky">
                <span class="admin-vstat-icon"><i data-lucide="globe" class="h-5 w-5"></i></span>
                <div class="min-w-0"><div class="admin-vstat-label">{{ __('admin.gsc.stats.properties') }}</div><div class="admin-vstat-value">{{ $stats['properties'] }}</div></div>
            </div>
            <div class="admin-vstat grad-emerald">
                <span class="admin-vstat-icon"><i data-lucide="circle-check" class="h-5 w-5"></i></span>
                <div class="min-w-0"><div class="admin-vstat-label">{{ __('admin.gsc.stats.active') }}</div><div class="admin-vstat-value">{{ $stats['active'] }}</div></div>
            </div>
        </div>

        {{-- 连接 --}}
        <div class="admin-card overflow-hidden">
            <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.gsc.section.connections') }}</span></div>
            @if ($connections->isEmpty())
                <div class="p-6 text-sm text-gray-500">{{ __('admin.gsc.empty.connections') }}</div>
            @else
                <table class="admin-table">
                    <thead><tr><th>{{ __('admin.gsc.field.connection') }}</th><th>{{ __('admin.gsc.field.auth_type') }}</th><th>{{ __('admin.gsc.field.sites_count') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($connections as $connection)
                            <tr>
                                <td><span class="font-semibold">{{ $connection->name }}</span></td>
                                <td><span class="admin-badge is-neutral">{{ __('admin.gsc.auth.'.$connection->provider) }}</span></td>
                                <td>{{ $connection->properties->count() }}</td>
                                <td class="text-right">
                                    <a href="{{ route('admin.google-search-console.sites', $connection->id) }}" class="text-blue-600 hover:underline">{{ __('admin.gsc.button.pick_sites') }}</a>
                                    <form method="POST" action="{{ route('admin.google-search-console.disconnect', $connection->id) }}" class="ml-3 inline" onsubmit="return confirm(@js(__('admin.gsc.confirm.disconnect')))">
                                        @csrf
                                        <button type="submit" class="text-red-600 hover:underline">{{ __('admin.gsc.button.disconnect') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- 被监控站点 --}}
        <div class="admin-card overflow-hidden">
            <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.gsc.section.properties') }}</span></div>
            @if ($properties->isEmpty())
                <div class="p-6 text-sm text-gray-500">{{ __('admin.gsc.empty.properties') }}</div>
            @else
                <table class="admin-table">
                    <thead><tr><th>{{ __('admin.gsc.field.name') }}</th><th>{{ __('admin.gsc.field.schedule') }}</th><th>{{ __('admin.gsc.snapshot.title') }}</th></tr></thead>
                    <tbody>
                        @foreach ($properties as $property)
                            <tr>
                                <td><a href="{{ route('admin.google-search-console.show', $property->id) }}" class="font-semibold text-blue-600 hover:underline">{{ $property->site_url }}</a></td>
                                <td><span class="admin-badge is-neutral">{{ __('admin.gsc.schedule.'.($property->schedule ?: 'manual')) }}</span></td>
                                <td>
                                    @if ($property->latestSnapshot)
                                        <span class="admin-badge {{ $property->latestSnapshot->status === 'success' ? 'is-success' : ($property->latestSnapshot->status === 'failed' ? 'is-danger' : 'is-warning') }}">{{ __('admin.gsc.snapshot.'.$property->latestSnapshot->status) }}</span>
                                        <span class="ml-1 text-xs text-gray-500">{{ optional($property->latestSnapshot->ran_at)->format('Y-m-d H:i') }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
