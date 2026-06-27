@extends('admin.layouts.app')

@php
    $connection = $property->connection;
    $sitemapStats = $latestSitemap && is_array($latestSitemap->stats) ? $latestSitemap->stats : [];
@endphp

@section('content')
    <div class="space-y-6">
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ $property->name }}</h1>
                <p class="admin-hero-sub"><span class="font-mono text-xs">{{ $property->site_url }}</span></p>
            </div>
            <div class="admin-hero-actions">
                <form method="POST" action="{{ route('admin.google-search-console.fetch', $property->id) }}">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>{{ __('admin.gsc.button.fetch') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.google-search-console.remove', $property->id) }}" onsubmit="return confirm(@js(__('admin.gsc.confirm.remove')))">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn-secondary">
                        <i data-lucide="trash-2" class="h-4 w-4"></i>{{ __('admin.gsc.button.remove') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card p-6">
            <div class="text-sm text-gray-700">
                {{ __('admin.gsc.field.connection') }}：<span class="font-semibold">{{ optional($connection)->name ?? '—' }}</span>
                <span class="admin-badge is-neutral ml-2">{{ $connection ? __('admin.gsc.auth.'.$connection->provider) : '—' }}</span>
            </div>
        </div>

        {{-- 收录概览（sitemap） --}}
        <div class="admin-card p-6">
            <div class="mb-3 admin-card-title">{{ __('admin.gsc.section.indexing') }}</div>
            @if ($latestSitemap && $latestSitemap->status === 'success')
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div><div class="text-xs text-gray-500">{{ __('admin.gsc.indexing.submitted') }}</div><div class="text-2xl font-semibold">{{ (int) ($sitemapStats['submitted'] ?? 0) }}</div></div>
                    <div><div class="text-xs text-gray-500">{{ __('admin.gsc.indexing.indexed') }}</div><div class="text-2xl font-semibold text-emerald-600">{{ (int) ($sitemapStats['indexed'] ?? 0) }}</div></div>
                    <div><div class="text-xs text-gray-500">{{ __('admin.gsc.indexing.sitemaps') }}</div><div class="text-2xl font-semibold">{{ (int) ($sitemapStats['sitemaps'] ?? 0) }}</div></div>
                    <div><div class="text-xs text-gray-500">{{ __('admin.gsc.snapshot.title') }}</div><div class="text-xs text-gray-500">{{ optional($latestSitemap->ran_at)->format('Y-m-d H:i') }}</div></div>
                </div>
            @else
                <p class="text-sm text-gray-500">{{ __('admin.gsc.indexing.empty') }}</p>
            @endif
        </div>

        {{-- 搜索表现 --}}
        <div class="admin-card overflow-hidden">
            <div class="admin-card-head">
                <span class="admin-card-title">{{ __('admin.gsc.section.search') }}</span>
                @if ($latestSearch)<span class="text-xs text-gray-500">{{ optional($latestSearch->ran_at)->format('Y-m-d H:i') }}</span>@endif
            </div>
            @if ($metrics->isEmpty())
                <div class="p-6 text-sm text-gray-500">{{ __('admin.gsc.search.empty') }}</div>
            @else
                <table class="admin-table">
                    <thead><tr>
                        <th>{{ __('admin.gsc.field.query') }}</th><th>{{ __('admin.gsc.field.page') }}</th>
                        <th>{{ __('admin.gsc.field.clicks') }}</th><th>{{ __('admin.gsc.field.impressions') }}</th>
                        <th>{{ __('admin.gsc.field.ctr') }}</th><th>{{ __('admin.gsc.field.position') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($metrics as $metric)
                            <tr>
                                <td>{{ $metric->query }}</td>
                                <td><span class="font-mono text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($metric->page, 60) }}</span></td>
                                <td>{{ $metric->clicks }}</td>
                                <td>{{ $metric->impressions }}</td>
                                <td>{{ number_format(((float) $metric->ctr) * 100, 1) }}%</td>
                                <td>{{ number_format((float) $metric->position, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- 单 URL 收录抽查 --}}
        <div class="admin-card p-6">
            <div class="mb-3 admin-card-title">{{ __('admin.gsc.section.inspect') }}</div>
            <form method="POST" action="{{ route('admin.google-search-console.inspect', $property->id) }}" class="space-y-3">
                @csrf
                <textarea class="admin-textarea" name="urls" rows="3" placeholder="https://example.com/article/abc"></textarea>
                <p class="text-xs text-gray-500">{{ __('admin.gsc.help.inspect') }}</p>
                <button type="submit" class="admin-btn admin-btn-secondary"><i data-lucide="scan-search" class="h-4 w-4"></i>{{ __('admin.gsc.button.inspect') }}</button>
            </form>

            @if ($inspections->isNotEmpty())
                <table class="admin-table mt-4">
                    <thead><tr><th>URL</th><th>{{ __('admin.gsc.field.verdict') }}</th><th>{{ __('admin.gsc.field.coverage_state') }}</th><th>{{ __('admin.gsc.field.last_crawl') }}</th></tr></thead>
                    <tbody>
                        @foreach ($inspections as $inspection)
                            <tr>
                                <td><span class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($inspection->url, 60) }}</span></td>
                                <td><span class="admin-badge {{ $inspection->verdict === 'PASS' ? 'is-success' : 'is-warning' }}">{{ $inspection->verdict ?? '—' }}</span></td>
                                <td class="text-xs">{{ $inspection->coverage_state ?? '—' }}</td>
                                <td class="text-xs text-gray-500">{{ optional($inspection->last_crawl_time)->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div>
            <a href="{{ route('admin.google-search-console.index') }}" class="admin-btn admin-btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>{{ __('admin.gsc.button.back') }}</a>
        </div>
    </div>
@endsection
