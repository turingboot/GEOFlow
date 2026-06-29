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

    public function test_super_admin_sees_tenant_switcher_with_all_option_and_tenants(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.tenant.switch'), false)
            ->assertSee(__('admin.tenant_switch.all'))
            ->assertSee($tenant->name);
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
