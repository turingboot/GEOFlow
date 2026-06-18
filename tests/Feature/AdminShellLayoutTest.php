<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShellLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_shell_renders_sidebar_navigation_and_topbar_controls(): void
    {
        $response = $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk();

        // Sidebar shell + every primary navigation entry must be reachable.
        $response->assertSee('id="admin-sidebar"', false);
        $response->assertSee('toggleSidebar()', false);
        foreach ([
            'admin.dashboard',
            'admin.analytics',
            'admin.tasks.index',
            'admin.distribution.index',
            'admin.articles.index',
            'admin.materials.index',
            'admin.ai.configurator',
            'admin.site-settings.index',
        ] as $routeName) {
            $response->assertSee(route($routeName), false);
        }

        // Topbar controls: notifications, language switch, user menu (logout).
        $response->assertSee(__('admin.header.notifications.title'));
        $response->assertSee(route('admin.locale.switch', ['locale' => 'en']), false);
        $response->assertSee(route('admin.logout'), false);
        $response->assertSee(__('admin.button.logout'));
    }

    public function test_super_admin_only_entries_are_gated(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.admin-users.index'), false)
            ->assertSee(route('admin.api-tokens.index'), false);

        $this->actingAs($this->admin('admin', 'standard_admin'), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.api-tokens.index'), false)
            ->assertDontSee(route('admin.admin-activity-logs'), false);
    }

    private function admin(string $role, string $username = 'shell_admin'): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'password' => 'secret-123',
            'email' => $username.'@example.com',
            'display_name' => 'Shell Admin',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
