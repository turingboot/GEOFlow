<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 谷歌搜录（Google Search Console 监控）模块数据表。
 *
 * 以「连接」为中心：每租户可有一个或多个 Google 连接（OAuth 一键授权或服务账号），
 * 一个连接名下挂多个被监控站点(property)，凭据加密存在连接上、多站点共用。
 * 全新表，直接内建 tenant_id。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gsc_connections')) {
            Schema::create('gsc_connections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->string('name', 120)->nullable();
                // oauth（refresh token）或 service_account（SA JSON）
                $table->string('provider', 30)->default('oauth')->index();
                $table->string('email', 190)->nullable();
                $table->string('secret_kind', 40)->default('oauth_refresh_token');
                $table->text('secret_ciphertext');
                $table->string('status', 30)->default('active')->index();
                $table->json('scopes')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gsc_properties')) {
            Schema::create('gsc_properties', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('gsc_connection_id')->constrained('gsc_connections')->cascadeOnDelete();
                $table->string('name', 120);
                // GSC 站点标识：域名属性 sc-domain:example.com；URL 属性 https://example.com/
                $table->string('site_url', 300);
                $table->string('schedule', 40)->default('daily');
                $table->string('status', 30)->default('active')->index();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamp('last_fetched_at')->nullable();
                $table->timestamps();
                $table->unique(['gsc_connection_id', 'site_url'], 'gsc_properties_connection_site_unique');
            });
        }

        if (! Schema::hasTable('gsc_snapshots')) {
            Schema::create('gsc_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('gsc_property_id')->constrained('gsc_properties')->cascadeOnDelete();
                $table->string('type', 30)->default('search_analytics')->index();
                $table->string('status', 30)->default('pending')->index();
                $table->integer('fetched_count')->default(0);
                $table->json('stats')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('ran_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gsc_search_metrics')) {
            Schema::create('gsc_search_metrics', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('gsc_snapshot_id')->constrained('gsc_snapshots')->cascadeOnDelete();
                $table->foreignId('gsc_property_id')->constrained('gsc_properties')->cascadeOnDelete();
                $table->string('query', 300)->nullable();
                $table->string('page', 500)->nullable();
                $table->integer('clicks')->default(0);
                $table->integer('impressions')->default(0);
                $table->float('ctr')->default(0);
                $table->float('position')->default(0);
                $table->date('date_start')->nullable();
                $table->date('date_end')->nullable();
                $table->json('raw')->nullable();
                $table->timestamps();
                $table->index(['gsc_snapshot_id', 'clicks'], 'gsc_search_metrics_snapshot_clicks_index');
            });
        }

        if (! Schema::hasTable('gsc_url_inspections')) {
            Schema::create('gsc_url_inspections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('gsc_snapshot_id')->constrained('gsc_snapshots')->cascadeOnDelete();
                $table->foreignId('gsc_property_id')->constrained('gsc_properties')->cascadeOnDelete();
                $table->string('url', 500);
                $table->string('coverage_state', 160)->nullable();
                $table->string('verdict', 40)->nullable()->index();
                $table->string('indexing_state', 60)->nullable();
                $table->string('robots_state', 60)->nullable();
                $table->string('google_canonical', 500)->nullable();
                $table->timestamp('last_crawl_time')->nullable();
                $table->json('raw')->nullable();
                $table->timestamps();
                $table->index(['gsc_snapshot_id'], 'gsc_url_inspections_snapshot_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_url_inspections');
        Schema::dropIfExists('gsc_search_metrics');
        Schema::dropIfExists('gsc_snapshots');
        Schema::dropIfExists('gsc_properties');
        Schema::dropIfExists('gsc_connections');
    }
};
