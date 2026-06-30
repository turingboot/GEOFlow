<?php

namespace App\Services\GeoFlow\GoogleSearchConsole;

use App\Models\GscProperty;
use App\Models\GscSearchMetric;
use App\Models\GscSnapshot;
use App\Models\GscUrlInspection;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GscInsightsService
{
    public const SEARCH_TABS = ['query', 'opportunity', 'striking', 'page', 'country', 'device', 'appearance', 'date'];

    private const PAGE_NAMES = ['gsc_query_page', 'gsc_opportunity_page', 'gsc_striking_page', 'gsc_page_page', 'gsc_country_page', 'gsc_device_page', 'gsc_appearance_page', 'gsc_date_page'];

    /**
     * @return array<string, mixed>
     */
    public function build(GscProperty $property, ?int $rangeDays = null, int $perPage = 20, string $activeTab = 'query'): array
    {
        $latestSearch = $this->latestSearchSnapshot($property);
        $latestSearchId = $latestSearch?->id;
        $rangeDays = max(1, min(365, $rangeDays ?? (int) config('geoflow.google_search_console.default_range_days', 90)));
        $perPage = max(10, min(100, $perPage));
        $activeTab = in_array($activeTab, self::SEARCH_TABS, true) ? $activeTab : 'query';

        $availableRange = $this->availableSearchRange($latestSearch);
        $selectedRange = $this->selectedRange($availableRange, $rangeDays);
        $hasDailyBreakdowns = $this->hasDailyBreakdowns($latestSearchId);

        $searchData = $this->searchData($latestSearch, $selectedRange, $hasDailyBreakdowns);
        [$top, $opportunity, $striking] = $searchData['query_segments'];
        $breakdowns = $searchData['breakdowns'];
        $dateSeries = $searchData['date_series'];

        return [
            'searchTrend' => $this->searchTrend($property),
            'topQueries' => $top,
            'opportunityQueries' => $opportunity,
            'strikingDistance' => $striking,
            'breakdowns' => $breakdowns,
            'dateSeries' => $dateSeries,
            'searchMeta' => [
                'range_days' => $rangeDays,
                'available_days' => $availableRange['days'],
                'available_start' => $availableRange['start'],
                'available_end' => $availableRange['end'],
                'needs_more_data' => $availableRange['days'] > 0 && $rangeDays > $availableRange['days'],
                'has_daily_breakdowns' => $hasDailyBreakdowns,
                'active_tab' => $activeTab,
                'per_page' => $perPage,
            ],
            'tables' => [
                'query' => $this->paginateRows($top, 'gsc_query_page', $perPage, $activeTab === 'query'),
                'opportunity' => $this->paginateRows($opportunity, 'gsc_opportunity_page', $perPage, $activeTab === 'opportunity'),
                'striking' => $this->paginateRows($striking, 'gsc_striking_page', $perPage, $activeTab === 'striking'),
                'page' => $this->paginateRows($breakdowns['page'], 'gsc_page_page', $perPage, $activeTab === 'page'),
                'country' => $this->paginateRows($breakdowns['country'], 'gsc_country_page', $perPage, $activeTab === 'country'),
                'device' => $this->paginateRows($breakdowns['device'], 'gsc_device_page', $perPage, $activeTab === 'device'),
                'appearance' => $this->paginateRows($breakdowns['search_appearance'], 'gsc_appearance_page', $perPage, $activeTab === 'appearance'),
                'date' => $this->paginateRows($dateSeries, 'gsc_date_page', $perPage, $activeTab === 'date'),
            ],
            'indexingTrend' => $this->indexingTrend($property),
            'indexingDropouts' => $this->indexingDropouts($property),
        ];
    }

    /**
     * @return array{
     *     query_segments: array{0:list<array<string,mixed>>,1:list<array<string,mixed>>,2:list<array<string,mixed>>},
     *     breakdowns: array<string,list<array<string,mixed>>>,
     *     date_series: list<array<string,mixed>>
     * }
     */
    private function searchData(?GscSnapshot $snapshot, array $selectedRange, bool $hasDailyBreakdowns): array
    {
        $snapshotId = $snapshot?->id;
        if ($snapshotId === null) {
            return [
                'query_segments' => [[], [], []],
                'breakdowns' => [
                    'page' => [],
                    'country' => [],
                    'device' => [],
                    'search_appearance' => [],
                ],
                'date_series' => [],
            ];
        }

        $cacheKey = implode(':', [
            'gsc-insights',
            'search-data',
            $snapshotId,
            optional($snapshot->updated_at)->getTimestamp() ?? 0,
            $selectedRange['start'] ?? 'none',
            $selectedRange['end'] ?? 'none',
            $hasDailyBreakdowns ? 'daily' : 'legacy',
        ]);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($snapshotId, $selectedRange, $hasDailyBreakdowns): array {
            return [
                'query_segments' => $this->querySegments($snapshotId, $selectedRange, $hasDailyBreakdowns),
                'breakdowns' => [
                    'page' => $this->breakdown($snapshotId, 'page', $selectedRange, $hasDailyBreakdowns),
                    'country' => $this->breakdown($snapshotId, 'country', $selectedRange, $hasDailyBreakdowns),
                    'device' => $this->breakdown($snapshotId, 'device', $selectedRange, $hasDailyBreakdowns),
                    'search_appearance' => $this->breakdown($snapshotId, 'search_appearance', $selectedRange, $hasDailyBreakdowns),
                ],
                'date_series' => $this->dateSeries($snapshotId, $selectedRange),
            ];
        });
    }

    private function latestSearchSnapshot(GscProperty $property): ?GscSnapshot
    {
        return $property->snapshots()
            ->where('type', GscSnapshot::TYPE_SEARCH_ANALYTICS)
            ->where('status', 'success')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function breakdown(?int $snapshotId, string $dimension, array $selectedRange, bool $hasDailyBreakdowns): array
    {
        if ($snapshotId === null) {
            return [];
        }

        if ($hasDailyBreakdowns) {
            return $this->aggregateDailyDimension($snapshotId, $dimension, $selectedRange);
        }

        return GscSearchMetric::query()
            ->where('gsc_snapshot_id', $snapshotId)
            ->where('dimension', $dimension)
            ->orderByDesc('clicks')->orderByDesc('impressions')
            ->get()
            ->map(static fn (GscSearchMetric $m): array => [
                'value' => (string) $m->dimension_value,
                'clicks' => (int) $m->clicks,
                'impressions' => (int) $m->impressions,
                'ctr' => (float) $m->ctr,
                'position' => round((float) $m->position, 1),
            ])
            ->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function dateSeries(?int $snapshotId, array $selectedRange): array
    {
        if ($snapshotId === null) {
            return [];
        }

        $query = GscSearchMetric::query()
            ->where('gsc_snapshot_id', $snapshotId)
            ->where('dimension', 'date');

        if ($selectedRange['start'] !== null && $selectedRange['end'] !== null) {
            $query->whereBetween('dimension_value', [$selectedRange['start'], $selectedRange['end']]);
        }

        return $query
            ->orderBy('dimension_value')
            ->get()
            ->map(static fn (GscSearchMetric $m): array => [
                'date' => (string) $m->dimension_value,
                'clicks' => (int) $m->clicks,
                'impressions' => (int) $m->impressions,
                'ctr' => (float) $m->ctr,
                'position' => round((float) $m->position, 1),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function searchTrend(GscProperty $property): ?array
    {
        $snaps = $property->snapshots()
            ->where('type', GscSnapshot::TYPE_SEARCH_ANALYTICS)
            ->where('status', 'success')
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        $latest = $snaps->get(0);
        if ($latest === null) {
            return null;
        }

        $previous = $snaps->get(1);
        $current = is_array($latest->stats) ? $latest->stats : [];
        $prior = $previous && is_array($previous->stats) ? $previous->stats : [];

        return [
            'has_previous' => $previous !== null,
            'ran_at' => $latest->ran_at,
            'clicks' => $this->delta((float) ($current['total_clicks'] ?? 0), isset($prior['total_clicks']) ? (float) $prior['total_clicks'] : null),
            'impressions' => $this->delta((float) ($current['total_impressions'] ?? 0), isset($prior['total_impressions']) ? (float) $prior['total_impressions'] : null),
            'position' => $this->delta((float) ($current['avg_position'] ?? 0), isset($prior['avg_position']) ? (float) $prior['avg_position'] : null, true),
        ];
    }

    /**
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>,2:list<array<string,mixed>>}
     */
    private function querySegments(?int $snapshotId, array $selectedRange, bool $hasDailyBreakdowns): array
    {
        if ($snapshotId === null) {
            return [[], [], []];
        }

        $byQuery = collect(
            $hasDailyBreakdowns
                ? $this->aggregateDailyDimension($snapshotId, 'query', $selectedRange)
                : GscSearchMetric::query()
                    ->where('gsc_snapshot_id', $snapshotId)
                    ->where('dimension', 'query')
                    ->orderByDesc('clicks')->orderByDesc('impressions')
                    ->get()
                    ->map(static fn (GscSearchMetric $m): array => [
                        'value' => (string) $m->dimension_value,
                        'clicks' => (int) $m->clicks,
                        'impressions' => (int) $m->impressions,
                        'ctr' => (float) $m->ctr,
                        'position' => round((float) $m->position, 1),
                    ])
                    ->all()
        )->map(static fn (array $r): array => [
            'query' => (string) ($r['value'] ?? $r['query'] ?? ''),
            'clicks' => (int) $r['clicks'],
            'impressions' => (int) $r['impressions'],
            'ctr' => (float) $r['ctr'],
            'position' => (float) $r['position'],
        ])->values();

        $minImpr = max(0, (int) config('geoflow.google_search_console.insights.opportunity_min_impressions', 50));
        $maxCtr = (float) config('geoflow.google_search_console.insights.opportunity_max_ctr', 0.02);
        $minPos = (float) config('geoflow.google_search_console.insights.striking_min_position', 10);
        $maxPos = (float) config('geoflow.google_search_console.insights.striking_max_position', 20);

        $top = $byQuery->sortByDesc('clicks')->values()->all();

        $opportunity = $byQuery
            ->filter(fn (array $r): bool => $r['impressions'] >= $minImpr && $r['ctr'] < $maxCtr)
            ->sortByDesc('impressions')->values()->all();

        $striking = $byQuery
            ->filter(fn (array $r): bool => $r['position'] > $minPos && $r['position'] <= $maxPos)
            ->sortByDesc('impressions')->values()->all();

        return [$top, $opportunity, $striking];
    }

    /**
     * @return list<array{value:string,clicks:int,impressions:int,ctr:float,position:float}>
     */
    private function aggregateDailyDimension(?int $snapshotId, string $dimension, array $selectedRange): array
    {
        if ($snapshotId === null || $selectedRange['start'] === null || $selectedRange['end'] === null) {
            return [];
        }

        return GscSearchMetric::query()
            ->select([
                'dimension_value',
                DB::raw('SUM(clicks) as clicks_sum'),
                DB::raw('SUM(impressions) as impressions_sum'),
                DB::raw('SUM(position * impressions) as weighted_position_sum'),
            ])
            ->where('gsc_snapshot_id', $snapshotId)
            ->where('dimension', 'date_'.$dimension)
            ->whereBetween('date_start', [$selectedRange['start'], $selectedRange['end']])
            ->groupBy('dimension_value')
            ->orderByDesc('clicks_sum')
            ->orderByDesc('impressions_sum')
            ->get()
            ->map(static function (GscSearchMetric $m): array {
                $clicks = (int) $m->getAttribute('clicks_sum');
                $impressions = (int) $m->getAttribute('impressions_sum');

                return [
                    'value' => (string) $m->dimension_value,
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $impressions > 0 ? $clicks / $impressions : 0.0,
                    'position' => $impressions > 0 ? round((float) $m->getAttribute('weighted_position_sum') / $impressions, 1) : 0.0,
                ];
            })
            ->all();
    }

    private function hasDailyBreakdowns(?int $snapshotId): bool
    {
        if ($snapshotId === null) {
            return false;
        }

        return GscSearchMetric::query()
            ->where('gsc_snapshot_id', $snapshotId)
            ->whereIn('dimension', ['date_query', 'date_page', 'date_country', 'date_device', 'date_search_appearance'])
            ->exists();
    }

    /**
     * @return array{start:?string,end:?string,days:int}
     */
    private function availableSearchRange(?GscSnapshot $snapshot): array
    {
        $stats = $snapshot && is_array($snapshot->stats) ? $snapshot->stats : [];
        $start = isset($stats['date_start']) ? (string) $stats['date_start'] : null;
        $end = isset($stats['date_end']) ? (string) $stats['date_end'] : null;

        if ($start === null || $end === null) {
            if ($snapshot !== null) {
                $bounds = GscSearchMetric::query()
                    ->where('gsc_snapshot_id', $snapshot->id)
                    ->where('dimension', 'date')
                    ->selectRaw('MIN(dimension_value) as start_date, MAX(dimension_value) as end_date')
                    ->first();

                $start = is_string($bounds?->getAttribute('start_date')) ? (string) $bounds->getAttribute('start_date') : null;
                $end = is_string($bounds?->getAttribute('end_date')) ? (string) $bounds->getAttribute('end_date') : null;
            }

            if ($start === null || $end === null) {
                return ['start' => null, 'end' => null, 'days' => 0];
            }
        }

        try {
            $startDate = CarbonImmutable::parse($start);
            $endDate = CarbonImmutable::parse($end);
        } catch (\Throwable) {
            return ['start' => null, 'end' => null, 'days' => 0];
        }

        return [
            'start' => $start,
            'end' => $end,
            'days' => (int) $startDate->diffInDays($endDate) + 1,
        ];
    }

    /**
     * @return array{start:?string,end:?string}
     */
    private function selectedRange(array $availableRange, int $rangeDays): array
    {
        if ($availableRange['end'] === null) {
            return ['start' => null, 'end' => null];
        }

        $end = CarbonImmutable::parse((string) $availableRange['end']);
        $start = $end->subDays(max(1, $rangeDays) - 1);

        if ($availableRange['start'] !== null) {
            $availableStart = CarbonImmutable::parse((string) $availableRange['start']);
            if ($start->lt($availableStart)) {
                $start = $availableStart;
            }
        }

        return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string,mixed>>
     */
    private function paginateRows(array $rows, string $pageName, int $perPage, bool $active): LengthAwarePaginator
    {
        $page = $active ? LengthAwarePaginator::resolveCurrentPage($pageName) : 1;
        $items = collect($rows);
        $pageItems = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $pageItems,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
                'query' => request()->except([...self::PAGE_NAMES, 'partial']),
            ],
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function indexingTrend(GscProperty $property): array
    {
        return $property->snapshots()
            ->where('type', GscSnapshot::TYPE_SITEMAPS)
            ->where('status', 'success')
            ->orderByDesc('id')
            ->limit(14)
            ->get()
            ->reverse()
            ->map(static fn (GscSnapshot $s): array => [
                'ran_at' => optional($s->ran_at)->format('m-d H:i'),
                'submitted' => (int) (($s->stats['submitted'] ?? 0)),
                'indexed' => (int) (($s->stats['indexed'] ?? 0)),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function indexingDropouts(GscProperty $property): array
    {
        $snaps = $property->snapshots()
            ->where('type', GscSnapshot::TYPE_URL_INSPECTION)
            ->where('status', 'success')
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        $latest = $snaps->get(0);
        $previous = $snaps->get(1);
        if ($latest === null || $previous === null) {
            return [];
        }

        $latestByUrl = GscUrlInspection::query()->where('gsc_snapshot_id', $latest->id)->get()->keyBy('url');
        $previousByUrl = GscUrlInspection::query()->where('gsc_snapshot_id', $previous->id)->get()->keyBy('url');

        $dropouts = [];
        foreach ($previousByUrl as $url => $prev) {
            if ($prev->verdict !== 'PASS') {
                continue;
            }
            $now = $latestByUrl->get($url);
            if ($now !== null && $now->verdict !== 'PASS') {
                $dropouts[] = [
                    'url' => (string) $url,
                    'was' => (string) $prev->verdict,
                    'now' => (string) ($now->verdict ?? ''),
                    'coverage_state' => (string) ($now->coverage_state ?? ''),
                ];
            }
        }

        return $dropouts;
    }

    /**
     * @return array<string, mixed>
     */
    private function delta(float $current, ?float $previous, bool $lowerIsBetter = false): array
    {
        $change = $previous === null ? null : $current - $previous;
        $pct = ($previous === null || $previous == 0.0) ? null : round(($current - $previous) / abs($previous) * 100, 1);

        $direction = 'flat';
        if ($change !== null && abs($change) > 0.0001) {
            $rising = $change > 0;
            $direction = ($rising xor $lowerIsBetter) ? 'good' : 'bad';
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'change' => $change,
            'pct' => $pct,
            'direction' => $direction,
        ];
    }
}
