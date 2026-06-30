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
use App\Support\GeoFlow\GscCountryName;
use App\Support\GeoFlow\GscOauthAppConfig;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        Cache::flush();
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
            ->post(route('admin.google-search-console.fetch', $property->id).'?tab=country', [
                'range_days' => 90,
            ])
            ->assertRedirect(route('admin.google-search-console.show', [
                $property->id,
                'range_days' => 90,
                'tab' => 'country',
            ]));

        Queue::assertPushedOn('trends', FetchGscJob::class);
        Queue::assertPushed(FetchGscJob::class, fn (FetchGscJob $job): bool => (new \ReflectionProperty($job, 'rangeDays'))->getValue($job) === 90);
    }

    public function test_show_page_fetch_form_keeps_selected_range_days(): void
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

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.show', $property->id).'?range_days=90')
            ->assertOk()
            ->assertSee('name="range_days" value="90"', false);
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
            'stats' => ['date_start' => '2026-06-20', 'date_end' => '2026-06-21', 'total_clicks' => 10, 'total_impressions' => 100, 'avg_position' => 5.0],
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
        foreach (range(1, 60) as $index) {
            GscSearchMetric::query()->create([
                'gsc_snapshot_id' => $snapshot->id,
                'gsc_property_id' => $property->id,
                'dimension' => 'query',
                'dimension_value' => 'keyword-'.$index,
                'clicks' => 20 - $index,
                'impressions' => 100 - $index,
                'ctr' => 0.1,
                'position' => 5.0,
            ]);
        }
        GscSearchMetric::query()->create([
            'gsc_snapshot_id' => $snapshot->id,
            'gsc_property_id' => $property->id,
            'dimension' => 'page',
            'dimension_value' => 'https://example.com/landing',
            'clicks' => 4,
            'impressions' => 20,
            'ctr' => 0.2,
            'position' => 2.0,
        ]);
        GscSearchMetric::query()->create([
            'gsc_snapshot_id' => $snapshot->id,
            'gsc_property_id' => $property->id,
            'dimension' => 'country',
            'dimension_value' => 'hkg',
            'clicks' => 8,
            'impressions' => 77,
            'ctr' => 0.104,
            'position' => 5.9,
        ]);
        foreach (['esp', 'zaf', 'pol', 'bel'] as $country) {
            GscSearchMetric::query()->create([
                'gsc_snapshot_id' => $snapshot->id,
                'gsc_property_id' => $property->id,
                'dimension' => 'country',
                'dimension_value' => $country,
                'clicks' => 8,
                'impressions' => 60,
                'ctr' => 0.13,
                'position' => 6.0,
            ]);
        }
        foreach (['2026-06-20' => 8, '2026-06-21' => 12] as $date => $impr) {
            GscSearchMetric::query()->create([
                'gsc_snapshot_id' => $snapshot->id,
                'gsc_property_id' => $property->id,
                'dimension' => 'date',
                'dimension_value' => $date,
                'clicks' => 1,
                'impressions' => $impr,
                'ctr' => 0.1,
                'position' => 5.0,
            ]);
        }

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.show', $property->id))
            ->assertOk()
            ->assertSee(__('admin.gsc.section.search'))
            ->assertSee('data-gsc-tab', false)
            ->assertSee('event.preventDefault();', false)
            ->assertSee('window.history.pushState', false)
            ->assertSee('loadGscSearchCard(btn.href)', false)
            ->assertSee('data-gsc-search-card', false)
            ->assertSee('partial', false)
            ->assertSee('loadGscSearchCard', false)
            ->assertSee('data-gsc-pagination', false)
            ->assertSee('gsc_query_page=2', false)
            ->assertSee('tab=query', false)
            ->assertSee('&#26368;&#21518;&#39029;', false)
            ->assertSee('<polyline', false)
            ->assertSee('data-gsc-chart-root', false)
            ->assertSee('data-gsc-custom-days', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('data-gsc-metric="clicks"', false)
            ->assertSee('data-gsc-metric="impressions"', false)
            ->assertSee('data-gsc-hover-line', false)
            ->assertSee('data-gsc-hover-date-bg', false)
            ->assertSee('data-gsc-page-row', false)
            ->assertSee('data-gsc-copy-toast', false)
            ->assertDontSee('data-gsc-page-actions-unused', false)
            ->assertSee('data-gsc-copy-url="https://example.com/landing"', false)
            ->assertSee('data-gsc-open-url="https://example.com/landing"', false)
            ->assertSee('HKG - 中国香港')
            ->assertSee('ESP - 西班牙')
            ->assertSee('ZAF - 南非')
            ->assertSee('POL - 波兰')
            ->assertSee('BEL - 比利时')
            ->assertSee('brandword');
    }

    public function test_gsc_country_name_maps_current_country_codes(): void
    {
        $codes = [
            'AFG', 'AGO', 'AIA', 'ALB', 'AND', 'ARE', 'ARG', 'ARM', 'ATG', 'AUS', 'AUT', 'AZE', 'BEL', 'BES', 'BFA',
            'BGD', 'BGR', 'BHR', 'BHS', 'BIH', 'BLM', 'BLR', 'BLZ', 'BMU', 'BOL', 'BRA', 'BRB', 'BRN', 'BTN', 'BWA',
            'CAN', 'CHE', 'CHL', 'CHN', 'CIV', 'CMR', 'COD', 'COG', 'COK', 'COL', 'CPV', 'CRI', 'CUB', 'CYM', 'CYP',
            'CZE', 'DEU', 'DNK', 'DOM', 'DZA', 'ECU', 'EGY', 'ESH', 'ESP', 'EST', 'ETH', 'FIN', 'FJI', 'FRA', 'FRO',
            'GAB', 'GBR', 'GEO', 'GGY', 'GHA', 'GIB', 'GIN', 'GNB', 'GNQ', 'GRC', 'GRD', 'GRL', 'GTM', 'GUM', 'GUY',
            'HKG', 'HND', 'HRV', 'HTI', 'HUN', 'IDN', 'IMN', 'IND', 'IRL', 'IRN', 'IRQ', 'ISL', 'ISR', 'ITA', 'JAM',
            'JEY', 'JOR', 'JPN', 'KAZ', 'KEN', 'KGZ', 'KHM', 'KNA', 'KOR', 'KWT', 'LAO', 'LBN', 'LBR', 'LBY', 'LCA',
            'LIE', 'LKA', 'LTU', 'LUX', 'LVA', 'MAC', 'MAF', 'MAR', 'MCO', 'MDA', 'MDG', 'MDV', 'MEX', 'MKD', 'MLI',
            'MLT', 'MMR', 'MNE', 'MNG', 'MOZ', 'MUS', 'MWI', 'MYS', 'NAM', 'NGA', 'NIC', 'NLD', 'NOR', 'NPL', 'NRU',
            'NZL', 'OMN', 'PAK', 'PAN', 'PER', 'PHL', 'PLW', 'PNG', 'POL', 'PRI', 'PRT', 'PRY', 'PSE', 'PYF', 'QAT',
            'REU', 'ROU', 'RUS', 'RWA', 'SAU', 'SDN', 'SEN', 'SGP', 'SLE', 'SLV', 'SOM', 'SRB', 'SUR', 'SVK', 'SVN',
            'SWE', 'SWZ', 'SXM', 'SYC', 'SYR', 'TGO', 'THA', 'TKM', 'TON', 'TTO', 'TUN', 'TUR', 'TWN', 'TZA', 'UGA',
            'UKR', 'URY', 'USA', 'UZB', 'VEN', 'VGB', 'VIR', 'VNM', 'VUT', 'XKK', 'YEM', 'ZAF', 'ZMB', 'ZWE', 'ZZZ',
        ];

        foreach ($codes as $code) {
            $this->assertNotNull(GscCountryName::name($code), $code.' should have a Chinese country name.');
        }

        $this->assertSame('ESP - 西班牙', GscCountryName::format('esp'));
        $this->assertSame('ZZZ - 未知地区', GscCountryName::format('ZZZ'));
    }

    public function test_show_page_can_render_search_card_partial(): void
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
            'stats' => ['date_start' => '2026-06-20', 'date_end' => '2026-06-21', 'total_clicks' => 10, 'total_impressions' => 100, 'avg_position' => 5.0],
            'ran_at' => now(),
        ]);
        $this->createSearchMetric($snapshot, $property, 'date', '2026-06-21', 1, 12, 5.0, '2026-06-21');
        $this->createSearchMetric($snapshot, $property, 'query', 'brandword', 10, 100, 5.0);

        $this->actingAs($admin, 'admin')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.google-search-console.show', $property->id).'?range_days=7&tab=query&partial=search')
            ->assertOk()
            ->assertSee('data-gsc-search-card', false)
            ->assertSee('brandword')
            ->assertDontSee('admin-hero-title', false);
    }

    public function test_show_page_ignores_partial_parameter_without_ajax(): void
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

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.show', $property->id).'?range_days=90&tab=country&partial=search')
            ->assertOk()
            ->assertSee('admin-hero-title', false)
            ->assertSee('data-gsc-search-card', false);
    }

    public function test_search_card_partial_does_not_keep_internal_or_stale_page_parameters(): void
    {
        Cache::flush();

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
            'stats' => ['date_start' => '2026-06-20', 'date_end' => '2026-06-21', 'total_clicks' => 10, 'total_impressions' => 100, 'avg_position' => 5.0],
            'ran_at' => now(),
        ]);
        $this->createSearchMetric($snapshot, $property, 'date', '2026-06-21', 1, 12, 5.0, '2026-06-21');
        foreach (range(1, 45) as $index) {
            $this->createSearchMetric($snapshot, $property, 'country', 'country-'.$index, 10, 100, 5.0);
        }

        $this->actingAs($admin, 'admin')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.google-search-console.show', $property->id).'?range_days=90&tab=country&per_page=20&gsc_query_page=2&partial=search')
            ->assertOk()
            ->assertSee('gsc_country_page=2', false)
            ->assertDontSee('partial=search', false)
            ->assertDontSee('gsc_query_page=2', false);
    }

    public function test_show_page_filters_daily_breakdown_tables_by_selected_range(): void
    {
        Cache::flush();

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
            'stats' => ['date_start' => '2026-06-01', 'date_end' => '2026-06-30', 'total_clicks' => 18, 'total_impressions' => 180, 'avg_position' => 4.0],
            'ran_at' => now(),
        ]);

        $this->createSearchMetric($snapshot, $property, 'date', '2026-06-10', 2, 40, 6.0, '2026-06-10');
        $this->createSearchMetric($snapshot, $property, 'date', '2026-06-29', 6, 60, 3.0, '2026-06-29');
        $this->createSearchMetric($snapshot, $property, 'date_query', 'oldword', 4, 80, 8.0, '2026-06-10');
        $this->createSearchMetric($snapshot, $property, 'date_query', 'recentword', 6, 60, 3.0, '2026-06-29');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.show', $property->id).'?range_days=7&tab=query')
            ->assertOk()
            ->assertSee('recentword')
            ->assertDontSee('oldword');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.show', $property->id).'?range_days=28&tab=query')
            ->assertOk()
            ->assertSee('recentword')
            ->assertSee('oldword');
    }

    public function test_show_page_prompts_fetch_when_selected_range_exceeds_available_data(): void
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
            'stats' => ['date_start' => '2026-06-01', 'date_end' => '2026-06-30', 'total_clicks' => 10, 'total_impressions' => 100, 'avg_position' => 5.0],
            'ran_at' => now(),
        ]);
        $this->createSearchMetric($snapshot, $property, 'date', '2026-06-30', 5, 50, 4.0, '2026-06-30');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.show', $property->id).'?range_days=100')
            ->assertOk()
            ->assertSee('name="range_days" value="100"', false)
            ->assertSee('100');
    }

    public function test_opportunity_tab_explains_empty_threshold_result_without_fetch_prompt(): void
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
            'stats' => ['date_start' => '2026-06-01', 'date_end' => '2026-06-30', 'total_clicks' => 10, 'total_impressions' => 100, 'avg_position' => 5.0],
            'ran_at' => now(),
        ]);

        $this->createSearchMetric($snapshot, $property, 'date', '2026-06-30', 5, 50, 4.0, '2026-06-30');
        $this->createSearchMetric($snapshot, $property, 'date_query', 'healthy-ctr', 10, 100, 5.0, '2026-06-30');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.show', $property->id).'?range_days=28&tab=opportunity')
            ->assertOk()
            ->assertSee(__('admin.gsc.insights.empty_opportunity'))
            ->assertDontSee('拉取最近 28 天数据');
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

    private function createSearchMetric(
        GscSnapshot $snapshot,
        GscProperty $property,
        string $dimension,
        string $value,
        int $clicks,
        int $impressions,
        float $position,
        ?string $date = null,
    ): GscSearchMetric {
        return GscSearchMetric::query()->create([
            'gsc_snapshot_id' => $snapshot->id,
            'gsc_property_id' => $property->id,
            'dimension' => $dimension,
            'dimension_value' => $value,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions > 0 ? $clicks / $impressions : 0,
            'position' => $position,
            'date_start' => $date,
            'date_end' => $date,
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
