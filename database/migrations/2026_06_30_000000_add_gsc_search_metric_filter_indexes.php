<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gsc_search_metrics')) {
            return;
        }

        Schema::table('gsc_search_metrics', function (Blueprint $table): void {
            $table->index(['gsc_snapshot_id', 'dimension', 'date_start'], 'gsc_metrics_snapshot_dimension_date_index');
            $table->index(['gsc_snapshot_id', 'dimension', 'clicks'], 'gsc_metrics_snapshot_dimension_clicks_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gsc_search_metrics')) {
            return;
        }

        Schema::table('gsc_search_metrics', function (Blueprint $table): void {
            $table->dropIndex('gsc_metrics_snapshot_dimension_date_index');
            $table->dropIndex('gsc_metrics_snapshot_dimension_clicks_index');
        });
    }
};
