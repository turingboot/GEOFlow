<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Tenant;
use App\Support\Tenancy\AdminTenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUsersManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_see_standard_admin_edit_and_delete_actions(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');
        $standardAdmin = $this->createAdmin('editor_admin', 'admin');

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index'))
            ->assertOk()
            ->assertSee(__('admin.button.edit'))
            ->assertSee(__('admin.button.delete'))
            ->assertSee(route('admin.admin-users.delete', ['adminId' => $standardAdmin->id]), false);
    }

    public function test_current_super_admin_can_see_own_edit_action_but_not_delete_action(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index'))
            ->assertOk()
            ->assertSee(__('admin.button.edit'))
            ->assertDontSee(route('admin.admin-users.delete', ['adminId' => $superAdmin->id]), false);
    }

    public function test_current_super_admin_can_update_own_profile_and_password_without_disabling_self(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.update', ['adminId' => $superAdmin->id]), [
                'username' => 'root_owner',
                'display_name' => 'Root Owner',
                'email' => 'root-owner@example.com',
                'status' => 'inactive',
                'password' => 'new-root-secret-123',
                'confirm_password' => 'new-root-secret-123',
            ])
            ->assertRedirect(route('admin.admin-users.index'));

        $superAdmin->refresh();

        $this->assertSame('root_owner', $superAdmin->username);
        $this->assertSame('Root Owner', $superAdmin->display_name);
        $this->assertSame('root-owner@example.com', $superAdmin->email);
        $this->assertSame('active', $superAdmin->status);
        $this->assertTrue(Hash::check('new-root-secret-123', $superAdmin->password));
    }

    public function test_super_admin_can_update_standard_admin_profile_and_password(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');
        $standardAdmin = $this->createAdmin('editor_admin', 'admin');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.update', ['adminId' => $standardAdmin->id]), [
                'username' => 'editor_ops',
                'display_name' => 'Editor Ops',
                'email' => 'editor-ops@example.com',
                'status' => 'inactive',
                'password' => 'new-secret-123',
                'confirm_password' => 'new-secret-123',
            ])
            ->assertRedirect(route('admin.admin-users.index'));

        $standardAdmin->refresh();

        $this->assertSame('editor_ops', $standardAdmin->username);
        $this->assertSame('Editor Ops', $standardAdmin->display_name);
        $this->assertSame('editor-ops@example.com', $standardAdmin->email);
        $this->assertSame('inactive', $standardAdmin->status);
        $this->assertTrue(Hash::check('new-secret-123', $standardAdmin->password));
    }

    public function test_super_admin_can_delete_standard_admin(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');
        $standardAdmin = $this->createAdmin('editor_admin', 'admin');
        $tenant = Tenant::query()->create([
            'name' => 'Editor Tenant',
            'slug' => 'editor-tenant',
            'owner_admin_id' => (int) $standardAdmin->id,
            'status' => 'active',
        ]);
        $standardAdmin->forceFill(['tenant_id' => (int) $tenant->id])->save();

        $this->actingAs($superAdmin, 'admin')
            ->withSession([AdminTenantContext::SESSION_KEY => (int) $tenant->id])
            ->post(route('admin.admin-users.delete', ['adminId' => $standardAdmin->id]))
            ->assertRedirect(route('admin.admin-users.index'))
            ->assertSessionMissing(AdminTenantContext::SESSION_KEY);

        $this->assertDatabaseMissing('admins', [
            'id' => $standardAdmin->id,
        ]);
        $this->assertDatabaseHas('tenants', [
            'id' => (int) $tenant->id,
            'owner_admin_id' => null,
            'status' => 'inactive',
        ]);
    }

    public function test_deleting_standard_admin_does_not_disable_default_tenant(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');
        $standardAdmin = $this->createAdmin('editor_admin', 'admin');
        $tenant = Tenant::query()->where('slug', 'default')->firstOrFail();
        $tenant->forceFill([
            'owner_admin_id' => (int) $standardAdmin->id,
            'status' => 'active',
        ])->save();
        $standardAdmin->forceFill(['tenant_id' => (int) $tenant->id])->save();

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.delete', ['adminId' => $standardAdmin->id]))
            ->assertRedirect(route('admin.admin-users.index'));

        $this->assertDatabaseHas('tenants', [
            'id' => (int) $tenant->id,
            'slug' => 'default',
            'status' => 'active',
        ]);
    }

    public function test_user_list_shows_enter_tenant_action_for_standard_admin_with_tenant(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');
        $standardAdmin = $this->createAdmin('editor_admin', 'admin');
        $tenant = Tenant::query()->create([
            'name' => 'Editor Tenant',
            'slug' => 'editor-tenant',
            'owner_admin_id' => (int) $standardAdmin->id,
            'status' => 'active',
        ]);
        $standardAdmin->forceFill(['tenant_id' => (int) $tenant->id])->save();

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index'))
            ->assertOk()
            ->assertSee(__('admin.tenant_switch.enter'))
            ->assertSee('name="tenant_id" value="'.$tenant->id.'"', false)
            ->assertSee('name="redirect" value="dashboard"', false);
    }

    public function test_user_list_supports_keyword_search(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');
        $this->createAdmin('editor_admin', 'admin');
        $this->createAdmin('writer_admin', 'admin');

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index', ['q' => 'editor']))
            ->assertOk()
            ->assertSee('editor_admin')
            ->assertDontSee('writer_admin');

        // 按邮箱也能搜到
        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index', ['q' => 'writer_admin@example.com']))
            ->assertOk()
            ->assertSee('writer_admin')
            ->assertDontSee('editor_admin');

        // 无匹配时展示空态；统计仍是全量口径
        $totalAdmins = Admin::query()->count();
        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index', ['q' => 'no-such-user']))
            ->assertOk()
            ->assertSee(__('admin.admin_users.empty_list'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['total_admins'] === $totalAdmins);
    }

    public function test_user_list_is_paginated(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');
        for ($i = 1; $i <= 25; $i++) {
            $this->createAdmin(sprintf('bulk_admin_%02d', $i), 'admin');
        }

        $response = $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index'))
            ->assertOk();

        $this->assertSame(20, $response->viewData('admins')->count());
        $this->assertSame(Admin::query()->count(), $response->viewData('admins')->total());

        // 第二页能看到剩余用户，且保留搜索参数的分页链接由 withQueryString 生成
        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('bulk_admin_25');
    }

    private function createAdmin(string $username, string $role): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'password' => 'secret-123',
            'email' => $username.'@example.com',
            'display_name' => $username,
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
