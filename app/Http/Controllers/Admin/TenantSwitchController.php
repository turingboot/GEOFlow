<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\AdminTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 超级管理员切换「当前操作租户」。仅 admin.super 可访问（见 routes/web.php）。
 */
class TenantSwitchController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $tenantId = (int) ($validated['tenant_id'] ?? 0);

        if ($tenantId > 0 && ! AdminTenantContext::isSelectableTenant($tenantId)) {
            return back()->withErrors(['tenant_id' => __('admin.tenant_switch.invalid')]);
        }

        AdminTenantContext::setActiveTenantId($tenantId > 0 ? $tenantId : null);

        return back()->with('message', $tenantId > 0
            ? __('admin.tenant_switch.switched')
            : __('admin.tenant_switch.switched_all'));
    }
}
