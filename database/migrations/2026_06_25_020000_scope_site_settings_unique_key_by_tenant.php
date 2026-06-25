<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings') || ! Schema::hasColumn('site_settings', 'tenant_id')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table site_settings drop constraint if exists site_settings_setting_key_unique');
            DB::statement('create unique index if not exists site_settings_tenant_setting_key_unique on site_settings (tenant_id, setting_key)');

            return;
        }

        Schema::table('site_settings', function ($table): void {
            try {
                $table->dropUnique('site_settings_setting_key_unique');
            } catch (Throwable) {
                //
            }

            $table->unique(['tenant_id', 'setting_key'], 'site_settings_tenant_setting_key_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index if exists site_settings_tenant_setting_key_unique');
            DB::statement('alter table site_settings add constraint site_settings_setting_key_unique unique (setting_key)');

            return;
        }

        Schema::table('site_settings', function ($table): void {
            $table->dropUnique('site_settings_tenant_setting_key_unique');
            $table->unique('setting_key', 'site_settings_setting_key_unique');
        });
    }
};
