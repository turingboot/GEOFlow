<?php

namespace Tests\Feature;

use App\Exceptions\TenantContextRequiredException;
use App\Models\Admin;
use App\Models\Prompt;
use App\Models\Tenant;
use App\Support\Tenancy\AdminTenantContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_tenant_status_pill_without_full_tenant_list(): void
    {
        $defaultTenant = Tenant::query()->where('slug', 'default')->firstOrFail();
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);
        $this->normalAdmin($tenant->id);

        // 状态条 + 搜索面板按需加载：页面只出现「全部租户」和切换/搜索端点，不再渲染全量租户列表。
        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.tenant.switch'), false)
            ->assertSee(route('admin.tenant.search'), false)
            ->assertSee(__('admin.tenant_switch.all'))
            ->assertDontSee($defaultTenant->name)
            ->assertDontSee($tenant->name);
    }

    public function test_super_admin_in_tenant_mode_sees_active_tenant_name_and_exit(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);
        $this->normalAdmin($tenant->id);

        $this->actingAs($this->superAdmin(), 'admin')
            ->withSession([AdminTenantContext::SESSION_KEY => $tenant->id])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('admin.tenant_switch.active', ['name' => $tenant->name]))
            ->assertSee(__('admin.tenant_switch.exit'));
    }

    public function test_super_admin_does_not_see_or_switch_to_orphan_tenants(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Orphan', 'slug' => 'orphan', 'status' => 'active']);
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee($tenant->name);

        $this->actingAs($superAdmin, 'admin')
            ->from(route('admin.dashboard'))
            ->post(route('admin.tenant.switch'), ['tenant_id' => $tenant->id])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('tenant_id')
            ->assertSessionMissing(AdminTenantContext::SESSION_KEY);
    }

    public function test_tenant_search_matches_by_tenant_name_admin_username_and_email(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);
        $owner = $this->normalAdmin($tenant->id);
        $tenant->update(['owner_admin_id' => $owner->id]);
        Tenant::query()->create(['name' => 'Orphan', 'slug' => 'orphan', 'status' => 'active']);
        $superAdmin = $this->superAdmin();

        // 按租户名搜索
        $byName = $this->actingAs($superAdmin, 'admin')
            ->getJson(route('admin.tenant.search', ['q' => 'acme']))
            ->assertOk()
            ->json();
        $this->assertSame(['Acme'], array_column($byName['results'], 'name'));
        $this->assertStringContainsString('tenant_admin', $byName['results'][0]['owner']);

        // 按名下管理员用户名 / 邮箱搜索
        $this->actingAs($superAdmin, 'admin')
            ->getJson(route('admin.tenant.search', ['q' => 'tenant_admin']))
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Acme');
        $this->actingAs($superAdmin, 'admin')
            ->getJson(route('admin.tenant.search', ['q' => 'tenant_admin@example.com']))
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Acme');

        // 无匹配（孤儿租户不可搜到）
        $this->actingAs($superAdmin, 'admin')
            ->getJson(route('admin.tenant.search', ['q' => 'orphan']))
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    public function test_tenant_search_with_empty_keyword_returns_recent_tenants(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);
        $this->normalAdmin($tenant->id);
        $superAdmin = $this->superAdmin();

        // 尚未进入过任何租户：最近列表为空
        $this->actingAs($superAdmin, 'admin')
            ->getJson(route('admin.tenant.search'))
            ->assertOk()
            ->assertJsonPath('recent', [])
            ->assertJsonPath('results', []);

        // 进入一次后，空关键字返回最近进入
        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.tenant.switch'), ['tenant_id' => $tenant->id]);
        $this->actingAs($superAdmin, 'admin')
            ->getJson(route('admin.tenant.search'))
            ->assertOk()
            ->assertJsonPath('recent.0.name', 'Acme');
    }

    public function test_tenant_search_is_forbidden_for_normal_admin(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);

        $this->actingAs($this->normalAdmin($tenant->id), 'admin')
            ->getJson(route('admin.tenant.search', ['q' => 'acme']))
            ->assertForbidden();
    }

    public function test_switch_with_redirect_dashboard_goes_to_dashboard(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);
        $this->normalAdmin($tenant->id);

        $this->actingAs($this->superAdmin(), 'admin')
            ->from(route('admin.admin-users.index'))
            ->post(route('admin.tenant.switch'), ['tenant_id' => $tenant->id, 'redirect' => 'dashboard'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas(AdminTenantContext::SESSION_KEY, $tenant->id);
    }

    public function test_super_admin_can_switch_into_default_tenant_without_owner_admin(): void
    {
        $tenant = Tenant::query()->where('slug', 'default')->firstOrFail();

        $this->actingAs($this->superAdmin(), 'admin')
            ->from(route('admin.dashboard'))
            ->post(route('admin.tenant.switch'), ['tenant_id' => $tenant->id])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas(AdminTenantContext::SESSION_KEY, $tenant->id);
    }

    public function test_normal_admin_does_not_see_tenant_switcher(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);

        $this->actingAs($this->normalAdmin($tenant->id), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.tenant.switch'), false);
    }

    public function test_super_admin_can_switch_into_a_specific_tenant(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);
        $this->normalAdmin($tenant->id);

        $this->actingAs($this->superAdmin(), 'admin')
            ->from(route('admin.dashboard'))
            ->post(route('admin.tenant.switch'), ['tenant_id' => $tenant->id])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas(AdminTenantContext::SESSION_KEY, $tenant->id);
    }

    public function test_super_admin_can_switch_back_to_all_tenants(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->withSession([AdminTenantContext::SESSION_KEY => 99])
            ->from(route('admin.dashboard'))
            ->post(route('admin.tenant.switch'), ['tenant_id' => 0])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionMissing(AdminTenantContext::SESSION_KEY);
    }

    public function test_switching_to_invalid_tenant_is_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->from(route('admin.dashboard'))
            ->post(route('admin.tenant.switch'), ['tenant_id' => 123456])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('tenant_id')
            ->assertSessionMissing(AdminTenantContext::SESSION_KEY);
    }

    public function test_normal_admin_cannot_switch_tenant(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);

        $this->actingAs($this->normalAdmin($tenant->id), 'admin')
            ->post(route('admin.tenant.switch'), ['tenant_id' => $tenant->id])
            ->assertForbidden();
    }

    public function test_creating_tenant_scoped_model_in_global_mode_is_blocked(): void
    {
        // 模拟超管「全部租户（只读总览）」模式：bypass=true，无具体 tenant_id。
        TenantContext::set(null, true);

        $this->expectException(TenantContextRequiredException::class);

        try {
            Prompt::query()->create(['name' => 'x', 'content' => 'y']);
        } finally {
            TenantContext::clear();
        }
    }

    private function superAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'root_admin',
            'password' => 'secret-123',
            'email' => 'root_admin@example.com',
            'display_name' => 'Root',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function normalAdmin(int $tenantId): Admin
    {
        return Admin::query()->create([
            'username' => 'tenant_admin',
            'password' => 'secret-123',
            'email' => 'tenant_admin@example.com',
            'display_name' => 'Tenant Admin',
            'role' => 'admin',
            'status' => 'active',
            'tenant_id' => $tenantId,
        ]);
    }
}
