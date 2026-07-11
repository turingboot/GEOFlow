<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Tenancy\AdminTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 超级管理员切换「当前操作租户」。仅 admin.super 可访问（见 routes/web.php）。
 */
class TenantSwitchController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'min:0'],
            'redirect' => ['nullable', 'string', Rule::in(['dashboard'])],
        ]);

        $tenantId = (int) ($validated['tenant_id'] ?? 0);

        if ($tenantId > 0 && ! AdminTenantContext::isSelectableTenant($tenantId)) {
            return back()->withErrors(['tenant_id' => __('admin.tenant_switch.invalid')]);
        }

        AdminTenantContext::setActiveTenantId($tenantId > 0 ? $tenantId : null);
        if ($tenantId > 0) {
            AdminTenantContext::rememberRecentTenant($tenantId);
        }

        $message = $tenantId > 0
            ? __('admin.tenant_switch.switched')
            : __('admin.tenant_switch.switched_all');

        if (($validated['redirect'] ?? null) === 'dashboard') {
            return redirect()->route('admin.dashboard')->with('message', $message);
        }

        return back()->with('message', $message);
    }

    /**
     * 切换面板的按需搜索：空关键字返回「最近进入」，有关键字按租户名/管理员用户名/邮箱模糊匹配。
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $keyword = trim((string) ($validated['q'] ?? ''));

        if ($keyword === '') {
            return response()->json([
                'recent' => AdminTenantContext::recentTenants()->map($this->presentTenant(...))->values(),
                'results' => [],
            ]);
        }

        return response()->json([
            'recent' => [],
            'results' => AdminTenantContext::searchTenants($keyword)->map($this->presentTenant(...))->values(),
        ]);
    }

    /**
     * @return array{id:int, name:string, owner:string}
     */
    private function presentTenant(Tenant $tenant): array
    {
        $ownerParts = array_filter([
            (string) ($tenant->owner?->username ?? ''),
            (string) ($tenant->owner?->email ?? ''),
        ], static fn (string $part): bool => $part !== '');

        return [
            'id' => (int) $tenant->id,
            'name' => (string) $tenant->name,
            'owner' => implode(' · ', $ownerParts),
        ];
    }
}
