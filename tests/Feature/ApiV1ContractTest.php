<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\Keyword;
use App\Models\KeywordLibrary;
use App\Models\KnowledgeBase;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TitleLibrary;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * API v1 契约：鉴权、scope、登录与统一信封（SQLite 测试库依赖 {@see 2026_04_18_120002_sqlite_geoflow_minimal_for_testing}）。
 */
class ApiV1ContractTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveAdmin(string $username = 'api_test_admin', string $password = 'secret-123'): Admin
    {
        $this->ensureBaseApiSchema();

        $tenant = Tenant::query()->create([
            'name' => $username,
            'slug' => $username,
            'status' => 'active',
        ]);

        $admin = Admin::query()->create([
            'tenant_id' => (int) $tenant->id,
            'username' => $username,
            'password' => $password,
            'email' => 't@example.com',
            'display_name' => 'API Test',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $tenant->forceFill(['owner_admin_id' => (int) $admin->id])->save();

        return $admin;
    }

    /**
     * @param  list<string>  $scopes
     * @return array{plain: string}
     */
    private function createBearerToken(Admin $admin, array $scopes): array
    {
        $tokenResult = $admin->createToken('contract-test', $scopes);
        $tokenResult->accessToken->forceFill([
            'tenant_id' => (int) $admin->tenant_id,
        ])->save();

        return ['plain' => $tokenResult->plainTextToken];
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function runForAdminTenant(Admin $admin, callable $callback): mixed
    {
        return TenantContext::run((int) $admin->tenant_id, $callback);
    }

    private function ensureBaseApiSchema(): void
    {
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

        if (! Schema::hasTable('keyword_libraries')) {
            Schema::create('keyword_libraries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('keyword_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keywords')) {
            Schema::create('keywords', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('library_id');
                $table->string('keyword');
                $table->integer('used_count')->default(0);
                $table->integer('usage_count')->default(0);
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

        if (! Schema::hasTable('knowledge_chunks')) {
            Schema::create('knowledge_chunks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('knowledge_base_id');
                $table->integer('chunk_index');
                $table->text('content');
                $table->string('content_hash', 64)->default('');
                $table->integer('token_count')->default(0);
                $table->text('embedding_json')->nullable();
                $table->integer('embedding_model_id')->nullable();
                $table->integer('embedding_dimensions')->default(0);
                $table->string('embedding_provider')->default('');
                $table->text('embedding_vector')->nullable();
                $table->timestamps();
                $table->unique(['knowledge_base_id', 'chunk_index']);
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
                $table->integer('created_count')->default(0);
                $table->integer('published_count')->default(0);
                $table->integer('loop_count')->default(0);
                $table->string('category_mode', 20)->default('smart');
                $table->integer('schedule_enabled')->default(1);
                $table->string('status', 20)->default('active');
                $table->string('publish_scope', 40)->default('local_and_distribution');
                $table->string('distribution_strategy', 40)->default('broadcast');
                $table->integer('distribution_cursor')->default(0);
                $table->unsignedBigInteger('fixed_category_id')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->timestamp('next_publish_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_error_at')->nullable();
                $table->text('last_error_message')->nullable();
                $table->integer('max_retry_count')->default(3);
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
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('article_images')) {
            Schema::create('article_images', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('article_id');
                $table->unsignedBigInteger('image_id');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('article_distributions')) {
            Schema::create('article_distributions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('article_id');
                $table->unsignedBigInteger('distribution_channel_id')->nullable();
                $table->string('status', 20)->default('pending');
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

        if (! Schema::hasTable('task_schedules')) {
            Schema::create('task_schedules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('task_id');
                $table->timestamp('next_run_time')->nullable();
                $table->string('status', 20)->default('pending');
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
    }

    public function test_catalog_requires_bearer_token(): void
    {
        $this->getJson('/api/v1/catalog')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'unauthorized');
    }

    public function test_login_validation_empty_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_error_response_includes_request_id_meta(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonStructure(['meta' => ['request_id', 'timestamp']]);
    }

    public function test_login_invalid_credentials_returns_401(): void
    {
        $this->createActiveAdmin('u1', 'right-pass');

        $this->postJson('/api/v1/auth/login', [
            'username' => 'u1',
            'password' => 'wrong-pass',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_login_success_returns_token_and_admin_summary(): void
    {
        $this->createActiveAdmin('u2', 'good-pass');

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'u2',
            'password' => 'good-pass',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['token', 'scopes', 'expires_at', 'admin' => ['id', 'username', 'display_name', 'role', 'status']],
                'meta' => ['request_id', 'timestamp'],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.expires_at'));
        $this->assertContains('materials:read', $response->json('data.scopes'));
        $this->assertContains('materials:write', $response->json('data.scopes'));
    }

    public function test_login_locks_account_after_repeated_password_failures(): void
    {
        $admin = $this->createActiveAdmin('lock_me', 'right-pass');

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'username' => 'lock_me',
                'password' => 'wrong-pass',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'username' => 'lock_me',
            'password' => 'wrong-pass',
        ])
            ->assertStatus(423)
            ->assertJsonPath('error.code', 'account_locked');

        $this->assertSame('locked', $admin->fresh()->status);
    }

    public function test_catalog_forbidden_when_scope_missing(): void
    {
        $admin = $this->createActiveAdmin('u3', 'p');
        $bearer = $this->createBearerToken($admin, ['tasks:read']);

        $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->getJson('/api/v1/catalog')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_catalog_success_envelope_with_catalog_read_scope(): void
    {
        $admin = $this->createActiveAdmin('u4', 'p');
        $bearer = $this->createBearerToken($admin, ['catalog:read']);

        $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->getJson('/api/v1/catalog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'models',
                    'prompts',
                    'keyword_libraries',
                    'title_libraries',
                    'image_libraries',
                    'knowledge_bases',
                    'authors',
                    'categories',
                ],
                'meta' => ['request_id', 'timestamp'],
            ]);
    }

    public function test_materials_require_materials_scope(): void
    {
        $admin = $this->createActiveAdmin('u5', 'p');
        $bearer = $this->createBearerToken($admin, ['catalog:read']);

        $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->getJson('/api/v1/materials')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_keyword_library_material_crud_and_items(): void
    {
        $admin = $this->createActiveAdmin('u6', 'p');
        $bearer = $this->createBearerToken($admin, ['materials:read', 'materials:write']);

        $create = $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->postJson('/api/v1/materials/keyword-libraries', [
                'name' => 'API Keywords',
                'description' => 'Created from API',
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'keyword-libraries')
            ->assertJsonPath('data.item.name', 'API Keywords');

        $libraryId = (int) $create->json('data.item.id');

        $item = $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->postJson("/api/v1/materials/keyword-libraries/{$libraryId}/items", [
                'keyword' => 'geo automation',
            ]);

        $item->assertCreated()
            ->assertJsonPath('data.parent_id', $libraryId)
            ->assertJsonPath('data.item.keyword', 'geo automation');

        $this->assertDatabaseHas('keyword_libraries', ['id' => $libraryId, 'keyword_count' => 1]);
        $this->assertDatabaseHas('keywords', ['library_id' => $libraryId, 'keyword' => 'geo automation']);

        $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->getJson('/api/v1/materials/keyword-libraries')
            ->assertOk()
            ->assertJsonPath('data.type', 'keyword-libraries')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_delete_material_items_refreshes_counts(): void
    {
        $admin = $this->createActiveAdmin('u7', 'p');
        $bearer = $this->createBearerToken($admin, ['materials:read', 'materials:write']);
        [$library, $keyword] = $this->runForAdminTenant($admin, function (): array {
            $library = KeywordLibrary::query()->create([
                'name' => 'Delete Items',
                'description' => '',
                'keyword_count' => 1,
            ]);
            $keyword = Keyword::query()->create([
                'library_id' => $library->id,
                'keyword' => 'delete me',
                'used_count' => 0,
                'usage_count' => 0,
            ]);

            return [$library, $keyword];
        });

        $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->deleteJson("/api/v1/materials/keyword-libraries/{$library->id}/items", [
                'ids' => [$keyword->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 1);

        $this->assertDatabaseMissing('keywords', ['id' => $keyword->id]);
        $this->assertDatabaseHas('keyword_libraries', ['id' => $library->id, 'keyword_count' => 0]);
    }

    public function test_task_delete_api_removes_task(): void
    {
        $admin = $this->createActiveAdmin('u8', 'p');
        $bearer = $this->createBearerToken($admin, ['tasks:write']);
        $task = $this->runForAdminTenant($admin, fn (): Task => Task::query()->create([
            'name' => 'API delete task',
            'status' => 'paused',
        ]));

        $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->deleteJson("/api/v1/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.id', $task->id);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_task_create_accepts_omitted_optional_material_fields(): void
    {
        $admin = $this->createActiveAdmin('u9', 'p');
        $bearer = $this->createBearerToken($admin, ['tasks:write']);
        [$model, $prompt, $titleLibrary] = $this->runForAdminTenant($admin, fn (): array => [
            AiModel::query()->create([
                'name' => 'Task Create Model',
                'model_id' => 'task-create-model',
                'model_type' => 'chat',
                'status' => 'active',
            ]),
            Prompt::query()->create([
                'name' => 'Task Create Prompt',
                'type' => 'content',
                'content' => 'Write an article.',
            ]),
            TitleLibrary::query()->create([
                'name' => 'Task Create Titles',
                'description' => '',
                'title_count' => 0,
            ]),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->postJson('/api/v1/tasks', [
                'name' => 'API create task with optional fields omitted',
                'title_library_id' => $titleLibrary->id,
                'prompt_id' => $prompt->id,
                'ai_model_id' => $model->id,
                'status' => 'paused',
                'category_mode' => 'smart',
                'draft_limit' => 1,
                'article_limit' => 1,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'API create task with optional fields omitted')
            ->assertJsonPath('data.image_library_id', null)
            ->assertJsonPath('data.author_id', null)
            ->assertJsonPath('data.knowledge_base_id', null)
            ->assertJsonPath('data.fixed_category_id', null);

        $this->assertDatabaseHas('tasks', [
            'id' => $response->json('data.id'),
            'image_library_id' => null,
            'author_id' => null,
            'knowledge_base_id' => null,
            'fixed_category_id' => null,
        ]);
    }

    public function test_task_create_prefers_knowledge_base_ids_over_legacy_knowledge_base_id(): void
    {
        $admin = $this->createActiveAdmin('u10', 'p');
        $bearer = $this->createBearerToken($admin, ['tasks:write']);
        [$model, $prompt, $titleLibrary, $legacyKnowledgeBase, $firstKnowledgeBase, $secondKnowledgeBase] = $this->runForAdminTenant($admin, fn (): array => [
            AiModel::query()->create([
                'name' => 'Task Create Model With Knowledge',
                'model_id' => 'task-create-model-with-knowledge',
                'model_type' => 'chat',
                'status' => 'active',
            ]),
            Prompt::query()->create([
                'name' => 'Task Create Prompt With Knowledge',
                'type' => 'content',
                'content' => 'Write an article.',
            ]),
            TitleLibrary::query()->create([
                'name' => 'Task Create Titles With Knowledge',
                'description' => '',
                'title_count' => 0,
            ]),
            KnowledgeBase::query()->create([
                'name' => 'Legacy Knowledge',
                'description' => '',
                'content' => 'Legacy content',
                'file_type' => 'markdown',
                'character_count' => 14,
                'word_count' => 14,
            ]),
            KnowledgeBase::query()->create([
                'name' => 'Primary Knowledge',
                'description' => '',
                'content' => 'Primary content',
                'file_type' => 'markdown',
                'character_count' => 15,
                'word_count' => 15,
            ]),
            KnowledgeBase::query()->create([
                'name' => 'Secondary Knowledge',
                'description' => '',
                'content' => 'Secondary content',
                'file_type' => 'markdown',
                'character_count' => 17,
                'word_count' => 17,
            ]),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->postJson('/api/v1/tasks', [
                'name' => 'API create task with multiple knowledge bases',
                'title_library_id' => $titleLibrary->id,
                'prompt_id' => $prompt->id,
                'ai_model_id' => $model->id,
                'status' => 'paused',
                'category_mode' => 'smart',
                'draft_limit' => 1,
                'article_limit' => 1,
                'knowledge_base_id' => (int) $legacyKnowledgeBase->id,
                'knowledge_base_ids' => [
                    (int) $firstKnowledgeBase->id,
                    (int) $secondKnowledgeBase->id,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.knowledge_base_id', (int) $firstKnowledgeBase->id)
            ->assertJsonPath('data.knowledge_base_ids.0', (int) $firstKnowledgeBase->id)
            ->assertJsonPath('data.knowledge_base_ids.1', (int) $secondKnowledgeBase->id)
            ->assertJsonCount(2, 'data.knowledge_base_ids');

        $taskId = (int) $response->json('data.id');
        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'knowledge_base_id' => (int) $firstKnowledgeBase->id,
        ]);
        $this->assertDatabaseHas('task_knowledge_bases', [
            'task_id' => $taskId,
            'knowledge_base_id' => (int) $firstKnowledgeBase->id,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('task_knowledge_bases', [
            'task_id' => $taskId,
            'knowledge_base_id' => (int) $secondKnowledgeBase->id,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseMissing('task_knowledge_bases', [
            'task_id' => $taskId,
            'knowledge_base_id' => (int) $legacyKnowledgeBase->id,
        ]);
    }

    public function test_material_api_cannot_delete_knowledge_base_referenced_by_task_pivot(): void
    {
        $admin = $this->createActiveAdmin('u11', 'p');
        $bearer = $this->createBearerToken($admin, ['materials:write']);
        [$knowledgeBase, $task] = $this->runForAdminTenant($admin, function (): array {
            $knowledgeBase = KnowledgeBase::query()->create([
                'name' => 'API Referenced Knowledge',
                'description' => '',
                'content' => 'Referenced content',
                'file_type' => 'markdown',
                'character_count' => 18,
                'word_count' => 18,
            ]);
            $task = Task::query()->create([
                'name' => 'API task uses knowledge',
                'status' => 'paused',
                'knowledge_base_id' => null,
            ]);
            $task->knowledgeBases()->attach((int) $knowledgeBase->id, ['sort_order' => 0]);

            return [$knowledgeBase, $task];
        });

        $this->withHeader('Authorization', 'Bearer '.$bearer['plain'])
            ->deleteJson('/api/v1/materials/knowledge-bases/'.(int) $knowledgeBase->id)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'material_in_use')
            ->assertJsonPath('error.details.task_count', 1);

        $this->assertDatabaseHas('knowledge_bases', [
            'id' => (int) $knowledgeBase->id,
        ]);
    }
}
