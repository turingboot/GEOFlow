<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\OptimizeArticleGeoJob;
use App\Models\Admin;
use App\Models\Article;
use App\Models\ArticleGeoAudit;
use App\Models\Author;
use App\Models\Category;
use App\Models\DistributionChannel;
use App\Models\Task;
use App\Services\GeoFlow\DistributionOrchestrator;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ArticleWorkflow;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * 文章管理页（按 bak/admin/articles.php 行为迁移）：
 * - GET 展示列表、筛选、统计与批量操作区
 * - POST 处理批量状态/审核更新与批量删除
 * - create/edit 共用同一 Blade 表单页
 */
class ArticleController extends Controller
{
    public function __construct(private readonly DistributionOrchestrator $distributionOrchestrator) {}

    /**
     * 文章管理首页：渲染筛选与列表。
     */
    public function index(Request $request): View
    {
        $filters = $this->buildFilters($request);
        $articles = $this->queryArticles($filters);
        $isTrashView = (bool) ($filters['trashed'] ?? false);

        return view('admin.articles.index', [
            'pageTitle' => $isTrashView
                ? __('admin.articles.trash.title')
                : __('admin.articles.page_title'),
            'activeMenu' => 'articles',
            'adminSiteName' => AdminWeb::siteName(),
            'articles' => $articles,
            'stats' => $isTrashView ? $this->loadTrashStats() : $this->loadStats(),
            'filters' => $filters,
            'tasks' => $this->loadTaskOptions(),
            'authors' => $this->loadAuthorOptions(),
            'distributionChannels' => $this->loadDistributionChannelOptions(),
            'articlesI18n' => $this->articlesI18n(),
            'isTrashView' => $isTrashView,
            'trashI18n' => $this->trashI18n(),
            'articleBatchRoutes' => $this->articleBatchRoutes($isTrashView),
        ]);
    }

