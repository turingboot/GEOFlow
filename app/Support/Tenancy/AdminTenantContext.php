<?php

namespace App\Support\Tenancy;

use App\Models\Admin;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * 后台管理员的「当前操作租户」解析。
 *
 * - 普通管理员：永远锁定到自身 tenant_id（读写都被 {@see TenantScope} 限定）。
 * - 超级管理员：可在右上角切换器选择具体租户进入读写，或选择「全部租户」进入只读总览（bypass）。
 *   选择存放在 session（{@see SESSION_KEY}），由 {@see InitializeTenantContext} 中间件每次请求解析。
 */
class AdminTenantContext
{
    public const SESSION_KEY = 'admin.active_tenant_id';

    /**
     * session 中选中的租户 id；未选择或选择「全部租户」时为 null。
     */
    public static function activeTenantId(): ?int
    {
        $id = Session::get(self::SESSION_KEY);
        $id = is_numeric($id) ? (int) $id : 0;

        return $id > 0 ? $id : null;
    }

    public static function setActiveTenantId(?int $tenantId): void
    {
        if ($tenantId !== null && $tenantId > 0) {
            Session::put(self::SESSION_KEY, $tenantId);

            return;
        }

        Session::forget(self::SESSION_KEY);
    }

    /**
     * 解析并应用某个管理员请求的租户上下文。
     */
    public static function applyForAdmin(?Admin $admin): void
    {
        if (! $admin instanceof Admin) {
            TenantContext::clear();

            return;
        }

        if (! $admin->isSuperAdmin()) {
            // 普通管理员固定在自己的租户内读写。
            TenantContext::set($admin->tenant_id, false);

            return;
        }

        $activeId = self::activeTenantId();
        if ($activeId !== null && self::isSelectableTenant($activeId)) {
            // 超管进入某个具体租户：读写都限定到该租户。
            TenantContext::set($activeId, false);

            return;
        }

        // 未选择 / 选择「全部租户」/ 选中的租户已失效：全局只读总览（bypass）。
        // 写操作默认落到超管自身租户（若有），与历史 TenantContext::fromAdmin 行为保持一致；
        // 自身无租户的超管（如生产环境的全局超管）创建受租户约束的数据时会触发
        // TenantContextRequiredException，引导其先在右上角选择一个具体租户。
        if ($activeId !== null) {
            self::setActiveTenantId(null);
        }
        TenantContext::set($admin->tenant_id, true);
    }

    public static function isSelectableTenant(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        return Tenant::query()
            ->whereKey($tenantId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * 供切换器展示的可选租户列表。
     *
     * @return Collection<int, Tenant>
     */
    public static function selectableTenants(): Collection
    {
        return Tenant::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);
    }
}
