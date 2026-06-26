<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskRun;
use App\Services\GeoFlow\JobQueueService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GeoFlowScheduleTasksCommand extends Command
{
    protected $signature = 'geoflow:schedule-tasks';

    protected $description = 'Scan active GeoFlow tasks and enqueue due jobs';

    public function __construct(
        private readonly JobQueueService $jobQueueService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $recoveredCount = $this->jobQueueService->recoverStaleJobs();
        $queuedCount = 0;
        $skippedCount = 0;

        $tenantIds = Task::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('tenant_id')
            ->distinct()
            ->pluck('tenant_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values();

        foreach ($tenantIds as $tenantId) {
            [$tenantQueued, $tenantSkipped] = TenantContext::run($tenantId, fn (): array => $this->processTenantTasks());
            $queuedCount += $tenantQueued;
            $skippedCount += $tenantSkipped;
        }

        $this->info(sprintf(
            'GeoFlow scheduler done: queued=%d, skipped=%d, recovered=%d',
            $queuedCount,
            $skippedCount,
            $recoveredCount
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function processTenantTasks(): array
    {
        $now = now();
        $queuedCount = 0;
        $skippedCount = 0;

        $tasks = Task::query()
            ->select(['id', 'tenant_id', 'name', 'publish_interval', 'draft_limit', 'article_limit', 'created_count', 'next_run_at', 'next_publish_at', 'schedule_enabled'])
            ->where('status', 'active')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        $taskIds = $tasks->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $busyTaskLookup = empty($taskIds)
            ? []
            : array_fill_keys(
                TaskRun::query()
                    ->whereIn('task_id', $taskIds)
                    ->whereIn('status', ['pending', 'running'])
                    ->groupBy('task_id')
                    ->pluck('task_id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
                true
            );

        $articleStats = empty($taskIds)
            ? collect()
            : DB::table('articles')
                ->selectRaw("
                    task_id,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_articles,
                    SUM(CASE WHEN status = 'draft' AND review_status IN ('approved','auto_approved') THEN 1 ELSE 0 END) AS publishable_drafts
                ")
                ->whereIn('task_id', $taskIds)
                ->where('tenant_id', TenantContext::id())
                ->whereNull('deleted_at')
                ->groupBy('task_id')
                ->get()
                ->mapWithKeys(static fn (object $row): array => [
                    (int) $row->task_id => [
                        'draft_articles' => (int) ($row->draft_articles ?? 0),
                        'publishable_drafts' => (int) ($row->publishable_drafts ?? 0),
                    ],
                ]);

        foreach ($tasks as $task) {
            $taskId = (int) $task->id;
            if ((int) ($task->schedule_enabled ?? 1) !== 1) {
                $skippedCount++;

                continue;
            }

            $articleLimit = max(1, (int) ($task->article_limit ?? $task->draft_limit ?? 10));
            $draftLimit = max(1, (int) ($task->draft_limit ?? 10));
            $createdCount = (int) ($task->created_count ?? 0);
            $stats = $articleStats->get($taskId, ['draft_articles' => 0, 'publishable_drafts' => 0]);
            $draftCount = (int) ($stats['draft_articles'] ?? 0);
            $publishableDrafts = (int) ($stats['publishable_drafts'] ?? 0);
            $nextPublishAt = $task->next_publish_at instanceof Carbon ? $task->next_publish_at : null;
            $canGenerate = $createdCount < $articleLimit && $draftCount < $draftLimit;
            $canPublishNow = $publishableDrafts > 0 && ($nextPublishAt === null || ! $nextPublishAt->greaterThan($now));

            if (! $canGenerate && ! $canPublishNow) {
                if ($publishableDrafts > 0 && $nextPublishAt instanceof Carbon) {
                    Task::query()->whereKey($taskId)->update([
                        'next_run_at' => $nextPublishAt,
                        'updated_at' => now(),
                    ]);
                }
                $skippedCount++;

                continue;
            }

            if (! $task->next_run_at instanceof Carbon) {
                $this->jobQueueService->initializeTaskSchedule($taskId);
                $skippedCount++;

                continue;
            }

            if ($task->next_run_at->greaterThan($now) && ! $canPublishNow) {
                $skippedCount++;

                continue;
            }

            if (isset($busyTaskLookup[$taskId])) {
                $skippedCount++;

                continue;
            }

            $taskRunId = $this->jobQueueService->enqueueTaskJob($taskId);
            if ($taskRunId === null) {
                $skippedCount++;

                continue;
            }

            $nextRunAt = $now->copy()->addSeconds(60);
            Task::query()->whereKey($taskId)->update([
                'next_run_at' => $nextRunAt,
                'updated_at' => now(),
            ]);
            $queuedCount++;
        }

        return [$queuedCount, $skippedCount];
    }
}
