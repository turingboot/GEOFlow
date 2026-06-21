<?php

namespace App\Services\GeoFlow\TopicPlan;

use App\Ai\Agents\TopicPlannerAgent;
use App\Models\AiModel;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\KeywordTrend;
use App\Models\KnowledgeBase;
use App\Models\TopicPlan;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * 选题规划层（外挂式扩展）：读趋势/关键词库/知识库/历史文章 → AI 生成月度选题池 → 落库。
 *
 * 不直接改写任务机制；规划结果经人工确认后由 {@see TopicPlanToTaskService} 投喂现有 Task。
 * AI 调用集中在 {@see runPlannerModel()}（protected，便于测试时替换为固定 JSON）。
 */
class MonthlyTopicPlannerService
{
    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto
    ) {}

    /**
     * 生成一个月度选题计划（草稿）。
     *
     * @param  array<string,mixed>  $params
     */
    public function generatePlan(array $params): TopicPlan
    {
        $model = AiModel::query()->find((int) ($params['ai_model_id'] ?? 0));
        if ($model === null) {
            throw new RuntimeException('AI 模型不存在');
        }

        $targetCount = max(1, min(100, (int) ($params['target_count'] ?? 30)));
        $dedupWindow = (int) ($params['dedup_window_days'] ?? config('geoflow.geo_audit.dedup_window_days', 90));

        $trendKeywords = $this->gatherTrendKeywords($params['trend_source_ids'] ?? [], $targetCount * 3);
        $libraryKeywords = $this->gatherLibraryKeywords($params['keyword_library_ids'] ?? []);
        $knowledgeSummaries = $this->gatherKnowledgeSummaries($params['knowledge_base_ids'] ?? []);
        $history = $this->gatherHistory($dedupWindow);

        $prompt = $this->buildPrompt([
            'trend_keywords' => $trendKeywords,
            'library_keywords' => $libraryKeywords,
            'knowledge_summaries' => $knowledgeSummaries,
            'history_titles' => array_column($history, 'title'),
            'target_count' => $targetCount,
        ]);

        $json = $this->runPlannerModel($model, $prompt);

        $historyKeywords = array_merge(array_column($history, 'title'), array_column($history, 'keyword'));
        $items = $this->parsePlanItems($json, $historyKeywords, $targetCount);

        return $this->persist($params, $items, [
            'trend_keyword_count' => count($trendKeywords),
            'library_keyword_count' => count($libraryKeywords),
            'knowledge_base_ids' => array_map('intval', (array) ($params['knowledge_base_ids'] ?? [])),
            'keyword_library_ids' => array_map('intval', (array) ($params['keyword_library_ids'] ?? [])),
            'history_count' => count($history),
        ]);
    }

    /**
     * 解析 AI 返回的 JSON 选题数组为归一化条目（纯函数，便于测试）。
     *
     * @param  array<int,string>  $historyKeywords
     * @return list<array<string,mixed>>
     */
    public function parsePlanItems(string $json, array $historyKeywords, int $targetCount): array
    {
        $decoded = $this->decodeJsonArray($json);

        $historySet = [];
        foreach ($historyKeywords as $value) {
            $key = $this->normalizeKey((string) $value);
            if ($key !== '') {
                $historySet[$key] = true;
            }
        }

        $items = [];
        $seen = [];
        foreach ($decoded as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $title = trim((string) ($raw['title'] ?? ''));
            $keyword = trim((string) ($raw['keyword'] ?? ''));
            if ($title === '' || $keyword === '') {
                continue;
            }

            $titleKey = $this->normalizeKey($title);
            if (isset($seen[$titleKey])) {
                continue;
            }
            $seen[$titleKey] = true;

            $dupRisk = (string) ($raw['dup_risk'] ?? '');
            $isHistoricalDuplicate = isset($historySet[$titleKey]) || isset($historySet[$this->normalizeKey($keyword)]);
            if ($isHistoricalDuplicate) {
                $dupRisk = 'high';
            } elseif (! in_array($dupRisk, ['low', 'medium', 'high'], true)) {
                $dupRisk = 'low';
            }

            $kbSupport = (string) ($raw['kb_support'] ?? '');
            if (! in_array($kbSupport, ['strong', 'weak', 'none'], true)) {
                $kbSupport = 'weak';
            }

            $secondary = [];
            if (isset($raw['secondary_keywords']) && is_array($raw['secondary_keywords'])) {
                foreach ($raw['secondary_keywords'] as $secondaryKeyword) {
                    $value = trim((string) $secondaryKeyword);
                    if ($value !== '') {
                        $secondary[] = $value;
                    }
                }
            }

            $items[] = [
                'title' => mb_substr($title, 0, 255),
                'keyword' => mb_substr($keyword, 0, 200),
                'secondary_keywords' => array_slice($secondary, 0, 10),
                'rationale' => trim((string) ($raw['rationale'] ?? '')) ?: null,
                'heat_score' => $this->clampInt($raw['heat_score'] ?? null, 0, 100),
                'kb_support' => $kbSupport,
                'dup_risk' => $dupRisk,
                'status' => 'suggested',
            ];

            if (count($items) >= $targetCount) {
                break;
            }
        }

        return $items;
    }

    /**
     * 组装规划提示词（纯函数，便于测试）。
     *
     * @param  array<string,mixed>  $context
     */
    public function buildPrompt(array $context): string
    {
        $trend = collect($context['trend_keywords'] ?? [])
            ->map(fn (array $row): string => sprintf('%s（热度 %d）', $row['keyword'] ?? '', (int) ($row['heat'] ?? 0)))
            ->implode('、');
        $libraryKeywords = implode('、', array_slice((array) ($context['library_keywords'] ?? []), 0, 80));
        $knowledge = implode("\n", array_map(static fn (string $line): string => '- '.$line, (array) ($context['knowledge_summaries'] ?? [])));
        $history = implode("\n", array_map(static fn (string $title): string => '- '.$title, array_slice((array) ($context['history_titles'] ?? []), 0, 60)));
        $targetCount = (int) ($context['target_count'] ?? 30);

        return implode("\n\n", array_filter([
            sprintf('请规划 %d 个选题，严格只输出 JSON 数组。', $targetCount),
            $trend !== '' ? "【关键词趋势（按热度）】\n".$trend : null,
            $libraryKeywords !== '' ? "【业务关键词库】\n".$libraryKeywords : null,
            $knowledge !== '' ? "【知识库摘要（用于判断选题是否有支撑）】\n".$knowledge : null,
            $history !== '' ? "【近期历史文章标题（务必避免重复）】\n".$history : null,
        ]));
    }

    /**
     * 实际调用模型（protected，测试可覆盖以返回固定 JSON）。
     */
    protected function runPlannerModel(AiModel $model, string $prompt): string
    {
        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) ($model->api_url ?? ''));
        if ($providerUrl === '') {
            throw new RuntimeException('AI 模型 API 地址为空');
        }

        $apiKey = $this->apiKeyCrypto->decrypt((string) ($model->getRawOriginal('api_key') ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('AI 模型密钥为空');
        }

        $driver = OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) ($model->model_id ?? ''));
        $providerName = OpenAiRuntimeProvider::registerProvider('topic-planner', $driver, $providerUrl, $apiKey);
        $maxTokens = (int) ($model->max_tokens ?? 0);
        $agent = new TopicPlannerAgent(maxTokens: $maxTokens > 0 ? $maxTokens : null);

        try {
            $response = $agent->prompt($prompt, [], $providerName, (string) ($model->model_id ?? ''));
        } catch (Throwable $e) {
            throw new RuntimeException('选题规划生成失败: '.OpenAiRuntimeProvider::normalizeApiException($e, $providerUrl), 0, $e);
        }

        return OpenAiRuntimeProvider::normalizeGeneratedText((string) ($response->text ?? ''));
    }

    /**
     * @param  array<int,int>  $sourceIds
     * @return list<array{keyword:string, heat:int}>
     */
    private function gatherTrendKeywords(array $sourceIds, int $limit): array
    {
        $query = KeywordTrend::query()->orderByDesc('heat')->orderByDesc('id');
        if ($sourceIds !== []) {
            $query->whereIn('keyword_trend_source_id', array_map('intval', $sourceIds));
        }

        $rows = $query->limit(max(10, $limit))->get(['keyword', 'heat']);
        $result = [];
        $seen = [];
        foreach ($rows as $row) {
            $keyword = trim((string) $row->keyword);
            $key = $this->normalizeKey($keyword);
            if ($keyword === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = ['keyword' => $keyword, 'heat' => (int) $row->heat];
        }

        return $result;
    }

    /**
     * @param  array<int,int>  $libraryIds
     * @return list<string>
     */
    private function gatherLibraryKeywords(array $libraryIds): array
    {
        if ($libraryIds === []) {
            return [];
        }

        return Keyword::query()
            ->whereIn('library_id', array_map('intval', $libraryIds))
            ->orderBy('used_count')
            ->limit(200)
            ->pluck('keyword')
            ->map(static fn ($keyword): string => trim((string) $keyword))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int,int>  $knowledgeBaseIds
     * @return list<string>
     */
    private function gatherKnowledgeSummaries(array $knowledgeBaseIds): array
    {
        if ($knowledgeBaseIds === []) {
            return [];
        }

        return KnowledgeBase::query()
            ->whereIn('id', array_map('intval', $knowledgeBaseIds))
            ->limit(30)
            ->get(['name', 'description'])
            ->map(static function (KnowledgeBase $base): string {
                $description = trim((string) $base->description);

                return $description !== '' ? $base->name.'：'.mb_substr($description, 0, 120) : (string) $base->name;
            })
            ->all();
    }

    /**
     * @return list<array{title:string, keyword:string}>
     */
    private function gatherHistory(int $windowDays): array
    {
        return Article::query()
            ->where('created_at', '>=', now()->subDays(max(1, $windowDays)))
            ->orderByDesc('id')
            ->limit(300)
            ->get(['title', 'original_keyword'])
            ->map(static fn (Article $article): array => [
                'title' => (string) $article->title,
                'keyword' => (string) $article->original_keyword,
            ])
            ->all();
    }

    /**
     * @param  array<string,mixed>  $params
     * @param  list<array<string,mixed>>  $items
     * @param  array<string,mixed>  $sourceSummary
     */
    private function persist(array $params, array $items, array $sourceSummary): TopicPlan
    {
        return DB::transaction(function () use ($params, $items, $sourceSummary): TopicPlan {
            $plan = TopicPlan::query()->create([
                'name' => trim((string) ($params['name'] ?? '')) ?: '选题规划',
                // 区间灵活：未指定时默认「今天 ~ 今天+30 天」，不再锚定自然月。
                'period_start' => $params['period_start'] ?? now()->toDateString(),
                'period_end' => $params['period_end'] ?? now()->addDays(30)->toDateString(),
                'status' => 'draft',
                'source_summary' => $sourceSummary,
                'ai_model_id' => ((int) ($params['ai_model_id'] ?? 0)) ?: null,
                'created_by_admin_id' => $params['created_by_admin_id'] ?? null,
            ]);

            $sortOrder = 0;
            foreach ($items as $item) {
                $plan->items()->create($item + ['sort_order' => ++$sortOrder]);
            }

            return $plan->load('items');
        });
    }

    /**
     * @return list<mixed>
     */
    private function decodeJsonArray(string $json): array
    {
        $text = trim($json);
        $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return (string) preg_replace('/[\s\p{P}]+/u', '', $value);
    }

    private function clampInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max($min, min($max, (int) $value));
    }
}