    /**
     * 批量更新发布状态。
     */
    public function batchUpdateStatus(Request $request): RedirectResponse
    {
        $articleIds = $this->extractArticleIds($request);
        if (empty($articleIds)) {
            return back()->withErrors(__('admin.articles.message.select_articles'));
        }

        try {
            return $this->handleBatchUpdateStatus($request, $articleIds);
        } catch (Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * 批量更新审核状态。
     */
    public function batchUpdateReview(Request $request): RedirectResponse
    {
        $articleIds = $this->extractArticleIds($request);
        if (empty($articleIds)) {
            return back()->withErrors(__('admin.articles.message.select_articles'));
        }

        try {
            return $this->handleBatchUpdateReview($request, $articleIds);
        } catch (Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * 批量删除文章。
     */
    public function batchDelete(Request $request): RedirectResponse
    {
        $articleIds = $this->extractArticleIds($request);
        if (empty($articleIds)) {
            return back()->withErrors(__('admin.articles.message.select_articles'));
        }

        try {
            return $this->handleBatchDelete($articleIds);
        } catch (Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * 批量恢复已软删除的文章。
     */
    public function batchRestore(Request $request): RedirectResponse
    {
        $articleIds = $this->extractArticleIds($request);
        if (empty($articleIds)) {
            return back()->withErrors(__('admin.articles.message.select_articles'));
        }

        try {
            $count = Article::onlyTrashed()->whereIn('id', $articleIds)->restore();

            return back()->with('message', __('admin.articles.trash.message.restore_success', ['count' => $count]));
        } catch (Throwable $e) {
            return back()->withErrors(__('admin.articles.trash.message.restore_failed'));
        }
    }

    /**
     * 批量永久删除（垃圾箱内）。
     */
    public function batchForceDelete(Request $request): RedirectResponse
    {
        $articleIds = $this->extractArticleIds($request);
        if (empty($articleIds)) {
            return back()->withErrors(__('admin.articles.message.select_articles'));
        }

        try {
            $models = Article::onlyTrashed()->whereIn('id', $articleIds)->get();
            $models->each(function (Article $article): void {
                $article->forceDelete();
            });

            return back()->with('message', __('admin.articles.trash.message.delete_success', ['count' => $models->count()]));
        } catch (Throwable $e) {
            return back()->withErrors(__('admin.articles.trash.message.delete_failed', ['message' => $e->getMessage()]));
        }
    }

    /**
     * 清空文章垃圾箱（全部永久删除）。
     */
    public function emptyTrash(): RedirectResponse
    {
        try {
            $models = Article::onlyTrashed()->get();
            if ($models->isEmpty()) {
                return back()->with('message', __('admin.articles.trash.message.empty_already'));
            }
            $total = $models->count();
            $models->each(function (Article $article): void {
                $article->forceDelete();
            });

            return back()->with('message', __('admin.articles.trash.message.empty_success', ['count' => $total]));
        } catch (Throwable $e) {
            return back()->withErrors(__('admin.articles.trash.message.empty_failed', ['message' => $e->getMessage()]));
        }
    }

    /**
     * 恢复单篇已删除文章。
     */
    public function restore(int $articleId): RedirectResponse
    {
        $article = Article::onlyTrashed()->whereKey($articleId)->firstOrFail();
        $article->restore();

        return back()->with('message', __('admin.articles.trash.message.restore_success', ['count' => 1]));
    }

    /**
     * 永久删除单篇已删除文章。
     */
    public function forceDelete(int $articleId): RedirectResponse
    {
        $article = Article::onlyTrashed()->whereKey($articleId)->firstOrFail();
        $article->forceDelete();

        return back()->with('message', __('admin.articles.trash.message.delete_success', ['count' => 1]));
    }

    /**
     * 文章创建页：与编辑页共用一个 Blade 模板。
     */
    public function create(): View
    {
        return view('admin.articles.form', [
            'pageTitle' => __('admin.article_create.page_title'),
            'activeMenu' => 'articles',
            'adminSiteName' => AdminWeb::siteName(),
            'isEdit' => false,
            'articleId' => null,
            'articleForm' => null,
            'formOptions' => $this->loadFormOptions(),
        ]);
    }

    /**
     * 创建文章：手动写入内容并按统一工作流校正状态。
     */
    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validateArticleForm($request, false);
        $tenantId = $this->resolveArticleTenantId($payload);
        $workflowState = ArticleWorkflow::normalizeState(
            $payload['status'],
            $payload['review_status']
        );

        try {
            $article = Article::query()->create([
                'tenant_id' => $tenantId,
                'title' => $payload['title'],
                'slug' => ArticleWorkflow::generateUniqueSlug($payload['title']),
                'content' => $payload['content'],
                'excerpt' => $payload['excerpt'] !== '' ? $payload['excerpt'] : mb_substr(strip_tags($payload['content']), 0, 200, 'UTF-8'),
                'keywords' => $payload['keywords'],
                'meta_description' => $payload['meta_description'],
                'category_id' => (int) $payload['category_id'],
                'author_id' => (int) $payload['author_id'],
                'status' => $workflowState['status'],
                'review_status' => $workflowState['review_status'],
                'published_at' => $workflowState['published_at'],
                'is_ai_generated' => 0,
                'is_hot' => (bool) ($payload['is_hot'] ?? false),
                'is_featured' => (bool) ($payload['is_featured'] ?? false),
            ]);
            if ($workflowState['status'] === 'published') {
                $this->distributionOrchestrator->enqueueForArticle($article);
            }
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(__('admin.article_create.error.create_exception', ['message' => $e->getMessage()]));
        }

        return redirect()
            ->route('admin.articles.edit', ['articleId' => (int) $article->id])
            ->with('message', __('admin.button.create_article'));
    }

    /**
     * 文章编辑页：复用创建页模板并回填现有数据。
     */
    public function edit(int $articleId): View|RedirectResponse
    {
        $article = Article::query()
            ->with(['task:id,name', 'author:id,name', 'category:id,name', 'latestGeoAudit'])
            ->whereKey($articleId)
            ->firstOrFail();

        return view('admin.articles.form', [
            'pageTitle' => __('admin.article_edit.page_title'),
            'activeMenu' => 'articles',
            'adminSiteName' => AdminWeb::siteName(),
            'isEdit' => true,
            'articleId' => $articleId,
            'articleForm' => [
                'title' => (string) $article->title,
                'excerpt' => (string) ($article->excerpt ?? ''),
                'content' => (string) $article->content,
                'keywords' => (string) ($article->keywords ?? ''),
                'meta_description' => (string) ($article->meta_description ?? ''),
                'status' => (string) $article->status,
                'review_status' => (string) $article->review_status,
                'category_id' => (string) $article->category_id,
                'author_id' => (string) $article->author_id,
                'slug' => (string) $article->slug,
                'published_at' => $article->published_at?->format('Y-m-d H:i:s'),
                'task_name' => (string) ($article->task->name ?? ''),
                'is_hot' => (bool) ($article->is_hot ?? false),
                'is_featured' => (bool) ($article->is_featured ?? false),
            ],
            'formOptions' => $this->loadFormOptions((int) ($article->tenant_id ?? 0)),
            'latestGeoAudit' => $article->latestGeoAudit,
            'geoAuditThreshold' => (int) config('geoflow.geo_audit.pass_threshold', 70),
            'geoAuditOptimizing' => (bool) Cache::get(OptimizeArticleGeoJob::lockKey($articleId), false),
            'geoAuditOptimizeError' => Cache::get(OptimizeArticleGeoJob::errorKey($articleId)),
        ]);
    }

    /**
     * 更新文章：保持创建/编辑一致的字段校验与状态归一化。
     */
    public function update(Request $request, int $articleId): RedirectResponse
    {
        $article = Article::query()->whereKey($articleId)->firstOrFail();
        $payload = $this->validateArticleForm($request, true, (int) ($article->tenant_id ?? 0));

        $workflowState = ArticleWorkflow::normalizeState(
            $payload['status'],
            $payload['review_status'],
            $article->published_at?->format('Y-m-d H:i:s')
        );

        try {
            $article->fill([
                'title' => $payload['title'],
                'slug' => $payload['title'] === $article->title
                    ? $article->slug
                    : ArticleWorkflow::generateUniqueSlug($payload['title'], (int) $article->id),
                'content' => $payload['content'],
                'excerpt' => $payload['excerpt'] !== '' ? $payload['excerpt'] : mb_substr(strip_tags($payload['content']), 0, 200, 'UTF-8'),
                'keywords' => $payload['keywords'],
                'meta_description' => $payload['meta_description'],
                'category_id' => (int) $payload['category_id'],
                'author_id' => (int) $payload['author_id'],
                'status' => $workflowState['status'],
                'review_status' => $workflowState['review_status'],
                'published_at' => $workflowState['published_at'],
                'is_hot' => (bool) ($payload['is_hot'] ?? false),
                'is_featured' => (bool) ($payload['is_featured'] ?? false),
            ])->save();
            if ($workflowState['status'] === 'published') {
                $this->distributionOrchestrator->enqueueForArticle($article);
            }
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(__('admin.article_edit.error.update_exception', ['message' => $e->getMessage()]));
        }

        return redirect()
            ->route('admin.articles.edit', ['articleId' => $articleId])
            ->with('message', __('admin.article_edit.message.update_success'));
    }

    /**
     * @return array{
     *     task_id: int,
     *     status: string,
     *     review_status: string,
     *     author_id: int,
     *     distribution_channel_ids: array<int, int>,
     *     date_from: string,
     *     date_to: string,
     *     search: string,
     *     per_page: int,
     *     trashed: bool,
     *     geo_audit_status: string
     * }
     */
    private function buildFilters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        $reviewStatus = (string) $request->query('review_status', '');

        if (! in_array($status, ['draft', 'published', 'private'], true)) {
            $status = '';
        }

        if (! in_array($reviewStatus, ['pending', 'approved', 'rejected', 'auto_approved'], true)) {
            $reviewStatus = '';
        }

        $geoAuditStatus = (string) $request->query('geo_audit_status', '');
        if (! in_array($geoAuditStatus, ['unscored', 'passed', 'needs_optimization', 'risk'], true)) {
            $geoAuditStatus = '';
        }

        return [
            'task_id' => max(0, (int) $request->query('task_id', 0)),
            'status' => $status,
            'review_status' => $reviewStatus,
            'geo_audit_status' => $geoAuditStatus,
            'author_id' => max(0, (int) $request->query('author_id', 0)),
            'distribution_channel_ids' => $this->extractDistributionChannelIds($request),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'search' => trim((string) $request->query('search', '')),
            'per_page' => min(100, max(10, (int) $request->query('per_page', 20) ?: 20)),
            'trashed' => $request->boolean('trashed'),
        ];
    }

    /**
     * @param  array{
     *     task_id: int,
     *     status: string,
     *     review_status: string,
     *     author_id: int,
     *     distribution_channel_ids: array<int, int>,
     *     date_from: string,
     *     date_to: string,
     *     search: string,
     *     per_page: int,
     *     trashed: bool,
     *     geo_audit_status: string
     * }  $filters
     */
    private function queryArticles(array $filters): LengthAwarePaginator
    {
        $query = ($filters['trashed'] ?? false)
            ? Article::onlyTrashed()
            : Article::query();

        $query->with([
            'task:id,name,need_review',
            'author:id,name',
            'category:id,name',
            'distributions.channel:id,name,domain',
            'syncedRemoteDistributions.channel:id,name,domain',
            'latestGeoAudit',
        ])->withCount([
            'distributions as distribution_total_count',
            'distributions as distribution_synced_count' => fn ($distributionQuery) => $distributionQuery->where('status', 'synced'),
            'distributions as distribution_failed_count' => fn ($distributionQuery) => $distributionQuery->where('status', 'failed'),
        ]);

        if ($filters['trashed'] ?? false) {
            $query->orderByDesc('deleted_at');
        } else {
            $query->orderByDesc('created_at');
        }

        if ($filters['task_id'] > 0) {
            $query->where('task_id', $filters['task_id']);
        }

        if (($filters['trashed'] ?? false) === false && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['trashed'] ?? false) === false && $filters['review_status'] !== '') {
            $query->where('review_status', $filters['review_status']);
        }

        if (($filters['trashed'] ?? false) === false && $filters['geo_audit_status'] !== '') {
            $threshold = (int) config('geoflow.geo_audit.pass_threshold', 70);
            $latestAuditIds = ArticleGeoAudit::query()
                ->selectRaw('MAX(id) as id')
                ->groupBy('article_id');

            match ($filters['geo_audit_status']) {
                'unscored' => $query->whereDoesntHave('latestGeoAudit'),
                'passed' => $query->whereHas('latestGeoAudit', fn ($auditQuery) => $auditQuery
                    ->whereIn('id', $latestAuditIds)
                    ->where('geo_score', '>=', $threshold)
                    ->where(function ($riskQuery): void {
                        $riskQuery->whereNull('risk_notes')->orWhereJsonLength('risk_notes', 0);
                    })),
                'needs_optimization' => $query->whereHas('latestGeoAudit', fn ($auditQuery) => $auditQuery
                    ->whereIn('id', $latestAuditIds)
                    ->where('geo_score', '<', $threshold)),
                'risk' => $query->whereHas('latestGeoAudit', fn ($auditQuery) => $auditQuery
                    ->whereIn('id', $latestAuditIds)
                    ->whereJsonLength('risk_notes', '>', 0)),
                default => null,
            };
        }

        if ($filters['author_id'] > 0) {
            $query->where('author_id', $filters['author_id']);
        }

        if (! empty($filters['distribution_channel_ids'])) {
            $query->whereHas('distributions', function ($distributionQuery) use ($filters): void {
                $distributionQuery->whereIn('distribution_channel_id', $filters['distribution_channel_ids']);
            });
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if ($filters['search'] !== '') {
            $query->where(function ($subQuery) use ($filters): void {
                $subQuery->where('title', 'like', '%'.$filters['search'].'%')
                    ->orWhere('content', 'like', '%'.$filters['search'].'%');
            });
        }

        return $query->paginate($filters['per_page'])->withQueryString();
    }

    /**
     * 测试环境缺少 articles 表时，返回空分页并保持页面可渲染。
     */
    private function emptyArticlesPaginator(int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: collect(),
            total: 0,
            perPage: $perPage,
            currentPage: max(1, (int) request()->query('page', 1)),
            options: [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @return array{total: int, published: int, draft: int, pending_review: int, observed: int, today: int}
     */
    private function loadStats(): array
    {
        $baseQuery = Article::query();

        return [
            'total' => (clone $baseQuery)->count(),
            'published' => (clone $baseQuery)->where('status', 'published')->count(),
            'draft' => (clone $baseQuery)->where('status', 'draft')->count(),
            'pending_review' => (clone $baseQuery)->where('review_status', 'pending')->count(),
            'observed' => (clone $baseQuery)->where('view_count', '>', 0)->count(),
            'today' => (clone $baseQuery)->whereDate('created_at', Carbon::today())->count(),
        ];
    }

    /**
     * @return array{trashed_total: int}
     */
    private function loadTrashStats(): array
    {
        return [
            'trashed_total' => Article::onlyTrashed()->count(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, domain: string, status: string}>
     */
    private function loadDistributionChannelOptions(): array
    {
        try {
            return DistributionChannel::query()
                ->select(['id', 'name', 'domain', 'status'])
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get()
                ->map(fn (DistributionChannel $channel): array => [
                    'id' => (int) $channel->id,
                    'name' => (string) $channel->name,
                    'domain' => (string) ($channel->domain ?? ''),
                    'status' => (string) ($channel->status ?? ''),
                ])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return array<int, int>
     */
    private function extractDistributionChannelIds(Request $request): array
    {
        $rawIds = $request->query('distribution_channel_ids', []);
        if (! is_array($rawIds)) {
            $rawIds = [$rawIds];
        }

        $legacyId = (int) $request->query('distribution_channel_id', 0);
        if ($legacyId > 0) {
            $rawIds[] = $legacyId;
        }

        return collect($rawIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function loadTaskOptions(): array
    {
        try {
            return Task::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Task $task): array => [
                    'id' => (int) $task->id,
                    'name' => (string) $task->name,
                ])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return array<int, array{id: int, name: string, tenant_id: int}>
     */
    private function loadAuthorOptions(?int $tenantId = null): array
    {
        try {
            return $this->tenantScopedQuery(Author::query(), $tenantId)
                ->select(['id', 'tenant_id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Author $author): array => [
                    'id' => (int) $author->id,
                    'tenant_id' => (int) ($author->tenant_id ?? 0),
                    'name' => (string) $author->name,
                ])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return array{
     *     categories: array<int, array{id: int, name: string, tenant_id: int}>,
     *     authors: array<int, array{id: int, name: string, tenant_id: int}>
     * }
     */
    private function loadFormOptions(?int $tenantId = null): array
    {
        $categories = [];
        $authors = $this->loadAuthorOptions($tenantId);

        try {
            $categories = $this->tenantScopedQuery(Category::query(), $tenantId)
                ->select(['id', 'tenant_id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => (int) $category->id,
                    'tenant_id' => (int) ($category->tenant_id ?? 0),
                    'name' => (string) $category->name,
                ])
                ->all();
        } catch (QueryException) {
            $categories = [];
        }

        return [
            'categories' => $categories,
            'authors' => $authors,
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     excerpt: string,
     *     content: string,
     *     keywords: string,
     *     meta_description: string,
     *     category_id: int,
     *     author_id: int,
     *     status: string,
     *     review_status: string
     *     is_hot: bool,
     *     is_featured: bool
     * }
     */
    private function validateArticleForm(Request $request, bool $isEdit, ?int $tenantId = null): array
    {
        $keyPrefix = $isEdit ? 'admin.article_edit.error' : 'admin.article_create.error';

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'category_id' => ['required', 'integer', 'min:1', $this->tenantExistsRule((new Category)->getTable(), $tenantId)],
            'author_id' => ['required', 'integer', 'min:1', $this->tenantExistsRule((new Author)->getTable(), $tenantId)],
            'status' => ['required', 'string', 'in:draft,published,private'],
            'review_status' => ['required', 'string', 'in:pending,approved,rejected,auto_approved'],
            'is_hot' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ], [
            'title.required' => __($keyPrefix.'.title_required'),
            'content.required' => __($keyPrefix.'.content_required'),
            'category_id.required' => __($keyPrefix.'.category_required'),
            'category_id.min' => __($keyPrefix.'.category_required'),
            'author_id.required' => __($keyPrefix.'.author_required'),
            'author_id.min' => __($keyPrefix.'.author_required'),
        ]);
    }

    /**
     * @param  array{category_id: int, author_id: int}  $payload
     *
     * @throws ValidationException
     */
    private function resolveArticleTenantId(array $payload): int
    {
        $categoryTenantId = (int) (DB::table((new Category)->getTable())
            ->where('id', (int) $payload['category_id'])
            ->value('tenant_id') ?? 0);
        $authorTenantId = (int) (DB::table((new Author)->getTable())
            ->where('id', (int) $payload['author_id'])
            ->value('tenant_id') ?? 0);

        if ($categoryTenantId <= 0) {
            throw ValidationException::withMessages([
                'category_id' => '所选分类缺少租户归属，不能创建文章。',
            ]);
        }

        if ($authorTenantId <= 0) {
            throw ValidationException::withMessages([
                'author_id' => '所选作者缺少租户归属，不能创建文章。',
            ]);
        }

        if ($categoryTenantId !== $authorTenantId) {
            throw ValidationException::withMessages([
                'category_id' => '所选分类和作者不属于同一租户。',
                'author_id' => '所选分类和作者不属于同一租户。',
            ]);
        }

        return $categoryTenantId;
    }

    private function currentTenantId(): int
    {
        $tenantId = TenantContext::id();
        if ($tenantId !== null && $tenantId > 0) {
            return $tenantId;
        }

        $adminId = (int) (Auth::guard('admin')->id() ?? 0);
        if ($adminId <= 0) {
            return 0;
        }

        return (int) (DB::table((new Admin)->getTable())->where('id', $adminId)->value('tenant_id') ?? 0);
    }

    private function targetTenantId(?int $tenantId = null): int
    {
        if ($tenantId !== null && $tenantId > 0) {
            return $tenantId;
        }

        $admin = Auth::guard('admin')->user();
        if ($admin instanceof Admin && $admin->isSuperAdmin()) {
            return 0;
        }

        return $this->currentTenantId();
    }

    /**
     * @return array<int, int>
     */
    private function extractArticleIds(Request $request): array
    {
        return collect($request->input('article_ids', []))
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $articleIds
     */
    private function handleBatchUpdateStatus(Request $request, array $articleIds): RedirectResponse
    {
        $newStatus = (string) $request->input('new_status', '');
        if (! in_array($newStatus, ['draft', 'published', 'private'], true)) {
            return back()->withErrors(__('admin.articles.message.select_status'));
        }

        $articles = Article::query()
            ->select(['id', 'review_status', 'published_at'])
            ->whereIn('id', $articleIds)
            ->get();

        foreach ($articles as $article) {
            $workflowState = ArticleWorkflow::normalizeState(
                $newStatus,
                (string) ($article->review_status ?? 'pending'),
                $article->published_at?->format('Y-m-d H:i:s')
            );

            Article::query()->whereKey((int) $article->id)->update([
                'status' => $workflowState['status'],
                'review_status' => $workflowState['review_status'],
                'published_at' => $workflowState['published_at'],
            ]);

            if ($workflowState['status'] === 'published') {
                $this->distributionOrchestrator->enqueueForArticle((int) $article->id);
            }
        }

        return back()->with('message', __('admin.articles.message.batch_status_updated', ['count' => count($articleIds)]));
    }

    /**
     * @param  array<int, int>  $articleIds
     */
    private function handleBatchUpdateReview(Request $request, array $articleIds): RedirectResponse
    {
        $reviewStatus = (string) $request->input('review_status', '');
        if (! in_array($reviewStatus, ['pending', 'approved', 'rejected', 'auto_approved'], true)) {
            return back()->withErrors(__('admin.articles.message.select_review'));
        }

        $articles = Article::query()
            ->with(['task:id,need_review'])
            ->select(['id', 'status', 'review_status', 'published_at', 'task_id'])
            ->whereIn('id', $articleIds)
            ->get();

        foreach ($articles as $article) {
            $desiredStatus = (string) ($article->status ?? 'draft');
            $needsReview = (int) ($article->task->need_review ?? 0);
            if (in_array($reviewStatus, ['approved', 'auto_approved'], true) && ($reviewStatus === 'auto_approved' || $needsReview === 0)) {
                $desiredStatus = 'published';
            }

            $workflowState = ArticleWorkflow::normalizeState(
                $desiredStatus,
                $reviewStatus,
                $article->published_at?->format('Y-m-d H:i:s')
            );

            Article::query()->whereKey((int) $article->id)->update([
                'status' => $workflowState['status'],
                'review_status' => $workflowState['review_status'],
                'published_at' => $workflowState['published_at'],
            ]);

            if ($workflowState['status'] === 'published') {
                $this->distributionOrchestrator->enqueueForArticle((int) $article->id);
            }
        }

        return back()->with('message', __('admin.articles.message.batch_review_updated', ['count' => count($articleIds)]));
    }

    /**
     * @param  array<int, int>  $articleIds
     */
    private function handleBatchDelete(array $articleIds): RedirectResponse
    {
        $articles = Article::query()->whereIn('id', $articleIds)->get();
        foreach ($articles as $article) {
            Article::query()->whereKey((int) $article->id)->delete();
        }

        return back()->with('message', __('admin.articles.message.batch_delete_success', ['count' => count($articleIds)]));
    }

    /**
     * 前端批量栏与快捷动作使用的文案字典。
     *
     * @return array<string, string>
     */
    private function articlesI18n(): array
    {
        return [
            'selectArticles' => __('admin.articles.message.select_articles'),
            'selectAction' => __('admin.articles.message.select_action'),
            'selectStatus' => __('admin.articles.message.select_status'),
            'selectReview' => __('admin.articles.message.select_review'),
            'confirmDeleteSelected' => __('admin.articles.confirm.delete_selected', ['count' => '__COUNT__']),
            'reviewApproved' => __('admin.articles.review.approved'),
            'reviewRejected' => __('admin.articles.review.rejected'),
            'confirmQuickReview' => __('admin.articles.confirm.quick_review', ['action' => '__ACTION__']),
            'confirmDelete' => __('admin.articles.confirm.delete'),
        ];
    }

    /**
     * 垃圾箱视图脚本使用的确认与操作文案。
     *
     * @return array<string, string>
     */
    private function trashI18n(): array
    {
        return [
            'alertSelect' => __('admin.articles.trash.alert_select'),
            'confirmBatchRestore' => __('admin.articles.trash.confirm_batch_restore', ['count' => '__COUNT__']),
            'confirmBatchForceDelete' => __('admin.articles.trash.confirm_batch_delete', ['count' => '__COUNT__']),
            'confirmEmpty' => __('admin.articles.trash.confirm_empty'),
        ];
    }

    /**
     * 批量操作表单提交目标 URL（普通列表与垃圾箱不同）。
     *
     * @return array<string, string>
     */
    private function articleBatchRoutes(bool $isTrashView): array
    {
        if ($isTrashView) {
            return [
                'batch_restore' => AdminWeb::routePath('admin.articles.batch.restore'),
                'batch_force_delete' => AdminWeb::routePath('admin.articles.batch.force-delete'),
            ];
        }

        return [
            'batch_update_status' => AdminWeb::routePath('admin.articles.batch.update-status'),
            'batch_update_review' => AdminWeb::routePath('admin.articles.batch.update-review'),
            'delete_articles' => AdminWeb::routePath('admin.articles.batch.delete'),
        ];
    }

    private function tenantScopedQuery($query, ?int $tenantId = null)
    {
        $targetTenantId = $this->targetTenantId($tenantId);
        if ($targetTenantId > 0) {
            $query->where('tenant_id', $targetTenantId);
        }

        return $query;
    }

    private function tenantExistsRule(string $table, ?int $tenantId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($table, $tenantId): void {
            $query = DB::table($table)->where('id', (int) $value);
            $targetTenantId = $this->targetTenantId($tenantId);
            if ($targetTenantId > 0) {
                $query->where('tenant_id', $targetTenantId);
            }

            if (! $query->exists()) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        };
    }
}
