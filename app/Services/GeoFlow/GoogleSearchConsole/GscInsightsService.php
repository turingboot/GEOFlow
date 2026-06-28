<?php

namespace App\Services\GeoFlow\GoogleSearchConsole;

use App\Models\GscProperty;
use App\Models\GscSearchMetric;
use App\Models\GscSnapshot;
use App\Models\GscUrlInspection;

/**
 * 基于已采集的快照/明细，派生分析视图：
 * - 搜索趋势（本期 vs 上期点击/曝光/排名涨跌）
 * - Top 点击词 / 机会词（高曝光低点击）/ 临门一脚词（排名 10–20）
 * - 收录率趋势（近期 sitemap 已收录曲线）
 * - 掉收录告警（某 URL 从已索引变为未索引）
 */
class GscInsightsService
{
    /**
     * @return array<string, mixed>
     */
    public function build(GscProperty $property): array
    {
        $latestSearchId = $this->latestSearchSnapshotId($property);
        [$top, $opportunity, $striking] = $this->querySegments($latestSearchId);

        return [
            'searchTrend' => $this->searchTrend($property),
            'topQueries' => $top,
            'opportunityQueries' => $opportunity,
            'strikingDistance' => $striking,
            'breakdowns' => [
                'page' => $this->breakdown($latestSearchId, 'page'),
                'country' => $this->breakdown($latestSearchId, 'country'),
                'device' => $this->breakdown($latestSearchId, 'device'),
                'search_appearance' => $this->breakdown($latestSearchId, 'search_appearance'),
            ],
            'dateSeries' => $this->dateSeries($latestSearchId),
            'indexingTrend' => $this->indexingTrend($property),
            'indexingDropouts' => $this->indexingDropouts($property),
        ];
    }

    private function latestSearchSnapshotId(GscProperty $property): ?int
    {
        $snapshot = $property->snapshots()
            ->where('type', GscSnapshot::TYPE_SEARCH_ANALYTICS)
            ->where('status', 'success')
            ->orderByDesc('id')
            ->first();

        return $snapshot?->id;
    }

    /**
     * 某个单维度的明细（取点击 Top N），列为：取值 + 4 个指标。
     *
     * @return list<array<string,mixed>>
     */
    private function breakdown(?int $snapshotId, string $dimension): array
    {
        if ($snapshotId === null) {
            return [];
        }

        $limit = max(1, (int) config('geoflow.google_search_console.insights.top_limit', 10));

        return GscSearchMetric::query()
            ->where('gsc_snapshot_id', $snapshotId)
            ->where('dimension', $dimension)
            ->orderByDesc('clicks')->orderByDesc('impressions')
            ->limit($limit)
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
     * 按日期的点击/曝光时间序列（用于顶部折线）。
     *
     * @return list<array<string,mixed>>
     */
    private function dateSeries(?int $snapshotId): array
    {
        if ($snapshotId === null) {
            return [];
        }

        return GscSearchMetric::query()
            ->where('gsc_snapshot_id', $snapshotId)
            ->where('dimension', 'date')
            ->orderBy('dimension_value')
            ->get()
            ->map(static fn (GscSearchMetric $m): array => [
                'date' => (string) $m->dimension_value,
                'clicks' => (int) $m->clicks,
                'impressions' => (int) $m->impressions,
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
    private function querySegments(?int $snapshotId): array
    {
        if ($snapshotId === null) {
            return [[], [], []];
        }

        // query 维度每行已是单词聚合，直接用。
        $byQuery = GscSearchMetric::query()
            ->where('gsc_snapshot_id', $snapshotId)
            ->where('dimension', 'query')
            ->get()
            ->map(static fn (GscSearchMetric $m): array => [
                'query' => (string) $m->dimension_value,
                'clicks' => (int) $m->clicks,
                'impressions' => (int) $m->impressions,
                'ctr' => (float) $m->ctr,
                'position' => round((float) $m->position, 1),
            ])
            ->values();

        $limit = max(1, (int) config('geoflow.google_search_console.insights.top_limit', 10));
        $minImpr = max(0, (int) config('geoflow.google_search_console.insights.opportunity_min_impressions', 50));
        $maxCtr = (float) config('geoflow.google_search_console.insights.opportunity_max_ctr', 0.02);
        $minPos = (float) config('geoflow.google_search_console.insights.striking_min_position', 10);
        $maxPos = (float) config('geoflow.google_search_console.insights.striking_max_position', 20);

        $top = $byQuery->sortByDesc('clicks')->take($limit)->values()->all();

        $opportunity = $byQuery
            ->filter(fn (array $r): bool => $r['impressions'] >= $minImpr && $r['ctr'] < $maxCtr)
            ->sortByDesc('impressions')->take($limit)->values()->all();

        $striking = $byQuery
            ->filter(fn (array $r): bool => $r['position'] > $minPos && $r['position'] <= $maxPos)
            ->sortByDesc('impressions')->take($limit)->values()->all();

        return [$top, $opportunity, $striking];
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
            // 排名类越小越好：上升的数值反而是“变差”。
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
