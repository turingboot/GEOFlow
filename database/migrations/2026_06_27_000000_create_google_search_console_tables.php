<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 谷歌搜录（Google Search Console 监控）模块数据表。
 *
 * 五张表镜像 keyword_trend 模块：属性(站点) + 加密凭据 + 拉取快照 + 搜索表现明细 + 收录状态明细。
 * 这些是全新表，直接内建 tenant_id（可空 + 外键），无需后续补租户列迁移。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gsc_properties')) {
            Schema::create('gsc_properties', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->string('name', 120);
                // GSC 站点标识：域名属性写 sc-domain:example.com，URL 属性写 https://example.com/
                $table->string('site_url', 300);
                // 认证方式：service_account（SA JSON）或 oauth（refresh token）
                $table->string('auth_type', 30)->default('service_account')->index();
                // OAuth 已连接账号邮箱（仅展示用，非密钥）
                $table->string('oauth_email', 190)->nullable();
                $table->string('schedule', 40)->default('manual');
                $table->string('status', 30)->default('active')->index();
                $table->json('config')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamp('last_fetched_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gsc_property_secrets')) {
            Schema::create('gsc_property_secrets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('gsc_property_id')->constrained('gsc_properties')->cascadeOnDelete();
                $table->string('key_id', 80);
                // 加密内容种类：service_account_json 或 oauth_refresh_token
                $table->string('secret_kind', 40)->default('service_account_json');
                $table->text('secret_ciphertext');
                $table->string('status', 30)->default('active')->index();
                $table->json('scopes')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                // key_id 按租户唯一，避免全局唯一造成跨租户撞键。
                $table->unique(['tenant_id', 'key_id'], 'gsc_property_secrets_tenant_key_unique');
            });
        }

        if (! Schema::hasTable('gsc_snapshots')) {
            Schema::create('gsc_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('gsc_property_id')->constrained('gsc_properties')->cascadeOnDelete();
                // 本次拉取的类型：search_analytics / url_inspection / sitemaps
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
                // 覆盖/索引判定：coverage_state 如「Submitted and indexed」；verdict 为 PASS/NEUTRAL/FAIL
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
        Schema::dropIfExists('gsc_property_secrets');
        Schema::dropIfExists('gsc_properties');
    }
};
