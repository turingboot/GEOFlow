<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->unsignedInteger('article_monthly_limit')->default(0);
            $table->unsignedInteger('knowledge_base_limit')->default(0);
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenant_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained('membership_plans')->restrictOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('remark')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'starts_at', 'ends_at']);
        });

        Schema::create('membership_usage_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_membership_id')->nullable()->constrained('tenant_memberships')->nullOnDelete();
            $table->string('period_key', 7);
            $table->timestamp('period_starts_at');
            $table->timestamp('period_ends_at');
            $table->unsignedInteger('article_published_used')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'period_key']);
        });

        $now = Carbon::now();
        DB::table('membership_plans')->insert([
            [
                'name' => '入门版',
                'article_monthly_limit' => 50,
                'knowledge_base_limit' => 3,
                'is_custom' => false,
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => '专业版',
                'article_monthly_limit' => 300,
                'knowledge_base_limit' => 10,
                'is_custom' => false,
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => '商业版',
                'article_monthly_limit' => 1000,
                'knowledge_base_limit' => 30,
                'is_custom' => false,
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => '企业版',
                'article_monthly_limit' => 0,
                'knowledge_base_limit' => 0,
                'is_custom' => true,
                'is_active' => true,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_usage_periods');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('membership_plans');
    }
};
