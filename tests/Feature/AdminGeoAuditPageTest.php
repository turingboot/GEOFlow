<?php

namespace Tests\Feature;

use App\Jobs\OptimizeArticleGeoJob;
use App\Models\Admin;
use App\Models\AiModel;
use App\Models\ArticleGeoAudit;
use App\Services\GeoFlow\GeoArticleAuditService;
use App\Services\GeoFlow\GeoArticleOptimizerService;
use App\Services\GeoFlow\KnowledgeRetrievalService;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\MakesGeoArticles;
use Tests\TestCase;

class AdminGeoAuditPageTest extends TestCase
{
    use MakesGeoArticles;
    use RefreshDatabase;

    public function test_index_renders_with_empty_state(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.geo-audits.index'))
            ->assertOk()
            ->assertSee(__('admin.geo_audit.page_title'))
            ->assertSee(__('admin.geo_audit.empty'));
    }

    public function test_index_lists_audited_articles(): void
    {
        $article = $this->makeArticle(['title' => 'GEO 面板测试文章']);
        ArticleGeoAudit::query()->create([
            'article_id' => $article->id,
            'geo_score' => 82,
            'title_keyword_match' => 90,
            'structure_score' => 80,
            'kb_coverage' => 70,
            'dup_ratio' => 10,
            'word_count' => 1000,
            'gate_decision' => ArticleGeoAudit::GATE_AUTO_APPROVED,
            'audited_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.geo-audits.index'))
            ->assertOk()
            ->assertSee('GEO 面板测试文章')
            ->assertSee('82');
    }

    public function test_show_renders_dimensions_and_suggestion(): void
    {
        $article = $this->makeArticle(['title' => 'GEO 详情测试']);
        ArticleGeoAudit::query()->create([
            'article_id' => $article->id,
            'geo_score' => 55,
            'title_keyword_match' => 40,
            'structure_score' => 30,
            'kb_coverage' => 60,
            'dup_ratio' => 50,
            'word_count' => 300,
            'gate_decision' => ArticleGeoAudit::GATE_TO_REVIEW,
            'suggestion' => 'GEO 评分低于阈值，建议转人工审核后再发布。',
            'risk_notes' => ['结构不足'],
            'audited_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.geo-audits.show', $article->id))
            ->assertOk()
            ->assertSee(__('admin.geo_audit.dimensions_title'))
            ->assertSee('结构不足');
    }

    public function test_reaudit_creates_new_audit_row(): void
    {
        $article = $this->makeArticle([
            'title' => 'AI 客服怎么选',
            'original_keyword' => 'AI 客服',
            'content' => "## AI 客服\n\n- 准确率\n- 多渠道\n\n综上所述。",
        ]);
        ArticleGeoAudit::query()->create([
            'article_id' => $article->id,
            'geo_score' => 50,
            'gate_decision' => ArticleGeoAudit::GATE_TO_REVIEW,
            'audited_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.geo-audits.reaudit', $article->id))
            ->assertRedirect(route('admin.geo-audits.show', $article->id));

        $this->assertSame(2, ArticleGeoAudit::query()->where('article_id', $article->id)->count());
    }

    public function test_optimize_dispatches_async_job_and_flags_optimizing(): void
    {
        Queue::fake();
        $article = $this->makeArticle(['title' => 'T', 'original_keyword' => 'kw', 'content' => '原文']);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.geo-audits.optimize', $article->id))
            ->assertRedirect(route('admin.geo-audits.show', $article->id))
            ->assertSessionHas('message', __('admin.geo_audit.message.optimize_started'));

        Queue::assertPushed(OptimizeArticleGeoJob::class, fn (OptimizeArticleGeoJob $job): bool => $job->articleId === (int) $article->id);
        $this->assertTrue((bool) Cache::get(OptimizeArticleGeoJob::lockKey($article->id)));
    }

    public function test_optimize_route_rewrites_article_and_redirects(): void
    {
        AiModel::query()->create(['name' => 'Writer', 'model_id' => 'gpt-x', 'api_url' => 'https://api.example.com/v1', 'status' => 'active']);
        $article = $this->makeArticle(['title' => 'AI 客服怎么选', 'original_keyword' => 'AI 客服', 'content' => '流水文字。']);
        ArticleGeoAudit::query()->create([
            'article_id' => $article->id,
            'geo_score' => 40,
            'gate_decision' => ArticleGeoAudit::GATE_TO_REVIEW,
            'audited_at' => now(),
        ]);

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

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.geo-audits.optimize', $article->id))
            ->assertRedirect(route('admin.geo-audits.show', $article->id));

        $this->assertSame($optimized, $article->fresh()->content);
    }

    public function test_index_hides_audits_of_trashed_articles(): void
    {
        $live = $this->makeArticle(['title' => '在用文章']);
        $trashed = $this->makeArticle(['title' => '回收站文章']);
        foreach ([$live, $trashed] as $article) {
            ArticleGeoAudit::query()->create([
                'article_id' => $article->id,
                'geo_score' => 60,
                'gate_decision' => ArticleGeoAudit::GATE_TO_REVIEW,
                'audited_at' => now(),
            ]);
        }
        $trashed->delete(); // 软删 → 进回收站
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.geo-audits.index'))
            ->assertOk()
            ->assertSee('在用文章')
            ->assertDontSee('回收站文章');

        // 回收站文章的详情页不可见
        $this->actingAs($admin, 'admin')
            ->get(route('admin.geo-audits.show', $trashed->id))
            ->assertRedirect(route('admin.geo-audits.index'));
    }

    public function test_force_deleting_article_cascades_geo_audits(): void
    {
        $article = $this->makeArticle();
        ArticleGeoAudit::query()->create([
            'article_id' => $article->id,
            'geo_score' => 60,
            'gate_decision' => ArticleGeoAudit::GATE_TO_REVIEW,
            'audited_at' => now(),
        ]);
        $this->assertSame(1, ArticleGeoAudit::query()->where('article_id', $article->id)->count());

        $article->forceDelete(); // 永久删除 → DB 外键级联

        $this->assertSame(0, ArticleGeoAudit::query()->where('article_id', $article->id)->count());
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'geo_admin',
            'password' => 'secret-123',
            'email' => 'geo-admin@example.com',
            'display_name' => 'GEO Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
