<?php

namespace App\Services\GeoFlow\GoogleSearchConsole;

use App\Models\GscProperty;
use App\Models\GscSearchMetric;
use App\Models\GscSnapshot;
use App\Models\GscUrlInspection;
use Illuminate\Support\Collection;

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
        [$top, $opportunity, $striking] = $this->querySegments($property);

        return [
            'searchTrend' => $this->searchTrend($property),
            'topQueries' => $top,
            'opportunityQueries' => $opportunity,
            'strikingDistance' => $striking,
            'indexingTrend' => $this->indexingTrend($property),
            'indexingDropouts' => $this->indexingDropouts($property),
        ];
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
    private function querySegments(GscProperty $property): array
    {
        $latest = $property->snapshots()
            ->where('type', GscSnapshot::TYPE_SEARCH_ANALYTICS)
            ->where('status', 'success')
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return [[], [], []];
        }

        $byQuery = GscSearchMetric::query()
            ->where('gsc_snapshot_id', $latest->id)
            ->get()
            ->groupBy('query')
            ->map(function (Collection $group): array {
                $clicks = (int) $group->sum('clicks');
                $impressions = (int) $group->sum('impressions');
                $weighted = $group->sum(fn (GscSearchMetric $m): float => (float) $m->position * (int) $m->impressions);

                return [
                    'query' => (string) $group->first()->query,
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $impressions > 0 ? $clicks / $impressions : 0.0,
                    'position' => $impressions > 0 ? round($weighted / $impressions, 1) : 0.0,
                ];
            })
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
