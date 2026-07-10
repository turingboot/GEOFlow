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
        $response->assertSee('id="user-menu"', false);
        $response->assertSee('width: 24rem; max-width: calc(100vw - 1rem);', false);
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

        $standardContent = $this->actingAs($this->admin('admin', 'standard_admin'), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.api-tokens.index'), false)
            ->assertDontSee(route('admin.admin-activity-logs'), false)
            ->getContent();

        $standardSidebar = $this->sidebarHtml($standardContent);
        $this->assertStringNotContainsString(route('admin.analytics'), $standardSidebar);
        $this->assertStringNotContainsString(__('admin.nav.analytics'), $standardSidebar);
    }

    public function test_sidebar_navigation_is_ordered_by_geo_execution_flow(): void
    {
        $content = $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        // 只截取侧边栏区域,避免页面其它位置的同名链接干扰顺序断言
        $start = strpos($content, 'id="admin-sidebar"');
        $this->assertNotFalse($start, '未渲染侧边栏');
        $end = strpos($content, '</aside>', $start);
        $this->assertNotFalse($end, '侧边栏未正确闭合');
        $sidebar = substr($content, $start, $end - $start);

        // 期望顺序:概览 → 调研/选题 → 备料/生产/分发 → 配置 →(超管)网站/会员/用户
        $expectedOrder = [
            'admin.dashboard',
            'admin.analytics',
            'admin.keyword-trends.index',
            'admin.google-search-console.index',
            'admin.topic-plans.index',
            'admin.materials.index',
            'admin.tasks.index',
            'admin.articles.index',
            'admin.distribution.index',
            'admin.ai.configurator',
            'admin.site-settings.index',
            'admin.memberships.index',
            'admin.admin-users.index',
        ];

        $lastPos = -1;
        foreach ($expectedOrder as $routeName) {
            $pos = strpos($sidebar, 'href="'.route($routeName).'"');
            $this->assertNotFalse($pos, "侧边栏缺少导航项: {$routeName}");
            $this->assertGreaterThan($lastPos, $pos, "导航顺序不符: {$routeName}");
            $lastPos = $pos;
        }
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

    private function sidebarHtml(string $content): string
    {
        $start = strpos($content, 'id="admin-sidebar"');
        $this->assertNotFalse($start, '未渲染侧边栏');
        $end = strpos($content, '</aside>', $start);
        $this->assertNotFalse($end, '侧边栏未正确闭合');

        return substr($content, $start, $end - $start);
    }
}
