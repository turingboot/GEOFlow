<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SiteSetting;
use App\Services\GeoFlow\KeywordTrend\KeywordTrendDataSourceCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminKeywordDataSourceConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_admin_keeps_three_ai_configurator_cards(): void
    {
        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.ai.configurator'))
            ->assertOk()
            ->assertDontSee(__('admin.ai_configurator.keyword_source_title'))
            ->assertDontSee(route('admin.keyword-data-source.edit'), false);
    }

    public function test_super_admin_sees_keyword_data_source_card_and_help(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.ai.configurator'))
            ->assertOk()
            ->assertSee(__('admin.ai_configurator.keyword_source_title'))
            ->assertSee(__('admin.ai_configurator.help_keyword_source'))
            ->assertSee('assets/admin/keyword-data-source-icon.png', false)
            ->assertSee('color: #FF7A3D;', false)
            ->assertSee(route('admin.keyword-data-source.edit'), false);
    }

    public function test_keyword_data_source_page_requires_super_admin(): void
    {
        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.keyword-data-source.edit'))
            ->assertForbidden();
    }

    public function test_super_admin_can_save_serpapi_api_key(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.keyword-data-source.update'), [
                'api_key' => 'serp-secret',
            ])
            ->assertRedirect(route('admin.keyword-data-source.edit'));

        $this->assertSame('serp-secret', app(KeywordTrendDataSourceCredentialService::class)->serpApiApiKey());
        $this->assertDatabaseHas('site_settings', [
            'tenant_id' => null,
            'setting_key' => 'keyword_trends_serpapi_api_key',
        ]);
    }

    public function test_standard_admin_can_read_global_serpapi_api_key(): void
    {
        app(KeywordTrendDataSourceCredentialService::class)->saveSerpApiApiKey('global-serp-secret');

        $this->actingAs($this->admin('admin'), 'admin');

        $this->assertSame('global-serp-secret', app(KeywordTrendDataSourceCredentialService::class)->serpApiApiKey());
    }

    public function test_empty_save_requires_serpapi_api_key_when_none_exists(): void
    {
        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.keyword-data-source.update'), [
                'api_key' => '',
            ])
            ->assertSessionHas('api_key_error', __('admin.keyword_data_source.error.api_key_required'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.keyword-data-source.edit'))
            ->assertOk()
            ->assertSee(__('admin.keyword_data_source.error.api_key_required'))
            ->assertDontSee('bg-red-100 border border-red-400', false);

        $this->assertFalse(SiteSetting::withoutGlobalScopes()->where('setting_key', 'keyword_trends_serpapi_api_key')->exists());
    }

    public function test_empty_save_keeps_existing_serpapi_api_key(): void
    {
        app(KeywordTrendDataSourceCredentialService::class)->saveSerpApiApiKey('existing-serp-secret');

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.keyword-data-source.update'), [
                'api_key' => '',
            ])
            ->assertRedirect(route('admin.keyword-data-source.edit'));

        $this->assertSame('existing-serp-secret', app(KeywordTrendDataSourceCredentialService::class)->serpApiApiKey());
        $this->assertSame(1, SiteSetting::withoutGlobalScopes()->where('setting_key', 'keyword_trends_serpapi_api_key')->count());
    }

    private function admin(string $role): Admin
    {
        return Admin::query()->create([
            'username' => 'keyword_data_source_'.$role.'_'.Admin::query()->count(),
            'password' => 'secret-123',
            'email' => 'keyword-data-source-'.$role.'@example.com',
            'display_name' => 'Keyword Data Source Admin',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
