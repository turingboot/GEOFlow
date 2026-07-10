<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\KeywordLibrary;
use App\Models\KeywordTrendSource;
use App\Services\GeoFlow\KeywordTrend\KeywordTrendDataSourceCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminKeywordTrendPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_empty_state(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.keyword-trends.index'))
            ->assertOk()
            ->assertSee(__('admin.keyword_trends.page_title'))
            ->assertSee(__('admin.keyword_trends.empty.sources'));
    }

    public function test_create_form_renders(): void
    {
        KeywordLibrary::query()->create(['name' => 'Lib', 'keyword_count' => 0]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.keyword-trends.create'))
            ->assertOk()
            ->assertSee('name="provider" value="serpapi"', false)
            ->assertDontSee('<select class="admin-select" id="provider" name="provider">', false)
            ->assertDontSee('name="api_key"', false)
            ->assertSee('name="target_keyword_library_id"', false);
    }

    public function test_store_creates_source_without_page_api_key_secret(): void
    {
        $library = KeywordLibrary::query()->create(['name' => 'Lib', 'keyword_count' => 0]);
        app(KeywordTrendDataSourceCredentialService::class)->saveSerpApiApiKey('serp-key');

        $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.keyword-trends.store'), [
            'name' => 'AI SEO',
            'provider' => 'dataforseo',
            'category' => 'ai seo',
            'seed_keywords' => "ai seo\nseo tools",
            'region' => 'US',
            'language' => 'en',
            'heat_threshold' => 50,
            'top_n' => 30,
            'target_keyword_library_id' => $library->id,
            'schedule' => 'daily',
            'auto_import' => '1',
            'dataforseo_login' => 'user@example.com',
            'location_name' => 'United States',
            'api_key' => 'secret-pass',
        ]);

        $source = KeywordTrendSource::query()->where('name', 'AI SEO')->firstOrFail();
        $response->assertRedirect(route('admin.keyword-trends.show', $source->id));

        $this->assertSame('serpapi', $source->provider);
        $this->assertSame(['ai seo', 'seo tools'], $source->seed_keywords);
        $this->assertSame((int) $library->id, (int) $source->target_keyword_library_id);
        $this->assertTrue((bool) $source->auto_import);
        $this->assertSame([], $source->resolvedConfig());
        $this->assertNull($source->activeSecret);
    }

    public function test_store_requires_configured_serpapi_api_key(): void
    {
        $library = KeywordLibrary::query()->create(['name' => 'Lib', 'keyword_count' => 0]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.keyword-trends.store'), [
                'name' => 'AI SEO',
                'provider' => 'serpapi',
                'category' => 'ai seo',
                'target_keyword_library_id' => $library->id,
                'schedule' => 'manual',
            ])
            ->assertSessionHasErrors(['provider' => __('admin.keyword_trends.message.serpapi_key_missing')]);

        $this->assertFalse(KeywordTrendSource::query()->where('name', 'AI SEO')->exists());
    }

    public function test_edit_form_hides_api_key_field(): void
    {
        $source = KeywordTrendSource::query()->create([
            'name' => 'AI SEO',
            'provider' => 'serpapi',
            'category' => 'ai seo',
            'region' => 'US',
            'schedule' => 'manual',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.keyword-trends.edit', $source->id))
            ->assertOk()
            ->assertSee('name="provider" value="serpapi"', false)
            ->assertDontSee('<select class="admin-select" id="provider" name="provider">', false)
            ->assertDontSee('name="api_key"', false);
    }

    public function test_keyword_trend_index_shows_provider_column_for_standard_admin(): void
    {
        KeywordTrendSource::query()->create([
            'name' => 'AI SEO',
            'provider' => 'serpapi',
            'category' => 'ai seo',
            'region' => 'US',
            'schedule' => 'manual',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.keyword-trends.index'))
            ->assertOk()
            ->assertSee(__('admin.keyword_trends.field.provider'))
            ->assertSee(__('admin.keyword_trends.provider.serpapi'));
    }

    public function test_show_renders_with_no_snapshot(): void
    {
        $source = KeywordTrendSource::query()->create([
            'name' => 'AI SEO', 'provider' => 'dataforseo', 'category' => 'ai seo',
            'region' => 'US', 'schedule' => 'manual', 'status' => 'active',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.keyword-trends.show', $source->id))
            ->assertOk()
            ->assertSee('AI SEO')
            ->assertSee(__('admin.keyword_trends.snapshot.none'));
    }

    private function admin(string $role = 'super_admin'): Admin
    {
        return Admin::query()->create([
            'username' => 'kt_admin_'.$role.'_'.Admin::query()->count(),
            'password' => 'secret-123',
            'email' => 'kt-admin-'.$role.'@example.com',
            'display_name' => 'KT Admin',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
