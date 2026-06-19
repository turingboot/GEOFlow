<?php

namespace Tests\Feature;

use App\Models\KeywordLibrary;
use App\Models\KeywordTrend;
use App\Models\KeywordTrendSource;
use App\Models\KeywordTrendSourceSecret;
use App\Services\GeoFlow\KeywordTrend\KeywordTrendOrchestrator;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeywordTrendSerpApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_serpapi_maps_rising_and_top_then_filters(): void
    {
        $library = KeywordLibrary::query()->create(['name' => 'Trends', 'keyword_count' => 0]);

        $source = KeywordTrendSource::query()->create([
            'name' => 'AI SEO', 'provider' => 'serpapi', 'category' => 'ai seo',
            'seed_keywords' => ['ai seo'], 'region' => 'US', 'language' => 'en',
            'heat_threshold' => 50, 'top_n' => 20, 'target_keyword_library_id' => $library->id,
            'auto_import' => false, 'status' => 'active',
        ]);

        KeywordTrendSourceSecret::query()->create([
            'keyword_trend_source_id' => $source->id,
            'key_id' => 'kts_serp',
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt('serp-key'),
            'status' => 'active',
            'scopes' => ['trend.fetch'],
        ]);

        Http::fake(['serpapi.com/*' => Http::response([
            'related_queries' => [
                'rising' => [
                    ['query' => 'ai seo agent', 'value' => 'Breakout', 'extracted_value' => 0],
                    ['query' => 'ai seo tools 2026', 'value' => '+250%', 'extracted_value' => 250],
                ],
                'top' => [
                    ['query' => 'best ai seo', 'value' => 100, 'extracted_value' => 100],
                    ['query' => 'cheap thing', 'value' => 10, 'extracted_value' => 10],
                ],
            ],
        ], 200)]);

        $snapshot = app(KeywordTrendOrchestrator::class)->run($source->fresh());

        $this->assertSame('success', $snapshot->status);
        $this->assertSame(4, $snapshot->fetched_count);
        // both rising kept; top: "best ai seo" (100>=50) kept, "cheap thing" (10<50, flat) filtered.
        $this->assertSame(3, $snapshot->kept_count);

        $this->assertTrue(KeywordTrend::query()->where('keyword', 'ai seo agent')->where('trend_direction', 'rising')->exists());
        $this->assertTrue(KeywordTrend::query()->where('keyword', 'best ai seo')->exists());
        $this->assertFalse(KeywordTrend::query()->where('keyword', 'cheap thing')->exists());
    }
}
