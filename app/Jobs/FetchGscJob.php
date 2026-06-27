<?php

namespace App\Jobs;

use App\Models\GscProperty;
use App\Services\GeoFlow\GoogleSearchConsole\GscOrchestrator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 异步拉取某个 GSC 属性的搜索表现 + sitemap 收录概览。
 * 跨租户安全：先绕过全局作用域加载，再在该属性的租户上下文中执行。
 */
class FetchGscJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(private readonly int $propertyId) {}

    public function handle(GscOrchestrator $orchestrator): void
    {
        $property = GscProperty::withoutGlobalScopes()->find($this->propertyId);
        if ($property === null) {
            return;
        }

        TenantContext::run((int) $property->tenant_id, function () use ($orchestrator, $property): void {
            $orchestrator->run($property);
        });
    }
}
