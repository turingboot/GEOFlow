<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSC 维度值/URL 列放宽到 text：落地页(page) URL 可超过 500 字符，
 * varchar(500) 会导致 searchAnalytics 入库 22001 溢出、整次搜索快照失败。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gsc_search_metrics')) {
            Schema::table('gsc_search_metrics', function (Blueprint $table): void {
                $table->text('dimension_value')->nullable()->change();
                $table->text('page')->nullable()->change();
            });
        }

        if (Schema::hasTable('gsc_url_inspections')) {
            Schema::table('gsc_url_inspections', function (Blueprint $table): void {
                $table->text('url')->change();
                $table->text('google_canonical')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // 放宽列宽不做回退（缩回 500 可能截断已有数据）。
    }
};
