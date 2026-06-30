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

    public function run(GscProperty $property, ?int $rangeDays = null): GscSnapshot
    {
        $snapshot = $this->runSearchAnalytics($property, $rangeDays);

        // sitemap 概览尽力而为，失败不影响主快照。
        try {
            $this->runSitemaps($property);
        } catch (Throwable) {
            // 已在 runSitemaps 内记录到其自身快照。
        }

        $property->forceFill(['last_fetched_at' => now()])->save();

        return $snapshot;
    }

    /** 我们存的维度名 => GSC API 维度名。 */
    public const SEARCH_DIMENSIONS = [
        'query' => 'query',
        'page' => 'page',
        'country' => 'country',
        'device' => 'device',
        'date' => 'date',
        'search_appearance' => 'searchAppearance',
    ];

    private const DAILY_BREAKDOWN_DIMENSIONS = [
        'query',
        'page',
        'country',
        'device',
        'search_appearance',
    ];

    public function runSearchAnalytics(GscProperty $property, ?int $rangeDays = null): GscSnapshot
    {
        $snapshot = $this->startSnapshot($property, GscSnapshot::TYPE_SEARCH_ANALYTICS);

        try {
            [$start, $end] = $this->dateRange($rangeDays);
            $kept = 0;
            $totalClicks = 0;
            $totalImpressions = 0;
            $weightedPosition = 0.0;
            $successfulPrimaryDimensions = 0;
            $lastError = '';
            $dailyDimensionErrors = [];

            foreach (self::SEARCH_DIMENSIONS as $name => $apiDimension) {
                try {
                    $data = $this->client->searchAnalytics($property, [
                        'startDate' => $start,
                        'endDate' => $end,
                        'dimensions' => [$apiDimension],
                        'rowLimit' => $this->rowLimit(),
                    ]);
                } catch (Throwable $dimError) {
                    // 某些维度可能无数据/不受支持（如无富结果时的 searchAppearance），跳过不影响其余维度。
                    $lastError = $dimError->getMessage();

                    continue;
                }

                $successfulPrimaryDimensions++;

                foreach ((is_array($data['rows'] ?? null) ? $data['rows'] : []) as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $keys = is_array($row['keys'] ?? null) ? $row['keys'] : [];
                    $impressions = (int) ($row['impressions'] ?? 0);
                    $position = (float) ($row['position'] ?? 0);
                    $clicks = (int) ($row['clicks'] ?? 0);
                    GscSearchMetric::query()->create([
                        'tenant_id' => $property->tenant_id,
                        'gsc_snapshot_id' => $snapshot->id,
                        'gsc_property_id' => $property->id,
                        'dimension' => $name,
                        'dimension_value' => (string) ($keys[0] ?? ''),
                        'clicks' => $clicks,
                        'impressions' => $impressions,
                        'ctr' => (float) ($row['ctr'] ?? 0),
                        'position' => $position,
                        'date_start' => $start,
                        'date_end' => $end,
                        'raw' => $row,
                    ]);
                    $kept++;
                    // 总计用 date 维度（按天汇总，含被匿名化的查询，最接近真实总量）。
                    if ($name === 'date') {
                        $totalClicks += $clicks;
                        $totalImpressions += $impressions;
                        $weightedPosition += $position * $impressions;
                    }
                }
            }

            foreach (self::DAILY_BREAKDOWN_DIMENSIONS as $name) {
                $apiDimension = self::SEARCH_DIMENSIONS[$name];

                try {
                    $data = $this->client->searchAnalytics($property, [
                        'startDate' => $start,
                        'endDate' => $end,
                        'dimensions' => ['date', $apiDimension],
                        'rowLimit' => $this->rowLimit(),
                    ]);
                } catch (Throwable $dimError) {
                    $lastError = $dimError->getMessage();
                    $dailyDimensionErrors[$name] = $dimError->getMessage();

                    continue;
                }

                foreach ((is_array($data['rows'] ?? null) ? $data['rows'] : []) as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $keys = is_array($row['keys'] ?? null) ? $row['keys'] : [];
                    $date = (string) ($keys[0] ?? '');
                    $value = (string) ($keys[1] ?? '');
                    if ($date === '' || $value === '') {
                        continue;
                    }

                    GscSearchMetric::query()->create([
                        'tenant_id' => $property->tenant_id,
                        'gsc_snapshot_id' => $snapshot->id,
                        'gsc_property_id' => $property->id,
                        'dimension' => 'date_'.$name,
                        'dimension_value' => $value,
                        'clicks' => (int) ($row['clicks'] ?? 0),
                        'impressions' => (int) ($row['impressions'] ?? 0),
                        'ctr' => (float) ($row['ctr'] ?? 0),
                        'position' => (float) ($row['position'] ?? 0),
                        'date_start' => $date,
                        'date_end' => $date,
                        'raw' => $row,
                    ]);
                    $kept++;
                }
            }

            // 所有维度都失败（如鉴权/限流）才算整体失败；个别维度无数据不影响。
            if ($successfulPrimaryDimensions === 0) {
                $this->finishSnapshot($snapshot, 'failed', 0, [], $lastError);

                return $snapshot->refresh();
            }

            $this->finishSnapshot($snapshot, 'success', $kept, [
                'date_start' => $start,
                'date_end' => $end,
                'total_clicks' => $totalClicks,
                'total_impressions' => $totalImpressions,
                'daily_dimensions' => array_values(array_diff(self::DAILY_BREAKDOWN_DIMENSIONS, array_keys($dailyDimensionErrors))),
                'daily_dimension_errors' => $dailyDimensionErrors,
                // 整体平均排名按曝光加权（与 GSC 概览口径一致）。
                'avg_position' => $totalImpressions > 0 ? round($weightedPosition / $totalImpressions, 1) : 0.0,
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
    private function dateRange(?int $rangeDays = null): array
    {
        $end = now()->subDays(2);
        $days = $rangeDays ?? (int) config('geoflow.google_search_console.default_range_days', 90);
        $start = $end->copy()->subDays(max(1, $days) - 1);

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
