<?php

namespace App\Services\GeoFlow;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Models\AiModel;
use App\Models\Article;
use App\Models\ArticleGeoAudit;
use App\Models\Task;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use RuntimeException;
use Throwable;

/**
 * GEO 一键优化（外挂式扩展）：按四维评价指标用 AI 重写文章正文，再重新评分。
 *
 * 仅在后台「GEO 评分」面板手动触发，不参与主链路生成；优化后只更新正文与评分，
 * 不自动改 review_status（是否发布仍由原审核流程决定）。AI 调用集中在
 * {@see runOptimizerModel()}（protected，便于测试时替换为固定文本）。
 */
class GeoArticleOptimizerService
{
    private const OPTIMIZER_INSTRUCTIONS = '你是资深 GEO（生成式引擎优化）编辑。请在保持原主题、关键词与事实不变的前提下，'
        .'按给定的评价指标重写文章，提升其被 AI 搜索引擎收录的友好度。只输出优化后的完整 Markdown 正文，不要输出任何解释或说明。';

    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly KnowledgeRetrievalService $knowledgeRetrievalService,
        private readonly GeoArticleAuditService $auditService,
    ) {}

    /**
     * 优化一篇文章并重新评分，返回新的评分记录。
     */
    public function optimize(Article $article, ?AiModel $model = null): ArticleGeoAudit
    {
        $model = $model ?? $this->resolveModel($article);
        if ($model === null) {
            throw new RuntimeException('找不到可用的写作 AI 模型');
        }

        $audit = ArticleGeoAudit::query()
            ->where('article_id', $article->id)
            ->orderByDesc('id')
            ->first() ?? $this->auditService->audit($article);

        $knowledgeContext = $this->resolveKnowledgeContext($article);
        $prompt = $this->buildOptimizePrompt($article, $audit, $knowledgeContext);

        $optimized = trim($this->runOptimizerModel($model, $prompt));
        if ($optimized === '') {
            throw new RuntimeException('AI 优化返回为空');
        }

        $excerpt = $this->buildExcerpt($optimized);
        Article::query()->whereKey($article->id)->update([
            'content' => $optimized,
            'excerpt' => $excerpt,
            'meta_description' => mb_substr($excerpt, 0, 120),
            'updated_at' => now(),
        ]);
        $article->refresh();

        // 优化后只重新落分，不改 review_status（人工正在面板操作，发布交由原审核流程）。
        return $this->auditService->audit($article);
    }

    /**
     * 调用模型重写正文（protected，测试可覆盖以返回固定文本）。
     */
    protected function runOptimizerModel(AiModel $model, string $prompt): string
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
        $providerName = OpenAiRuntimeProvider::registerProvider('geo-optimizer', $driver, $providerUrl, $apiKey);
        $maxTokens = (int) ($model->max_tokens ?? 0);
        $agent = new MarkdownContentWriterAgent(
            instructions: self::OPTIMIZER_INSTRUCTIONS,
            maxTokens: $maxTokens > 0 ? $maxTokens : null,
        );

        try {
            $response = $agent->prompt($prompt, [], $providerName, (string) ($model->model_id ?? ''));
        } catch (Throwable $e) {
            throw new RuntimeException('AI 优化失败: '.OpenAiRuntimeProvider::normalizeApiException($e, $providerUrl), 0, $e);
        }

        return OpenAiRuntimeProvider::normalizeGeneratedText((string) ($response->text ?? ''));
    }

    private function buildOptimizePrompt(Article $article, ArticleGeoAudit $audit, string $knowledgeContext): string
    {
        $threshold = (int) config('geoflow.geo_audit.pass_threshold', 70);
        $keyword = trim((string) $article->original_keyword);

        $instructions = [];
        if ($audit->title_keyword_match < 70) {
            $instructions[] = sprintf('确保核心关键词「%s」出现在标题、首段和至少一个小标题中。', $keyword !== '' ? $keyword : $article->title);
        }
        if ($audit->structure_score < 70) {
            $instructions[] = '补全结构：使用 H2/H3 小标题分层，加入有序/无序列表，必要时加表格，并在结尾给出总结段。';
        }
        if ($audit->kb_coverage < 70) {
            $instructions[] = '结合下方知识库资料，补充权威、具体的事实与数据，提升内容可信度与引用覆盖。';
        }
        if ($audit->dup_ratio > 40) {
            $instructions[] = '与同类历史文章明显区分：增加独特视角、案例或最新信息，避免雷同表述。';
        }
        if ($instructions === []) {
            $instructions[] = '在保持主题不变的前提下，进一步提升结构清晰度、关键词覆盖与可读性。';
        }

        return implode("\n\n", array_filter([
            sprintf('【优化目标】把这篇文章的 GEO 综合分提升到 %d 分以上（当前 %d 分）。', $threshold, (int) $audit->geo_score),
            '【当前各维度分】标题↔关键词 '.$audit->title_keyword_match.' / 结构 '.$audit->structure_score
                .' / 知识库引用 '.$audit->kb_coverage.' / 历史重复度 '.$audit->dup_ratio.'（越低越好）。',
            "【具体要求】\n- ".implode("\n- ", $instructions),
            $keyword !== '' ? '【核心关键词】'.$keyword : null,
            '【原标题】'.$article->title,
            $knowledgeContext !== '' ? "【知识库资料】\n".$knowledgeContext : null,
            "【原文（Markdown，请在此基础上重写并直接输出优化后的完整 Markdown）】\n".(string) $article->content,
        ]));
    }

    private function resolveModel(Article $article): ?AiModel
    {
        if ($article->task_id) {
            $task = Task::query()->find((int) $article->task_id);
            if ($task && $task->ai_model_id) {
                $model = AiModel::query()->find((int) $task->ai_model_id);
                if ($model !== null) {
                    return $model;
                }
            }
        }

        return AiModel::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('model_type')->orWhere('model_type', '')->orWhere('model_type', 'chat');
            })
            ->orderBy('id')
            ->first();
    }

    private function resolveKnowledgeContext(Article $article): string
    {
        if (! $article->task_id) {
            return '';
        }

        $task = Task::query()->with('knowledgeBases:id')->find((int) $article->task_id);
        $knowledgeBaseIds = $task ? $task->knowledgeBases->pluck('id')->map('intval')->all() : [];
        if ($knowledgeBaseIds === []) {
            return '';
        }

        try {
            return $this->knowledgeRetrievalService->retrieveContextFromMany(
                $knowledgeBaseIds,
                trim($article->title.' '.(string) $article->original_keyword),
                5,
                2400,
            );
        } catch (Throwable $e) {
            return '';
        }
    }

    private function buildExcerpt(string $content): string
    {
        $plain = (string) preg_replace('/[#>*`\[\]\(\)!|>-]+/u', ' ', $content);
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($plain)));

        return mb_substr($plain, 0, 200);
    }
}
