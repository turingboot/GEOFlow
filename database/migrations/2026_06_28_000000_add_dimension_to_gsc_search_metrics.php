<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 搜索表现明细支持多维度切分：query / page / country / device / date / search_appearance。
 * dimension 标记这一行属于哪个维度，dimension_value 是该维度的取值。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gsc_search_metrics') && ! Schema::hasColumn('gsc_search_metrics', 'dimension')) {
            Schema::table('gsc_search_metrics', function (Blueprint $table): void {
                $table->string('dimension', 30)->default('query')->after('gsc_property_id')->index();
                $table->string('dimension_value', 500)->nullable()->after('dimension');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gsc_search_metrics') && Schema::hasColumn('gsc_search_metrics', 'dimension')) {
            Schema::table('gsc_search_metrics', function (Blueprint $table): void {
                $table->dropColumn(['dimension', 'dimension_value']);
            });
        }
    }
};
