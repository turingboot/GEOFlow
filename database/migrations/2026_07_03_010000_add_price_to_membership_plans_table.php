<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('membership_plans', 'price')) {
            return;
        }

        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->decimal('price', 10, 2)->default(0)->after('knowledge_base_limit');
        });

        DB::table('membership_plans')->where('name', '入门版')->update(['price' => 99]);
        DB::table('membership_plans')->where('name', '专业版')->update(['price' => 299]);
        DB::table('membership_plans')->where('name', '商业版')->update(['price' => 999]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('membership_plans', 'price')) {
            return;
        }

        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->dropColumn('price');
        });
    }
};
