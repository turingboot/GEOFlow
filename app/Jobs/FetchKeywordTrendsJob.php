<?php

namespace App\Jobs;

use App\Models\KeywordTrendSource;
use App\Services\GeoFlow\KeywordTrend\KeywordTrendOrchestrator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchKeywordTrendsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(private readonly int $sourceId) {}

    public function handle(KeywordTrendOrchestrator $orchestrator): void
    {
        $source = KeywordTrendSource::withoutGlobalScopes()->find($this->sourceId);
        if ($source === null) {
            return;
        }

        TenantContext::run((int) $source->tenant_id, function () use ($orchestrator, $source): void {
            $orchestrator->run($source);
        });
    }
}
