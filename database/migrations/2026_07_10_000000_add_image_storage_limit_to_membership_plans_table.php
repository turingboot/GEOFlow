<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('membership_plans', 'image_storage_limit_bytes')) {
            return;
        }

        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->unsignedBigInteger('image_storage_limit_bytes')->default(0)->after('price');
        });

        DB::table('membership_plans')->where('sort_order', 10)->update(['image_storage_limit_bytes' => 100 * 1024 * 1024]);
        DB::table('membership_plans')->where('sort_order', 20)->update(['image_storage_limit_bytes' => 1024 * 1024 * 1024]);
        DB::table('membership_plans')->where('sort_order', 30)->update(['image_storage_limit_bytes' => 5 * 1024 * 1024 * 1024]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('membership_plans', 'image_storage_limit_bytes')) {
            return;
        }

        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->dropColumn('image_storage_limit_bytes');
        });
    }
};
