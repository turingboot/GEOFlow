<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->rescopeUnique(
            'distribution_channel_secrets',
            'distribution_channel_secrets_key_id_unique',
            ['tenant_id', 'key_id'],
            'distribution_channel_secrets_tenant_key_unique'
        );

        $this->rescopeUnique(
            'article_distributions',
            'article_distributions_idempotency_key_unique',
            ['tenant_id', 'idempotency_key'],
            'article_distributions_tenant_idempotency_unique'
        );

        $this->rescopeUnique(
            'site_theme_replications',
            'site_theme_replications_theme_id_unique',
            ['tenant_id', 'theme_id'],
            'site_theme_replications_tenant_theme_unique'
        );

        $this->rescopeUnique(
            'keyword_trend_source_secrets',
            'keyword_trend_source_secrets_key_id_unique',
            ['tenant_id', 'key_id'],
            'keyword_trend_source_secrets_tenant_key_unique'
        );
    }

    public function down(): void
    {
        $this->restoreUnique(
            'keyword_trend_source_secrets',
            'keyword_trend_source_secrets_tenant_key_unique',
            ['key_id'],
            'keyword_trend_source_secrets_key_id_unique'
        );

        $this->restoreUnique(
            'site_theme_replications',
            'site_theme_replications_tenant_theme_unique',
            ['theme_id'],
            'site_theme_replications_theme_id_unique'
        );

        $this->restoreUnique(
            'article_distributions',
            'article_distributions_tenant_idempotency_unique',
            ['idempotency_key'],
            'article_distributions_idempotency_key_unique'
        );

        $this->restoreUnique(
            'distribution_channel_secrets',
            'distribution_channel_secrets_tenant_key_unique',
            ['key_id'],
            'distribution_channel_secrets_key_id_unique'
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function rescopeUnique(string $tableName, string $oldIndex, array $columns, string $newIndex): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return;
            }
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $oldIndex, $columns, $newIndex): void {
            if ($this->hasUniqueIndex($tableName, $oldIndex)) {
                $table->dropUnique($oldIndex);
            }

            if (! $this->hasUniqueIndex($tableName, $newIndex)) {
                $table->unique($columns, $newIndex);
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function restoreUnique(string $tableName, string $oldIndex, array $columns, string $newIndex): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return;
            }
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $oldIndex, $columns, $newIndex): void {
            if ($this->hasUniqueIndex($tableName, $oldIndex)) {
                $table->dropUnique($oldIndex);
            }

            if (! $this->hasUniqueIndex($tableName, $newIndex)) {
                $table->unique($columns, $newIndex);
            }
        });
    }

    private function hasUniqueIndex(string $tableName, string $indexName): bool
    {
        return Schema::hasIndex($tableName, $indexName, 'unique');
    }
};
