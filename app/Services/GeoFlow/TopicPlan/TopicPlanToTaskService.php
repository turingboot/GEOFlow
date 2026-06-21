<?php

namespace App\Services\GeoFlow\TopicPlan;

use App\Models\Title;
use App\Models\TitleLibrary;
use App\Models\TopicPlan;
use App\Services\GeoFlow\TaskLifecycleService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 选题规划层（外挂式扩展）：把人工确认的选题投喂给现有任务机制（软排期）。
 *
 * 流程：确认条目 → 写入「计划标题库」的 titles 行（带 keyword）→ 现有 pickTitle() 自然消费；
 * 同时用 {@see TaskLifecycleService::createTask()} 建 1 个任务，用 publish_interval 软排期铺开。
 * 不修改主链路（schedule/pickTitle/worker）。
 */
class TopicPlanToTaskService
{
    public function __construct(
        private readonly TaskLifecycleService $taskLifecycleService
    ) {}

    /**
     * @param  array<string,mixed>  $params  需含 prompt_id、ai_model_id；其余排期项可选。
     * @return array{title_library_id:int, task:array<string,mixed>}
     */
    public function dispatch(TopicPlan $plan, array $params): array
    {
        $confirmedItems = $plan->items()
            ->where('status', 'confirmed')
            ->orderBy('sort_order')
            ->get();

        if ($confirmedItems->isEmpty()) {
            throw new RuntimeException('没有已确认的选题，无法投喂任务');
        }

        // 1) 创建「计划标题库」，把确认选题写为 titles 行（带 keyword）。
        $libraryId = DB::transaction(function () use ($plan, $confirmedItems): int {
            $library = TitleLibrary::query()->create([
                'name' => mb_substr($plan->name.' · 选题', 0, 120),
                'description' => '由选题规划自动生成',
                'title_count' => 0,
                'is_ai_generated' => 1,
            ]);

            foreach ($confirmedItems as $item) {
                $title = Title::query()->create([
                    'library_id' => $library->id,
                    'title' => $item->title,
                    'keyword' => $item->keyword,
                    'is_ai_generated' => true,
                    'used_count' => 0,
                    'usage_count' => 0,
                ]);

                $item->update(['status' => 'dispatched', 'created_title_id' => (int) $title->id]);
            }

            TitleLibrary::query()->whereKey($library->id)->update(['title_count' => $confirmedItems->count()]);

            $plan->update([
                'status' => 'dispatched',
                'target_title_library_id' => (int) $library->id,
            ]);

            return (int) $library->id;
        });

        // 2) 用现有 createTask 建任务（软排期：article_limit = 选题数，publish_interval 铺开节奏）。
        $categoryMode = (string) ($params['category_mode'] ?? 'smart');
        $status = (string) ($params['status'] ?? 'paused');

        $task = $this->taskLifecycleService->createTask([
            'name' => mb_substr($plan->name.' · 自动任务', 0, 120),
            'title_library_id' => $libraryId,
            'prompt_id' => (int) ($params['prompt_id'] ?? 0),
            'ai_model_id' => (int) ($params['ai_model_id'] ?? 0),
            'article_limit' => $confirmedItems->count(),
            'draft_limit' => max(1, (int) ($params['draft_limit'] ?? $confirmedItems->count())),
            'publish_interval' => max(60, (int) ($params['publish_interval'] ?? 86400)),
            'need_review' => (int) ($params['need_review'] ?? 1),
            'category_mode' => in_array($categoryMode, ['smart', 'fixed'], true) ? $categoryMode : 'smart',
            'fixed_category_id' => $params['fixed_category_id'] ?? null,
            'publish_scope' => (string) ($params['publish_scope'] ?? 'local_only'),
            'knowledge_base_ids' => array_map('intval', (array) ($params['knowledge_base_ids'] ?? [])),
            'status' => in_array($status, ['active', 'paused'], true) ? $status : 'paused',
        ]);

        return [
            'title_library_id' => $libraryId,
            'task' => $task,
        ];
    }
}
