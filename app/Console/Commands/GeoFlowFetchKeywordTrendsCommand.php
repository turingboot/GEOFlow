<?php

namespace App\Console\Commands;

use App\Jobs\FetchKeywordTrendsJob;
use App\Models\KeywordTrendSource;
use App\Services\GeoFlow\KeywordTrend\KeywordTrendOrchestrator;
use Illuminate\Console\Command;

class GeoFlowFetchKeywordTrendsCommand extends Command
{
    protected $signature = 'geoflow:fetch-keyword-trends {--source= : Only this source id} {--sync : Run inline instead of queueing}';

    protected $description = 'Dispatch due keyword-trend sources to fetch + import trending keywords';

    public function handle(KeywordTrendOrchestrator $orchestrator): int
    {
        $sourceId = $this->option('source');
        $only = $sourceId !== null && $sourceId !== '';

        $query = KeywordTrendSource::query()->where('status', 'active');
        if ($only) {
            $query->whereKey((int) $sourceId);
        }

        $processed = 0;
        foreach ($query->get() as $source) {
            // When targeting a specific source, ignore the schedule (manual run).
            if (! $only && ! $this->isDue($source)) {
                continue;
            }

            if ($this->option('sync')) {
                $orchestrator->run($source);
            } else {
                FetchKeywordTrendsJob::dispatch((int) $source->id)->onQueue('trends');
            }
            $processed++;
        }

        $this->info("Keyword trends: {$processed} source(s) processed.");

        return self::SUCCESS;
    }

    private function isDue(KeywordTrendSource $source): bool
    {
        $schedule = (string) $source->schedule;
        if ($schedule === '' || $schedule === 'manual') {
            return false;
        }

        $last = $source->last_fetched_at;
        if ($last === null) {
            return true;
        }

        $cutoff = match ($schedule) {
            'hourly' => now()->subHour(),
            'weekly' => now()->subWeek(),
            default => now()->subDay(),
        };

        return $last->lessThan($cutoff);
    }
}
