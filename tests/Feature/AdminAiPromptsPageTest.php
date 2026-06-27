<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiPromptsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_content_prompts_are_visible(): void
    {
        // 迁移内置的 GEO 默认正文提示词归属默认租户（id 1）。超级管理员可跨租户查看，
        // 因此用 super_admin 绕过 TenantScope 来断言这批内置提示词在页面可见。
        $admin = Admin::query()->create([
            'username' => 'ai_prompt_admin',
            'password' => 'secret-123',
            'email' => 'ai-prompt-admin@example.com',
            'display_name' => 'AI Prompt Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.ai-prompts'))
            ->assertOk()
            ->assertSee('GEO营销学·信任型正文生成')
            ->assertSee('GEO榜单型正文生成')
            ->assertSee('GEO Marketing · Trust-Based Article Generation (English)')
            ->assertSee('GEO Ranking-Style Article Generation (English)');
    }
}
