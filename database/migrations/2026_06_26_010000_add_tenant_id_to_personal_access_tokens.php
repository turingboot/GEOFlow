<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens') || Schema::hasColumn('personal_access_tokens', 'tenant_id')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('tokenable_id')->constrained('tenants')->nullOnDelete();
        });

        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'tenant_id')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\Admin')
                ->whereNull('personal_access_tokens.tenant_id')
                ->update([
                    'tenant_id' => DB::raw('(select tenant_id from admins where admins.id = personal_access_tokens.tokenable_id and admins.tenant_id is not null limit 1)'),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('personal_access_tokens') || ! Schema::hasColumn('personal_access_tokens', 'tenant_id')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
