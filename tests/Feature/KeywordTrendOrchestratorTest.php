<?php

namespace Tests\Feature;

use App\Models\Keyword;
use App\Models\KeywordLibrary;
use App\Models\KeywordTrend;
use App\Models\KeywordTrendSource;
use App\Models\KeywordTrendSourceSecret;
use App\Services\GeoFlow\KeywordTrend\KeywordTrendOrchestrator;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeywordTrendOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_filters_by_heat_and_auto_imports_into_library(): void
    {
        $library = KeywordLibrary::query()->create(['name' => 'Trends', 'keyword_count' => 0]);

        $source = KeywordTrendSource::query()->create([
            'name' => 'AI SEO',
            'provider' => 'dataforseo',
            'category' => 'ai seo',
            'seed_keywords' => ['ai seo'],
            'region' => 'US',
            'language' => 'en',
            'timeframe' => 'past_month',
            'heat_threshold' => 40,
            'top_n' => 10,
            'target_keyword_library_id' => $library->id,
            'auto_import' => true,
            'status' => 'active',
            'config' => ['login' => 'test@example.com', 'location_name' => 'United States'],
        ]);

        KeywordTrendSourceSecret::query()->create([
            'keyword_trend_source_id' => $source->id,
            'key_id' => 'kts_test',
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt('api-password'),
            'status' => 'active',
            'scopes' => ['trend.fetch'],
        ]);

        Http::fake(['api.dataforseo.com/*' => Http::response([
            'status_code' => 20000,
            'tasks' => [[
                'status_code' => 20000,
                'result' => [[
                    'items' => [
                        ['keyword' => 'ai seo tools', 'keyword_info' => ['search_volume' => 12000, 'monthly_searches' => [['search_volume' => 8000], ['search_volume' => 9000], ['search_volume' => 15000]]]],
                        ['keyword' => 'keyword research', 'keyword_info' => ['search_volume' => 5000, 'monthly_searches' => [['search_volume' => 5000], ['search_volume' => 5000], ['search_volume' => 5200]]]],
                        ['keyword' => 'low volume term', 'keyword_info' => ['search_volume' => 5, 'monthly_searches' => [['search_volume' => 5]]]],
                    ],
                ]],
            ]],
        ], 200)]);

        $snapshot = app(KeywordTrendOrchestrator::class)->run($source->fresh());

        $this->assertSame('success', $snapshot->status);
        $this->assertSame(3, $snapshot->fetched_count);
        $this->assertSame(2, $snapshot->kept_count);
        $this->assertSame(2, $snapshot->imported_count);

        $this->assertSame(2, KeywordTrend::query()->where('keyword_trend_source_id', $source->id)->count());
        $this->assertSame(2, Keyword::query()->where('library_id', $library->id)->count());
        $this->assertSame(2, (int) $library->fresh()->keyword_count);
        $this->assertTrue(Keyword::query()->where('library_id', $library->id)->where('keyword', 'ai seo tools')->exists());
        $this->assertFalse(Keyword::query()->where('keyword', 'low volume term')->exists());
    }

    public function test_missing_credentials_marks_snapshot_failed(): void
    {
        $source = KeywordTrendSource::query()->create([
            'name' => 'No creds',
            'provider' => 'dataforseo',
            'category' => 'ai seo',
            'region' => 'US',
            'heat_threshold' => 40,
            'top_n' => 10,
            'status' => 'active',
            'config' => ['login' => 'test@example.com'],
        ]);

        $snapshot = app(KeywordTrendOrchestrator::class)->run($source);

        $this->assertSame('failed', $snapshot->status);
        $this->assertNotEmpty($snapshot->error);
    }
}
