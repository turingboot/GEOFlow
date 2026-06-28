<?php

namespace Tests\Feature;

use App\Models\GscConnection;
use App\Models\GscProperty;
use App\Models\GscSearchMetric;
use App\Models\GscSnapshot;
use App\Models\GscUrlInspection;
use App\Services\GeoFlow\GoogleSearchConsole\GscAuthResolver;
use App\Services\GeoFlow\GoogleSearchConsole\GscOrchestrator;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GscOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'geoflow.google_search_console.oauth_client_id' => 'client-123.apps.googleusercontent.com',
            'geoflow.google_search_console.oauth_client_secret' => 'secret-xyz',
        ]);
    }

    public function test_run_search_analytics_persists_metrics_and_snapshot(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.t', 'expires_in' => 3599]),
            '*searchAnalytics*' => Http::response(['rows' => [
                ['keys' => ['geoflow 教程', 'https://example.com/a'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.035, 'position' => 4.2],
                ['keys' => ['gsc 监控', 'https://example.com/b'], 'clicks' => 3, 'impressions' => 90, 'ctr' => 0.033, 'position' => 8.1],
            ]]),
            '*/sitemaps' => Http::response(['sitemap' => [
                ['path' => 'https://example.com/sitemap.xml', 'contents' => [['type' => 'web', 'submitted' => 100, 'indexed' => 80]]],
            ]]),
        ]);

        $property = $this->makeOauthProperty();
        $snapshot = app(GscOrchestrator::class)->runSearchAnalytics($property);

        $this->assertSame('success', $snapshot->status);
        // 6 个维度 × 2 行（fake 对所有 searchAnalytics 调用返回同样的 2 行）。
        $this->assertSame(12, (int) $snapshot->fetched_count);
        // 总计取自 date 维度：曝光 340+90=430、点击 12+3=15。
        $this->assertSame(430, (int) ($snapshot->stats['total_impressions'] ?? 0));
        $this->assertSame(15, (int) ($snapshot->stats['total_clicks'] ?? 0));

        $metric = GscSearchMetric::query()->where('dimension', 'query')->where('dimension_value', 'geoflow 教程')->firstOrFail();
        $this->assertSame((int) $property->tenant_id, (int) $metric->tenant_id);
        $this->assertSame(12, (int) $metric->clicks);
        // 每个维度都存了一行（query/page/country/device/date/search_appearance）。
        $this->assertSame(6, GscSearchMetric::query()->where('gsc_snapshot_id', $snapshot->id)->where('dimension_value', 'geoflow 教程')->count());
    }

    public function test_run_aggregates_sitemap_indexing_overview(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.t', 'expires_in' => 3599]),
            '*searchAnalytics*' => Http::response(['rows' => []]),
            '*/sitemaps' => Http::response(['sitemap' => [
                ['path' => 'https://example.com/sitemap.xml', 'contents' => [['type' => 'web', 'submitted' => 200, 'indexed' => 150]]],
            ]]),
        ]);

        $property = $this->makeOauthProperty();
        app(GscOrchestrator::class)->run($property);

        $sitemapSnapshot = GscSnapshot::query()
            ->where('gsc_property_id', $property->id)
            ->where('type', GscSnapshot::TYPE_SITEMAPS)
            ->firstOrFail();

        $this->assertSame('success', $sitemapSnapshot->status);
        $this->assertSame(150, (int) ($sitemapSnapshot->stats['indexed'] ?? 0));
        $this->assertSame(200, (int) ($sitemapSnapshot->stats['submitted'] ?? 0));
        $this->assertNotNull($property->fresh()->last_fetched_at);
    }

    public function test_inspect_urls_records_indexing_status(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.t', 'expires_in' => 3599]),
            '*urlInspection*' => Http::response(['inspectionResult' => ['indexStatusResult' => [
                'verdict' => 'PASS',
                'coverageState' => 'Submitted and indexed',
                'indexingState' => 'INDEXING_ALLOWED',
                'robotsTxtState' => 'ALLOWED',
                'lastCrawlTime' => '2026-06-20T08:00:00Z',
            ]]]),
        ]);

        $property = $this->makeOauthProperty();
        $snapshot = app(GscOrchestrator::class)->inspectUrls($property, ['https://example.com/a']);

        $this->assertSame('success', $snapshot->status);
        $this->assertSame(1, (int) ($snapshot->stats['indexed'] ?? 0));
        $inspection = GscUrlInspection::query()->where('gsc_snapshot_id', $snapshot->id)->firstOrFail();
        $this->assertSame('PASS', $inspection->verdict);
        $this->assertSame('Submitted and indexed', $inspection->coverage_state);
        $this->assertSame((int) $property->tenant_id, (int) $inspection->tenant_id);
    }

    public function test_api_failure_marks_snapshot_failed_without_rows(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.t', 'expires_in' => 3599]),
            '*searchAnalytics*' => Http::response('quota exceeded', 429),
        ]);

        $property = $this->makeOauthProperty();
        $snapshot = app(GscOrchestrator::class)->runSearchAnalytics($property);

        $this->assertSame('failed', $snapshot->status);
        $this->assertNotEmpty($snapshot->error);
        $this->assertSame(0, GscSearchMetric::query()->where('gsc_snapshot_id', $snapshot->id)->count());
    }

    private function makeOauthProperty(): GscProperty
    {
        $connection = GscConnection::query()->create([
            'name' => '示例连接',
            'provider' => GscConnection::PROVIDER_OAUTH,
            'email' => 'a@b.com',
            'secret_kind' => GscConnection::KIND_OAUTH_REFRESH,
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt('1//refresh-token'),
            'status' => 'active',
            'scopes' => [GscAuthResolver::SCOPE],
        ]);

        $property = GscProperty::query()->create([
            'gsc_connection_id' => $connection->id,
            'name' => '示例站点',
            'site_url' => 'sc-domain:example.com',
            'schedule' => 'daily',
            'status' => 'active',
        ]);

        $this->assertGreaterThan(0, TenantContext::id());

        return $property->fresh(['connection']);
    }
}
