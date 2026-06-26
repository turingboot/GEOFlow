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
        $this->scopeApiIdempotencyUniqueKey();
    }

    public function down(): void
    {
        if (Schema::hasTable('api_idempotency_keys') && Schema::hasColumn('api_idempotency_keys', 'tenant_id')) {
            Schema::table('api_idempotency_keys', function (Blueprint $table): void {
                if ($this->hasUniqueIndex('api_idempotency_keys', 'api_idempotency_keys_tenant_key_route_unique')) {
                    $table->dropUnique('api_idempotency_keys_tenant_key_route_unique');
                }
                if (! $this->hasUniqueIndex('api_idempotency_keys', 'api_idempotency_keys_idempotency_key_route_key_unique')) {
                    $table->unique(['idempotency_key', 'route_key'], 'api_idempotency_keys_idempotency_key_route_key_unique');
                }
            });
        }

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
            'article_distributions',
            'distribution_logs',
            'task_runs',
            'article_geo_audits',
            'article_reviews',
            'article_images',
            'task_schedules',
            'distribution_channel_secrets',
            'knowledge_chunks',
            'view_logs',
            'admin_activity_logs',
            'api_idempotency_keys',
            'keywords',
            'titles',
            'images',
            'sensitive_words',
        ];
    }

    private function backfillTenantIds(): void
    {
        $defaultTenantId = $this->defaultTenantId();

        $this->backfillChildren('article_distributions', 'articles', 'article_id', $defaultTenantId);
        $this->backfillChildren('distribution_logs', 'articles', 'article_id', $defaultTenantId);
        $this->backfillChildren('distribution_logs', 'distribution_channels', 'distribution_channel_id', $defaultTenantId);
        $this->backfillChildren('distribution_logs', 'article_distributions', 'article_distribution_id', $defaultTenantId);
        $this->backfillChildren('task_runs', 'tasks', 'task_id', $defaultTenantId);
        $this->backfillChildren('article_geo_audits', 'articles', 'article_id', $defaultTenantId);
        $this->backfillChildren('article_reviews', 'articles', 'article_id', $defaultTenantId);
        $this->backfillChildren('article_images', 'articles', 'article_id', $defaultTenantId);
        $this->backfillChildren('task_schedules', 'tasks', 'task_id', $defaultTenantId);
        $this->backfillChildren('distribution_channel_secrets', 'distribution_channels', 'distribution_channel_id', $defaultTenantId);
        $this->backfillChildren('knowledge_chunks', 'knowledge_bases', 'knowledge_base_id', $defaultTenantId);
        $this->backfillChildren('view_logs', 'articles', 'article_id', $defaultTenantId);
        $this->backfillChildren('keywords', 'keyword_libraries', 'library_id', $defaultTenantId);
        $this->backfillChildren('titles', 'title_libraries', 'library_id', $defaultTenantId);
        $this->backfillChildren('images', 'image_libraries', 'library_id', $defaultTenantId);

        $this->backfillFromAdmin('admin_activity_logs', 'admin_id', $defaultTenantId);

        foreach (['api_idempotency_keys', 'sensitive_words', 'view_logs', 'admin_activity_logs'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
            }
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

    private function backfillChildren(string $childTable, string $parentTable, string $foreignKey, ?int $defaultTenantId): void
    {
        if (! Schema::hasTable($childTable) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($childTable, 'tenant_id')) {
            return;
        }

        if (Schema::hasColumn($childTable, $foreignKey) && Schema::hasColumn($parentTable, 'tenant_id')) {
            DB::table($childTable)
                ->whereNull($childTable.'.tenant_id')
                ->whereNotNull($foreignKey)
                ->update([
                    'tenant_id' => DB::raw("(select tenant_id from {$parentTable} where {$parentTable}.id = {$childTable}.{$foreignKey} and {$parentTable}.tenant_id is not null limit 1)"),
                ]);
        }

        DB::table($childTable)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
    }

    private function backfillFromAdmin(string $tableName, string $adminColumn, ?int $defaultTenantId): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasTable('admins') || ! Schema::hasColumn($tableName, 'tenant_id') || ! Schema::hasColumn($tableName, $adminColumn)) {
            return;
        }

        DB::table($tableName)
            ->whereNull($tableName.'.tenant_id')
            ->whereNotNull($adminColumn)
            ->update([
                'tenant_id' => DB::raw("(select tenant_id from admins where admins.id = {$tableName}.{$adminColumn} and admins.tenant_id is not null limit 1)"),
            ]);

        DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
    }

    private function scopeApiIdempotencyUniqueKey(): void
    {
        if (! Schema::hasTable('api_idempotency_keys') || ! Schema::hasColumn('api_idempotency_keys', 'tenant_id')) {
            return;
        }

        Schema::table('api_idempotency_keys', function (Blueprint $table): void {
            if ($this->hasUniqueIndex('api_idempotency_keys', 'api_idempotency_keys_idempotency_key_route_key_unique')) {
                $table->dropUnique('api_idempotency_keys_idempotency_key_route_key_unique');
            }
            if (! $this->hasUniqueIndex('api_idempotency_keys', 'api_idempotency_keys_tenant_key_route_unique')) {
                $table->unique(['tenant_id', 'idempotency_key', 'route_key'], 'api_idempotency_keys_tenant_key_route_unique');
            }
        });
    }

    private function hasUniqueIndex(string $tableName, string $indexName): bool
    {
        return Schema::hasIndex($tableName, $indexName, 'unique');
    }
};
