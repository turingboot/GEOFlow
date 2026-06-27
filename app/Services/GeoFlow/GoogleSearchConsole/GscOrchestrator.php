<?php

namespace App\Services\GeoFlow\GoogleSearchConsole;

use App\Models\GscProperty;
use App\Models\GscSearchMetric;
use App\Models\GscSnapshot;
use App\Models\GscUrlInspection;
use Throwable;

/**
 * 谷歌搜录拉取编排：每个动作产出一条快照，失败只记入 snapshot.error，不向上抛。
 *
 * - run()                 常态化监控：搜索表现 + sitemap 收录概览。
 * - runSearchAnalytics()  词/页 + 点击/曝光/CTR/排名 明细入库。
 * - runSitemaps()         sitemap 提交数 vs 已索引数概览。
 * - inspectUrls()         指定 URL 列表的收录状态（受每日配额保护）。
 */
class GscOrchestrator
{
    public function __construct(
        private readonly GoogleSearchConsoleClient $client
    ) {}

    public function run(GscProperty $property): GscSnapshot
    {
        $snapshot = $this->runSearchAnalytics($property);

        // sitemap 概览尽力而为，失败不影响主快照。
        try {
            $this->runSitemaps($property);
        } catch (Throwable) {
            // 已在 runSitemaps 内记录到其自身快照。
        }

        $property->forceFill(['last_fetched_at' => now()])->save();

        return $snapshot;
    }

    public function runSearchAnalytics(GscProperty $property): GscSnapshot
    {
        $snapshot = $this->startSnapshot($property, GscSnapshot::TYPE_SEARCH_ANALYTICS);

        try {
            [$start, $end] = $this->dateRange();
            $data = $this->client->searchAnalytics($property, [
                'startDate' => $start,
                'endDate' => $end,
                'dimensions' => ['query', 'page'],
                'rowLimit' => $this->rowLimit(),
            ]);

            $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
            $kept = 0;
            $totalClicks = 0;
            $totalImpressions = 0;

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $keys = is_array($row['keys'] ?? null) ? $row['keys'] : [];
                GscSearchMetric::query()->create([
                    'tenant_id' => $property->tenant_id,
                    'gsc_snapshot_id' => $snapshot->id,
                    'gsc_property_id' => $property->id,
                    'query' => (string) ($keys[0] ?? ''),
                    'page' => (string) ($keys[1] ?? ''),
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => (float) ($row['position'] ?? 0),
                    'date_start' => $start,
                    'date_end' => $end,
                    'raw' => $row,
                ]);
                $kept++;
                $totalClicks += (int) ($row['clicks'] ?? 0);
                $totalImpressions += (int) ($row['impressions'] ?? 0);
            }

            $this->finishSnapshot($snapshot, 'success', $kept, [
                'date_start' => $start,
                'date_end' => $end,
                'total_clicks' => $totalClicks,
                'total_impressions' => $totalImpressions,
            ]);
        } catch (Throwable $e) {
            $this->finishSnapshot($snapshot, 'failed', 0, [], $e->getMessage());
        }

        return $snapshot->refresh();
    }

    public function runSitemaps(GscProperty $property): GscSnapshot
    {
        $snapshot = $this->startSnapshot($property, GscSnapshot::TYPE_SITEMAPS);

        try {
            $data = $this->client->listSitemaps($property);
            $sitemaps = is_array($data['sitemap'] ?? null) ? $data['sitemap'] : [];

            $submitted = 0;
            $indexed = 0;
            foreach ($sitemaps as $sitemap) {
                foreach ((array) ($sitemap['contents'] ?? []) as $content) {
                    $submitted += (int) ($content['submitted'] ?? 0);
                    $indexed += (int) ($content['indexed'] ?? 0);
                }
            }

            $this->finishSnapshot($snapshot, 'success', count($sitemaps), [
                'sitemaps' => count($sitemaps),
                'submitted' => $submitted,
                'indexed' => $indexed,
            ]);
        } catch (Throwable $e) {
            $this->finishSnapshot($snapshot, 'failed', 0, [], $e->getMessage());
        }

        return $snapshot->refresh();
    }

    /**
     * @param  list<string>  $urls
     */
    public function inspectUrls(GscProperty $property, array $urls): GscSnapshot
    {
        $snapshot = $this->startSnapshot($property, GscSnapshot::TYPE_URL_INSPECTION);

        $quota = (int) config('geoflow.google_search_console.url_inspection_daily_quota', 2000);
        $urls = array_values(array_filter(array_unique(array_map('strval', $urls)), static fn (string $u): bool => $u !== ''));
        if ($quota > 0) {
            $urls = array_slice($urls, 0, $quota);
        }

        try {
            $count = 0;
            $indexedCount = 0;
            foreach ($urls as $url) {
                $data = $this->client->inspectUrl($property, $url);
                $result = $data['inspectionResult']['indexStatusResult'] ?? [];
                $result = is_array($result) ? $result : [];
                $verdict = (string) ($result['verdict'] ?? '');

                GscUrlInspection::query()->create([
                    'tenant_id' => $property->tenant_id,
                    'gsc_snapshot_id' => $snapshot->id,
                    'gsc_property_id' => $property->id,
                    'url' => $url,
                    'coverage_state' => (string) ($result['coverageState'] ?? '') ?: null,
                    'verdict' => $verdict ?: null,
                    'indexing_state' => (string) ($result['indexingState'] ?? '') ?: null,
                    'robots_state' => (string) ($result['robotsTxtState'] ?? '') ?: null,
                    'google_canonical' => (string) ($result['googleCanonical'] ?? '') ?: null,
                    'last_crawl_time' => $this->parseTime($result['lastCrawlTime'] ?? null),
                    'raw' => $result,
                ]);

                $count++;
                if ($verdict === 'PASS') {
                    $indexedCount++;
                }
            }

            $this->finishSnapshot($snapshot, 'success', $count, [
                'inspected' => $count,
                'indexed' => $indexedCount,
                'requested' => count($urls),
            ]);
        } catch (Throwable $e) {
            $this->finishSnapshot($snapshot, 'failed', 0, [], $e->getMessage());
        }

        return $snapshot->refresh();
    }

    private function startSnapshot(GscProperty $property, string $type): GscSnapshot
    {
        return GscSnapshot::query()->create([
            'tenant_id' => $property->tenant_id,
            'gsc_property_id' => $property->id,
            'type' => $type,
            'status' => 'running',
            'ran_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function finishSnapshot(GscSnapshot $snapshot, string $status, int $fetched, array $stats, ?string $error = null): void
    {
        $snapshot->forceFill([
            'status' => $status,
            'fetched_count' => $fetched,
            'stats' => $stats,
            'error' => $error,
        ])->save();
    }

    /**
     * 结束日期取 today-2（GSC 数据约 2 天延迟），起始日期回溯配置天数。
     *
     * @return array{0:string,1:string}
     */
    private function dateRange(): array
    {
        $end = now()->subDays(2);
        $start = $end->copy()->subDays(max(1, (int) config('geoflow.google_search_console.default_range_days', 28)));

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    private function rowLimit(): int
    {
        return max(1, min(25000, (int) config('geoflow.google_search_console.row_limit', 1000)));
    }

    private function parseTime(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return now()->parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
