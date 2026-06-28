@extends('admin.layouts.app')

@php
    $connection = $property->connection;
    $sitemapStats = $latestSitemap && is_array($latestSitemap->stats) ? $latestSitemap->stats : [];
    $trend = $insights['searchTrend'] ?? null;
    $dropouts = $insights['indexingDropouts'] ?? [];
    $indexingTrend = $insights['indexingTrend'] ?? [];
    $maxIndexed = collect($indexingTrend)->max('indexed') ?: 1;
    $dateSeries = $insights['dateSeries'] ?? [];
    $maxDateClicks = collect($dateSeries)->max('clicks') ?: 1;
    $breakdowns = $insights['breakdowns'] ?? [];
    $asValueRows = fn (array $rows, string $key) => collect($rows)->map(fn ($r) => [
        'value' => (string) ($r[$key] ?? ''),
        'clicks' => (int) ($r['clicks'] ?? 0),
        'impressions' => (int) ($r['impressions'] ?? 0),
        'ctr' => (float) ($r['ctr'] ?? 0),
        'position' => (float) ($r['position'] ?? 0),
    ])->all();
    $searchTabs = [
        ['key' => 'query', 'label' => __('admin.gsc.field.query'), 'col' => __('admin.gsc.field.query'), 'rows' => $asValueRows($insights['topQueries'] ?? [], 'query')],
        ['key' => 'opportunity', 'label' => __('admin.gsc.insights.opportunity'), 'col' => __('admin.gsc.field.query'), 'rows' => $asValueRows($insights['opportunityQueries'] ?? [], 'query')],
        ['key' => 'striking', 'label' => __('admin.gsc.insights.striking'), 'col' => __('admin.gsc.field.query'), 'rows' => $asValueRows($insights['strikingDistance'] ?? [], 'query')],
        ['key' => 'page', 'label' => __('admin.gsc.field.page'), 'col' => __('admin.gsc.field.page'), 'rows' => $breakdowns['page'] ?? []],
        ['key' => 'country', 'label' => __('admin.gsc.insights.dim_country'), 'col' => __('admin.gsc.insights.dim_country'), 'rows' => $breakdowns['country'] ?? []],
        ['key' => 'device', 'label' => __('admin.gsc.insights.dim_device'), 'col' => __('admin.gsc.insights.dim_device'), 'rows' => $breakdowns['device'] ?? []],
        ['key' => 'appearance', 'label' => __('admin.gsc.insights.dim_appearance'), 'col' => __('admin.gsc.insights.dim_appearance'), 'rows' => $breakdowns['search_appearance'] ?? []],
        ['key' => 'date', 'label' => __('admin.gsc.insights.dim_date'), 'col' => __('admin.gsc.insights.dim_date'), 'rows' => $asValueRows($dateSeries, 'date')],
    ];
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
                @unless ($isSuperAdmin)
                    <form method="POST" action="{{ route('admin.google-search-console.remove', $property->id) }}" onsubmit="return confirm(@js(__('admin.gsc.confirm.remove')))">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn-secondary">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>{{ __('admin.gsc.button.remove') }}
                        </button>
                    </form>
                @endunless
            </div>
        </div>

        <div class="admin-card p-6">
            <div class="text-sm text-gray-700">
                {{ __('admin.gsc.field.connection') }}：<span class="font-semibold">{{ optional($connection)->name ?? '—' }}</span>
                <span class="admin-badge is-neutral ml-2">{{ $connection ? __('admin.gsc.auth.'.$connection->provider) : '—' }}</span>
            </div>
        </div>

        {{-- 搜索表现：图在最上面 + 横向 tab 切换 --}}
        <div class="admin-card p-6">
            <div class="mb-4 flex items-center justify-between">
                <span class="admin-card-title">{{ __('admin.gsc.section.search') }}</span>
                @if ($latestSearch)<span class="text-xs text-gray-500">{{ optional($latestSearch->ran_at)->format('Y-m-d H:i') }}</span>@endif
            </div>

            @if (! empty($dateSeries))
                <div class="flex h-28 items-end gap-px">
                    @foreach ($dateSeries as $pt)
                        <div class="flex flex-1 flex-col items-center justify-end" title="{{ $pt['date'] }} · {{ __('admin.gsc.field.clicks') }} {{ $pt['clicks'] }} · {{ __('admin.gsc.field.impressions') }} {{ $pt['impressions'] }}">
                            <div class="w-full rounded-t bg-indigo-400 hover:bg-indigo-500" style="height: {{ max(2, (int) round(($pt['clicks'] / $maxDateClicks) * 100)) }}px"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mb-5 mt-1 flex justify-between text-[10px] text-gray-400">
                    <span>{{ $dateSeries[0]['date'] ?? '' }}</span>
                    <span>{{ __('admin.gsc.field.clicks') }}</span>
                    <span>{{ $dateSeries[count($dateSeries) - 1]['date'] ?? '' }}</span>
                </div>
            @endif

            <div data-gsc-tabs>
                <div class="flex flex-wrap gap-1 border-b border-gray-200">
                    @foreach ($searchTabs as $i => $tab)
                        <button type="button" data-gsc-tab="{{ $tab['key'] }}"
                            class="-mb-px border-b-2 px-3 py-2 text-sm {{ $i === 0 ? 'border-indigo-500 font-medium text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>
                @foreach ($searchTabs as $i => $tab)
                    <div data-gsc-panel="{{ $tab['key'] }}" class="{{ $i === 0 ? '' : 'hidden' }} pt-3">
                        @if (empty($tab['rows']))
                            <p class="p-4 text-sm text-gray-500">{{ __('admin.gsc.insights.empty') }}</p>
                        @else
                            <table class="admin-table">
                                <thead><tr>
                                    <th>{{ $tab['col'] }}</th><th>{{ __('admin.gsc.field.clicks') }}</th>
                                    <th>{{ __('admin.gsc.field.impressions') }}</th><th>{{ __('admin.gsc.field.ctr') }}</th><th>{{ __('admin.gsc.field.position') }}</th>
                                </tr></thead>
                                <tbody>
                                    @foreach ($tab['rows'] as $r)
                                        <tr>
                                            <td><span class="{{ $tab['key'] === 'page' ? 'font-mono text-xs' : '' }}">{{ \Illuminate\Support\Str::limit($r['value'] ?: '—', 60) }}</span></td>
                                            <td>{{ $r['clicks'] }}</td>
                                            <td>{{ $r['impressions'] }}</td>
                                            <td>{{ number_format($r['ctr'] * 100, 1) }}%</td>
                                            <td>{{ number_format($r['position'], 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 掉收录告警 --}}
        @if (! empty($dropouts))
            <div class="rounded-md border border-red-300 bg-red-50 px-4 py-3">
                <div class="mb-2 text-sm font-semibold text-red-700">
                    <i data-lucide="alert-triangle" class="inline h-4 w-4"></i>
                    {{ __('admin.gsc.insights.dropout_title') }}（{{ count($dropouts) }}）
                </div>
                <ul class="space-y-1 text-xs text-red-700">
                    @foreach ($dropouts as $d)
                        <li><span class="font-mono">{{ \Illuminate\Support\Str::limit($d['url'], 70) }}</span> — {{ $d['coverage_state'] ?: $d['now'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 趋势对比（本期 vs 上期） --}}
        @if ($trend)
            <div class="admin-card p-6">
                <div class="mb-3 flex items-center justify-between">
                    <span class="admin-card-title">{{ __('admin.gsc.section.trend') }}</span>
                    @unless ($trend['has_previous'])<span class="text-xs text-gray-400">{{ __('admin.gsc.insights.trend_no_previous') }}</span>@endunless
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    @foreach (['clicks' => 'clicks', 'impressions' => 'impressions', 'position' => 'position'] as $k => $field)
                        @php($m = $trend[$k])
                        <div>
                            <div class="text-xs text-gray-500">{{ __('admin.gsc.field.'.$field) }}</div>
                            <div class="text-2xl font-semibold">{{ $k === 'position' ? number_format((float) $m['current'], 1) : (int) $m['current'] }}</div>
                            @if (! is_null($m['change']))
                                <div class="text-xs {{ $m['direction'] === 'good' ? 'text-emerald-600' : ($m['direction'] === 'bad' ? 'text-red-600' : 'text-gray-400') }}">
                                    {{ $m['change'] > 0 ? '▲' : ($m['change'] < 0 ? '▼' : '—') }}
                                    {{ $k === 'position' ? number_format(abs((float) $m['change']), 1) : (int) abs($m['change']) }}
                                    @if (! is_null($m['pct'])) ({{ $m['pct'] > 0 ? '+' : '' }}{{ $m['pct'] }}%) @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

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
                @if (count($indexingTrend) > 1)
                    <div class="mt-5">
                        <div class="mb-2 text-xs text-gray-500">{{ __('admin.gsc.insights.indexing_trend') }}</div>
                        <div class="flex h-20 items-end gap-1">
                            @foreach ($indexingTrend as $pt)
                                <div class="flex flex-1 flex-col items-center justify-end" title="{{ $pt['ran_at'] }} · {{ $pt['indexed'] }}/{{ $pt['submitted'] }}">
                                    <div class="w-full rounded-t bg-emerald-400" style="height: {{ max(2, (int) round(($pt['indexed'] / $maxIndexed) * 72)) }}px"></div>
                                    <div class="mt-1 text-[10px] text-gray-400">{{ $pt['indexed'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <p class="text-sm text-gray-500">{{ __('admin.gsc.indexing.empty') }}</p>
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

    <script>
        document.querySelectorAll('[data-gsc-tabs]').forEach(function (root) {
            var btns = root.querySelectorAll('[data-gsc-tab]');
            var panels = root.querySelectorAll('[data-gsc-panel]');
            btns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var key = btn.getAttribute('data-gsc-tab');
                    btns.forEach(function (b) {
                        var on = b === btn;
                        b.classList.toggle('border-indigo-500', on);
                        b.classList.toggle('font-medium', on);
                        b.classList.toggle('text-indigo-600', on);
                        b.classList.toggle('border-transparent', !on);
                        b.classList.toggle('text-gray-500', !on);
                    });
                    panels.forEach(function (p) {
                        p.classList.toggle('hidden', p.getAttribute('data-gsc-panel') !== key);
                    });
                });
            });
        });
    </script>
@endsection
