<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Prompt;
use App\Models\SiteSetting;
use App\Models\Task;
use App\Models\Tenant;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAiModelsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_test_chat_model_connection(): void
    {
        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'OK']],
                ],
            ]),
        ]);

        $model = $this->createAiModel('chat');

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->postJson(route('admin.ai-models.test', ['modelId' => (int) $model->id]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.model_type', 'chat')
            ->assertJsonPath('meta.http_status', 200);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://ai.test/v1/chat/completions'
            && $request['model'] === 'test-chat-model'
            && $request->hasHeader('Authorization', 'Bearer test-api-key'));
    }

    public function test_admin_models_page_shows_test_action(): void
    {
        $this->createAiModel('chat');

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->get(route('admin.ai-models.index'));

        $response->assertOk()
            ->assertSee(__('admin.ai_models.test'));
    }

    public function test_regular_admin_cannot_manage_ai_models_from_models_page(): void
    {
        $this->createAiModel('chat');

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->get(route('admin.ai-models.index'));

        $response->assertOk()
            ->assertSee(__('admin.ai_models.test'))
            ->assertDontSee('onclick="showCreateModelModal()" class="admin-btn', false)
            ->assertDontSee("onclick='editModel", false)
            ->assertDontSee('onclick="deleteModel', false);
    }

    public function test_super_admin_can_manage_ai_models_from_models_page(): void
    {
        $this->createAiModel('chat');

        $response = $this->actingAs($this->createSuperAdmin(), 'admin')
            ->get(route('admin.ai-models.index'));

        $response->assertOk()
            ->assertSee('onclick="showCreateModelModal()" class="admin-btn', false)
            ->assertSee("onclick='editModel", false)
            ->assertSee('onclick="deleteModel', false);
    }

    public function test_regular_admin_cannot_create_update_or_delete_ai_models(): void
    {
        $admin = $this->createAdmin();
        $model = $this->createAiModel('chat');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.ai-models.store'), [
                'name' => 'Forbidden Chat',
                'version' => 'test',
                'api_key' => 'test-api-key',
                'model_id' => 'forbidden-chat',
                'model_type' => 'chat',
                'api_url' => 'https://ai.test',
                'failover_priority' => 100,
                'daily_limit' => 0,
            ])
            ->assertForbidden();

        $this->assertFalse(AiModel::query()->where('model_id', 'forbidden-chat')->exists());

        $this->actingAs($admin, 'admin')
            ->put(route('admin.ai-models.update', ['modelId' => (int) $model->id]), [
                'name' => 'Forbidden Update',
                'version' => 'test',
                'api_key' => '',
                'model_id' => 'forbidden-update',
                'model_type' => 'chat',
                'api_url' => 'https://ai.test',
                'failover_priority' => 100,
                'daily_limit' => 0,
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertSame('test-chat-model', $model->refresh()->model_id);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.ai-models.delete', ['modelId' => (int) $model->id]))
            ->assertForbidden();

        $this->assertModelExists($model);
    }

    public function test_regular_admin_can_see_shared_models_from_other_tenants(): void
    {
        $tenantOne = Tenant::query()->create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one-ai-models',
            'status' => 'active',
        ]);
        $tenantTwo = Tenant::query()->create([
            'name' => 'Tenant Two',
            'slug' => 'tenant-two-ai-models',
            'status' => 'active',
        ]);
        $admin = $this->createAdmin(['tenant_id' => (int) $tenantOne->id]);
        $sharedModel = $this->createAiModel('chat', [
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Shared Platform Chat',
            'model_id' => 'shared-platform-chat',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.ai-models.index'));

        $response->assertOk()
            ->assertSee($sharedModel->name)
            ->assertSee('shared-platform-chat');
    }

    public function test_regular_admin_model_usage_stats_are_scoped_to_current_tenant(): void
    {
        $tenantOne = Tenant::query()->create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one-model-usage',
            'status' => 'active',
        ]);
        $tenantTwo = Tenant::query()->create([
            'name' => 'Tenant Two',
            'slug' => 'tenant-two-model-usage',
            'status' => 'active',
        ]);
        $admin = $this->createAdmin([
            'username' => 'tenant_one_model_usage_admin',
            'email' => 'tenant-one-model-usage@example.com',
            'tenant_id' => (int) $tenantOne->id,
        ]);
        $model = $this->createAiModel('chat', [
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Shared Usage Chat',
            'model_id' => 'shared-usage-chat',
            'daily_limit' => 20,
            'used_today' => 999,
            'total_used' => 26,
        ]);
        $this->createAiModel('chat', [
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Other Tenant Usage Chat',
            'model_id' => 'other-tenant-usage-chat',
            'daily_limit' => 20,
            'used_today' => 999,
            'total_used' => 88,
        ]);

        $this->createTaskWithArticles($tenantOne, $model, 2, 2);
        $this->createTaskWithArticles($tenantTwo, $model, 7, 7);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.ai-models.index'));

        $response->assertOk()
            ->assertSee(__('admin.ai_models.usage_tasks', ['count' => '1']))
            ->assertSee(__('admin.ai_models.usage_articles', ['count' => '2']))
            ->assertSee(__('admin.ai_models.usage_total', ['count' => '26']))
            ->assertSee('2 / 20')
            ->assertDontSee('999 / 20')
            ->assertDontSee(__('admin.ai_models.usage_articles', ['count' => '9']))
            ->assertDontSee(__('admin.ai_models.usage_total', ['count' => '88']));
    }

    public function test_ai_configurator_usage_stats_are_scoped_to_current_tenant(): void
    {
        $tenantOne = Tenant::query()->create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one-configurator-usage',
            'status' => 'active',
        ]);
        $tenantTwo = Tenant::query()->create([
            'name' => 'Tenant Two',
            'slug' => 'tenant-two-configurator-usage',
            'status' => 'active',
        ]);
        $admin = $this->createAdmin([
            'username' => 'tenant_one_configurator_admin',
            'email' => 'tenant-one-configurator@example.com',
            'tenant_id' => (int) $tenantOne->id,
        ]);
        $model = $this->createAiModel('chat', [
            'tenant_id' => (int) $tenantOne->id,
            'used_today' => 26,
            'total_used' => 80,
        ]);
        $this->createAiModel('chat', [
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Other Tenant Configurator Chat',
            'model_id' => 'other-tenant-configurator-chat',
            'used_today' => 999,
            'total_used' => 999,
        ]);

        Prompt::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Prompt',
            'type' => 'content',
            'content' => 'Tenant one prompt',
        ]);
        Prompt::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Tenant Two Prompt',
            'type' => 'content',
            'content' => 'Tenant two prompt',
        ]);
        $this->createTaskWithArticles($tenantOne, $model, 2, 2);
        $this->createTaskWithArticles($tenantTwo, $model, 7, 7);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.ai.configurator'));

        $response->assertOk()
            ->assertSee('>1</div>', false)
            ->assertSee('>80</div>', false)
            ->assertSee('>26</div>', false)
            ->assertDontSee('>999</div>', false);
    }

    public function test_admin_models_page_works_before_max_tokens_migration_runs(): void
    {
        Schema::table('ai_models', function (Blueprint $table): void {
            $table->dropColumn('max_tokens');
        });

        $this->createAiModel('chat');

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->get(route('admin.ai-models.index'));

        $response->assertOk()
            ->assertSee(__('admin.ai_models.list_title'));
    }

    public function test_admin_saves_max_tokens_only_for_chat_models(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.ai-models.store'), [
                'name' => 'Long Form Chat',
                'version' => 'test',
                'api_key' => 'test-api-key',
                'model_id' => 'long-chat',
                'model_type' => 'chat',
                'api_url' => 'https://ai.test',
                'failover_priority' => 100,
                'daily_limit' => 0,
                'max_tokens' => 12000,
            ])
            ->assertRedirect(route('admin.ai-models.index'));

        $this->assertSame(12000, (int) AiModel::query()->where('model_id', 'long-chat')->value('max_tokens'));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.ai-models.store'), [
                'name' => 'Embedding Model',
                'version' => 'test',
                'api_key' => 'test-api-key',
                'model_id' => 'embedding-model',
                'model_type' => 'embedding',
                'api_url' => 'https://ai.test',
                'failover_priority' => 100,
                'daily_limit' => 0,
                'max_tokens' => 12000,
            ])
            ->assertRedirect(route('admin.ai-models.index'));

        $this->assertNull(AiModel::query()->where('model_id', 'embedding-model')->value('max_tokens'));
    }

    public function test_admin_can_test_embedding_model_connection(): void
    {
        Http::fake([
            'https://ai.test/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3]],
                ],
            ]),
        ]);

        $model = $this->createAiModel('embedding');

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->postJson(route('admin.ai-models.test', ['modelId' => (int) $model->id]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.model_type', 'embedding')
            ->assertJsonPath('meta.http_status', 200);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://ai.test/v1/embeddings'
            && $request['model'] === 'test-embedding-model'
            && $request['input'] === 'GEOFlow embedding connection test');
    }

    public function test_admin_can_test_volcengine_embedding_model_connection(): void
    {
        Http::fake([
            'https://ark.cn-beijing.volces.com/api/v3/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.11, 0.22, 0.33]],
                ],
            ]),
        ]);

        $model = $this->createAiModel('embedding', [
            'name' => 'Doubao Embedding',
            'model_id' => 'doubao-embedding-text-240515',
            'api_url' => 'https://ark.cn-beijing.volces.com/api/v3',
        ]);

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->postJson(route('admin.ai-models.test', ['modelId' => (int) $model->id]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.model_type', 'embedding')
            ->assertJsonPath('meta.http_status', 200);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://ark.cn-beijing.volces.com/api/v3/embeddings'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request['model'] === 'doubao-embedding-text-240515'
            && $request['input'] === 'GEOFlow embedding connection test');
    }

    public function test_admin_can_test_gemini_chat_model_connection(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'OK'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $model = $this->createAiModel('chat', [
            'name' => 'Gemini 3 Flash Preview',
            'model_id' => 'gemini-3-flash-preview',
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->postJson(route('admin.ai-models.test', ['modelId' => (int) $model->id]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.model_type', 'chat')
            ->assertJsonPath('meta.http_status', 200);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent'
            && $request->hasHeader('x-goog-api-key', 'test-api-key')
            && ($request['contents'][0]['parts'][0]['text'] ?? '') === 'Reply with OK.'
            && ($request['generationConfig']['thinkingConfig']['thinkingLevel'] ?? '') === 'minimal'
            && ($request['generationConfig']['maxOutputTokens'] ?? 0) >= 64);
    }

    public function test_admin_can_test_gemini_embedding_model_connection_with_retrieval_prefix(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:batchEmbedContents' => Http::response([
                'embeddings' => [
                    ['values' => [0.1, 0.2, 0.3]],
                ],
            ]),
        ]);

        $model = $this->createAiModel('embedding', [
            'name' => 'Gemini Embedding 2',
            'model_id' => 'gemini-embedding-2',
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
        ]);

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->postJson(route('admin.ai-models.test', ['modelId' => (int) $model->id]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.model_type', 'embedding')
            ->assertJsonPath('meta.http_status', 200);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:batchEmbedContents'
            && $request->hasHeader('x-goog-api-key', 'test-api-key')
            && ($request['requests'][0]['content']['parts'][0]['text'] ?? '') === 'task: search result | query: GEOFlow embedding connection test'
            && ! isset($request['requests'][0]['taskType'])
            && ! isset($request['taskType']));
    }

    public function test_gemini_three_pro_connection_test_uses_low_thinking_level(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-preview:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'OK'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $model = $this->createAiModel('chat', [
            'name' => 'Gemini 3 Pro Preview',
            'model_id' => 'gemini-3-pro-preview',
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->postJson(route('admin.ai-models.test', ['modelId' => (int) $model->id]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-preview:generateContent'
            && ($request['generationConfig']['thinkingConfig']['thinkingLevel'] ?? '') === 'low'
            && ($request['generationConfig']['maxOutputTokens'] ?? 0) >= 64);
    }

    public function test_admin_models_page_shows_embedding_quick_fill_presets_and_notice(): void
    {
        $response = $this->actingAs($this->createSuperAdmin(), 'admin')
            ->get(route('admin.ai-models.index'));

        $response->assertOk()
            ->assertSee('MiniMax-M3', false)
            ->assertSee('MiniMax M2.7', false)
            ->assertSee('MiniMax-M2.7-highspeed', false)
            ->assertSee('Gemini', false)
            ->assertSee('Gemini Embedding', false)
            ->assertSee('Doubao Embedding', false)
            ->assertSee('doubao-embedding-text-240515', false)
            ->assertSee(__('admin.ai_models.gemini_embedding_notice'));
    }

    public function test_admin_can_update_knowledge_chunking_config(): void
    {
        $model = $this->createAiModel('chat');

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->post(route('admin.ai-models.chunking-config'), [
                'knowledge_chunk_strategy' => 'semantic_llm',
                'knowledge_chunking_model_id' => (int) $model->id,
            ]);

        $response->assertRedirect(route('admin.ai-models.index'))
            ->assertSessionHas('message');

        $this->assertSame(
            'semantic_llm',
            (string) SiteSetting::query()->where('setting_key', 'knowledge_chunk_strategy')->value('setting_value')
        );
        $this->assertSame(
            (string) $model->id,
            (string) SiteSetting::query()->where('setting_key', 'knowledge_chunking_model_id')->value('setting_value')
        );
    }

    public function test_admin_models_page_shows_knowledge_chunking_config(): void
    {
        $model = $this->createAiModel('chat', ['name' => 'Gemini 3.1 Flash Lite']);

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->get(route('admin.ai-models.index'));

        $response->assertOk()
            ->assertSee(__('admin.ai_models.chunking_title'))
            ->assertSee(__('admin.ai_models.chunk_strategy_semantic'))
            ->assertSee('Gemini 3.1 Flash Lite');
    }

    public function test_model_connection_test_reports_provider_errors(): void
    {
        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response(['detail' => 'API Key invalid'], 401),
        ]);

        $model = $this->createAiModel('chat');

        $response = $this->actingAs($this->createAdmin(), 'admin')
            ->postJson(route('admin.ai-models.test', ['modelId' => (int) $model->id]));

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('meta.http_status', 401);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAdmin(array $overrides = []): Admin
    {
        return Admin::query()->create(array_merge([
            'username' => 'ai_model_admin',
            'password' => 'secret-123',
            'email' => 'ai-model-admin@example.com',
            'display_name' => 'AI Model Admin',
            'role' => 'admin',
            'status' => 'active',
        ], $overrides));
    }

    private function createSuperAdmin(): Admin
    {
        return $this->createAdmin([
            'username' => 'ai_model_super_admin',
            'email' => 'ai-model-super-admin@example.com',
            'display_name' => 'AI Model Super Admin',
            'role' => 'super_admin',
        ]);
    }

    private function createAiModel(string $type, array $overrides = []): AiModel
    {
        return AiModel::query()->create(array_merge([
            'name' => $type === 'embedding' ? 'Test Embedding' : 'Test Chat',
            'version' => 'test',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('test-api-key'),
            'model_id' => $type === 'embedding' ? 'test-embedding-model' : 'test-chat-model',
            'model_type' => $type,
            'api_url' => 'https://ai.test',
            'failover_priority' => 100,
            'daily_limit' => 0,
            'used_today' => 0,
            'total_used' => 0,
            'status' => 'active',
        ], $overrides));
    }

    private function createTaskWithArticles(Tenant $tenant, AiModel $model, int $createdCount, int $articleCount): void
    {
        $category = Category::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => 'Category '.$tenant->id.' '.$createdCount,
            'slug' => 'category-'.$tenant->id.'-'.$createdCount.'-'.$articleCount,
        ]);
        $author = Author::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => 'Author '.$tenant->id.' '.$createdCount,
        ]);
        $task = Task::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => 'Task '.$tenant->id.' '.$createdCount,
            'ai_model_id' => (int) $model->id,
            'created_count' => $createdCount,
            'status' => 'active',
        ]);

        for ($index = 1; $index <= $articleCount; $index++) {
            Article::query()->create([
                'tenant_id' => (int) $tenant->id,
                'title' => 'Article '.$tenant->id.' '.$createdCount.' '.$index,
                'slug' => 'article-'.$tenant->id.'-'.$createdCount.'-'.$index,
                'content' => 'Content',
                'category_id' => (int) $category->id,
                'author_id' => (int) $author->id,
                'task_id' => (int) $task->id,
                'status' => 'published',
                'review_status' => 'approved',
                'is_ai_generated' => 1,
            ]);
        }
    }
}
