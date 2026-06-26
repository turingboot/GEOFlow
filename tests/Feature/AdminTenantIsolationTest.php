<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\Article;
use App\Models\ArticleDistribution;
use App\Models\ArticleGeoAudit;
use App\Models\Author;
use App\Models\Category;
use App\Models\DistributionChannel;
use App\Models\DistributionLog;
use App\Models\KeywordLibrary;
use App\Models\KeywordTrendSource;
use App\Models\KnowledgeBase;
use App\Models\Prompt;
use App\Models\SiteSetting;
use App\Models\SiteThemeReplication;
use App\Models\Task;
use App\Models\TaskRun;
use App\Models\Tenant;
use App\Models\TitleLibrary;
use App\Models\TopicPlan;
use App\Models\UrlImportJob;
use App\Services\Admin\Analytics\AnalyticsFilter;
use App\Services\Admin\Analytics\AnalyticsOverviewService;
use App\Support\Site\SiteSettingsBag;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantStoragePath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AdminTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureTenantSchema();
    }

    public function test_admin_article_list_only_shows_current_tenant_articles(): void
    {
        [$adminOne, $tenantOne] = $this->adminWithTenant('tenant_one_admin', 'tenant-one');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_admin', 'tenant-two');

        $articleOne = $this->articleForTenant($tenantOne, 'Tenant One Article', 'tenant-one-article');
        $articleTwo = $this->articleForTenant($tenantTwo, 'Tenant Two Article', 'tenant-two-article');

        $this->actingAs($adminOne, 'admin')
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee($articleOne->title)
            ->assertDontSee($articleTwo->title);
    }

    public function test_admin_cannot_open_article_from_another_tenant(): void
    {
        [$adminOne] = $this->adminWithTenant('tenant_one_editor', 'tenant-one-editor');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_editor', 'tenant-two-editor');

        $otherArticle = $this->articleForTenant($tenantTwo, 'Other Tenant Article', 'other-tenant-article');

        $this->actingAs($adminOne, 'admin')
            ->get(route('admin.articles.edit', ['articleId' => (int) $otherArticle->id]))
            ->assertNotFound();
    }

    public function test_super_admin_edits_article_with_options_scoped_to_article_tenant(): void
    {
        $superAdmin = Admin::query()->create([
            'username' => 'super_admin_editor',
            'password' => 'secret-123',
            'email' => 'super-admin-editor@example.com',
            'display_name' => 'Super Admin Editor',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        [, $tenantOne] = $this->adminWithTenant('tenant_one_resource_owner', 'tenant-one-resource-owner');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_resource_owner', 'tenant-two-resource-owner');

        $tenantOneCategory = Category::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Editable Category',
            'slug' => 'tenant-one-editable-category',
        ]);
        $tenantOneAuthor = Author::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Editable Author',
        ]);
        $tenantTwoArticle = $this->articleForTenant($tenantTwo, 'Tenant Two Editable Article', 'tenant-two-editable-article');

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.articles.edit', ['articleId' => (int) $tenantTwoArticle->id]))
            ->assertOk()
            ->assertSee('Tenant Two Editable Article')
            ->assertSee('Tenant Two Editable Article Category')
            ->assertSee('Tenant Two Editable Article Author')
            ->assertDontSee('Tenant One Editable Category')
            ->assertDontSee('Tenant One Editable Author');

        $this->actingAs($superAdmin, 'admin')
            ->put(route('admin.articles.update', ['articleId' => (int) $tenantTwoArticle->id]), [
                'title' => 'Tenant Two Editable Article',
                'excerpt' => 'excerpt',
                'content' => 'content',
                'keywords' => '',
                'meta_description' => '',
                'category_id' => (int) $tenantOneCategory->id,
                'author_id' => (int) $tenantOneAuthor->id,
                'status' => 'draft',
                'review_status' => 'pending',
            ])
            ->assertSessionHasErrors(['category_id', 'author_id']);
    }

    public function test_created_article_receives_current_tenant_id_and_rejects_foreign_author(): void
    {
        [$adminOne, $tenantOne] = $this->adminWithTenant('tenant_one_creator', 'tenant-one-creator');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_creator', 'tenant-two-creator');

        $category = Category::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Category',
            'slug' => 'tenant-one-category',
        ]);
        $foreignAuthor = Author::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Foreign Author',
        ]);
        $this->assertSame((int) $tenantTwo->id, (int) $foreignAuthor->tenant_id);

        $this->actingAs($adminOne, 'admin')
            ->post(route('admin.articles.store'), [
                'title' => 'Cross Tenant Author Test',
                'excerpt' => 'excerpt',
                'content' => 'content',
                'keywords' => '',
                'meta_description' => '',
                'category_id' => (int) $category->id,
                'author_id' => (int) $foreignAuthor->id,
                'status' => 'draft',
                'review_status' => 'pending',
            ])
            ->assertSessionHasErrors('author_id');

        $author = Author::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Author',
        ]);

        $this->actingAs($adminOne, 'admin')
            ->post(route('admin.articles.store'), [
                'title' => 'Tenant Scoped Article',
                'excerpt' => 'excerpt',
                'content' => 'content',
                'keywords' => '',
                'meta_description' => '',
                'category_id' => (int) $category->id,
                'author_id' => (int) $author->id,
                'status' => 'draft',
                'review_status' => 'pending',
            ])
            ->assertRedirect();

        $article = Article::withoutGlobalScopes()
            ->where('title', 'Tenant Scoped Article')
            ->firstOrFail();

        $this->assertSame((int) $tenantOne->id, (int) $article->tenant_id);
    }

    public function test_task_create_rejects_knowledge_base_from_another_tenant(): void
    {
        [$adminOne, $tenantOne] = $this->adminWithTenant('tenant_one_tasker', 'tenant-one-tasker');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_tasker', 'tenant-two-tasker');

        $category = Category::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Task Category',
            'slug' => 'task-category',
        ]);
        $titleLibrary = TitleLibrary::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Title Library',
        ]);
        $prompt = Prompt::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Content Prompt',
            'type' => 'content',
            'content' => 'Write content.',
        ]);
        $aiModel = AiModel::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Model',
            'model_id' => 'test-model',
            'status' => 'active',
        ]);
        $foreignKnowledgeBase = KnowledgeBase::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Foreign Knowledge',
            'content' => 'foreign',
        ]);

        $this->actingAs($adminOne, 'admin')
            ->post(route('admin.tasks.store'), [
                'task_name' => 'Tenant Task',
                'title_library_id' => (int) $titleLibrary->id,
                'prompt_id' => (int) $prompt->id,
                'ai_model_id' => (int) $aiModel->id,
                'knowledge_base_id' => (int) $foreignKnowledgeBase->id,
                'fixed_category_id' => (int) $category->id,
                'status' => 'active',
                'article_limit' => 1,
                'draft_limit' => 1,
                'publish_interval' => 1,
                'category_mode' => 'fixed',
                'model_selection_mode' => 'fixed',
                'publish_scope' => 'local_only',
            ])
            ->assertSessionHasErrors('knowledge_base_id');
    }

    public function test_public_site_only_shows_current_tenant_articles_when_tenant_context_is_set(): void
    {
        [$adminOne, $tenantOne] = $this->adminWithTenant('tenant_one_public', 'tenant-one-public');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_public', 'tenant-two-public');

        $articleOne = $this->articleForTenant($tenantOne, 'Tenant One Public Article', 'tenant-one-public-article');
        $articleTwo = $this->articleForTenant($tenantTwo, 'Tenant Two Public Article', 'tenant-two-public-article');

        $this->actingAs($adminOne, 'admin')
            ->get(route('site.home'))
            ->assertOk()
            ->assertSee($articleOne->title)
            ->assertDontSee($articleTwo->title);

        $this->actingAs($adminOne, 'admin')
            ->get(route('site.article', ['slug' => $articleOne->slug]))
            ->assertOk()
            ->assertSee($articleOne->title);

        $this->actingAs($adminOne, 'admin')
            ->get(route('site.article', ['slug' => $articleTwo->slug]))
            ->assertNotFound();
    }

    public function test_keyword_trend_sources_are_isolated_and_reject_foreign_target_library(): void
    {
        [$adminOne, $tenantOne] = $this->adminWithTenant('tenant_one_trends', 'tenant-one-trends');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_trends', 'tenant-two-trends');

        $libraryOne = KeywordLibrary::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Trend Library',
        ]);
        $libraryTwo = KeywordLibrary::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Tenant Two Trend Library',
        ]);
        $sourceOne = KeywordTrendSource::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Trend Source',
            'provider' => 'generic_http_api',
            'category' => 'A',
            'target_keyword_library_id' => (int) $libraryOne->id,
            'status' => 'active',
        ]);
        $sourceTwo = KeywordTrendSource::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Tenant Two Trend Source',
            'provider' => 'generic_http_api',
            'category' => 'B',
            'target_keyword_library_id' => (int) $libraryTwo->id,
            'status' => 'active',
        ]);

        $this->actingAs($adminOne, 'admin')
            ->get(route('admin.keyword-trends.index'))
            ->assertOk()
            ->assertSee($sourceOne->name)
            ->assertDontSee($sourceTwo->name);

        $this->actingAs($adminOne, 'admin')
            ->post(route('admin.keyword-trends.store'), [
                'name' => 'Cross Tenant Trend Source',
                'provider' => 'generic_http_api',
                'category' => 'A',
                'target_keyword_library_id' => (int) $libraryTwo->id,
            ])
            ->assertSessionHasErrors('target_keyword_library_id');
    }

    public function test_topic_plans_are_isolated_and_reject_foreign_resources(): void
    {
        [$adminOne, $tenantOne] = $this->adminWithTenant('tenant_one_topic', 'tenant-one-topic');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_topic', 'tenant-two-topic');

        $modelOne = AiModel::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Topic Model',
            'model_id' => 'topic-model-one',
            'status' => 'active',
        ]);
        $foreignModel = AiModel::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Tenant Two Topic Model',
            'model_id' => 'topic-model-two',
            'status' => 'active',
        ]);
        $planOne = TopicPlan::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Topic Plan',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'draft',
            'ai_model_id' => (int) $modelOne->id,
        ]);
        $planTwo = TopicPlan::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Tenant Two Topic Plan',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'draft',
            'ai_model_id' => (int) $foreignModel->id,
        ]);

        $this->actingAs($adminOne, 'admin')
            ->get(route('admin.topic-plans.index'))
            ->assertOk()
            ->assertSee($planOne->name)
            ->assertDontSee($planTwo->name);

        $this->actingAs($adminOne, 'admin')
            ->post(route('admin.topic-plans.store'), [
                'name' => 'Cross Tenant Topic Plan',
                'ai_model_id' => (int) $foreignModel->id,
                'target_count' => 3,
            ])
            ->assertSessionHasErrors('ai_model_id');
    }

    public function test_url_import_jobs_are_isolated(): void
    {
        [$adminOne, $tenantOne] = $this->adminWithTenant('tenant_one_url', 'tenant-one-url');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_url', 'tenant-two-url');

        $jobOne = UrlImportJob::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'url' => 'https://example.com/a',
            'normalized_url' => 'https://example.com/a',
            'source_domain' => 'example.com',
            'page_title' => 'Tenant One URL Job',
            'status' => 'queued',
            'current_step' => 'queued',
            'progress_percent' => 0,
            'options_json' => '{}',
            'result_json' => '',
            'error_message' => '',
            'created_by' => 'tenant_one_url',
        ]);
        $jobTwo = UrlImportJob::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'url' => 'https://example.org/b',
            'normalized_url' => 'https://example.org/b',
            'source_domain' => 'example.org',
            'page_title' => 'Tenant Two URL Job',
            'status' => 'queued',
            'current_step' => 'queued',
            'progress_percent' => 0,
            'options_json' => '{}',
            'result_json' => '',
            'error_message' => '',
            'created_by' => 'tenant_two_url',
        ]);

        $this->actingAs($adminOne, 'admin')
            ->get(route('admin.url-import.history'))
            ->assertOk()
            ->assertSee($jobOne->url)
            ->assertDontSee($jobTwo->url);

        $this->actingAs($adminOne, 'admin')
            ->get(route('admin.url-import.show', ['jobId' => (int) $jobTwo->id]))
            ->assertNotFound();
    }

    public function test_site_settings_cache_is_scoped_per_tenant(): void
    {
        [, $tenantOne] = $this->adminWithTenant('tenant_one_settings', 'tenant-one-settings');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_settings', 'tenant-two-settings');

        SiteSetting::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'setting_key' => 'site_name',
            'setting_value' => 'Tenant One Site',
        ]);
        SiteSetting::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'setting_key' => 'site_name',
            'setting_value' => 'Tenant Two Site',
        ]);
        SiteSettingsBag::forget();

        $nameOne = TenantContext::run((int) $tenantOne->id, fn (): string => SiteSettingsBag::get('site_name'));
        $nameTwo = TenantContext::run((int) $tenantTwo->id, fn (): string => SiteSettingsBag::get('site_name'));

        $this->assertSame('Tenant One Site', $nameOne);
        $this->assertSame('Tenant Two Site', $nameTwo);
    }

    public function test_site_theme_replications_are_isolated_and_reject_foreign_ai_model(): void
    {
        [$adminOne, $tenantOne] = $this->adminWithTenant('tenant_one_theme', 'tenant-one-theme');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_theme', 'tenant-two-theme');

        $modelOne = AiModel::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Theme Model',
            'model_id' => 'theme-model-one',
            'model_type' => 'chat',
            'status' => 'active',
        ]);
        $foreignModel = AiModel::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Tenant Two Theme Model',
            'model_id' => 'theme-model-two',
            'model_type' => 'chat',
            'status' => 'active',
        ]);
        $replication = SiteThemeReplication::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Tenant Two Theme Replication',
            'theme_id' => 'tenant-two-theme-copy',
            'ai_model_id' => (int) $foreignModel->id,
            'status' => SiteThemeReplication::STATUS_QUEUED,
            'home_url' => 'https://example.com',
            'category_url' => 'https://example.com/category',
            'article_url' => 'https://example.com/article',
            'style_preference' => 'content_site',
            'compliance_status' => 'pending',
        ]);

        $this->actingAs($adminOne, 'admin')
            ->get(route('admin.site-settings.theme-replications.show', ['replicationId' => (int) $replication->id]))
            ->assertNotFound();

        $this->actingAs($adminOne, 'admin')
            ->post(route('admin.site-settings.theme-replications.store'), [
                'name' => 'Cross Tenant Theme',
                'theme_id' => 'cross-tenant-theme',
                'ai_model_id' => (int) $foreignModel->id,
                'home_url' => 'https://example.com',
                'category_url' => 'https://example.com/category',
                'article_url' => 'https://example.com/article',
                'style_preference' => 'content_site',
                'compliance_ack' => '1',
            ])
            ->assertSessionHasErrors('ai_model_id');

        $this->actingAs($adminOne, 'admin')
            ->post(route('admin.site-settings.theme-replications.store'), [
                'name' => 'Tenant One Theme',
                'theme_id' => 'tenant-one-theme-copy',
                'ai_model_id' => (int) $modelOne->id,
                'home_url' => 'https://example.com',
                'category_url' => 'https://example.com/category',
                'article_url' => 'https://example.com/article',
                'style_preference' => 'content_site',
                'compliance_ack' => '1',
            ])
            ->assertRedirect();
    }

    public function test_distribution_jobs_and_logs_are_isolated_by_tenant(): void
    {
        [$adminOne, $tenantOne] = $this->adminWithTenant('tenant_one_distribution', 'tenant-one-distribution');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_distribution', 'tenant-two-distribution');

        $articleOne = $this->articleForTenant($tenantOne, 'Tenant One Distribution', 'tenant-one-distribution');
        $articleTwo = $this->articleForTenant($tenantTwo, 'Tenant Two Distribution', 'tenant-two-distribution');
        $channelOne = DistributionChannel::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Channel',
            'domain' => 'tenant-one.example.test',
            'endpoint_url' => 'https://tenant-one.example.test',
            'status' => 'active',
        ]);
        $channelTwo = DistributionChannel::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Tenant Two Channel',
            'domain' => 'tenant-two.example.test',
            'endpoint_url' => 'https://tenant-two.example.test',
            'status' => 'active',
        ]);

        $visibleDistribution = ArticleDistribution::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'article_id' => (int) $articleOne->id,
            'distribution_channel_id' => (int) $channelOne->id,
            'action' => 'publish',
            'status' => 'failed',
            'idempotency_key' => 'tenant-one-distribution',
        ]);
        $hiddenDistribution = ArticleDistribution::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'article_id' => (int) $articleTwo->id,
            'distribution_channel_id' => (int) $channelTwo->id,
            'action' => 'publish',
            'status' => 'failed',
            'idempotency_key' => 'tenant-two-distribution',
        ]);
        DistributionLog::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'distribution_channel_id' => (int) $channelOne->id,
            'article_distribution_id' => (int) $visibleDistribution->id,
            'article_id' => (int) $articleOne->id,
            'level' => 'info',
            'message' => 'tenant one log',
            'created_at' => now(),
        ]);
        DistributionLog::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'distribution_channel_id' => (int) $channelTwo->id,
            'article_distribution_id' => (int) $hiddenDistribution->id,
            'article_id' => (int) $articleTwo->id,
            'level' => 'info',
            'message' => 'tenant two log',
            'created_at' => now(),
        ]);

        $this->actingAs($adminOne, 'admin')
            ->get(route('admin.distribution.index'))
            ->assertOk()
            ->assertSee('Tenant One Channel')
            ->assertSee('tenant one log')
            ->assertDontSee('Tenant Two Channel')
            ->assertDontSee('tenant two log');

        $this->actingAs($adminOne, 'admin')
            ->post(route('admin.distribution.retry', ['distributionId' => (int) $hiddenDistribution->id]))
            ->assertSessionHasErrors();
    }

    public function test_geo_audits_and_task_runs_are_isolated_by_tenant(): void
    {
        [$adminOne, $tenantOne] = $this->adminWithTenant('tenant_one_audit', 'tenant-one-audit');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_audit', 'tenant-two-audit');

        $articleOne = $this->articleForTenant($tenantOne, 'Tenant One Audit', 'tenant-one-audit');
        $articleTwo = $this->articleForTenant($tenantTwo, 'Tenant Two Audit', 'tenant-two-audit');
        $taskFixturesOne = $this->taskFixturesForTenant($tenantOne);
        $taskFixturesTwo = $this->taskFixturesForTenant($tenantTwo);
        ArticleGeoAudit::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'article_id' => (int) $articleOne->id,
            'geo_score' => 91,
            'gate_decision' => ArticleGeoAudit::GATE_AUTO_APPROVED,
            'audited_at' => now(),
        ]);
        ArticleGeoAudit::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'article_id' => (int) $articleTwo->id,
            'geo_score' => 11,
            'gate_decision' => ArticleGeoAudit::GATE_TO_REVIEW,
            'audited_at' => now(),
        ]);
        $taskOne = Task::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'name' => 'Tenant One Task',
            'title_library_id' => (int) $taskFixturesOne['title_library']->id,
            'prompt_id' => (int) $taskFixturesOne['prompt']->id,
            'ai_model_id' => (int) $taskFixturesOne['ai_model']->id,
            'status' => 'active',
        ]);
        $taskTwo = Task::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'name' => 'Tenant Two Task',
            'title_library_id' => (int) $taskFixturesTwo['title_library']->id,
            'prompt_id' => (int) $taskFixturesTwo['prompt']->id,
            'ai_model_id' => (int) $taskFixturesTwo['ai_model']->id,
            'status' => 'active',
        ]);
        TaskRun::query()->create([
            'tenant_id' => (int) $tenantOne->id,
            'task_id' => (int) $taskOne->id,
            'status' => 'failed',
            'error_message' => 'tenant one failure',
        ]);
        TaskRun::query()->create([
            'tenant_id' => (int) $tenantTwo->id,
            'task_id' => (int) $taskTwo->id,
            'status' => 'failed',
            'error_message' => 'tenant two failure',
        ]);

        $this->actingAs($adminOne, 'admin')
            ->get(route('admin.geo-audits.index'))
            ->assertOk()
            ->assertSee('Tenant One Audit')
            ->assertDontSee('Tenant Two Audit');

        $taskHealth = TenantContext::run(
            (int) $tenantOne->id,
            fn (): array => app(AnalyticsOverviewService::class)->taskHealth(AnalyticsFilter::fromRequest([]))
        );
        $failureMessages = collect($taskHealth['recent_failures'])
            ->pluck('error_message')
            ->all();

        $this->assertContains('tenant one failure', $failureMessages);
        $this->assertNotContains('tenant two failure', $failureMessages);
    }

    public function test_api_token_creation_persists_selected_tenant_binding(): void
    {
        $superAdmin = Admin::query()->create([
            'username' => 'super_admin_api_token',
            'password' => 'secret-123',
            'email' => 'super-admin-api-token@example.com',
            'display_name' => 'Super Admin API Token',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        [, $tenant] = $this->adminWithTenant('tenant_api_owner', 'tenant-api-owner');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.api-tokens.store'), [
                'name' => 'Tenant Bound Token',
                'tenant_id' => (int) $tenant->id,
                'scopes' => ['tasks:read'],
                'expires_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('admin.api-tokens.index'));

        $token = PersonalAccessToken::query()
            ->where('name', 'Tenant Bound Token')
            ->firstOrFail();

        $this->assertSame((int) $tenant->id, (int) $token->tenant_id);
        $this->assertSame((int) $tenant->owner_admin_id, (int) $token->tokenable_id);
    }

    public function test_tenant_storage_paths_are_namespaced_by_current_tenant(): void
    {
        [, $tenantOne] = $this->adminWithTenant('tenant_one_storage', 'tenant-one-storage');
        [, $tenantTwo] = $this->adminWithTenant('tenant_two_storage', 'tenant-two-storage');

        $pathOne = TenantContext::run((int) $tenantOne->id, fn (): string => TenantStoragePath::prefix('uploads/images/2026/06'));
        $pathTwo = TenantContext::run((int) $tenantTwo->id, fn (): string => TenantStoragePath::prefix('uploads/images/2026/06'));

        $this->assertSame('tenants/'.(int) $tenantOne->id.'/uploads/images/2026/06', $pathOne);
        $this->assertSame('tenants/'.(int) $tenantTwo->id.'/uploads/images/2026/06', $pathTwo);
        $this->assertNotSame($pathOne, $pathTwo);
    }

    /**
     * @return array{0: Admin, 1: Tenant}
     */
    private function adminWithTenant(string $username, string $slug): array
    {
        $tenant = Tenant::query()->create([
            'name' => $username,
            'slug' => $slug,
            'status' => 'active',
        ]);

        $admin = Admin::query()->create([
            'tenant_id' => (int) $tenant->id,
            'username' => $username,
            'password' => 'secret-123',
            'email' => $username.'@example.com',
            'display_name' => $username,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $tenant->forceFill(['owner_admin_id' => (int) $admin->id])->save();

        return [$admin, $tenant];
    }

    private function ensureTenantSchema(): void
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
                $table->integer('expiration');
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
                $table->integer('failover_priority')->default(100);
                $table->integer('daily_limit')->default(0);
                $table->integer('used_today')->default(0);
                $table->integer('total_used')->default(0);
                $table->integer('max_tokens')->default(0);
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
                $table->timestamp('created_at')->nullable();
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
                $table->integer('draft_limit')->default(10);
                $table->integer('article_limit')->default(10);
                $table->integer('created_count')->default(0);
                $table->integer('published_count')->default(0);
                $table->integer('schedule_enabled')->default(1);
                $table->string('status', 20)->default('active');
                $table->string('publish_scope', 40)->default('local_and_distribution');
                $table->string('distribution_strategy', 40)->default('broadcast');
                $table->unsignedBigInteger('fixed_category_id')->nullable();
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
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('distribution_channel_secrets')) {
            Schema::create('distribution_channel_secrets', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('distribution_channel_id');
                $table->string('key_id');
                $table->text('secret_hash');
                $table->text('secret_ciphertext');
                $table->string('status')->default('active');
                $table->json('scopes')->nullable();
                $table->timestamp('last_used_at')->nullable();
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

        if (! Schema::hasTable('keyword_trend_sources')) {
            Schema::create('keyword_trend_sources', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('provider');
                $table->string('category');
                $table->json('seed_keywords')->nullable();
                $table->string('region')->nullable();
                $table->string('language')->nullable();
                $table->string('timeframe')->nullable();
                $table->integer('heat_threshold')->nullable();
                $table->integer('top_n')->nullable();
                $table->unsignedBigInteger('target_keyword_library_id')->nullable();
                $table->boolean('auto_import')->default(false);
                $table->boolean('ai_relevance')->default(false);
                $table->string('schedule')->nullable();
                $table->string('status')->default('active');
                $table->json('config')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamp('last_fetched_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keyword_trend_source_secrets')) {
            Schema::create('keyword_trend_source_secrets', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('keyword_trend_source_id');
                $table->string('key_id');
                $table->text('secret_ciphertext');
                $table->string('status')->default('active');
                $table->json('scopes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keyword_trend_snapshots')) {
            Schema::create('keyword_trend_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('keyword_trend_source_id');
                $table->string('status')->default('running');
                $table->integer('fetched_count')->default(0);
                $table->integer('kept_count')->default(0);
                $table->integer('imported_count')->default(0);
                $table->json('stats')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('ran_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keyword_trends')) {
            Schema::create('keyword_trends', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('keyword_trend_snapshot_id');
                $table->unsignedBigInteger('keyword_trend_source_id');
                $table->string('keyword');
                $table->integer('heat')->default(0);
                $table->integer('search_volume')->nullable();
                $table->string('trend_direction')->nullable();
                $table->integer('delta')->nullable();
                $table->string('region')->nullable();
                $table->string('language')->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->json('raw')->nullable();
                $table->boolean('imported')->default(false);
                $table->unsignedBigInteger('keyword_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('topic_plans')) {
            Schema::create('topic_plans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->string('status')->default('draft');
                $table->json('source_summary')->nullable();
                $table->unsignedBigInteger('ai_model_id')->nullable();
                $table->unsignedBigInteger('target_title_library_id')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('topic_plan_items')) {
            Schema::create('topic_plan_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('topic_plan_id');
                $table->string('title');
                $table->string('status')->default('draft');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('url_import_jobs')) {
            Schema::create('url_import_jobs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('url', 2048);
                $table->string('normalized_url', 2048);
                $table->string('source_domain');
                $table->string('page_title')->default('');
                $table->string('status')->default('queued');
                $table->string('current_step')->default('queued');
                $table->integer('progress_percent')->default(0);
                $table->longText('options_json')->default('');
                $table->longText('result_json')->default('');
                $table->text('error_message')->default('');
                $table->string('created_by')->default('');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('url_import_job_logs')) {
            Schema::create('url_import_job_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('job_id');
                $table->string('step')->default('');
                $table->string('level')->default('info');
                $table->text('message')->default('');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('setting_key');
                $table->longText('setting_value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_theme_replications')) {
            Schema::create('site_theme_replications', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('theme_id');
                $table->string('base_theme_id')->nullable();
                $table->unsignedBigInteger('ai_model_id')->nullable();
                $table->string('status')->default('queued');
                $table->string('home_url', 500)->default('');
                $table->string('category_url', 500)->default('');
                $table->string('article_url', 500)->default('');
                $table->string('style_preference')->default('content_site');
                $table->json('source_fingerprints')->nullable();
                $table->json('analysis_json')->nullable();
                $table->json('generated_files_json')->nullable();
                $table->json('preview_snapshot_json')->nullable();
                $table->integer('current_version')->default(0);
                $table->string('compliance_status')->default('pending');
                $table->json('compliance_report_json')->nullable();
                $table->integer('iteration_count')->default(0);
                $table->text('error_message')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_theme_replication_logs')) {
            Schema::create('site_theme_replication_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('replication_id');
                $table->string('level');
                $table->string('step');
                $table->text('message');
                $table->json('context_json')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_theme_replication_versions')) {
            Schema::create('site_theme_replication_versions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('replication_id');
                $table->integer('version');
                $table->string('prompt_hash')->default('');
                $table->text('feedback')->nullable();
                $table->json('blueprint_json')->nullable();
                $table->json('files_json')->nullable();
                $table->json('compliance_report_json')->nullable();
                $table->string('draft_views_path')->default('');
                $table->string('draft_assets_path')->default('');
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
                $table->string('original_keyword')->default('');
                $table->text('keywords')->nullable();
                $table->text('meta_description')->nullable();
                $table->string('status', 20)->default('draft');
                $table->string('review_status', 20)->default('pending');
                $table->integer('view_count')->default(0);
                $table->integer('is_ai_generated')->default(0);
                $table->boolean('is_hot')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->timestamps();
                $table->timestamp('published_at')->nullable();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('article_distributions')) {
            Schema::create('article_distributions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('article_id');
                $table->unsignedBigInteger('distribution_channel_id');
                $table->string('action', 20)->default('publish');
                $table->string('status', 20)->default('queued');
                $table->string('remote_url')->nullable();
                $table->string('idempotency_key')->default('');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('distribution_logs')) {
            Schema::create('distribution_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('distribution_channel_id')->nullable();
                $table->unsignedBigInteger('article_distribution_id')->nullable();
                $table->unsignedBigInteger('article_id')->nullable();
                $table->string('level')->default('info');
                $table->string('event')->nullable();
                $table->text('message');
                $table->json('context')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('article_geo_audits')) {
            Schema::create('article_geo_audits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('article_id');
                $table->integer('geo_score')->default(0);
                $table->integer('title_keyword_match')->default(0);
                $table->integer('structure_score')->default(0);
                $table->integer('kb_coverage')->default(0);
                $table->integer('dup_ratio')->default(0);
                $table->integer('word_count')->default(0);
                $table->string('gate_decision')->default('passthrough');
                $table->text('suggestion')->nullable();
                $table->json('risk_notes')->nullable();
                $table->json('details')->nullable();
                $table->unsignedBigInteger('ai_model_id')->nullable();
                $table->timestamp('audited_at')->nullable();
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

        foreach (['admins', 'ai_models', 'prompts', 'keyword_libraries', 'title_libraries', 'image_libraries', 'authors', 'categories', 'knowledge_bases', 'distribution_channels', 'tasks', 'articles', 'article_distributions', 'distribution_logs', 'article_geo_audits', 'task_runs', 'site_settings', 'keyword_trend_sources', 'topic_plans', 'topic_plan_items', 'url_import_jobs', 'url_import_job_logs', 'site_theme_replications', 'site_theme_replication_logs', 'site_theme_replication_versions'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->unsignedBigInteger('tenant_id')->nullable()->index();
                });
            }
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
    }

    private function articleForTenant(Tenant $tenant, string $title, string $slug): Article
    {
        $category = Category::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => $title.' Category',
            'slug' => $slug.'-category',
        ]);
        $author = Author::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => $title.' Author',
        ]);

        return Article::query()->create([
            'tenant_id' => (int) $tenant->id,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => 'excerpt',
            'content' => 'content',
            'category_id' => (int) $category->id,
            'author_id' => (int) $author->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);
    }

    /**
     * @return array{title_library: TitleLibrary, prompt: Prompt, ai_model: AiModel}
     */
    private function taskFixturesForTenant(Tenant $tenant): array
    {
        return [
            'title_library' => TitleLibrary::query()->create([
                'tenant_id' => (int) $tenant->id,
                'name' => 'Task Title Library '.$tenant->id,
            ]),
            'prompt' => Prompt::query()->create([
                'tenant_id' => (int) $tenant->id,
                'name' => 'Task Prompt '.$tenant->id,
                'type' => 'content',
                'content' => 'Write content.',
            ]),
            'ai_model' => AiModel::query()->create([
                'tenant_id' => (int) $tenant->id,
                'name' => 'Task Model '.$tenant->id,
                'model_id' => 'task-model-'.$tenant->id,
                'status' => 'active',
            ]),
        ];
    }
}
