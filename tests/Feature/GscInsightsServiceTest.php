<?php

namespace Tests\Feature;

use App\Models\GscConnection;
use App\Models\GscProperty;
use App\Models\GscSearchMetric;
use App\Models\GscSnapshot;
use App\Models\GscUrlInspection;
use App\Services\GeoFlow\GoogleSearchConsole\GscInsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GscInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_trend_computes_deltas_and_direction(): void
    {
        $property = $this->makeProperty();
        // 先建上期(低 id)，再建本期(高 id)。
        $this->snapshot($property, GscSnapshot::TYPE_SEARCH_ANALYTICS, ['total_clicks' => 100, 'total_impressions' => 1000, 'avg_position' => 8.0]);
        $this->snapshot($property, GscSnapshot::TYPE_SEARCH_ANALYTICS, ['total_clicks' => 150, 'total_impressions' => 1200, 'avg_position' => 6.0]);

        $trend = app(GscInsightsService::class)->build($property)['searchTrend'];

        $this->assertTrue($trend['has_previous']);
        $this->assertSame(50.0, $trend['clicks']['change']);
        $this->assertSame('good', $trend['clicks']['direction']);
        // 排名从 8 降到 6 = 变好（越小越好）。
        $this->assertSame('good', $trend['position']['direction']);
        $this->assertSame(-2.0, $trend['position']['change']);
    }

    public function test_query_segments_split_top_opportunity_striking(): void
    {
        $property = $this->makeProperty();
        $snap = $this->snapshot($property, GscSnapshot::TYPE_SEARCH_ANALYTICS, []);

        $this->metric($snap, $property, 'brand', clicks: 80, impressions: 1000, ctr: 0.08, position: 2.0);
        $this->metric($snap, $property, 'opp', clicks: 2, impressions: 800, ctr: 0.0025, position: 25.0);
        $this->metric($snap, $property, 'near', clicks: 30, impressions: 300, ctr: 0.1, position: 14.0);

        $insights = app(GscInsightsService::class)->build($property);

        $this->assertSame('brand', $insights['topQueries'][0]['query']);
        $this->assertSame(['opp'], array_column($insights['opportunityQueries'], 'query'));
        $this->assertSame(['near'], array_column($insights['strikingDistance'], 'query'));
    }

    public function test_indexing_trend_series_is_chronological(): void
    {
        $property = $this->makeProperty();
        $this->snapshot($property, GscSnapshot::TYPE_SITEMAPS, ['submitted' => 100, 'indexed' => 70]);
        $this->snapshot($property, GscSnapshot::TYPE_SITEMAPS, ['submitted' => 100, 'indexed' => 85]);

        $series = app(GscInsightsService::class)->build($property)['indexingTrend'];

        $this->assertCount(2, $series);
        $this->assertSame(70, $series[0]['indexed']);
        $this->assertSame(85, $series[1]['indexed']);
    }

    public function test_indexing_dropouts_detected(): void
    {
        $property = $this->makeProperty();
        $prev = $this->snapshot($property, GscSnapshot::TYPE_URL_INSPECTION, []);
        $this->inspection($prev, $property, 'https://e/a', 'PASS');
        $this->inspection($prev, $property, 'https://e/b', 'PASS');

        $latest = $this->snapshot($property, GscSnapshot::TYPE_URL_INSPECTION, []);
        $this->inspection($latest, $property, 'https://e/a', 'FAIL', 'Crawled - currently not indexed');
        $this->inspection($latest, $property, 'https://e/b', 'PASS');

        $dropouts = app(GscInsightsService::class)->build($property)['indexingDropouts'];

        $this->assertCount(1, $dropouts);
        $this->assertSame('https://e/a', $dropouts[0]['url']);
        $this->assertSame('Crawled - currently not indexed', $dropouts[0]['coverage_state']);
    }

    private function makeProperty(): GscProperty
    {
        $connection = GscConnection::query()->create([
            'name' => 'conn',
            'provider' => GscConnection::PROVIDER_OAUTH,
            'email' => 'a@b.com',
            'secret_kind' => GscConnection::KIND_OAUTH_REFRESH,
            'secret_ciphertext' => 'enc:v1:x',
            'status' => 'active',
        ]);

        return GscProperty::query()->create([
            'gsc_connection_id' => $connection->id,
            'name' => 'site',
            'site_url' => 'sc-domain:example.com',
            'schedule' => 'daily',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function snapshot(GscProperty $property, string $type, array $stats, string $status = 'success'): GscSnapshot
    {
        return GscSnapshot::query()->create([
            'gsc_property_id' => $property->id,
            'type' => $type,
            'status' => $status,
            'stats' => $stats,
            'ran_at' => now(),
        ]);
    }

    private function metric(GscSnapshot $snap, GscProperty $p, string $query, int $clicks, int $impressions, float $ctr, float $position): void
    {
        GscSearchMetric::query()->create([
            'gsc_snapshot_id' => $snap->id,
            'gsc_property_id' => $p->id,
            'query' => $query,
            'page' => 'https://e/'.$query,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $ctr,
            'position' => $position,
        ]);
    }

    private function inspection(GscSnapshot $snap, GscProperty $p, string $url, string $verdict, string $coverage = ''): void
    {
        GscUrlInspection::query()->create([
            'gsc_snapshot_id' => $snap->id,
            'gsc_property_id' => $p->id,
            'url' => $url,
            'verdict' => $verdict,
            'coverage_state' => $coverage ?: null,
        ]);
    }
}
