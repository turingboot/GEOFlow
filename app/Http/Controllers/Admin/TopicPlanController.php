<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\KeywordLibrary;
use App\Models\KeywordTrendSource;
use App\Models\KnowledgeBase;
use App\Models\Prompt;
use App\Models\TopicPlan;
use App\Services\GeoFlow\TopicPlan\MonthlyTopicPlannerService;
use App\Services\GeoFlow\TopicPlan\TopicPlanToTaskService;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * 选题规划后台（外挂式扩展）：月度选题规划 + 选题确认/排期。
 * 只往主链路喂数据（写 titles + 建 Task），不改 schedule/pickTitle/worker。
 */
class TopicPlanController extends Controller
{
    public function __construct(
        private readonly MonthlyTopicPlannerService $planner,
        private readonly TopicPlanToTaskService $dispatcher,
    ) {}

    public function index(): View
    {
        $plans = TopicPlan::query()->withCount('items')->orderByDesc('id')->limit(100)->get();

        return view('admin.topic-plans.index', [
            'pageTitle' => __('admin.topic_plans.page_title'),
            'activeMenu' => 'topic_plans',
            'adminSiteName' => AdminWeb::siteName(),
            'plans' => $plans,
        ]);
    }

    public function create(): View
    {
        return view('admin.topic-plans.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'ai_model_id' => ['required', 'integer'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'target_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'keyword_library_ids' => ['nullable', 'array'],
            'knowledge_base_ids' => ['nullable', 'array'],
            'trend_source_ids' => ['nullable', 'array'],
        ]);

        try {
            $plan = $this->planner->generatePlan([
                'name' => $data['name'],
                'ai_model_id' => (int) $data['ai_model_id'],
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'target_count' => (int) ($data['target_count'] ?? 30),
                'keyword_library_ids' => $data['keyword_library_ids'] ?? [],
                'knowledge_base_ids' => $data['knowledge_base_ids'] ?? [],
                'trend_source_ids' => $data['trend_source_ids'] ?? [],
                'created_by_admin_id' => auth('admin')->id(),
            ]);
        } catch (Throwable $e) {
            return redirect()->route('admin.topic-plans.create')
                ->withInput()
                ->withErrors(__('admin.topic_plans.message.generate_failed', ['error' => $e->getMessage()]));
        }

        return redirect()->route('admin.topic-plans.show', $plan->id)
            ->with('message', __('admin.topic_plans.message.generated', ['count' => $plan->items->count()]));
    }

    public function show(int $planId): View|RedirectResponse
    {
        $plan = TopicPlan::query()->with('items')->find($planId);
        if ($plan === null) {
            return redirect()->route('admin.topic-plans.index')->withErrors(__('admin.topic_plans.message.not_found'));
        }

        return view('admin.topic-plans.show', [
            'pageTitle' => __('admin.topic_plans.detail_title'),
            'activeMenu' => 'topic_plans',
            'adminSiteName' => AdminWeb::siteName(),
            'plan' => $plan,
            'contentPrompts' => Prompt::query()->where('type', 'content')->orderBy('name')->get(['id', 'name']),
            'chatModels' => $this->activeChatModels(),
            'knowledgeBases' => KnowledgeBase::query()->orderByDesc('id')->limit(200)->get(['id', 'name']),
        ]);
    }

    public function confirm(Request $request, int $planId): RedirectResponse
    {
        $plan = TopicPlan::query()->with('items')->find($planId);
        if ($plan === null) {
            return redirect()->route('admin.topic-plans.index')->withErrors(__('admin.topic_plans.message.not_found'));
        }

        $confirmedIds = array_map('intval', (array) $request->input('item_ids', []));
        foreach ($plan->items as $item) {
            // 已投喂的条目不再改动。
            if ($item->status === 'dispatched') {
                continue;
            }
            $item->update(['status' => in_array((int) $item->id, $confirmedIds, true) ? 'confirmed' : 'rejected']);
        }

        if ($plan->status === 'draft') {
            $plan->update(['status' => 'confirmed']);
        }

        return redirect()->route('admin.topic-plans.show', $plan->id)
            ->with('message', __('admin.topic_plans.message.confirmed'));
    }

    public function dispatch(Request $request, int $planId): RedirectResponse
    {
        $plan = TopicPlan::query()->with('items')->find($planId);
        if ($plan === null) {
            return redirect()->route('admin.topic-plans.index')->withErrors(__('admin.topic_plans.message.not_found'));
        }

        $data = $request->validate([
            'prompt_id' => ['required', 'integer'],
            'ai_model_id' => ['required', 'integer'],
            'publish_interval' => ['nullable', 'integer', 'min:60'],
            'need_review' => ['nullable', 'boolean'],
            'category_mode' => ['nullable', 'in:smart,fixed'],
            'publish_scope' => ['nullable', 'in:local_and_distribution,distribution_only,local_only'],
            'status' => ['nullable', 'in:active,paused'],
            'knowledge_base_ids' => ['nullable', 'array'],
        ]);

        try {
            $this->dispatcher->dispatch($plan, [
                'prompt_id' => (int) $data['prompt_id'],
                'ai_model_id' => (int) $data['ai_model_id'],
                'publish_interval' => (int) ($data['publish_interval'] ?? 86400),
                'need_review' => (int) ($data['need_review'] ?? 1),
                'category_mode' => $data['category_mode'] ?? 'smart',
                'publish_scope' => $data['publish_scope'] ?? 'local_only',
                'status' => $data['status'] ?? 'paused',
                'knowledge_base_ids' => $data['knowledge_base_ids'] ?? [],
            ]);
        } catch (Throwable $e) {
            return redirect()->route('admin.topic-plans.show', $plan->id)
                ->withErrors(__('admin.topic_plans.message.dispatch_failed', ['error' => $e->getMessage()]));
        }

        return redirect()->route('admin.topic-plans.show', $plan->id)
            ->with('message', __('admin.topic_plans.message.dispatched'));
    }

    /**
     * @return array<string,mixed>
     */
    private function formData(): array
    {
        return [
            'pageTitle' => __('admin.topic_plans.create_title'),
            'activeMenu' => 'topic_plans',
            'adminSiteName' => AdminWeb::siteName(),
            'chatModels' => $this->activeChatModels(),
            'keywordLibraries' => KeywordLibrary::query()->orderBy('name')->get(['id', 'name']),
            'knowledgeBases' => KnowledgeBase::query()->orderByDesc('id')->limit(200)->get(['id', 'name']),
            'trendSources' => KeywordTrendSource::query()->orderByDesc('id')->get(['id', 'name']),
        ];
    }

    private function activeChatModels()
    {
        return AiModel::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('model_type')->orWhere('model_type', '')->orWhere('model_type', 'chat');
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
