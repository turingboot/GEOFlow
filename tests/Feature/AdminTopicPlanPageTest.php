<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\TopicPlan;
use App\Services\GeoFlow\TopicPlan\MonthlyTopicPlannerService;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTopicPlanPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_empty_state(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.topic-plans.index'))
            ->assertOk()
            ->assertSee(__('admin.topic_plans.page_title'))
            ->assertSee(__('admin.topic_plans.empty'));
    }

    public function test_create_form_renders(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.topic-plans.create'))
            ->assertOk()
            ->assertSee('name="ai_model_id"', false)
            ->assertSee('name="target_count"', false);
    }

    public function test_store_generates_plan_with_stubbed_ai(): void
    {
        $model = AiModel::query()->create(['name' => 'M', 'model_id' => 'gpt-x', 'api_url' => 'https://api.example.com/v1', 'status' => 'active']);

        $this->app->bind(MonthlyTopicPlannerService::class, fn ($app) => new class($app->make(ApiKeyCrypto::class)) extends MonthlyTopicPlannerService
        {
            protected function runPlannerModel(AiModel $model, string $prompt): string
            {
                return (string) json_encode([
                    ['title' => '选题一', 'keyword' => '词一'],
                    ['title' => '选题二', 'keyword' => '词二'],
                ]);
            }
        });

        $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.topic-plans.store'), [
            'name' => '2026-07 选题',
            'ai_model_id' => $model->id,
            'target_count' => 30,
        ]);

        $plan = TopicPlan::query()->latest('id')->first();
        $this->assertNotNull($plan);
        $response->assertRedirect(route('admin.topic-plans.show', $plan->id));
        $this->assertSame(2, $plan->items()->count());
    }

    public function test_confirm_updates_item_statuses(): void
    {
        $plan = TopicPlan::query()->create(['name' => 'P', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'status' => 'draft']);
        $keep = $plan->items()->create(['title' => '保留', 'keyword' => 'a', 'status' => 'suggested', 'sort_order' => 1]);
        $drop = $plan->items()->create(['title' => '剔除', 'keyword' => 'b', 'status' => 'suggested', 'sort_order' => 2]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.topic-plans.confirm', $plan->id), ['item_ids' => [$keep->id]])
            ->assertRedirect(route('admin.topic-plans.show', $plan->id));

        $this->assertSame('confirmed', $keep->fresh()->status);
        $this->assertSame('rejected', $drop->fresh()->status);
        $this->assertSame('confirmed', $plan->fresh()->status);
    }

    public function test_dispatch_creates_task(): void
    {
        $prompt = Prompt::query()->create(['name' => '写作', 'type' => 'content', 'content' => 'x']);
        $model = AiModel::query()->create(['name' => 'M', 'model_id' => 'gpt-x', 'api_url' => 'https://api.example.com/v1', 'status' => 'active']);
        $plan = TopicPlan::query()->create(['name' => 'P', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'status' => 'confirmed']);
        $plan->items()->create(['title' => '选题一', 'keyword' => '词一', 'status' => 'confirmed', 'sort_order' => 1]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.topic-plans.dispatch', $plan->id), [
                'prompt_id' => $prompt->id,
                'ai_model_id' => $model->id,
                'publish_interval' => 86400,
                'status' => 'paused',
            ])
            ->assertRedirect(route('admin.topic-plans.show', $plan->id));

        $this->assertSame('dispatched', $plan->fresh()->status);
        $this->assertSame(1, Task::query()->count());
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'tp_admin',
            'password' => 'secret-123',
            'email' => 'tp-admin@example.com',
            'display_name' => 'TP Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
