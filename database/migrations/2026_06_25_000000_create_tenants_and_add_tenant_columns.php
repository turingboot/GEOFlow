<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 120)->unique();
                $table->foreignId('owner_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('admins') && ! Schema::hasColumn('admins', 'tenant_id')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            });
        }

        foreach ([
            'ai_models',
            'prompts',
            'keyword_libraries',
            'title_libraries',
            'image_libraries',
            'authors',
            'categories',
            'knowledge_bases',
            'distribution_channels',
            'tasks',
            'articles',
        ] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $after = $tableName === 'articles' ? 'id' : 'id';
                    $table->foreignId('tenant_id')->nullable()->after($after)->constrained('tenants')->nullOnDelete();
                });
            }
        }

        $this->ensureDefaultTenant();
    }

    public function down(): void
    {
        foreach ([
            'articles',
            'tasks',
            'distribution_channels',
            'knowledge_bases',
            'categories',
            'authors',
            'image_libraries',
            'title_libraries',
            'keyword_libraries',
            'prompts',
            'ai_models',
            'admins',
        ] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('tenant_id');
                });
            }
        }

        Schema::dropIfExists('tenants');
    }

    private function ensureDefaultTenant(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        $defaultTenantId = DB::table('tenants')->where('slug', 'default')->value('id');
        if (! $defaultTenantId) {
            $ownerAdminId = Schema::hasTable('admins')
                ? DB::table('admins')->orderBy('id')->value('id')
                : null;

            $defaultTenantId = DB::table('tenants')->insertGetId([
                'name' => '默认租户',
                'slug' => 'default',
                'owner_admin_id' => $ownerAdminId,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'tenant_id')) {
            return;
        }

        DB::table('admins')
            ->whereNull('tenant_id')
            ->where(function ($query): void {
                $query->whereNull('role')
                    ->orWhereNotIn(DB::raw('LOWER(role)'), ['super_admin', 'superadmin']);
            })
            ->update([
                'tenant_id' => $defaultTenantId,
                'updated_at' => now(),
            ]);

        foreach ([
            'ai_models',
            'prompts',
            'keyword_libraries',
            'title_libraries',
            'image_libraries',
            'authors',
            'categories',
            'knowledge_bases',
            'distribution_channels',
            'tasks',
            'articles',
        ] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
            }
        }

    }
};
