<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\OptimizeArticleGeoJob;
use App\Models\Article;
use App\Models\ArticleGeoAudit;
use App\Services\GeoFlow\GeoArticleAuditService;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * 文章 GEO 评分面板（外挂式扩展，只读 + 重新评分）。
 * 不替换现有审核流程，仅展示 GEO 质检层产出的评分与建议。
 */
class GeoAuditController extends Controller
{
    public function __construct(
        private readonly GeoArticleAuditService $auditService,
    ) {}

    public function index(): View
    {
        // 每篇文章取最新一条评分。
        $latestIds = ArticleGeoAudit::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('article_id')
            ->pluck('id');

        $audits = ArticleGeoAudit::query()
            ->with('article:id,title,status,review_status')
            ->whereIn('id', $latestIds)
            ->whereHas('article') // 排除回收站（软删）文章的评分；文章永久删除时评分已随 FK 级联清除。
            ->orderByDesc('audited_at')
            ->limit(200)
            ->get();

        return view('admin.geo-audits.index', [
            'pageTitle' => __('admin.geo_audit.page_title'),
            'activeMenu' => 'geo_audit',
            'adminSiteName' => AdminWeb::siteName(),
            'audits' => $audits,
            'threshold' => (int) config('geoflow.geo_audit.pass_threshold', 70),
            'stats' => [
                'total' => $audits->count(),
                'auto' => $audits->where('gate_decision', ArticleGeoAudit::GATE_AUTO_APPROVED)->count(),
                'review' => $audits->where('gate_decision', ArticleGeoAudit::GATE_TO_REVIEW)->count(),
                'avg' => $audits->isEmpty() ? 0 : (int) round((float) $audits->avg('geo_score')),
            ],
        ]);
    }

    public function show(int $articleId): View|RedirectResponse
    {
        $audit = ArticleGeoAudit::query()
            ->with('article')
            ->where('article_id', $articleId)
            ->whereHas('article') // 回收站文章的评分不可见；恢复后自动重现，永久删除则随 FK 级联清除。
            ->orderByDesc('id')
            ->first();

        if ($audit === null) {
            return redirect()->route('admin.geo-audits.index')
                ->withErrors(__('admin.geo_audit.message.not_found'));
        }

        $history = ArticleGeoAudit::query()
            ->where('article_id', $articleId)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('admin.geo-audits.show', [
            'pageTitle' => __('admin.geo_audit.detail_title'),
            'activeMenu' => 'geo_audit',
            'adminSiteName' => AdminWeb::siteName(),
            'audit' => $audit,
            'history' => $history,
            'threshold' => (int) config('geoflow.geo_audit.pass_threshold', 70),
            'optimizing' => (bool) Cache::get(OptimizeArticleGeoJob::lockKey($articleId), false),
            'optimizeError' => Cache::get(OptimizeArticleGeoJob::errorKey($articleId)),
        ]);
    }

    public function reaudit(Request $request, int $articleId): RedirectResponse
    {
        $article = Article::query()->find($articleId);
        if ($article === null) {
            return redirect()->route('admin.geo-audits.index')
                ->withErrors(__('admin.geo_audit.message.not_found'));
        }

        // 人工触发的重新评分只落分，不改 review_status（人工正在查看，避免意外流转）。
        $this->auditService->audit($article);

        if ($request->input('return_to') === 'article') {
            return redirect()->route('admin.articles.edit', $articleId)
                ->with('message', __('admin.geo_audit.message.reaudited'));
        }

        return redirect()->route('admin.geo-audits.show', $articleId)
            ->with('message', __('admin.geo_audit.message.reaudited'));
    }

    public function optimize(Request $request, int $articleId): RedirectResponse
    {
        $article = Article::query()->find($articleId, ['id']);
        if ($article === null) {
            return redirect()->route('admin.geo-audits.index')
                ->withErrors(__('admin.geo_audit.message.not_found'));
        }

        // 已在优化中则不重复入队。
        if (Cache::get(OptimizeArticleGeoJob::lockKey($articleId))) {
            if ($request->input('return_to') === 'article') {
                return redirect()->route('admin.articles.edit', $articleId)
                    ->with('message', __('admin.geo_audit.message.optimize_running'));
            }

            return redirect()->route('admin.geo-audits.show', $articleId)
                ->with('message', __('admin.geo_audit.message.optimize_running'));
        }

        // 标记「优化中」并异步入队：AI 重写耗时较长，避免阻塞 HTTP 请求 / 触发超时。
        Cache::put(OptimizeArticleGeoJob::lockKey($articleId), true, now()->addMinutes(10));
        Cache::forget(OptimizeArticleGeoJob::errorKey($articleId));
        OptimizeArticleGeoJob::dispatch($articleId);

        if ($request->input('return_to') === 'article') {
            return redirect()->route('admin.articles.edit', $articleId)
                ->with('message', __('admin.geo_audit.message.optimize_started'));
        }

        return redirect()->route('admin.geo-audits.show', $articleId)
            ->with('message', __('admin.geo_audit.message.optimize_started'));
    }
}
