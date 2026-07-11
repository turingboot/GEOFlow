<?php

namespace App\Support\Tenancy;

use App\Models\Admin;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
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

    public const RECENT_SESSION_KEY = 'admin.recent_tenant_ids';

    public const RECENT_LIMIT = 5;

    public const SEARCH_LIMIT = 20;

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

        return self::selectableQuery()->whereKey($tenantId)->exists();
    }

    /**
     * 当前进入的租户（供顶栏状态条展示）；全部租户模式下为 null。
     */
    public static function activeTenant(): ?Tenant
    {
        $activeId = self::activeTenantId();
        if ($activeId === null) {
            return null;
        }

        return Tenant::query()->whereKey($activeId)->first(['id', 'name', 'slug']);
    }

    /**
     * 按关键字搜索可进入的租户（匹配租户名 / 名下管理员用户名 / 邮箱），供切换面板按需加载。
     *
     * @return Collection<int, Tenant>
     */
    public static function searchTenants(string $keyword, int $limit = self::SEARCH_LIMIT): Collection
    {
        $query = self::selectableQuery()->with('owner:id,username,email');

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $like = '%'.mb_strtolower($keyword).'%';
            $query->where(function ($outer) use ($like): void {
                $outer->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereHas('admins', function ($adminQuery) use ($like): void {
                        $adminQuery->whereRaw('LOWER(username) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like]);
                    });
            });
        }

        return $query->orderBy('id')->limit(max(1, $limit))->get(['id', 'name', 'slug', 'owner_admin_id']);
    }

    /**
     * 最近进入过的租户（按进入时间倒序，存于 session）。
     *
     * @return Collection<int, Tenant>
     */
    public static function recentTenants(): Collection
    {
        $ids = array_values(array_filter(array_map(
            static fn ($id): int => is_numeric($id) ? (int) $id : 0,
            (array) Session::get(self::RECENT_SESSION_KEY, [])
        ), static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return new Collection;
        }

        $order = array_flip($ids);

        return self::selectableQuery()
            ->with('owner:id,username,email')
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'slug', 'owner_admin_id'])
            ->sortBy(static fn (Tenant $tenant): int => $order[(int) $tenant->id] ?? PHP_INT_MAX)
            ->values();
    }

    public static function rememberRecentTenant(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }

        $ids = array_values(array_filter(array_map(
            static fn ($id): int => is_numeric($id) ? (int) $id : 0,
            (array) Session::get(self::RECENT_SESSION_KEY, [])
        ), static fn (int $id): bool => $id > 0 && $id !== $tenantId));

        array_unshift($ids, $tenantId);
        Session::put(self::RECENT_SESSION_KEY, array_slice($ids, 0, self::RECENT_LIMIT));
    }

    /**
     * 可进入租户的基础约束：活跃，且是默认租户或名下存在活跃的普通管理员。
     *
     * @return Builder<Tenant>
     */
    private static function selectableQuery()
    {
        return Tenant::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where('slug', 'default')
                    ->orWhereHas('admins', function ($adminQuery): void {
                        $adminQuery->where('status', 'active')
                            ->whereNotIn('role', ['super_admin', 'superadmin']);
                    });
            });
    }
}
