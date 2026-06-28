<?php

namespace Tests\Feature;

use App\Jobs\FetchGscJob;
use App\Models\Admin;
use App\Models\GscConnection;
use App\Models\GscProperty;
use App\Models\GscSearchMetric;
use App\Models\GscSnapshot;
use App\Models\SystemState;
use App\Models\Tenant;
use App\Services\GeoFlow\GoogleSearchConsole\GscAuthResolver;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\GscOauthAppConfig;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminGscPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_guest_is_redirected_from_gsc_pages(): void
    {
        $this->get(route('admin.google-search-console.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_index_with_oauth_not_configured_notice(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.google-search-console.index'))
            ->assertOk()
            ->assertSee(__('admin.gsc.page_title'))
            ->assertSee(__('admin.gsc.notice.oauth_not_configured'));
    }

    public function test_super_admin_saves_oauth_app_settings_encrypted(): void
    {
        $this->actingAs($this->makeAdmin('super_admin'), 'admin')
            ->post(route('admin.google-search-console.settings.save'), [
                'client_id' => 'cid.apps.googleusercontent.com',
                'client_secret' => 'super-secret',
                'redirect_uri' => 'https://app.test/geo_admin/google-search-console/oauth/callback',
            ])
            ->assertRedirect(route('admin.google-search-console.settings'));

        $state = SystemState::query()->where('key', GscOauthAppConfig::STATE_KEY)->firstOrFail();
        $this->assertSame('cid.apps.googleusercontent.com', $state->value['client_id'] ?? null);
        $this->assertStringStartsWith('enc:v1:', (string) ($state->value['client_secret'] ?? ''));
        $this->assertSame('super-secret', app(GscOauthAppConfig::class)->clientSecret());
    }

    public function test_regular_admin_cannot_open_settings(): void
    {
        $this->actingAs($this->makeAdmin('admin'), 'admin')
            ->get(route('admin.google-search-console.settings'))
            ->assertForbidden();
    }

    public function test_service_account_connection_is_created_encrypted(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.google-search-console.service-account.store'), [
                'name' => '我的 SA',
                'service_account_json' => '{"type":"service_account","client_email":"svc@p.iam.gserviceaccount.com","private_key":"x"}',
            ])
            ->assertRedirect();

        $connection = GscConnection::query()->where('provider', GscConnection::PROVIDER_SERVICE_ACCOUNT)->firstOrFail();
        $this->assertSame((int) $admin->tenant_id, (int) $connection->tenant_id);
        $this->assertStringStartsWith('enc:v1:', (string) $connection->secret_ciphertext);
        $this->assertStringContainsString('svc@p.iam.gserviceaccount.com', app(ApiKeyCrypto::class)->decrypt((string) $connection->secret_ciphertext));
    }

    public function test_service_account_rejects_invalid_json(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.google-search-console.service-account.store'), [
                'service_account_json' => 'not-json',
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, GscConnection::query()->count());
    }

    public function test_sites_picker_lists_and_adds_properties(): void
    {
        config([
            'geoflow.google_search_console.oauth_client_id' => 'cid',
            'geoflow.google_search_console.oauth_client_secret' => 'csecret',
        ]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.t', 'expires_in' => 3599]),
            '*googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [
                ['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner'],
                ['siteUrl' => 'https://blog.example.com/', 'permissionLevel' => 'siteFullUser'],
            ]]),
        ]);

        $admin = $this->makeAdmin();
        $connection = $this->makeOauthConnection();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.sites', $connection->id))
            ->assertOk()
            ->assertSee('sc-domain:example.com')
            ->assertSee('blog.example.com');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.google-search-console.add-sites', $connection->id), [
                'sites' => ['sc-domain:example.com', 'https://blog.example.com/'],
            ])
            ->assertRedirect(route('admin.google-search-console.index'));

        $this->assertSame(2, GscProperty::query()->where('gsc_connection_id', $connection->id)->count());
    }

    public function test_fetch_dispatches_job_on_trends_queue(): void
    {
        Queue::fake();
        $admin = $this->makeAdmin();
        $connection = $this->makeOauthConnection();
        $property = GscProperty::query()->create([
            'gsc_connection_id' => $connection->id,
            'name' => 'site',
            'site_url' => 'sc-domain:example.com',
            'schedule' => 'daily',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.google-search-console.fetch', $property->id))
            ->assertRedirect(route('admin.google-search-console.show', $property->id));

        Queue::assertPushedOn('trends', FetchGscJob::class);
    }

    public function test_show_page_renders_with_insights(): void
    {
        $admin = $this->makeAdmin();
        $connection = $this->makeOauthConnection();
        $property = GscProperty::query()->create([
            'gsc_connection_id' => $connection->id,
            'name' => 'site',
            'site_url' => 'sc-domain:example.com',
            'schedule' => 'daily',
            'status' => 'active',
        ]);
        $snapshot = GscSnapshot::query()->create([
            'gsc_property_id' => $property->id,
            'type' => GscSnapshot::TYPE_SEARCH_ANALYTICS,
            'status' => 'success',
            'stats' => ['total_clicks' => 10, 'total_impressions' => 100, 'avg_position' => 5.0],
            'ran_at' => now(),
        ]);
        GscSearchMetric::query()->create([
            'gsc_snapshot_id' => $snapshot->id,
            'gsc_property_id' => $property->id,
            'dimension' => 'query',
            'dimension_value' => 'brandword',
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'position' => 5.0,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.show', $property->id))
            ->assertOk()
            ->assertSee(__('admin.gsc.section.search'))
            ->assertSee('data-gsc-tab', false)
            ->assertSee('brandword');
    }

    public function test_disconnect_removes_connection_and_its_sites(): void
    {
        $admin = $this->makeAdmin();
        $connection = $this->makeOauthConnection();
        GscProperty::query()->create([
            'gsc_connection_id' => $connection->id,
            'name' => 'site',
            'site_url' => 'sc-domain:example.com',
            'schedule' => 'daily',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.google-search-console.disconnect', $connection->id))
            ->assertRedirect(route('admin.google-search-console.index'));

        $this->assertSame(0, GscConnection::query()->count());
        $this->assertSame(0, GscProperty::query()->count());
    }

    public function test_super_admin_sees_cross_tenant_overview(): void
    {
        $this->makeOauthConnection(); // 当前测试租户下的连接

        $other = Tenant::query()->create(['name' => '租户B', 'slug' => 'tenant-b', 'status' => 'active']);
        TenantContext::run((int) $other->id, function (): void {
            GscConnection::query()->create([
                'name' => 'conn-b',
                'provider' => GscConnection::PROVIDER_OAUTH,
                'email' => 'b@b.com',
                'secret_kind' => GscConnection::KIND_OAUTH_REFRESH,
                'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt('1//rt-b'),
                'status' => 'active',
                'scopes' => [GscAuthResolver::SCOPE],
            ]);
        });

        $this->actingAs($this->makeAdmin('super_admin'), 'admin')
            ->get(route('admin.google-search-console.index'))
            ->assertOk()
            ->assertSee(__('admin.gsc.notice.super_overview'))
            ->assertSee('conn-b')
            ->assertSee('租户B');
    }

    public function test_super_admin_cannot_use_self_service_actions(): void
    {
        $super = $this->makeAdmin('super_admin');

        $this->actingAs($super, 'admin')
            ->get(route('admin.google-search-console.connect'))
            ->assertRedirect(route('admin.google-search-console.index'))
            ->assertSessionHasErrors();

        $this->actingAs($super, 'admin')
            ->get(route('admin.google-search-console.service-account'))
            ->assertRedirect(route('admin.google-search-console.index'));
    }

    private function makeOauthConnection(): GscConnection
    {
        return GscConnection::query()->create([
            'name' => 'conn',
            'provider' => GscConnection::PROVIDER_OAUTH,
            'email' => 'a@b.com',
            'secret_kind' => GscConnection::KIND_OAUTH_REFRESH,
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt('1//rt'),
            'status' => 'active',
            'scopes' => [GscAuthResolver::SCOPE],
        ]);
    }

    private function makeAdmin(string $role = 'admin'): Admin
    {
        return Admin::query()->create([
            'username' => 'gsc_'.$role.'_'.bin2hex(random_bytes(3)),
            'password' => 'secret-123',
            'email' => 'gsc-'.bin2hex(random_bytes(3)).'@example.com',
            'display_name' => 'GSC Admin',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
