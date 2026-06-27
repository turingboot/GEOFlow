<?php

namespace Tests\Feature;

use App\Jobs\FetchGscJob;
use App\Models\GscProperty;
use App\Models\GscPropertySecret;
use App\Models\GscSnapshot;
use App\Services\GeoFlow\GoogleSearchConsole\GscAuthResolver;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GscCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'geoflow.google_search_console.oauth_client_id' => 'cid',
            'geoflow.google_search_console.oauth_client_secret' => 'csecret',
        ]);
    }

    public function test_sync_run_fetches_due_property(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.t', 'expires_in' => 3599]),
            '*searchAnalytics*' => Http::response(['rows' => [
                ['keys' => ['kw', 'https://example.com/a'], 'clicks' => 1, 'impressions' => 10, 'ctr' => 0.1, 'position' => 5.0],
            ]]),
            '*/sitemaps' => Http::response(['sitemap' => []]),
        ]);

        $property = $this->makeDueProperty();

        $this->artisan('geoflow:fetch-gsc', ['--sync' => true])->assertExitCode(0);

        $snapshot = GscSnapshot::query()
            ->where('gsc_property_id', $property->id)
            ->where('type', GscSnapshot::TYPE_SEARCH_ANALYTICS)
            ->firstOrFail();
        $this->assertSame('success', $snapshot->status);
        $this->assertNotNull($property->fresh()->last_fetched_at);
    }

    public function test_queues_job_for_due_property(): void
    {
        Queue::fake();
        $property = $this->makeDueProperty();

        $this->artisan('geoflow:fetch-gsc')->assertExitCode(0);

        Queue::assertPushedOn('trends', FetchGscJob::class);
    }

    public function test_manual_only_skips_when_not_due(): void
    {
        Queue::fake();
        $this->makeDueProperty(schedule: 'manual');

        $this->artisan('geoflow:fetch-gsc')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    private function makeDueProperty(string $schedule = 'daily'): GscProperty
    {
        $property = GscProperty::query()->create([
            'name' => '示例站点',
            'site_url' => 'sc-domain:example.com',
            'auth_type' => 'oauth',
            'schedule' => $schedule,
            'status' => 'active',
            'last_fetched_at' => null,
        ]);

        $property->secrets()->create([
            'key_id' => 'gsc_'.bin2hex(random_bytes(6)),
            'secret_kind' => GscPropertySecret::KIND_OAUTH_REFRESH,
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt('1//refresh'),
            'status' => 'active',
            'scopes' => [GscAuthResolver::SCOPE],
        ]);

        return $property->fresh(['activeSecret']);
    }
}
