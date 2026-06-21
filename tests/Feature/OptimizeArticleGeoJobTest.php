<?php

namespace Tests\Feature;

use App\Jobs\OptimizeArticleGeoJob;
use App\Models\AiModel;
use App\Services\GeoFlow\GeoArticleAuditService;
use App\Services\GeoFlow\GeoArticleOptimizerService;
use App\Services\GeoFlow\KnowledgeRetrievalService;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\MakesGeoArticles;
use Tests\TestCase;

class OptimizeArticleGeoJobTest extends TestCase
{
    use MakesGeoArticles;
    use RefreshDatabase;

    public function test_job_optimizes_and_clears_lock_on_success(): void
    {
        AiModel::query()->create(['name' => 'Writer', 'model_id' => 'gpt-x', 'api_url' => 'https://api.example.com/v1', 'status' => 'active']);
        $article = $this->makeArticle(['title' => 'AI 客服怎么选', 'original_keyword' => 'AI 客服', 'content' => '流水文字。']);
        Cache::put(OptimizeArticleGeoJob::lockKey($article->id), true, now()->addMinutes(10));

        $optimized = "## AI 客服\n\nAI 客服很重要。\n\n- 列表\n\n总结。";
        $this->app->bind(GeoArticleOptimizerService::class, function ($app) use ($optimized) {
            $stub = new class($app->make(ApiKeyCrypto::class), $app->make(KnowledgeRetrievalService::class), $app->make(GeoArticleAuditService::class)) extends GeoArticleOptimizerService
            {
                public string $canned = '';

                protected function runOptimizerModel(AiModel $model, string $prompt): string
                {
                    return $this->canned;
                }
            };
            $stub->canned = $optimized;

            return $stub;
        });

        (new OptimizeArticleGeoJob($article->id))->handle($this->app->make(GeoArticleOptimizerService::class));

        $this->assertSame($optimized, $article->fresh()->content);
        $this->assertFalse((bool) Cache::get(OptimizeArticleGeoJob::lockKey($article->id)));
        $this->assertNull(Cache::get(OptimizeArticleGeoJob::errorKey($article->id)));
    }

    public function test_job_records_error_and_clears_lock_on_failure(): void
    {
        // 不创建任何 AI 模型 → 优化器 optimize() 抛「找不到模型」。
        $article = $this->makeArticle(['content' => '原文']);
        Cache::put(OptimizeArticleGeoJob::lockKey($article->id), true, now()->addMinutes(10));

        (new OptimizeArticleGeoJob($article->id))->handle($this->app->make(GeoArticleOptimizerService::class));

        $this->assertFalse((bool) Cache::get(OptimizeArticleGeoJob::lockKey($article->id)));
        $this->assertNotNull(Cache::get(OptimizeArticleGeoJob::errorKey($article->id)));
        $this->assertSame('原文', $article->fresh()->content, '失败时不应改动正文');
    }
}
