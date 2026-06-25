<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tenantTables() as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                });
            }
        }

        $this->backfillTenantIds();
    }

    public function down(): void
    {
        foreach (array_reverse($this->tenantTables()) as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('tenant_id');
                });
            }
        }
    }

    /**
     * @return list<string>
     */
    private function tenantTables(): array
    {
        return [
            'site_settings',
            'keyword_trend_sources',
            'keyword_trend_source_secrets',
            'keyword_trend_snapshots',
            'keyword_trends',
            'topic_plans',
            'topic_plan_items',
            'url_import_jobs',
            'url_import_job_logs',
            'site_theme_replications',
            'site_theme_replication_logs',
            'site_theme_replication_versions',
        ];
    }

    private function backfillTenantIds(): void
    {
        $defaultTenantId = $this->defaultTenantId();

        $this->backfillFromAdmin('keyword_trend_sources', 'created_by_admin_id', $defaultTenantId);
        $this->backfillFromAdmin('topic_plans', 'created_by_admin_id', $defaultTenantId);
        $this->backfillFromAdmin('site_theme_replications', 'created_by_admin_id', $defaultTenantId);

        $this->backfillChildren('keyword_trend_source_secrets', 'keyword_trend_sources', 'keyword_trend_source_id', $defaultTenantId);
        $this->backfillChildren('keyword_trend_snapshots', 'keyword_trend_sources', 'keyword_trend_source_id', $defaultTenantId);
        $this->backfillChildren('keyword_trends', 'keyword_trend_sources', 'keyword_trend_source_id', $defaultTenantId);
        $this->backfillChildren('topic_plan_items', 'topic_plans', 'topic_plan_id', $defaultTenantId);
        $this->backfillChildren('url_import_job_logs', 'url_import_jobs', 'job_id', $defaultTenantId);
        $this->backfillChildren('site_theme_replication_logs', 'site_theme_replications', 'replication_id', $defaultTenantId);
        $this->backfillChildren('site_theme_replication_versions', 'site_theme_replications', 'replication_id', $defaultTenantId);

        if (Schema::hasTable('url_import_jobs') && Schema::hasColumn('url_import_jobs', 'tenant_id')) {
            DB::table('url_import_jobs')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
        }

        if (Schema::hasTable('site_settings') && Schema::hasColumn('site_settings', 'tenant_id')) {
            DB::table('site_settings')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
        }
    }

    private function defaultTenantId(): ?int
    {
        if (! Schema::hasTable('tenants')) {
            return null;
        }

        return DB::table('tenants')->where('slug', 'default')->value('id')
            ?: DB::table('tenants')->orderBy('id')->value('id');
    }

    private function backfillFromAdmin(string $tableName, string $adminColumn, ?int $defaultTenantId): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'tenant_id')) {
            return;
        }

        if (Schema::hasTable('admins') && Schema::hasColumn($tableName, $adminColumn)) {
            DB::table($tableName)
                ->whereNull($tableName.'.tenant_id')
                ->whereNotNull($adminColumn)
                ->update([
                    'tenant_id' => DB::raw("(select tenant_id from admins where admins.id = {$tableName}.{$adminColumn} and admins.tenant_id is not null limit 1)"),
                ]);
        }

        DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
    }

    private function backfillChildren(string $childTable, string $parentTable, string $foreignKey, ?int $defaultTenantId): void
    {
        if (! Schema::hasTable($childTable) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($childTable, 'tenant_id')) {
            return;
        }

        DB::table($childTable)
            ->whereNull($childTable.'.tenant_id')
            ->whereNotNull($foreignKey)
            ->update([
                'tenant_id' => DB::raw("(select tenant_id from {$parentTable} where {$parentTable}.id = {$childTable}.{$foreignKey} and {$parentTable}.tenant_id is not null limit 1)"),
            ]);

        DB::table($childTable)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
    }
};
