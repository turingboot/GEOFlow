<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\KeywordLibrary;
use App\Models\KeywordTrendSource;
use App\Support\GeoFlow\ApiKeyCrypto;
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
            ->assertSee('name="provider"', false)
            ->assertSee('name="api_key"', false)
            ->assertSee('name="target_keyword_library_id"', false);
    }

    public function test_store_creates_source_and_encrypted_secret(): void
    {
        $library = KeywordLibrary::query()->create(['name' => 'Lib', 'keyword_count' => 0]);

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

        $this->assertSame('dataforseo', $source->provider);
        $this->assertSame(['ai seo', 'seo tools'], $source->seed_keywords);
        $this->assertSame((int) $library->id, (int) $source->target_keyword_library_id);
        $this->assertTrue((bool) $source->auto_import);
        $this->assertSame('user@example.com', $source->resolvedConfig()['login'] ?? null);

        $secret = $source->activeSecret;
        $this->assertNotNull($secret);
        $this->assertSame('secret-pass', app(ApiKeyCrypto::class)->decrypt($secret->secret_ciphertext));
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

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'kt_admin',
            'password' => 'secret-123',
            'email' => 'kt-admin@example.com',
            'display_name' => 'KT Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
