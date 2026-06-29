<?php

namespace Tests\Unit;

use App\Models\ArticleGeoAudit;
use App\Services\GeoFlow\GeoArticleAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesGeoArticles;
use Tests\TestCase;

class GeoArticleAuditServiceTest extends TestCase
{
    use MakesGeoArticles;
    use RefreshDatabase;

    private function service(): GeoArticleAuditService
    {
        return app(GeoArticleAuditService::class);
    }

    public function test_well_structured_article_scores_high_and_auto_approves(): void
    {
        config(['geoflow.geo_audit.pass_threshold' => 70]);

        $article = $this->makeArticle([
            'title' => 'AI 客服系统怎么选',
            'original_keyword' => 'AI 客服',
            'review_status' => 'approved',
            'content' => "## AI 客服的优势\n\nAI 客服可以 7x24 小时响应客户咨询。\n\n## 如何选择 AI 客服\n\n- 看意图识别准确率\n- 看知识库对接能力\n- 看多渠道支持\n\n| 维度 | 说明 |\n| --- | --- |\n| 准确率 | 越高越好 |\n\n综上，选择 AI 客服需要综合评估。",
        ]);

        $audit = $this->service()->auditAndApplyGate((int) $article->id);

        $this->assertNotNull($audit);
        $this->assertGreaterThanOrEqual(70, $audit->geo_score);
        $this->assertSame(ArticleGeoAudit::GATE_AUTO_APPROVED, $audit->gate_decision);
        $this->assertGreaterThan(0, $audit->word_count);

        $article->refresh();
        $this->assertSame('auto_approved', $article->review_status);
    }

    public function test_poor_article_scores_low_and_routes_to_review(): void
    {
        config(['geoflow.geo_audit.pass_threshold' => 70]);

        $article = $this->makeArticle([
            'title' => '随便写点东西',
            'original_keyword' => '完全不相关的目标关键词',
            'review_status' => 'approved',
            'content' => '这是一段没有任何结构的流水文字，没有小标题也没有列表，更没有覆盖目标关键词。',
        ]);

        $audit = $this->service()->auditAndApplyGate((int) $article->id);

        $this->assertNotNull($audit);
        $this->assertLessThan(70, $audit->geo_score);
        $this->assertSame(ArticleGeoAudit::GATE_TO_REVIEW, $audit->gate_decision);
        $this->assertNotEmpty($audit->risk_notes);

        $article->refresh();
        $this->assertSame('pending', $article->review_status);
    }

    public function test_audit_does_not_mutate_review_status(): void
    {
        $article = $this->makeArticle([
            'title' => '无所谓',
            'original_keyword' => '无关词',
            'review_status' => 'approved',
            'content' => '没有结构的内容。',
        ]);

        $this->service()->audit($article);

        $article->refresh();
        $this->assertSame('approved', $article->review_status, 'audit() 应只落分，不改 review_status');
        $this->assertDatabaseHas('article_geo_audits', ['article_id' => $article->id]);
    }

    public function test_audit_records_rule_version_for_score_explainability(): void
    {
        config(['geoflow.geo_audit.rule_version' => 'rules-2026-06']);

        $article = $this->makeArticle(['title' => '测试', 'original_keyword' => '测试', 'content' => '## 测试\n\n内容']);

        $audit = $this->service()->audit($article);

        $this->assertSame('rules-2026-06', $audit->details['rule_version'] ?? null);
    }

    public function test_disabled_config_is_respected_by_caller_contract(): void
    {
        // 评分本身与 enabled 无关；enabled 仅由 Worker 钩子读取。这里验证关闭后仍可手动评分。
        config(['geoflow.geo_audit.enabled' => false]);
        $article = $this->makeArticle(['title' => '测试', 'original_keyword' => '测试', 'content' => '## 测试\n\n内容']);

        $audit = $this->service()->audit($article);

        $this->assertInstanceOf(ArticleGeoAudit::class, $audit);
    }
}
