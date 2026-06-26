<?php

namespace Tests\Feature;

use App\Jobs\FetchKeywordTrendsJob;
use App\Models\KeywordTrendSource;
use App\Models\Tenant;
use App\Services\GeoFlow\KeywordTrend\KeywordTrendOrchestrator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class KeywordTrendCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_due_sources_only(): void
    {
        Queue::fake();
        $tenant = $this->tenant();

        // Due: daily schedule, never fetched.
        KeywordTrendSource::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => 'Due', 'provider' => 'dataforseo', 'category' => 'ai',
            'schedule' => 'daily', 'status' => 'active', 'last_fetched_at' => null,
        ]);
        // Not due: manual.
        KeywordTrendSource::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => 'Manual', 'provider' => 'dataforseo', 'category' => 'ai',
            'schedule' => 'manual', 'status' => 'active',
        ]);
        // Not due: daily but fetched 1 hour ago.
        KeywordTrendSource::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => 'Recent', 'provider' => 'dataforseo', 'category' => 'ai',
            'schedule' => 'daily', 'status' => 'active', 'last_fetched_at' => now()->subHour(),
        ]);

        $this->artisan('geoflow:fetch-keyword-trends')->assertSuccessful();

        Queue::assertPushed(FetchKeywordTrendsJob::class, 1);
        Queue::assertPushedOn('trends', FetchKeywordTrendsJob::class);
    }

    public function test_source_option_queues_that_source_regardless_of_schedule(): void
    {
        Queue::fake();
        $tenant = $this->tenant();

        $source = KeywordTrendSource::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => 'Manual one', 'provider' => 'dataforseo', 'category' => 'ai',
            'schedule' => 'manual', 'status' => 'active',
        ]);

        $this->artisan('geoflow:fetch-keyword-trends', ['--source' => $source->id])->assertSuccessful();

        Queue::assertPushed(FetchKeywordTrendsJob::class, 1);
    }

    public function test_sync_option_runs_source_inside_tenant_context(): void
    {
        $tenant = $this->tenant('sync-tenant');
        $source = KeywordTrendSource::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => 'Manual sync',
            'provider' => 'dataforseo',
            'category' => 'ai',
            'schedule' => 'manual',
            'status' => 'active',
        ]);

        $orchestrator = Mockery::mock(KeywordTrendOrchestrator::class);
        $orchestrator->shouldReceive('run')
            ->once()
            ->with(Mockery::on(function (KeywordTrendSource $received) use ($source, $tenant): bool {
                return (int) $received->id === (int) $source->id
                    && TenantContext::id() === (int) $tenant->id;
            }));
        $this->app->instance(KeywordTrendOrchestrator::class, $orchestrator);

        $this->artisan('geoflow:fetch-keyword-trends', [
            '--source' => $source->id,
            '--sync' => true,
        ])->assertSuccessful();
    }

    private function tenant(string $slug = 'keyword-trend-tenant'): Tenant
    {
        $this->ensureKeywordTrendSchema();

        $tenant = Tenant::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'status' => 'active',
        ]);

        TenantContext::set((int) $tenant->id);

        return $tenant;
    }

    private function ensureKeywordTrendSchema(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 120)->unique();
                $table->unsignedBigInteger('owner_admin_id')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keyword_trend_sources')) {
            Schema::create('keyword_trend_sources', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('provider');
                $table->string('category');
                $table->json('seed_keywords')->nullable();
                $table->string('region')->nullable();
                $table->string('language')->nullable();
                $table->string('timeframe')->nullable();
                $table->integer('heat_threshold')->nullable();
                $table->integer('top_n')->nullable();
                $table->unsignedBigInteger('target_keyword_library_id')->nullable();
                $table->boolean('auto_import')->default(false);
                $table->boolean('ai_relevance')->default(false);
                $table->string('schedule')->nullable();
                $table->string('status')->default('active');
                $table->json('config')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamp('last_fetched_at')->nullable();
                $table->timestamps();
            });
        }
    }
}
