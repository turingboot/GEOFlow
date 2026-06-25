<?php

namespace App\Services\GeoFlow\KeywordTrend;

use App\Models\KeywordTrend;
use App\Models\KeywordTrendSnapshot;
use App\Models\KeywordTrendSource;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Runs a fetch for one source: provider fetch -> dedupe -> filter (heat / rising, top-N)
 * -> persist snapshot + trend rows -> optional auto-import into the keyword library.
 */
class KeywordTrendOrchestrator
{
    public function __construct(
        private readonly KeywordTrendProviderManager $providers,
        private readonly KeywordTrendImportService $importer,
        private readonly KeywordTrendRelevanceFilter $relevanceFilter,
    ) {}

    public function run(KeywordTrendSource $source): KeywordTrendSnapshot
    {
        $snapshot = KeywordTrendSnapshot::query()->create([
            'tenant_id' => (int) $source->tenant_id,
            'keyword_trend_source_id' => $source->id,
            'status' => 'running',
            'ran_at' => Carbon::now(),
        ]);

        try {
            $provider = $this->providers->forSource($source);
            $trends = $provider->fetchTrends($source);
            $kept = $this->filter($source, $trends);

            if ((bool) $source->ai_relevance) {
                $kept = $this->relevanceFilter->filter((string) $source->category, $kept);
            }

            $now = Carbon::now();
            foreach ($kept as $t) {
                KeywordTrend::query()->create([
                    'tenant_id' => (int) $source->tenant_id,
                    'keyword_trend_snapshot_id' => $snapshot->id,
                    'keyword_trend_source_id' => $source->id,
                    'keyword' => $t->keyword,
                    'heat' => $t->heat,
                    'search_volume' => $t->searchVolume,
                    'trend_direction' => $t->trendDirection,
                    'delta' => $t->delta,
                    'region' => $t->region,
                    'language' => $t->language,
                    'captured_at' => $now,
                    'raw' => $t->raw,
                    'imported' => false,
                ]);
            }

            $importedCount = 0;
            if ($source->isAutoImport()) {
                $result = $this->importer->import($source, $snapshot->trends()->get());
                $importedCount = (int) $result['imported'];
            }

            $snapshot->update([
                'status' => 'success',
                'fetched_count' => count($trends),
                'kept_count' => count($kept),
                'imported_count' => $importedCount,
                'stats' => [
                    'fetched' => count($trends),
                    'kept' => count($kept),
                    'imported' => $importedCount,
                ],
            ]);
            $source->update(['last_fetched_at' => $now]);
        } catch (Throwable $e) {
            $snapshot->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $snapshot->fresh() ?? $snapshot;
    }

    /**
     * Dedupe by lowercased keyword (keep hottest), filter by heat threshold or rising,
     * sort by heat desc, take top-N.
     *
     * @param  list<NormalizedTrend>  $trends
     * @return list<NormalizedTrend>
     */
    private function filter(KeywordTrendSource $source, array $trends): array
    {
        $threshold = (int) ($source->heat_threshold ?? 60);
        $topN = max(1, (int) ($source->top_n ?: 50));

        $byKeyword = [];
        foreach ($trends as $t) {
            $key = mb_strtolower(trim($t->keyword));
            if ($key === '') {
                continue;
            }
            if (! isset($byKeyword[$key]) || $t->heat > $byKeyword[$key]->heat) {
                $byKeyword[$key] = $t;
            }
        }

        $kept = array_values(array_filter(
            $byKeyword,
            static fn (NormalizedTrend $t): bool => $t->heat >= $threshold || $t->trendDirection === 'rising',
        ));

        usort($kept, static fn (NormalizedTrend $a, NormalizedTrend $b): int => $b->heat <=> $a->heat);

        return array_slice($kept, 0, $topN);
    }
}
