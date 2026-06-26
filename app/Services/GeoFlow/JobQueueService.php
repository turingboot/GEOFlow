<?php

namespace App\Services\GeoFlow;

use App\Jobs\ProcessGeoFlowTaskJob;
use App\Models\Task;
use App\Models\TaskRun;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class JobQueueService
{
    public function initializeTaskSchedule(int $taskId, int $delaySeconds = 60): void
    {
        DB::transaction(function () use ($taskId, $delaySeconds): void {
            $task = Task::query()
                ->whereKey($taskId)
                ->lockForUpdate()
                ->first(['id', 'next_run_at', 'next_publish_at', 'schedule_enabled', 'max_retry_count', 'publish_interval']);

            if (! $task) {
                return;
            }

            $now = now();
            $updates = ['updated_at' => $now];

            if ($task->next_run_at === null) {
                $updates['next_run_at'] = $now->copy()->addSeconds(max(1, $delaySeconds));
            }

            if ($task->next_publish_at === null) {
                $updates['next_publish_at'] = $now->copy()->addSeconds(max(60, (int) ($task->publish_interval ?? 3600)));
            }

            if ($task->schedule_enabled === null) {
                $updates['schedule_enabled'] = 1;
            }

            if ($task->max_retry_count === null) {
                $updates['max_retry_count'] = 3;
            }

            Task::query()->whereKey($taskId)->update($updates);
        });
    }

    public function hasPendingOrRunningJob(int $taskId): bool
    {
        return TaskRun::query()
            ->where('task_id', $taskId)
            ->whereIn('status', ['pending', 'running'])
            ->exists();
    }

    public function enqueueTaskJob(int $taskId, string $jobType = 'generate_article', array $payload = [], ?string $availableAt = null): ?int
    {
        $run = DB::transaction(function () use ($taskId, $jobType, $payload, $availableAt): ?TaskRun {
            $taskRow = Task::query()
                ->whereKey($taskId)
                ->lockForUpdate()
                ->first(['id', 'tenant_id', 'max_retry_count']);

            if (! $taskRow) {
                return null;
            }

            $exists = TaskRun::query()
                ->where('task_id', $taskId)
                ->whereIn('status', ['pending', 'running'])
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                return null;
            }

            $maxAttempts = max(1, (int) ($taskRow->max_retry_count ?? 3));
            $availableAtValue = $availableAt ? Carbon::parse($availableAt) : now();

            return TaskRun::query()->create([
                'tenant_id' => (int) ($taskRow->tenant_id ?? 0) ?: null,
                'task_id' => $taskId,
                'status' => 'pending',
                'meta' => [
                    'job_type' => $jobType,
                    'payload' => $payload,
                    'attempt_count' => 0,
                    'max_attempts' => $maxAttempts,
                    'available_at' => $availableAtValue->toDateTimeString(),
                ],
                'started_at' => $availableAtValue,
                'finished_at' => null,
            ]);
        });

        if (! $run) {
            return null;
        }

        $this->dispatchLaravelQueueJob((int) $run->id, $run->started_at);
        $this->broadcastOverviewUpdate();

        return (int) $run->id;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function claimPendingJobById(int $jobId, string $workerId): ?array
    {
        $claimedJob = DB::transaction(function () use ($jobId, $workerId): ?array {
            $run = TaskRun::query()
                ->with('task:id,status,schedule_enabled,publish_interval')
                ->whereKey($jobId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $run) {
                return null;
            }

            $task = $run->task;
            if (! $task || ($task->status ?? 'paused') !== 'active' || (int) ($task->schedule_enabled ?? 1) !== 1) {
                TaskRun::query()
                    ->whereKey((int) $run->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'cancelled',
                        'finished_at' => now(),
                        'error_message' => 'Task is not active; pending run cancelled.',
                    ]);

                return null;
            }

            $meta = $this->normalizeMeta($run->meta);
            $availableAt = (string) ($meta['available_at'] ?? '');
            if ($availableAt !== '' && Carbon::parse($availableAt)->greaterThan(now())) {
                return null;
            }

            $affected = TaskRun::query()
                ->whereKey($jobId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'running',
                    'started_at' => now(),
                    'meta' => array_merge($meta, ['worker_id' => $workerId]),
                ]);

            if ($affected !== 1) {
                return null;
            }

            $row = $run->getAttributes();
            $row['status'] = 'running';
            $row['worker_id'] = $workerId;
            $row['publish_interval'] = (int) ($task->publish_interval ?? 0);
            $row['task_status'] = (string) ($task->status ?? 'paused');

            return $row;
        });

        if (is_array($claimedJob)) {
            $this->broadcastOverviewUpdate();
        }

        return $claimedJob;
    }

    public function claimNextJob(string $workerId): ?array
    {
        return null;
    }

    public function completeJob(int $jobId, int $taskId, ?int $articleId, int $durationMs, array $meta = []): void
    {
        TaskRun::query()->whereKey($jobId)->update([
            'status' => 'completed',
            'finished_at' => now(),
            'article_id' => $articleId,
            'duration_ms' => $durationMs,
            'meta' => $meta,
            'error_message' => '',
        ]);

        Task::query()->whereKey($taskId)->update([
            'last_run_at' => now(),
            'last_success_at' => now(),
            'last_error_at' => null,
            'last_error_message' => '',
            'updated_at' => now(),
        ]);

        $this->broadcastOverviewUpdate();
        $this->enqueueFollowUpGenerationIfNeeded($taskId, $meta);
    }

    public function failJob(int $jobId, int $taskId, string $errorMessage, int $durationMs, int $retryDelaySeconds = 60): void
    {
        $run = TaskRun::query()->whereKey($jobId)->first();
        if (! $run) {
            return;
        }

        $runMeta = $this->normalizeMeta($run->meta);
        $attemptCount = (int) ($runMeta['attempt_count'] ?? 0) + 1;
        $maxAttempts = max(1, (int) ($runMeta['max_attempts'] ?? 3));
        $shouldRetry = $attemptCount < $maxAttempts;
        $nextAvailableAt = now()->addSeconds(max(1, $retryDelaySeconds));

        $newMeta = array_merge($runMeta, [
            'attempt_count' => $attemptCount,
            'max_attempts' => $maxAttempts,
            'last_error' => $errorMessage,
            'available_at' => $shouldRetry ? $nextAvailableAt->toDateTimeString() : ($runMeta['available_at'] ?? ''),
        ]);

        TaskRun::query()->whereKey($jobId)->update([
            'status' => $shouldRetry ? 'pending' : 'failed',
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
            'finished_at' => $shouldRetry ? null : now(),
            'meta' => $newMeta,
        ]);

        Task::query()->whereKey($taskId)->update([
            'last_run_at' => now(),
            'last_error_at' => now(),
            'last_error_message' => $errorMessage,
            'updated_at' => now(),
        ]);

        if ($shouldRetry) {
            $this->dispatchLaravelQueueJob($jobId, $nextAvailableAt);
        }

        $this->broadcastOverviewUpdate();
    }

    public function cancelJob(int $jobId, int $taskId, string $reason = 'Stopped by administrator'): void
    {
        TaskRun::query()->whereKey($jobId)->update([
            'status' => 'cancelled',
            'finished_at' => now(),
            'error_message' => $reason,
            'duration_ms' => 0,
        ]);

        Task::query()->whereKey($taskId)->update([
            'last_run_at' => now(),
            'last_error_at' => now(),
            'last_error_message' => $reason,
            'updated_at' => now(),
        ]);

        $this->broadcastOverviewUpdate();
    }

    public function recoverStaleJobs(int $timeoutSeconds = 600): int
    {
        $threshold = now()->subSeconds(max(60, $timeoutSeconds));
        $candidateRows = TaskRun::withoutGlobalScopes()
            ->where('status', 'running')
            ->where('started_at', '<', $threshold)
            ->orderBy('id')
            ->get(['id', 'tenant_id'])
            ->map(static fn (TaskRun $run): array => [
                'id' => (int) $run->id,
                'tenant_id' => (int) ($run->tenant_id ?? 0),
            ])
            ->all();

        $recovered = 0;
        foreach ($candidateRows as $candidateRow) {
            $jobId = (int) $candidateRow['id'];
            $tenantId = (int) $candidateRow['tenant_id'];
            if ($tenantId <= 0) {
                continue;
            }

            $didRecover = TenantContext::run($tenantId, function () use ($jobId): bool {
                $run = TaskRun::query()
                    ->with('task:id,status,schedule_enabled')
                    ->whereKey($jobId)
                    ->where('status', 'running')
                    ->first();

                if (! $run) {
                    return false;
                }

                $task = $run->task;
                if (! $task || ($task->status ?? 'paused') !== 'active' || (int) ($task->schedule_enabled ?? 1) !== 1) {
                    TaskRun::query()
                        ->whereKey($jobId)
                        ->where('status', 'running')
                        ->update([
                            'status' => 'cancelled',
                            'finished_at' => now(),
                            'error_message' => 'Task is not active; stale running run cancelled.',
                        ]);

                    return false;
                }

                $affected = TaskRun::query()
                    ->whereKey($jobId)
                    ->where('status', 'running')
                    ->update([
                        'status' => 'pending',
                        'finished_at' => null,
                        'error_message' => '',
                    ]);

                if ($affected === 1) {
                    $this->dispatchLaravelQueueJob($jobId);

                    return true;
                }

                return false;
            });

            if ($didRecover) {
                $recovered++;
            }
        }

        return $recovered;
    }

    private function dispatchLaravelQueueJob(int $taskRunId, mixed $availableAt = null): void
    {
        $dispatch = ProcessGeoFlowTaskJob::dispatch($taskRunId)->onQueue('geoflow');

        if ($availableAt instanceof Carbon) {
            $dispatch->delay($availableAt);

            return;
        }

        if (is_string($availableAt) && trim($availableAt) !== '') {
            try {
                $dispatch->delay(Carbon::parse($availableAt));
            } catch (Throwable) {
                // Ignore invalid datetime values.
            }
        }
    }

    private function enqueueFollowUpGenerationIfNeeded(int $taskId, array $meta): void
    {
        if (($meta['action'] ?? '') !== 'generate_draft') {
            return;
        }

        if ((string) config('queue.default') === 'sync') {
            return;
        }

        $task = Task::query()
            ->whereKey($taskId)
            ->first(['id', 'status', 'schedule_enabled', 'created_count', 'article_limit', 'draft_limit']);
        if (! $task || ($task->status ?? 'paused') !== 'active' || (int) ($task->schedule_enabled ?? 1) !== 1) {
            return;
        }

        $articleLimit = max(1, (int) ($task->article_limit ?? $task->draft_limit ?? 10));
        if ((int) ($task->created_count ?? 0) >= $articleLimit) {
            return;
        }

        $draftLimit = max(1, (int) ($task->draft_limit ?? 10));
        $draftCount = DB::table('articles')
            ->where('task_id', $taskId)
            ->when(TenantContext::id() !== null, fn ($query) => $query->where('tenant_id', TenantContext::id()))
            ->where('status', 'draft')
            ->whereNull('deleted_at')
            ->count();
        if ($draftCount >= $draftLimit) {
            return;
        }

        $this->enqueueTaskJob($taskId, 'generate_article', [
            'source' => 'follow_up_generation',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function broadcastOverviewUpdate(): void
    {
        try {
            app(TaskRealtimeBroadcastService::class)->broadcastOverview();
        } catch (Throwable) {
            // Ignore realtime broadcast failures.
        }
    }
}
