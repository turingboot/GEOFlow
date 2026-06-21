<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\ArticleGeoAudit;
use App\Services\GeoFlow\GeoArticleAuditService;
use App\Services\GeoFlow\GeoArticleOptimizerService;
use App\Services\GeoFlow\KnowledgeRetrievalService;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\MakesGeoArticles;
use Tests\TestCase;

class GeoArticleOptimizerServiceTest extends TestCase
{
    use MakesGeoArticles;
    use RefreshDatabase;

    private function stubbedOptimizer(string $optimized): GeoArticleOptimizerService
    {
        $service = new class(app(ApiKeyCrypto::class), app(KnowledgeRetrievalService::class), app(GeoArticleAuditService::class)) extends GeoArticleOptimizerService
        {
            public string $optimized = '';

            protected function runOptimizerModel(AiModel $model, string $prompt): string
            {
                return $this->optimized;
            }
        };
        $service->optimized = $optimized;

        return $service;
    }

    public function test_optimize_rewrites_content_and_rescores_higher(): void
    {
        AiModel::query()->create(['name' => 'Writer', 'model_id' => 'gpt-x', 'api_url' => 'https://api.example.com/v1', 'status' => 'active']);

        $article = $this->makeArticle([
            'title' => 'AI 客服系统怎么选',
            'original_keyword' => 'AI 客服',
            'content' => '一段没有结构、也没有覆盖关键词的流水文字。',
        ]);
        $before = app(GeoArticleAuditService::class)->audit($article);

        $optimized = "## AI 客服的核心能力\n\nAI 客服需要稳定的意图识别。\n\n## 如何选择 AI 客服\n\n- 看准确率\n- 看知识库对接\n\n| 维度 | 说明 |\n| --- | --- |\n| 准确率 | 越高越好 |\n\n综上，选择 AI 客服要综合评估。";
        $audit = $this->stubbedOptimizer($optimized)->optimize($article->fresh());

        $article->refresh();
        $this->assertSame($optimized, $article->content);
        $this->assertNotSame('', (string) $article->excerpt);
        $this->assertGreaterThanOrEqual($before->geo_score, $audit->geo_score);
        // 优化前后各一条评分记录（至少 2 条）。
        $this->assertGreaterThanOrEqual(2, ArticleGeoAudit::query()->where('article_id', $article->id)->count());
    }

    public function test_optimize_does_not_change_review_status(): void
    {
        AiModel::query()->create(['name' => 'Writer', 'model_id' => 'gpt-x', 'api_url' => 'https://api.example.com/v1', 'status' => 'active']);
        $article = $this->makeArticle(['title' => 'T', 'original_keyword' => 'kw', 'content' => '原文', 'review_status' => 'pending']);

        $this->stubbedOptimizer("## kw\n\n内容\n\n- a\n\n总结。")->optimize($article);

        $this->assertSame('pending', $article->fresh()->review_status);
    }

    public function test_optimize_throws_without_model(): void
    {
        $article = $this->makeArticle(['content' => '原文']);

        $this->expectException(RuntimeException::class);
        $this->stubbedOptimizer('## x')->optimize($article);
    }
}
