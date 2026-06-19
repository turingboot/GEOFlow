<?php

namespace Tests\Feature;

use App\Jobs\FetchKeywordTrendsJob;
use App\Models\KeywordTrendSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KeywordTrendCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_due_sources_only(): void
    {
        Queue::fake();

        // Due: daily schedule, never fetched.
        KeywordTrendSource::query()->create([
            'name' => 'Due', 'provider' => 'dataforseo', 'category' => 'ai',
            'schedule' => 'daily', 'status' => 'active', 'last_fetched_at' => null,
        ]);
        // Not due: manual.
        KeywordTrendSource::query()->create([
            'name' => 'Manual', 'provider' => 'dataforseo', 'category' => 'ai',
            'schedule' => 'manual', 'status' => 'active',
        ]);
        // Not due: daily but fetched 1 hour ago.
        KeywordTrendSource::query()->create([
            'name' => 'Recent', 'provider' => 'dataforseo', 'category' => 'ai',
            'schedule' => 'daily', 'status' => 'active', 'last_fetched_at' => now()->subHour(),
        ]);

        $this->artisan('geoflow:fetch-keyword-trends')->assertSuccessful();

        Queue::assertPushed(FetchKeywordTrendsJob::class, 1);
        Queue::assertPushedOn('trends', FetchKeywordTrendsJob::class);
    }

    public function test_source_option_queues_that_source_regardless_of_schedule(): void
    {
        Queue::fake();

        $source = KeywordTrendSource::query()->create([
            'name' => 'Manual one', 'provider' => 'dataforseo', 'category' => 'ai',
            'schedule' => 'manual', 'status' => 'active',
        ]);

        $this->artisan('geoflow:fetch-keyword-trends', ['--source' => $source->id])->assertSuccessful();

        Queue::assertPushed(FetchKeywordTrendsJob::class, 1);
    }
}
