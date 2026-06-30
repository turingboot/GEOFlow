@extends('admin.layouts.app')

@php
    $connection = $property->connection;
    $sitemapStats = $latestSitemap && is_array($latestSitemap->stats) ? $latestSitemap->stats : [];
    $trend = $insights['searchTrend'] ?? null;
    $dropouts = $insights['indexingDropouts'] ?? [];
    $indexingTrend = $insights['indexingTrend'] ?? [];
    $maxIndexed = collect($indexingTrend)->max('indexed') ?: 1;
    $dateSeries = $insights['dateSeries'] ?? [];
    $breakdowns = $insights['breakdowns'] ?? [];
    $searchMeta = $insights['searchMeta'] ?? [];
    $searchTables = $insights['tables'] ?? [];
    $activeSearchTab = (string) ($searchMeta['active_tab'] ?? 'query');
    $activeRangeDays = (int) ($searchMeta['range_days'] ?? 90);
    $activePerPage = (int) ($searchMeta['per_page'] ?? 20);
    $searchPageNames = ['gsc_query_page', 'gsc_opportunity_page', 'gsc_striking_page', 'gsc_page_page', 'gsc_country_page', 'gsc_device_page', 'gsc_appearance_page', 'gsc_date_page'];
    $searchUrl = function (array $overrides = []) use ($searchPageNames): string {
        $query = array_merge(request()->except([...$searchPageNames, 'partial']), $overrides);

        return request()->url().($query === [] ? '' : '?'.http_build_query($query));
    };
    $rangeUrl = fn (int $days) => $searchUrl(['range_days' => $days, 'tab' => $activeSearchTab]);
    $tabUrl = fn (string $tab) => $searchUrl(['tab' => $tab]);
    $formatCountry = fn (string $code): string => \App\Support\GeoFlow\GscCountryName::format($code);
    $emptyText = fn (string $tabKey): string => match ($tabKey) {
        'opportunity' => __('admin.gsc.insights.empty_opportunity'),
        'striking' => __('admin.gsc.insights.empty_striking'),
        default => __('admin.gsc.insights.empty'),
    };
    $searchTabs = [
        ['key' => 'query', 'label' => __('admin.gsc.field.query'), 'col' => __('admin.gsc.field.query'), 'rows' => $searchTables['query'] ?? collect()],
        ['key' => 'opportunity', 'label' => __('admin.gsc.insights.opportunity'), 'col' => __('admin.gsc.field.query'), 'rows' => $searchTables['opportunity'] ?? collect()],
        ['key' => 'striking', 'label' => __('admin.gsc.insights.striking'), 'col' => __('admin.gsc.field.query'), 'rows' => $searchTables['striking'] ?? collect()],
        ['key' => 'page', 'label' => __('admin.gsc.field.page'), 'col' => __('admin.gsc.field.page'), 'rows' => $searchTables['page'] ?? collect()],
        ['key' => 'country', 'label' => __('admin.gsc.insights.dim_country'), 'col' => __('admin.gsc.insights.dim_country'), 'rows' => $searchTables['country'] ?? collect()],
        ['key' => 'device', 'label' => __('admin.gsc.insights.dim_device'), 'col' => __('admin.gsc.insights.dim_device'), 'rows' => $searchTables['device'] ?? collect()],
        ['key' => 'appearance', 'label' => __('admin.gsc.insights.dim_appearance'), 'col' => __('admin.gsc.insights.dim_appearance'), 'rows' => $searchTables['appearance'] ?? collect()],
        ['key' => 'date', 'label' => __('admin.gsc.insights.dim_date'), 'col' => __('admin.gsc.insights.dim_date'), 'rows' => $searchTables['date'] ?? collect()],
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
                <a href="{{ route('admin.google-search-console.index') }}" class="admin-btn admin-btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>{{ __('admin.gsc.button.back') }}</a>
                <form method="POST" action="{{ route('admin.google-search-console.fetch', $property->id) }}" data-gsc-fetch-form>
                    @csrf
                    <input type="hidden" name="range_days" value="{{ $activeRangeDays }}">
                    <input type="hidden" name="tab" value="{{ $activeSearchTab }}">
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
                {{ __('admin.gsc.field.connection') }}: <span class="font-semibold">{{ optional($connection)->name ?? '-' }}</span>
                <span class="admin-badge is-neutral ml-2">{{ $connection ? __('admin.gsc.auth.'.$connection->provider) : '-' }}</span>
            </div>
        </div>

        {{-- 搜索表现：图在最上面 + 横向 tab 切换 --}}
        <div class="admin-card p-6" data-gsc-search-card>
            <div class="mb-4 flex items-center justify-between">
                <span class="admin-card-title">{{ __('admin.gsc.section.search') }}</span>
                @if ($latestSearch)<span class="text-xs text-gray-500">{{ optional($latestSearch->ran_at)->format('Y-m-d H:i') }}</span>@endif
            </div>

            @if (! empty($dateSeries))
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3" data-gsc-chart-toolbar>
                    <div class="inline-flex flex-wrap overflow-hidden rounded-md border border-gray-200 bg-white text-xs text-gray-600">
                        <a class="border-r border-gray-200 px-3 py-1.5 hover:bg-gray-50 {{ $activeRangeDays === 1 ? 'bg-blue-50 font-medium text-blue-700' : '' }}" href="{{ $rangeUrl(1) }}">{{ $activeRangeDays === 1 ? '✓ ' : '' }}24 &#23567;&#26102;</a>
                        <a class="border-r border-gray-200 px-3 py-1.5 hover:bg-gray-50 {{ $activeRangeDays === 7 ? 'bg-blue-50 font-medium text-blue-700' : '' }}" href="{{ $rangeUrl(7) }}">{{ $activeRangeDays === 7 ? '✓ ' : '' }}7 &#22825;</a>
                        <a class="border-r border-gray-200 px-3 py-1.5 hover:bg-gray-50 {{ $activeRangeDays === 28 ? 'bg-blue-50 font-medium text-blue-700' : '' }}" href="{{ $rangeUrl(28) }}">{{ $activeRangeDays === 28 ? '✓ ' : '' }}28 &#22825;</a>
                        <a class="border-r border-gray-200 px-3 py-1.5 hover:bg-gray-50 {{ $activeRangeDays === 90 ? 'bg-blue-50 font-medium text-blue-700' : '' }}" href="{{ $rangeUrl(90) }}">{{ $activeRangeDays === 90 ? '✓ ' : '' }}3 &#20010;&#26376;</a>
                        <button type="button" class="px-3 py-1.5 hover:bg-gray-50 {{ ! in_array($activeRangeDays, [1, 7, 28, 90], true) ? 'bg-blue-50 font-medium text-blue-700' : '' }}" data-gsc-custom-toggle>{{ ! in_array($activeRangeDays, [1, 7, 28, 90], true) ? '✓ ' : '' }}&#33258;&#36873;&#22825;&#25968;</button>
                    </div>
                    <form method="GET" action="{{ route('admin.google-search-console.show', $property->id) }}" class="{{ in_array($activeRangeDays, [1, 7, 28, 90], true) ? 'hidden' : 'inline-flex' }} items-center gap-2 text-xs text-gray-600" data-gsc-custom-panel>
                        <input type="hidden" name="tab" value="{{ $activeSearchTab }}">
                        <input type="hidden" name="per_page" value="{{ $activePerPage }}">
                        <span>&#26368;&#36817;</span>
                        <input type="number" min="1" max="365" value="{{ $activeRangeDays }}" name="range_days" class="h-8 w-20 rounded-md border border-gray-200 px-2 text-xs focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100" data-gsc-custom-days>
                        <span>&#22825;</span>
                    </form>
                    <div class="flex items-center gap-4 text-xs text-gray-600">
                        <label class="inline-flex cursor-pointer items-center gap-1.5 text-emerald-600">
                            <input type="checkbox" class="h-3.5 w-3.5 rounded border-gray-300 text-emerald-500 focus:ring-emerald-200" data-gsc-metric="clicks" checked>
                            {{ __('admin.gsc.field.clicks') }}
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-1.5 text-indigo-600">
                            <input type="checkbox" class="h-3.5 w-3.5 rounded border-gray-300 text-indigo-500 focus:ring-indigo-200" data-gsc-metric="impressions" checked>
                            {{ __('admin.gsc.field.impressions') }}
                        </label>
                    </div>
                </div>
                <div class="mb-4" data-gsc-chart-root data-gsc-series='@json($dateSeries)'>
                    <svg viewBox="0 0 720 210" class="w-full select-none" data-gsc-chart>
                        <g data-gsc-grid></g>
                        <polyline data-gsc-line="impressions" fill="none" stroke="#818cf8" stroke-width="2" vector-effect="non-scaling-stroke" />
                        <polyline data-gsc-line="clicks" fill="none" stroke="#34d399" stroke-width="2" vector-effect="non-scaling-stroke" />
                        <g data-gsc-hover class="hidden">
                            <line data-gsc-hover-line x1="0" x2="0" y1="8" y2="166" stroke="#94a3b8" stroke-dasharray="3 3" stroke-width="1" />
                            <circle data-gsc-hover-dot="clicks" r="3.5" fill="#34d399" stroke="#ffffff" stroke-width="2" />
                            <circle data-gsc-hover-dot="impressions" r="3.5" fill="#818cf8" stroke="#ffffff" stroke-width="2" />
                            <text data-gsc-hover-value="clicks" fill="#059669" font-size="11" font-weight="600"></text>
                            <text data-gsc-hover-value="impressions" fill="#4f46e5" font-size="11" font-weight="600"></text>
                            <rect data-gsc-hover-date-bg y="183" width="74" height="20" rx="4" fill="#f1f5f9" stroke="#cbd5e1" />
                            <text data-gsc-hover-date y="197" fill="#475569" font-size="10" text-anchor="middle"></text>
                        </g>
                        <rect x="30" y="8" width="684" height="176" fill="transparent" data-gsc-chart-hitbox />
                    </svg>
                </div>
            @endif

            @if (! empty($searchMeta['needs_more_data']))
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <span>&#24403;&#21069;&#20165;&#26377;&#26368;&#36817; {{ (int) ($searchMeta['available_days'] ?? 0) }} &#22825;&#25968;&#25454;&#65292;&#21487;&#25289;&#21462;&#26368;&#36817; {{ $activeRangeDays }} &#22825;&#25968;&#25454;&#21518;&#20877;&#26597;&#30475;&#23436;&#25972;&#32467;&#26524;&#12290;</span>
                    <form method="POST" action="{{ route('admin.google-search-console.fetch', $property->id) }}" data-gsc-fetch-form>
                        @csrf
                        <input type="hidden" name="range_days" value="{{ $activeRangeDays }}">
                        <input type="hidden" name="tab" value="{{ $activeSearchTab }}">
                        <button type="submit" class="admin-btn admin-btn-secondary">&#25289;&#21462;&#26368;&#36817; {{ $activeRangeDays }} &#22825;&#25968;&#25454;</button>
                    </form>
                </div>
            @endif

            <div data-gsc-tabs>
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-200">
                    <div class="flex flex-wrap gap-1">
                    @foreach ($searchTabs as $tab)
                        <a href="{{ $tabUrl($tab['key']) }}" data-gsc-tab="{{ $tab['key'] }}"
                            class="-mb-px border-b-2 px-3 py-2 text-sm {{ $activeSearchTab === $tab['key'] ? 'border-indigo-500 font-medium text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                            {{ $tab['label'] }}
                        </a>
                    @endforeach
                    </div>
                </div>
                @foreach ($searchTabs as $tab)
                    @php($rows = $tab['rows'])
                    <div data-gsc-panel="{{ $tab['key'] }}" class="{{ $activeSearchTab === $tab['key'] ? '' : 'hidden' }} pt-3">
                        @if ($rows->count() === 0)
                            <p class="p-4 text-sm text-gray-500">{{ $emptyText($tab['key']) }}</p>
                        @else
                            <table class="admin-table">
                                <thead><tr>
                                    <th>{{ $tab['col'] }}</th>@if ($tab['key'] === 'page')<th class="w-20"></th>@endif<th>{{ __('admin.gsc.field.clicks') }}</th>
                                    <th>{{ __('admin.gsc.field.impressions') }}</th><th>{{ __('admin.gsc.field.ctr') }}</th><th>{{ __('admin.gsc.field.position') }}</th>
                                </tr></thead>
                                <tbody>
                                    @foreach ($rows as $r)
                                        @php($rawValue = (string) ($r['value'] ?? $r['query'] ?? $r['date'] ?? ''))
                                        @php($displayValue = $tab['key'] === 'country' ? $formatCountry($rawValue) : $rawValue)
                                        <tr @if ($tab['key'] === 'page') data-gsc-page-row tabindex="0" class="cursor-pointer" @endif>
                                            @if ($tab['key'] === 'page')
                                                <td>
                                                    <span class="inline-flex max-w-full items-center gap-2">
                                                        <span class="min-w-0 truncate font-mono text-xs">{{ \Illuminate\Support\Str::limit($displayValue ?: '-', 60) }}</span>
                                                    </span>
                                                </td>
                                                <td class="w-20">
                                                    <span class="hidden items-center gap-1" data-gsc-page-actions>
                                                        <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-200 text-gray-500 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600" data-gsc-copy-url="{{ $rawValue }}" title="&#22797;&#21046;">
                                                            <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                                        </button>
                                                        <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-200 text-gray-500 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600" data-gsc-open-url="{{ $rawValue }}" title="&#25171;&#24320;">
                                                            <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                                                        </button>
                                                    </span>
                                                </td>
                                            @elseif ($tab['key'] === 'country')
                                                <td><span>{{ \Illuminate\Support\Str::limit($displayValue ?: '-', 60) }}</span></td>
                                            @else
                                            <td><span>{{ \Illuminate\Support\Str::limit($displayValue ?: '-', 60) }}</span></td>
                                            @endif
                                            <td>{{ $r['clicks'] }}</td>
                                            <td>{{ $r['impressions'] }}</td>
                                            <td>{{ number_format($r['ctr'] * 100, 1) }}%</td>
                                            <td>{{ number_format($r['position'], 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @include('admin.google-search-console._search-pagination', ['rows' => $rows, 'tab' => $tab])
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
                        <li><span class="font-mono">{{ \Illuminate\Support\Str::limit($d['url'], 70) }}</span> - {{ $d['coverage_state'] ?: $d['now'] }}</li>
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
                                    {{ $m['change'] > 0 ? '+' : ($m['change'] < 0 ? '-' : '0') }}
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
                                <div class="flex flex-1 flex-col items-center justify-end" title="{{ $pt['ran_at'] }} - {{ $pt['indexed'] }}/{{ $pt['submitted'] }}">
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
                                <td><span class="admin-badge {{ $inspection->verdict === 'PASS' ? 'is-success' : 'is-warning' }}">{{ $inspection->verdict ?? '-' }}</span></td>
                                <td class="text-xs">{{ $inspection->coverage_state ?? '-' }}</td>
                                <td class="text-xs text-gray-500">{{ optional($inspection->last_crawl_time)->format('Y-m-d H:i') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="pointer-events-none fixed right-6 bottom-8 z-50 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white opacity-0 shadow-lg transition-opacity duration-700" data-gsc-copy-toast>
        复制成功
    </div>

    <script>
        function initGscTabs(scope) {
            scope = scope || document;
            scope.querySelectorAll('[data-gsc-tabs]').forEach(function (root) {
                if (root.dataset.gscTabsReady === '1') {
                    return;
                }
                root.dataset.gscTabsReady = '1';
            var btns = root.querySelectorAll('[data-gsc-tab]');
            var panels = root.querySelectorAll('[data-gsc-panel]');
            btns.forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                        return;
                    }

                    event.preventDefault();
                    if (btn.href) {
                        loadGscSearchCard(btn.href);
                    }
                });
            });
            });
        }

        function initGscCharts(scope) {
            scope = scope || document;
            scope.querySelectorAll('[data-gsc-chart-root]').forEach(function (root) {
            if (root.dataset.gscChartReady === '1') {
                return;
            }
            root.dataset.gscChartReady = '1';
            var svg = root.querySelector('[data-gsc-chart]');
            var rawSeries = JSON.parse(root.getAttribute('data-gsc-series') || '[]');
            var toolbar = root.closest('[data-gsc-search-card]') ? root.closest('[data-gsc-search-card]').querySelector('[data-gsc-chart-toolbar]') : document.querySelector('[data-gsc-chart-toolbar]');
            var customToggle = toolbar ? toolbar.querySelector('[data-gsc-custom-toggle]') : null;
            var customPanel = toolbar ? toolbar.querySelector('[data-gsc-custom-panel]') : null;
            var customInput = toolbar ? toolbar.querySelector('[data-gsc-custom-days]') : null;
            var metricInputs = toolbar ? toolbar.querySelectorAll('[data-gsc-metric]') : [];
            var grid = root.querySelector('[data-gsc-grid]');
            var hitbox = root.querySelector('[data-gsc-chart-hitbox]');
            var hover = root.querySelector('[data-gsc-hover]');
            var hoverLine = root.querySelector('[data-gsc-hover-line]');
            var dateBg = root.querySelector('[data-gsc-hover-date-bg]');
            var dateText = root.querySelector('[data-gsc-hover-date]');
            var lines = {
                impressions: root.querySelector('[data-gsc-line="impressions"]'),
                clicks: root.querySelector('[data-gsc-line="clicks"]'),
            };
            var dots = {
                impressions: root.querySelector('[data-gsc-hover-dot="impressions"]'),
                clicks: root.querySelector('[data-gsc-hover-dot="clicks"]'),
            };
            var values = {
                impressions: root.querySelector('[data-gsc-hover-value="impressions"]'),
                clicks: root.querySelector('[data-gsc-hover-value="clicks"]'),
            };
            var state = {
                rangeDays: 90,
                activeMetrics: { clicks: true, impressions: true },
                points: [],
                labels: [],
            };
            var chart = { w: 720, h: 210, left: 30, right: 6, top: 8, bottom: 34 };
            chart.plotW = chart.w - chart.left - chart.right;
            chart.plotH = chart.h - chart.top - chart.bottom;
            chart.axisY = chart.top + chart.plotH;

            function parseDate(value) {
                var parts = String(value).split('-').map(function (part) {
                    return parseInt(part, 10);
                });

                return new Date(parts[0], (parts[1] || 1) - 1, parts[2] || 1);
            }

            function xAt(index, total) {
                if (total <= 1) {
                    return chart.left + chart.plotW / 2;
                }

                return chart.left + (index / (total - 1)) * chart.plotW;
            }

            function yAt(value, maxValue) {
                return chart.top + chart.plotH - (value / maxValue) * chart.plotH;
            }

            function numberLabel(value) {
                return new Intl.NumberFormat().format(value);
            }

            function selectedSeries() {
                if (! rawSeries.length) {
                    return [];
                }

                return rawSeries.slice().sort(function (a, b) {
                    return parseDate(a.date) - parseDate(b.date);
                });
            }

            function buildPoints(series, maxValue) {
                return series.map(function (point, index) {
                    var x = xAt(index, series.length);
                    var clicks = parseInt(point.clicks || 0, 10);
                    var impressions = parseInt(point.impressions || 0, 10);

                    return {
                        date: point.date,
                        clicks: clicks,
                        impressions: impressions,
                        x: x,
                        yClicks: yAt(clicks, maxValue),
                        yImpressions: yAt(impressions, maxValue),
                    };
                });
            }

            function buildLabels(points) {
                if (! points.length) {
                    return [];
                }

                var indexes = [0, Math.floor((points.length - 1) / 2), points.length - 1].filter(function (value, index, all) {
                    return all.indexOf(value) === index;
                });

                return indexes.map(function (index) {
                    return {
                        date: points[index].date,
                        x: points[index].x,
                        anchor: index === 0 ? 'start' : (index === points.length - 1 ? 'end' : 'middle'),
                    };
                });
            }

            function drawGrid(maxValue) {
                var html = '';
                var ticks = 4;

                for (var t = 0; t <= ticks; t++) {
                    var tickValue = Math.round((maxValue * t) / ticks);
                    var y = yAt(tickValue, maxValue);
                    html += '<line x1="' + chart.left + '" y1="' + y.toFixed(1) + '" x2="' + chart.w + '" y2="' + y.toFixed(1) + '" stroke="#f1f5f9" stroke-width="1" />';
                    html += '<text x="2" y="' + (y + 4).toFixed(1) + '" font-size="11" fill="#9ca3af">' + tickValue + '</text>';
                }

                state.labels.forEach(function (label) {
                    html += '<text data-gsc-axis-label x="' + label.x.toFixed(1) + '" y="197" font-size="10" fill="#9ca3af" text-anchor="' + label.anchor + '">' + label.date + '</text>';
                });

                grid.innerHTML = html;
            }

            function hideHover() {
                if (hover) {
                    hover.classList.add('hidden');
                }
                root.querySelectorAll('[data-gsc-axis-label]').forEach(function (label) {
                    label.classList.remove('opacity-0');
                });
            }

            function drawChart() {
                var series = selectedSeries();
                var activeValues = [];

                series.forEach(function (point) {
                    if (state.activeMetrics.clicks) {
                        activeValues.push(parseInt(point.clicks || 0, 10));
                    }
                    if (state.activeMetrics.impressions) {
                        activeValues.push(parseInt(point.impressions || 0, 10));
                    }
                });

                var rawMax = Math.max.apply(null, activeValues.concat([1]));
                var step = Math.max(1, Math.ceil(rawMax / 4));
                var maxValue = step * 4;
                state.points = buildPoints(series, maxValue);
                state.labels = buildLabels(state.points);
                drawGrid(maxValue);

                lines.clicks.setAttribute('points', state.activeMetrics.clicks ? state.points.map(function (point) {
                    return point.x.toFixed(1) + ',' + point.yClicks.toFixed(1);
                }).join(' ') : '');
                lines.impressions.setAttribute('points', state.activeMetrics.impressions ? state.points.map(function (point) {
                    return point.x.toFixed(1) + ',' + point.yImpressions.toFixed(1);
                }).join(' ') : '');
                hideHover();
            }

            function showHover(point) {
                var dateBoxWidth = 74;
                var dateX = Math.max(dateBoxWidth / 2, Math.min(chart.w - dateBoxWidth / 2, point.x));
                var valueOnLeft = point.x > chart.w - 120;
                var valueX = valueOnLeft ? Math.max(chart.left + 4, point.x - 92) : Math.min(point.x + 8, chart.w - 92);

                function boundedValueY(y, offset) {
                    var next = y + offset;
                    if (next < chart.top + 12) {
                        next = y + 16;
                    }
                    if (next > chart.axisY - 8) {
                        next = chart.axisY - 8;
                    }

                    return next;
                }

                var clicksY = boundedValueY(point.yClicks, -8);
                var impressionsY = boundedValueY(point.yImpressions, -8);
                if (Math.abs(clicksY - impressionsY) < 14) {
                    var topY = Math.min(point.yClicks, point.yImpressions);
                    clicksY = boundedValueY(topY, state.activeMetrics.impressions ? -18 : -8);
                    impressionsY = boundedValueY(topY, state.activeMetrics.clicks ? 18 : -8);
                    if (Math.abs(clicksY - impressionsY) < 14) {
                        impressionsY = Math.min(chart.axisY - 8, clicksY + 16);
                    }
                }

                hover.classList.remove('hidden');
                hoverLine.setAttribute('x1', point.x.toFixed(1));
                hoverLine.setAttribute('x2', point.x.toFixed(1));
                dateBg.setAttribute('x', (dateX - dateBoxWidth / 2).toFixed(1));
                dateText.setAttribute('x', dateX.toFixed(1));
                dateText.textContent = point.date;

                dots.clicks.classList.toggle('hidden', !state.activeMetrics.clicks);
                values.clicks.classList.toggle('hidden', !state.activeMetrics.clicks);
                dots.impressions.classList.toggle('hidden', !state.activeMetrics.impressions);
                values.impressions.classList.toggle('hidden', !state.activeMetrics.impressions);

                dots.clicks.setAttribute('cx', point.x.toFixed(1));
                dots.clicks.setAttribute('cy', point.yClicks.toFixed(1));
                dots.impressions.setAttribute('cx', point.x.toFixed(1));
                dots.impressions.setAttribute('cy', point.yImpressions.toFixed(1));
                values.clicks.setAttribute('x', valueX.toFixed(1));
                values.clicks.setAttribute('y', clicksY.toFixed(1));
                values.clicks.textContent = '\u70b9\u51fb ' + numberLabel(point.clicks);
                values.impressions.setAttribute('x', valueX.toFixed(1));
                values.impressions.setAttribute('y', impressionsY.toFixed(1));
                values.impressions.textContent = '\u66dd\u5149 ' + numberLabel(point.impressions);

                root.querySelectorAll('[data-gsc-axis-label]').forEach(function (label) {
                    var labelX = parseFloat(label.getAttribute('x') || '0');
                    label.classList.toggle('opacity-0', Math.abs(labelX - point.x) < 48);
                });
            }

            function pointFromEvent(event) {
                if (! state.points.length) {
                    return null;
                }

                var rect = svg.getBoundingClientRect();
                var x = ((event.clientX - rect.left) / rect.width) * chart.w;

                return state.points.reduce(function (nearest, point) {
                    return Math.abs(point.x - x) < Math.abs(nearest.x - x) ? point : nearest;
                }, state.points[0]);
            }

            if (customToggle && customPanel && customInput) {
                customToggle.addEventListener('click', function () {
                    customPanel.classList.remove('hidden');
                    customPanel.classList.add('inline-flex');
                    customInput.focus();
                });
                customInput.addEventListener('change', function () {
                    submitGscSearchForm(customInput.form);
                });
            }

            metricInputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    var checkedMetrics = Array.prototype.slice.call(metricInputs).filter(function (metricInput) {
                        return metricInput.checked;
                    });
                    if (! checkedMetrics.length) {
                        input.checked = true;
                    }
                    state.activeMetrics[input.getAttribute('data-gsc-metric')] = input.checked;
                    drawChart();
                });
            });

            if (hitbox) {
                hitbox.addEventListener('mousemove', function (event) {
                    var point = pointFromEvent(event);
                    if (point) {
                        showHover(point);
                    }
                });
                hitbox.addEventListener('mouseleave', hideHover);
            }

            drawChart();
            });
        }

        function gscPartialUrl(url) {
            var nextUrl = new URL(url, window.location.href);
            nextUrl.searchParams.set('partial', 'search');

            return nextUrl.toString();
        }

        function gscBrowserUrl(url) {
            var nextUrl = new URL(url, window.location.href);
            nextUrl.searchParams.delete('partial');

            return nextUrl.toString();
        }

        function replaceGscSearchCard(html, url) {
            var browserUrl = gscBrowserUrl(url);
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var nextCard = doc.querySelector('[data-gsc-search-card]');
            var currentCard = document.querySelector('[data-gsc-search-card]');
            if (! nextCard || ! currentCard) {
                window.location.href = browserUrl;

                return;
            }

            currentCard.replaceWith(nextCard);
            if (window.history && typeof window.history.pushState === 'function') {
                window.history.pushState({ gscSearch: true }, '', browserUrl);
            }
            initGscTabs(nextCard);
            initGscCharts(nextCard);
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        }

        function loadGscSearchCard(url) {
            var currentCard = document.querySelector('[data-gsc-search-card]');
            if (currentCard) {
                currentCard.classList.add('opacity-60');
            }

            return fetch(gscPartialUrl(url), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
                credentials: 'same-origin',
            }).then(function (response) {
                if (! response.ok) {
                    throw new Error('Failed to load GSC search card');
                }

                return response.text();
            }).then(function (html) {
                replaceGscSearchCard(html, url);
            }).catch(function () {
                window.location.href = url;
            }).finally(function () {
                var card = document.querySelector('[data-gsc-search-card]');
                if (card) {
                    card.classList.remove('opacity-60');
                }
            });
        }

        function submitGscSearchForm(form) {
            if (! form) {
                return;
            }

            var url = new URL(form.action, window.location.href);
            new FormData(form).forEach(function (value, key) {
                url.searchParams.set(key, value);
            });
            loadGscSearchCard(url.toString());
        }

        function syncGscFetchForm(form) {
            var currentUrl = new URL(window.location.href);
            var rangeDays = currentUrl.searchParams.get('range_days') || '90';
            var tab = currentUrl.searchParams.get('tab') || 'query';
            var rangeInput = form.querySelector('input[name="range_days"]');
            var tabInput = form.querySelector('input[name="tab"]');

            if (rangeInput) {
                rangeInput.value = rangeDays;
            }
            if (tabInput) {
                tabInput.value = tab;
            }
        }

        document.addEventListener('click', function (event) {
            var rangeLink = event.target.closest('[data-gsc-chart-toolbar] a[href]');
            if (! rangeLink || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                return;
            }

            event.preventDefault();
            loadGscSearchCard(rangeLink.href);
        });

        document.addEventListener('click', function (event) {
            var pageLink = event.target.closest('[data-gsc-pagination] a[href]');
            if (! pageLink || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                return;
            }

            event.preventDefault();
            loadGscSearchCard(pageLink.href);
        });

        document.addEventListener('change', function (event) {
            var perPageSelect = event.target.closest('[data-gsc-search-card] select[name="per_page"]');
            if (! perPageSelect) {
                return;
            }

            event.preventDefault();
            submitGscSearchForm(perPageSelect.form);
        });

        document.addEventListener('submit', function (event) {
            var fetchForm = event.target.closest('[data-gsc-fetch-form]');
            if (! fetchForm) {
                return;
            }

            syncGscFetchForm(fetchForm);
        });

        function hideGscPageActions() {
            document.querySelectorAll('[data-gsc-page-row]').forEach(function (row) {
                row.classList.remove('bg-blue-50');
                row.querySelectorAll('[data-gsc-page-actions]').forEach(function (actions) {
                    actions.classList.add('hidden');
                    actions.classList.remove('inline-flex');
                });
            });
        }

        async function copyGscText(text) {
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
                await navigator.clipboard.writeText(text);

                return true;
            }

            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            var copied = document.execCommand('copy');
            textarea.remove();

            return copied;
        }

        var gscCopyToastTimer = null;

        function showGscCopyToast() {
            if (window.AdminUtils && typeof window.AdminUtils.showToast === 'function') {
                window.AdminUtils.showToast('\u590d\u5236\u6210\u529f', 'success');

                return;
            }

            var toast = document.querySelector('[data-gsc-copy-toast]');
            if (! toast) {
                return;
            }

            window.clearTimeout(gscCopyToastTimer);
            toast.classList.remove('opacity-0');
            toast.classList.add('opacity-100');
            gscCopyToastTimer = window.setTimeout(function () {
                toast.classList.remove('opacity-100');
                toast.classList.add('opacity-0');
            }, 900);
        }

        document.addEventListener('click', function (event) {
            var copyButton = event.target.closest('[data-gsc-copy-url]');
            if (copyButton) {
                event.preventDefault();
                event.stopPropagation();
                copyGscText(copyButton.getAttribute('data-gsc-copy-url') || '').then(function (copied) {
                    if (copied) {
                        showGscCopyToast();
                    }
                }).catch(function () {});

                return;
            }

            var openButton = event.target.closest('[data-gsc-open-url]');
            if (openButton) {
                event.preventDefault();
                event.stopPropagation();
                window.open(openButton.getAttribute('data-gsc-open-url') || '', '_blank', 'noopener,noreferrer');

                return;
            }

            var row = event.target.closest('[data-gsc-page-row]');
            if (! row) {
                return;
            }

            hideGscPageActions();
            row.classList.add('bg-blue-50');
            row.querySelectorAll('[data-gsc-page-actions]').forEach(function (actions) {
                actions.classList.remove('hidden');
                actions.classList.add('inline-flex');
            });
        });

        document.querySelectorAll('[data-gsc-page-row]').forEach(function (row) {
            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    row.click();
                }
            });
        });

        initGscTabs(document);
        initGscCharts(document);
    </script>
@endsection
