<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('keyword_trend_sources') && ! Schema::hasColumn('keyword_trend_sources', 'ai_relevance')) {
            Schema::table('keyword_trend_sources', function (Blueprint $table): void {
                $table->boolean('ai_relevance')->default(false)->after('auto_import');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('keyword_trend_sources') && Schema::hasColumn('keyword_trend_sources', 'ai_relevance')) {
            Schema::table('keyword_trend_sources', function (Blueprint $table): void {
                $table->dropColumn('ai_relevance');
            });
        }
    }
};
