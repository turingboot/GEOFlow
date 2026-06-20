<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Services\GeoFlow\KeywordTrend\KeywordTrendRelevanceFilter;
use App\Services\GeoFlow\KeywordTrend\NormalizedTrend;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeywordTrendRelevanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<NormalizedTrend>
     */
    private function trends(): array
    {
        return [
            new NormalizedTrend('dog washing station', 100),
            new NormalizedTrend('dog bath tub', 90),
            new NormalizedTrend('shark cordless pet stick vacuum', 100),
        ];
    }

    private function seedChatModel(): void
    {
        AiModel::query()->create([
            'name' => 'Test Chat',
            'version' => 'test',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('test-key'),
            'model_id' => 'test-chat',
            'model_type' => 'chat',
            'api_url' => 'https://ai.test',
            'failover_priority' => 1,
            'daily_limit' => 0,
            'used_today' => 0,
            'total_used' => 0,
            'max_tokens' => 4096,
            'status' => 'active',
        ]);
    }

    public function test_ai_filter_drops_irrelevant_keywords(): void
    {
        $this->seedChatModel();

        Http::fake(['https://ai.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => '["dog washing station", "dog bath tub"]'],
                'finish_reason' => 'stop',
            ]],
        ])]);

        $kept = app(KeywordTrendRelevanceFilter::class)->filter('Pet Bathtub', $this->trends());
        $keywords = array_map(static fn (NormalizedTrend $t): string => $t->keyword, $kept);

        $this->assertContains('dog washing station', $keywords);
        $this->assertNotContains('shark cordless pet stick vacuum', $keywords);
        $this->assertCount(2, $kept);
    }

    public function test_fails_open_when_no_chat_model_configured(): void
    {
        // No AiModel -> keep all (never drop everything on a missing/failed model).
        $kept = app(KeywordTrendRelevanceFilter::class)->filter('Pet Bathtub', $this->trends());

        $this->assertCount(3, $kept);
    }
}
