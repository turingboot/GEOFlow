@php
    $dateSeries = $insights['dateSeries'] ?? [];
    $chartTotals = collect($dateSeries)->reduce(
        fn (array $totals, array $point): array => [
            'clicks' => $totals['clicks'] + (int) ($point['clicks'] ?? 0),
            'impressions' => $totals['impressions'] + (int) ($point['impressions'] ?? 0),
        ],
        ['clicks' => 0, 'impressions' => 0],
    );
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
        </div>
        <div class="mb-4 overflow-hidden rounded-xl border border-gray-100 bg-white" data-gsc-chart-root data-gsc-series='@json($dateSeries)'>
            <div class="grid max-w-xl grid-cols-1 sm:grid-cols-2">
                <label class="group min-h-32 cursor-pointer bg-blue-500 p-5 text-white transition data-[inactive=true]:bg-white data-[inactive=true]:text-gray-500" data-gsc-metric-card="clicks">
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" class="h-4 w-4 rounded border-white/70 bg-transparent text-blue-600 focus:ring-2 focus:ring-white/60 group-data-[inactive=true]:border-gray-400 group-data-[inactive=true]:text-blue-500" data-gsc-metric="clicks" checked>
                        {{ __('admin.gsc.field.clicks') }}
                    </span>
                    <span class="mt-5 block text-4xl font-light leading-none">{{ number_format($chartTotals['clicks']) }}</span>
                </label>
                <label class="group min-h-32 cursor-pointer bg-violet-700 p-5 text-white transition data-[inactive=true]:bg-white data-[inactive=true]:text-gray-500" data-gsc-metric-card="impressions">
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" class="h-4 w-4 rounded border-white/70 bg-transparent text-violet-700 focus:ring-2 focus:ring-white/60 group-data-[inactive=true]:border-gray-400 group-data-[inactive=true]:text-violet-600" data-gsc-metric="impressions" checked>
                        {{ __('admin.gsc.field.impressions') }}
                    </span>
                    <span class="mt-5 block text-4xl font-light leading-none">{{ number_format($chartTotals['impressions']) }}</span>
                </label>
            </div>
            <svg viewBox="0 0 760 260" class="w-full select-none" data-gsc-chart>
                <g data-gsc-grid></g>
                <polyline data-gsc-line="impressions" fill="none" stroke="#5b36b8" stroke-width="2" vector-effect="non-scaling-stroke" />
                <polyline data-gsc-line="clicks" fill="none" stroke="#4285f4" stroke-width="2" vector-effect="non-scaling-stroke" />
                <g data-gsc-hover class="hidden">
                    <line data-gsc-hover-line x1="0" x2="0" y1="44" y2="204" stroke="#9ca3af" stroke-dasharray="3 3" stroke-width="1" />
                    <circle data-gsc-hover-dot="clicks" r="3.5" fill="#4285f4" stroke="#ffffff" stroke-width="2" />
                    <circle data-gsc-hover-dot="impressions" r="3.5" fill="#5b36b8" stroke="#ffffff" stroke-width="2" />
                    <text data-gsc-hover-value="clicks" fill="#2563eb" font-size="11" font-weight="600"></text>
                    <text data-gsc-hover-value="impressions" fill="#5b36b8" font-size="11" font-weight="600"></text>
                    <rect data-gsc-hover-date-bg y="224" width="74" height="20" rx="4" fill="#f1f5f9" stroke="#cbd5e1" />
                    <text data-gsc-hover-date y="238" fill="#475569" font-size="10" text-anchor="middle"></text>
                </g>
                <rect x="42" y="44" width="664" height="160" fill="transparent" data-gsc-chart-hitbox />
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
