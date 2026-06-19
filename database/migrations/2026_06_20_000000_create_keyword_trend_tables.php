<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keyword_trend_sources')) {
            Schema::create('keyword_trend_sources', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('provider', 40)->index();
                $table->string('category', 160);
                $table->json('seed_keywords')->nullable();
                $table->string('region', 16)->default('US');
                $table->string('language', 16)->nullable();
                $table->string('timeframe', 32)->default('past_month');
                $table->integer('heat_threshold')->default(60);
                $table->integer('top_n')->default(50);
                $table->unsignedBigInteger('target_keyword_library_id')->nullable()->index();
                $table->boolean('auto_import')->default(false);
                $table->string('schedule', 40)->default('manual');
                $table->string('status', 30)->default('active')->index();
                $table->json('config')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamp('last_fetched_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keyword_trend_source_secrets')) {
            Schema::create('keyword_trend_source_secrets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('keyword_trend_source_id')->constrained('keyword_trend_sources')->cascadeOnDelete();
                $table->string('key_id', 80)->unique();
                $table->text('secret_ciphertext');
                $table->string('status', 30)->default('active')->index();
                $table->json('scopes')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keyword_trend_snapshots')) {
            Schema::create('keyword_trend_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('keyword_trend_source_id')->constrained('keyword_trend_sources')->cascadeOnDelete();
                $table->string('status', 30)->default('pending')->index();
                $table->integer('fetched_count')->default(0);
                $table->integer('kept_count')->default(0);
                $table->integer('imported_count')->default(0);
                $table->json('stats')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('ran_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keyword_trends')) {
            Schema::create('keyword_trends', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('keyword_trend_snapshot_id')->constrained('keyword_trend_snapshots')->cascadeOnDelete();
                $table->foreignId('keyword_trend_source_id')->constrained('keyword_trend_sources')->cascadeOnDelete();
                $table->string('keyword', 200);
                $table->integer('heat')->default(0)->index();
                $table->integer('search_volume')->nullable();
                $table->string('trend_direction', 16)->nullable();
                $table->integer('delta')->nullable();
                $table->string('region', 16)->nullable();
                $table->string('language', 16)->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->json('raw')->nullable();
                $table->boolean('imported')->default(false)->index();
                $table->unsignedBigInteger('keyword_id')->nullable();
                $table->timestamps();
                $table->unique(['keyword_trend_snapshot_id', 'keyword'], 'keyword_trend_snapshot_keyword_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_trends');
        Schema::dropIfExists('keyword_trend_snapshots');
        Schema::dropIfExists('keyword_trend_source_secrets');
        Schema::dropIfExists('keyword_trend_sources');
    }
};
