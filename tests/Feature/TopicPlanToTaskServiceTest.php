<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\Title;
use App\Models\TitleLibrary;
use App\Models\TopicPlan;
use App\Services\GeoFlow\TopicPlan\TopicPlanToTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TopicPlanToTaskServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_creates_title_library_titles_and_task(): void
    {
        $prompt = Prompt::query()->create(['name' => '写作提示', 'type' => 'content', 'content' => '写一篇文章']);
        $model = AiModel::query()->create([
            'name' => 'Chat Model',
            'model_id' => 'gpt-x',
            'api_url' => 'https://api.example.com/v1',
            'status' => 'active',
        ]);

        $plan = TopicPlan::query()->create([
            'name' => '2026-07 选题',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => 'confirmed',
        ]);
        $plan->items()->create(['title' => '选题一', 'keyword' => '词一', 'status' => 'confirmed', 'sort_order' => 1]);
        $plan->items()->create(['title' => '选题二', 'keyword' => '词二', 'status' => 'confirmed', 'sort_order' => 2]);
        $plan->items()->create(['title' => '未确认', 'keyword' => '词三', 'status' => 'suggested', 'sort_order' => 3]);

        $result = app(TopicPlanToTaskService::class)->dispatch($plan, [
            'prompt_id' => $prompt->id,
            'ai_model_id' => $model->id,
            'publish_interval' => 86400,
            'need_review' => 1,
            'status' => 'paused',
        ]);

        // 计划标题库 + 2 条已确认选题写成 titles（未确认的不写）
        $libraryId = $result['title_library_id'];
        $this->assertSame(2, Title::query()->where('library_id', $libraryId)->count());
        $this->assertSame(2, (int) TitleLibrary::query()->whereKey($libraryId)->value('title_count'));

        // 任务指向该库，article_limit = 确认条目数
        $task = Task::query()->where('title_library_id', $libraryId)->first();
        $this->assertNotNull($task);
        $this->assertSame(2, (int) $task->article_limit);
        $this->assertSame((int) $prompt->id, (int) $task->prompt_id);
        $this->assertSame((int) $model->id, (int) $task->ai_model_id);

        // 计划与条目状态流转
        $plan->refresh();
        $this->assertSame('dispatched', $plan->status);
        $this->assertSame($libraryId, (int) $plan->target_title_library_id);
        $this->assertSame(2, $plan->items()->where('status', 'dispatched')->count());
        $this->assertSame(1, $plan->items()->where('status', 'suggested')->count());
    }

    public function test_dispatch_without_confirmed_items_throws(): void
    {
        $plan = TopicPlan::query()->create([
            'name' => '空计划',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => 'draft',
        ]);
        $plan->items()->create(['title' => '仅建议', 'keyword' => 'kw', 'status' => 'suggested', 'sort_order' => 1]);

        $this->expectException(RuntimeException::class);
        app(TopicPlanToTaskService::class)->dispatch($plan, ['prompt_id' => 1, 'ai_model_id' => 1]);
    }
}
