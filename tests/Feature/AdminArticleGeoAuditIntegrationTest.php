<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\Article;
use App\Models\ArticleGeoAudit;
use App\Models\Author;
use App\Models\Category;
use App\Models\Task;
use App\Models\Title;
use App\Models\TitleLibrary;
use App\Services\GeoFlow\WorkerExecutionService;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesGeoArticles;
use Tests\TestCase;

class AdminArticleGeoAuditIntegrationTest extends TestCase
{
    use MakesGeoArticles;
    use RefreshDatabase;

    public function test_article_list_shows_geo_score_status_and_filter(): void
    {
        $article = $this->makeArticle([
            'title' => 'GEO 列表融入测试',
            'content' => "## 结构\n\n正文",
        ]);
        ArticleGeoAudit::query()->create([
            'article_id' => $article->id,
            'geo_score' => 58,
            'title_keyword_match' => 50,
            'structure_score' => 60,
            'kb_coverage' => 40,
            'dup_ratio' => 10,
            'word_count' => 300,
            'gate_decision' => ArticleGeoAudit::GATE_TO_REVIEW,
            'suggestion' => '补摘要、加 FAQ、改开头、补结论、加内链。',
            'risk_notes' => ['结构不足'],
            'details' => ['rule_version' => 'test-v1'],
            'audited_at' => now(),
        ]);
        $this->makeArticle(['title' => '未评分文章']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.articles.index', ['geo_audit_status' => 'risk']))
            ->assertOk()
            ->assertSee('GEO 评分')
            ->assertSee('有风险')
            ->assertSee('58')
            ->assertSee('GEO 列表融入测试')
            ->assertDontSee('未评分文章');
    }

    public function test_article_edit_page_shows_geo_panel_actions_and_specific_suggestion(): void
    {
        $article = $this->makeArticle([
            'title' => 'GEO 编辑页融入测试',
            'review_status' => 'approved',
        ]);
        ArticleGeoAudit::query()->create([
            'article_id' => $article->id,
            'geo_score' => 66,
            'title_keyword_match' => 70,
            'structure_score' => 65,
            'kb_coverage' => 55,
            'dup_ratio' => 12,
            'word_count' => 600,
            'gate_decision' => ArticleGeoAudit::GATE_TO_REVIEW,
            'suggestion' => '补摘要、加 FAQ、改开头、补结论、加内链。',
            'risk_notes' => ['缺少 FAQ'],
            'details' => ['rule_version' => 'test-v1'],
            'audited_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.articles.edit', ['articleId' => (int) $article->id]))
            ->assertOk()
            ->assertSee('GEO 评分')
            ->assertSee('不参与审核流')
            ->assertSee('66')
            ->assertSee('标题关键词匹配')
            ->assertSee('补摘要、加 FAQ、改开头、补结论、加内链。')
            ->assertSee('重新评分')
            ->assertSee('AI 一键优化')
            ->assertSee('GEO 评分建议先优化');
    }

    public function test_article_page_reaudit_creates_score_without_changing_review_status(): void
    {
        $article = $this->makeArticle([
            'title' => 'AI 客服怎么选',
            'original_keyword' => 'AI 客服',
            'content' => "## AI 客服\n\n- 准确率\n- 多渠道\n\n总结。",
            'review_status' => 'approved',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.geo-audits.reaudit', ['articleId' => (int) $article->id]), [
                'return_to' => 'article',
            ])
            ->assertRedirect(route('admin.articles.edit', ['articleId' => (int) $article->id]));

        $this->assertSame('approved', (string) $article->fresh()->review_status);
        $this->assertSame(1, ArticleGeoAudit::query()->where('article_id', (int) $article->id)->count());
    }

    public function test_generated_article_auto_score_does_not_change_workflow_status(): void
    {
        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => "## AI Customer Service\n\n- Accuracy\n- Channels\n\nSummary."]],
                ],
            ]),
        ]);

        $model = AiModel::query()->create([
            'name' => 'Test Writer',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('test-key'),
            'model_id' => 'test-chat-model',
            'model_type' => 'chat',
            'api_url' => 'https://ai.test',
            'status' => 'active',
        ]);
        $library = TitleLibrary::query()->create(['name' => 'GEO Title Library']);
        Title::query()->create([
            'library_id' => $library->id,
            'title' => 'AI Customer Service Guide',
            'keyword' => 'AI customer service',
        ]);
        $category = Category::query()->create(['name' => 'GEO Category', 'slug' => 'geo-category']);
        $author = Author::query()->create(['name' => 'GEO Author']);
        $task = Task::query()->create([
            'name' => 'GEO generation audit test',
            'title_library_id' => $library->id,
            'ai_model_id' => $model->id,
            'author_id' => $author->id,
            'category_mode' => 'fixed',
            'fixed_category_id' => $category->id,
            'need_review' => 0,
            'status' => 'active',
            'schedule_enabled' => 1,
            'article_limit' => 5,
        ]);

        $result = app(WorkerExecutionService::class)->executeTask((int) $task->id);
        $article = Article::query()->findOrFail((int) $result['article_id']);

        $this->assertSame('approved', (string) $article->review_status);
        $this->assertSame('draft', (string) $article->status);
        $this->assertSame(1, ArticleGeoAudit::query()->where('article_id', (int) $article->id)->count());
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'article_geo_admin',
            'password' => 'secret-123',
            'email' => 'article-geo-admin@example.com',
            'display_name' => 'Article GEO Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}
