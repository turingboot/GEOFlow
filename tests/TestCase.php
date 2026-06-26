<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

/**
 * 测试基类：Feature 测试如需数据库可在用例中 use {@see RefreshDatabase}。
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeDefaultTenantContext();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    protected function afterRefreshingDatabase()
    {
        $this->initializeDefaultTenantContext();
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        $this->initializeDefaultTenantContext();

        return $response;
    }

    public function createApplication()
    {
        $this->forceTestingDatabaseEnvironment();

        $app = parent::createApplication();

        $app->instance('env', 'testing');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.pgsql.url', null);

        return $app;
    }

    private function forceTestingDatabaseEnvironment(): void
    {
        $variables = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];

        foreach ($variables as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
    }

    protected function initializeDefaultTenantContext(): void
    {
        $this->ensureBaseTenantTestingSchema();

        if (! Schema::hasTable('tenants')) {
            TenantContext::clear();

            return;
        }

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'test-default'],
            [
                'name' => 'Test Default Tenant',
                'status' => 'active',
            ]
        );

        TenantContext::set((int) $tenant->id);
        $this->registerAdminTenantDefault();
    }

    private function registerAdminTenantDefault(): void
    {
        Admin::creating(static function (Admin $admin): void {
            if (! $admin->tenant_id && TenantContext::id()) {
                $admin->tenant_id = TenantContext::id();
            }
        });
    }

    private function ensureBaseTenantTestingSchema(): void
    {
        if ($this->app['db']->getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 120)->unique();
                $table->unsignedBigInteger('owner_admin_id')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('username', 50)->unique();
                $table->string('password');
                $table->string('email')->default('');
                $table->string('display_name')->default('');
                $table->string('role', 20)->default('admin');
                $table->string('status', 20)->default('active');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('last_login')->nullable();
                $table->string('welcome_seen_version')->nullable();
                $table->timestamp('welcome_dismissed_at')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->morphs('tokenable');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('personal_access_tokens', 'tenant_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration')->index();
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration')->index();
            });
        }

        if (! Schema::hasTable('ai_models')) {
            Schema::create('ai_models', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('version')->default('');
                $table->string('api_key', 500)->default('');
                $table->string('model_id');
                $table->string('model_type')->nullable();
                $table->string('api_url', 500)->default('');
                $table->integer('daily_limit')->default(0);
                $table->integer('used_today')->default(0);
                $table->integer('total_used')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prompts')) {
            Schema::create('prompts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('type', 50);
                $table->text('content');
                $table->text('variables')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('authors')) {
            Schema::create('authors', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->text('bio')->nullable();
                $table->string('email')->default('');
                $table->string('avatar')->default('');
                $table->string('website')->default('');
                $table->text('social_links')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('title_libraries')) {
            Schema::create('title_libraries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('title_count')->default(0);
                $table->string('generation_type', 20)->default('manual');
                $table->unsignedBigInteger('keyword_library_id')->nullable();
                $table->unsignedBigInteger('ai_model_id')->nullable();
                $table->unsignedBigInteger('prompt_id')->nullable();
                $table->integer('generation_rounds')->default(1);
                $table->integer('is_ai_generated')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('titles')) {
            Schema::create('titles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('library_id');
                $table->string('title');
                $table->string('keyword')->default('');
                $table->integer('used_count')->default(0);
                $table->integer('usage_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('image_libraries')) {
            Schema::create('image_libraries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('image_count')->default(0);
                $table->integer('used_task_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('images')) {
            Schema::create('images', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('library_id');
                $table->string('filename');
                $table->string('original_name');
                $table->string('file_name')->default('');
                $table->string('file_path')->default('');
                $table->integer('file_size')->default(0);
                $table->string('mime_type')->default('');
                $table->integer('width')->default(0);
                $table->integer('height')->default(0);
                $table->text('tags')->nullable();
                $table->integer('used_count')->default(0);
                $table->integer('usage_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('knowledge_bases')) {
            Schema::create('knowledge_bases', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->text('content')->default('');
                $table->integer('character_count')->default(0);
                $table->integer('used_task_count')->default(0);
                $table->string('file_type', 20)->default('markdown');
                $table->string('file_path', 500)->default('');
                $table->integer('word_count')->default(0);
                $table->integer('usage_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->unsignedBigInteger('title_library_id')->nullable();
                $table->unsignedBigInteger('image_library_id')->nullable();
                $table->unsignedBigInteger('knowledge_base_id')->nullable();
                $table->unsignedBigInteger('prompt_id')->nullable();
                $table->unsignedBigInteger('ai_model_id')->nullable();
                $table->unsignedBigInteger('author_id')->nullable();
                $table->integer('image_count')->default(0);
                $table->integer('need_review')->default(1);
                $table->integer('publish_interval')->default(3600);
                $table->string('author_type', 20)->default('random');
                $table->unsignedBigInteger('custom_author_id')->nullable();
                $table->integer('auto_keywords')->default(1);
                $table->integer('auto_description')->default(1);
                $table->integer('draft_limit')->default(10);
                $table->integer('article_limit')->default(10);
                $table->integer('is_loop')->default(0);
                $table->string('model_selection_mode', 20)->default('fixed');
                $table->string('status', 20)->default('active');
                $table->string('publish_scope', 40)->default('local_and_distribution');
                $table->string('distribution_strategy', 40)->default('broadcast');
                $table->integer('distribution_cursor')->default(0);
                $table->integer('created_count')->default(0);
                $table->integer('published_count')->default(0);
                $table->integer('loop_count')->default(0);
                $table->string('category_mode', 20)->default('smart');
                $table->unsignedBigInteger('fixed_category_id')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->timestamp('next_publish_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_error_at')->nullable();
                $table->text('last_error_message')->nullable();
                $table->integer('schedule_enabled')->default(1);
                $table->integer('max_retry_count')->default(3);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_runs')) {
            Schema::create('task_runs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('task_id');
                $table->string('status');
                $table->unsignedBigInteger('article_id')->nullable();
                $table->text('error_message')->nullable();
                $table->integer('duration_ms')->default(0);
                $table->json('meta')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_knowledge_bases')) {
            Schema::create('task_knowledge_bases', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('task_id');
                $table->unsignedBigInteger('knowledge_base_id');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('distribution_channels')) {
            Schema::create('distribution_channels', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('domain')->default('');
                $table->string('endpoint_url')->default('');
                $table->string('channel_type')->default('geoflow_agent');
                $table->string('status', 20)->default('active');
                $table->text('config')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_distribution_channels')) {
            Schema::create('task_distribution_channels', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('task_id');
                $table->unsignedBigInteger('distribution_channel_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('articles')) {
            Schema::create('articles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('excerpt')->nullable();
                $table->text('content');
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('author_id');
                $table->unsignedBigInteger('task_id')->nullable();
                $table->string('status', 20)->default('draft');
                $table->string('review_status', 20)->default('pending');
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('article_distributions')) {
            Schema::create('article_distributions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('article_id');
                $table->unsignedBigInteger('distribution_channel_id')->nullable();
                $table->string('action', 20)->default('publish');
                $table->string('status', 20)->default('pending');
                $table->string('idempotency_key')->default('');
                $table->text('last_error_message')->nullable();
                $table->timestamps();
            });
        }
    }
}
