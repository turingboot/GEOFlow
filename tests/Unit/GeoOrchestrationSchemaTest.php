<?php

namespace Tests\Unit;

use App\Models\ArticleGeoAudit;
use App\Models\TopicPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\MakesGeoArticles;
use Tests\TestCase;

class GeoOrchestrationSchemaTest extends TestCase
{
    use MakesGeoArticles;
    use RefreshDatabase;

    public function test_geo_orchestration_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('topic_plans'));
        $this->assertTrue(Schema::hasTable('topic_plan_items'));
        $this->assertTrue(Schema::hasTable('article_geo_audits'));

        foreach (['name', 'period_start', 'period_end', 'status', 'source_summary', 'ai_model_id', 'target_title_library_id'] as $column) {
            $this->assertTrue(Schema::hasColumn('topic_plans', $column), "topic_plans.$column");
        }
        foreach (['topic_plan_id', 'title', 'keyword', 'secondary_keywords', 'planned_publish_at', 'status', 'created_title_id', 'sort_order'] as $column) {
            $this->assertTrue(Schema::hasColumn('topic_plan_items', $column), "topic_plan_items.$column");
        }
        foreach (['article_id', 'geo_score', 'title_keyword_match', 'structure_score', 'kb_coverage', 'dup_ratio', 'word_count', 'gate_decision', 'suggestion', 'risk_notes', 'details', 'audited_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('article_geo_audits', $column), "article_geo_audits.$column");
        }
    }

    public function test_topic_plan_relations_and_casts(): void
    {
        $plan = TopicPlan::query()->create([
            'name' => '2026-07 选题规划',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => 'draft',
            'source_summary' => ['trend_snapshot_ids' => [1, 2]],
        ]);
        $item = $plan->items()->create([
            'title' => 'AI 客服怎么选',
            'keyword' => 'ai客服',
            'secondary_keywords' => ['智能客服', '客服机器人'],
            'planned_publish_at' => '2026-07-02',
            'status' => 'suggested',
            'sort_order' => 1,
        ]);

        $this->assertIsArray($plan->source_summary);
        $this->assertSame([1, 2], $plan->source_summary['trend_snapshot_ids']);
        $this->assertIsArray($item->secondary_keywords);
        $this->assertSame('2026-07-02', $item->planned_publish_at->format('Y-m-d'));
        $this->assertTrue($plan->items()->whereKey($item->id)->exists());
        $this->assertSame($plan->id, $item->plan->id);
    }

    public function test_article_geo_audit_casts(): void
    {
        $article = $this->makeArticle();

        $audit = ArticleGeoAudit::query()->create([
            'article_id' => $article->id,
            'geo_score' => 82,
            'title_keyword_match' => 90,
            'structure_score' => 80,
            'kb_coverage' => 75,
            'dup_ratio' => 12,
            'word_count' => 1200,
            'gate_decision' => ArticleGeoAudit::GATE_AUTO_APPROVED,
            'risk_notes' => ['结构略简单'],
            'details' => ['title' => ['hit' => true]],
            'audited_at' => now(),
        ]);

        $this->assertIsArray($audit->risk_notes);
        $this->assertIsArray($audit->details);
        $this->assertSame(82, $audit->geo_score);
        $this->assertNotNull($audit->audited_at);
    }
}
