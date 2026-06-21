<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 选题规划层：月度选题计划
        if (! Schema::hasTable('topic_plans')) {
            Schema::create('topic_plans', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 160);
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 30)->default('draft')->index();
                $table->json('source_summary')->nullable();
                $table->unsignedBigInteger('ai_model_id')->nullable()->index();
                $table->unsignedBigInteger('target_title_library_id')->nullable()->index();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamps();
            });
        }

        // 选题规划层：选题条目
        if (! Schema::hasTable('topic_plan_items')) {
            Schema::create('topic_plan_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('topic_plan_id')->constrained('topic_plans')->cascadeOnDelete();
                $table->string('title', 255);
                $table->string('keyword', 200);
                $table->json('secondary_keywords')->nullable();
                $table->text('rationale')->nullable();
                $table->integer('heat_score')->nullable();
                $table->string('kb_support', 16)->nullable();
                $table->string('dup_risk', 16)->nullable();
                $table->date('planned_publish_at')->nullable();
                $table->string('status', 30)->default('suggested')->index();
                $table->unsignedBigInteger('created_title_id')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // GEO 质检层：文章评分
        if (! Schema::hasTable('article_geo_audits')) {
            Schema::create('article_geo_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
                $table->integer('geo_score')->default(0)->index();
                $table->integer('title_keyword_match')->default(0);
                $table->integer('structure_score')->default(0);
                $table->integer('kb_coverage')->default(0);
                $table->integer('dup_ratio')->default(0);
                $table->integer('word_count')->default(0);
                $table->string('gate_decision', 30)->default('passthrough')->index();
                $table->text('suggestion')->nullable();
                $table->json('risk_notes')->nullable();
                $table->json('details')->nullable();
                $table->unsignedBigInteger('ai_model_id')->nullable();
                $table->timestamp('audited_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('article_geo_audits');
        Schema::dropIfExists('topic_plan_items');
        Schema::dropIfExists('topic_plans');
    }
};
