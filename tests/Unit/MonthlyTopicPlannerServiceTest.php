<?php

namespace Tests\Unit;

use App\Models\AiModel;
use App\Models\TopicPlan;
use App\Services\GeoFlow\TopicPlan\MonthlyTopicPlannerService;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesGeoArticles;
use Tests\TestCase;

class MonthlyTopicPlannerServiceTest extends TestCase
{
    use MakesGeoArticles;
    use RefreshDatabase;

    private function service(): MonthlyTopicPlannerService
    {
        return app(MonthlyTopicPlannerService::class);
    }

    public function test_parse_plan_items_normalizes_and_marks_history_duplicates(): void
    {
        $json = (string) json_encode([
            ['title' => 'AI 客服怎么选', 'keyword' => 'ai客服', 'secondary_keywords' => ['智能客服'], 'heat_score' => 88, 'kb_support' => 'strong', 'dup_risk' => 'low'],
            ['title' => '已有历史选题', 'keyword' => '历史关键词', 'heat_score' => 200, 'kb_support' => 'bogus'],
            ['title' => 'AI 客服怎么选', 'keyword' => 'ai客服'], // 标题重复 -> 丢弃
            ['title' => '', 'keyword' => 'x'], // 非法 -> 丢弃
        ]);

        $items = $this->service()->parsePlanItems($json, ['已有历史选题'], 30);

        $this->assertCount(2, $items);
        $this->assertSame('AI 客服怎么选', $items[0]['title']);
        $this->assertSame(88, $items[0]['heat_score']);
        $this->assertSame('strong', $items[0]['kb_support']);
        $this->assertSame(['智能客服'], $items[0]['secondary_keywords']);
        // 命中历史 -> dup_risk=high；非法 kb_support 归一化为 weak；heat 截断到 100
        $this->assertSame('high', $items[1]['dup_risk']);
        $this->assertSame('weak', $items[1]['kb_support']);
        $this->assertSame(100, $items[1]['heat_score']);
    }

    public function test_parse_handles_code_fenced_json(): void
    {
        $json = "```json\n[{\"title\":\"标题A\",\"keyword\":\"kw\"}]\n```";

        $items = $this->service()->parsePlanItems($json, [], 30);

        $this->assertCount(1, $items);
        $this->assertSame('标题A', $items[0]['title']);
    }

    public function test_parse_respects_target_count(): void
    {
        $payload = [];
        for ($i = 0; $i < 10; $i++) {
            $payload[] = ['title' => "标题{$i}", 'keyword' => "词{$i}"];
        }

        $items = $this->service()->parsePlanItems((string) json_encode($payload), [], 3);

        $this->assertCount(3, $items);
    }

    public function test_generate_plan_persists_plan_and_items(): void
    {
        $model = AiModel::query()->create([
            'name' => 'Planner Model',
            'model_id' => 'gpt-x',
            'api_url' => 'https://api.example.com/v1',
            'status' => 'active',
        ]);
        $this->makeArticle(['title' => '历史标题', 'original_keyword' => '历史词']);

        $canned = (string) json_encode([
            ['title' => '选题一', 'keyword' => '词一', 'heat_score' => 70, 'kb_support' => 'strong', 'dup_risk' => 'low'],
            ['title' => '选题二', 'keyword' => '词二'],
        ]);

        $service = new class(app(ApiKeyCrypto::class)) extends MonthlyTopicPlannerService
        {
            public string $canned = '';

            protected function runPlannerModel(AiModel $model, string $prompt): string
            {
                return $this->canned;
            }
        };
        $service->canned = $canned;

        $plan = $service->generatePlan([
            'name' => '2026-07 选题',
            'ai_model_id' => $model->id,
            'target_count' => 30,
        ]);

        $this->assertInstanceOf(TopicPlan::class, $plan);
        $this->assertSame('draft', $plan->status);
        $this->assertCount(2, $plan->items);
        $this->assertSame('选题一', $plan->items[0]->title);
        $this->assertSame('suggested', $plan->items[0]->status);
    }
}
