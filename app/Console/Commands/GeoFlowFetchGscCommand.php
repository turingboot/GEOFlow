<?php

namespace App\Console\Commands;

use App\Jobs\FetchGscJob;
use App\Models\GscProperty;
use App\Services\GeoFlow\GoogleSearchConsole\GscOrchestrator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * 扫描到期的谷歌搜录属性并拉取数据。
 *
 * 跨租户：调度场景下没有租户上下文，必须先枚举各租户、再在各自上下文中查询，
 * 否则全局 TenantScope 会按 tenant_id=NULL 过滤而扫不到任何属性
 * （对齐 GeoFlowScheduleTasksCommand 的写法，而非 keyword-trends 命令）。
 */
class GeoFlowFetchGscCommand extends Command
{
    protected $signature = 'geoflow:fetch-gsc {--property= : Only this property id} {--sync : Run inline instead of queueing}';

    protected $description = 'Scan due Google Search Console properties and fetch search + indexing data';

    public function handle(GscOrchestrator $orchestrator): int
    {
        $propertyOption = $this->option('property');
        $onlyId = $propertyOption !== null && $propertyOption !== '' ? (int) $propertyOption : null;

        $tenantIds = GscProperty::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('tenant_id')
            ->when($onlyId !== null, fn ($q) => $q->whereKey($onlyId))
            ->distinct()
            ->pluck('tenant_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values();

        $processed = 0;
        foreach ($tenantIds as $tenantId) {
            $processed += TenantContext::run($tenantId, function () use ($orchestrator, $onlyId): int {
                return $this->processTenant($orchestrator, $onlyId);
            });
        }

        $this->info("GSC fetch: {$processed} property(ies) processed.");

        return self::SUCCESS;
    }

    private function processTenant(GscOrchestrator $orchestrator, ?int $onlyId): int
    {
        $query = GscProperty::query()->where('status', 'active');
        if ($onlyId !== null) {
            $query->whereKey($onlyId);
        }

        $processed = 0;
        foreach ($query->get() as $property) {
            // 指定 property 时视为手动运行，忽略调度节奏。
            if ($onlyId === null && ! $this->isDue($property)) {
                continue;
            }

            if ($this->option('sync')) {
                $orchestrator->run($property);
            } else {
                // 复用 trends 队列：与关键词趋势同属「定时外部数据拉取」的低优先级后台任务，
                // 各 worker / Horizon 均已监听，无需新增队列与部署配置。
                FetchGscJob::dispatch((int) $property->id)->onQueue('trends');
            }
            $processed++;
        }

        return $processed;
    }

    private function isDue(GscProperty $property): bool
    {
        $schedule = (string) $property->schedule;
        if ($schedule === '' || $schedule === 'manual') {
            return false;
        }

        $last = $property->last_fetched_at;
        if ($last === null) {
            return true;
        }

        $cutoff = match ($schedule) {
            'hourly' => now()->subHour(),
            'weekly' => now()->subWeek(),
            default => now()->subDay(),
        };

        return $last->lessThan($cutoff);
    }
}
