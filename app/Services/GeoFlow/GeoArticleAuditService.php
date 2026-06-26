<?php

namespace App\Services\GeoFlow;

use App\Models\Article;
use App\Models\ArticleGeoAudit;
use App\Models\Task;
use Throwable;

/**
 * GEO 质检层（外挂式扩展）。
 *
 * 文章生成后对其打 GEO 友好度分（四维），并由软闸口决定建议：
 * - 达标：建议自动通过（auto_approved）
 * - 不达标：转人工审核（pending）
 *
 * 评分本身不依赖 AI 调用（三维纯本地规则 + 一维复用现有 RAG），确定可测；
 * 不修改主链路逻辑，仅由 {@see auditAndApplyGate()} 调整草稿的 review_status。
 */
class GeoArticleAuditService
{
    public function __construct(
        private readonly KnowledgeRetrievalService $knowledgeRetrievalService
    ) {}

    /**
     * 评分并落库（不改 review_status，职责单一，便于测试）。
     *
     * @param  array{keyword?:string, knowledge_base_ids?:array<int,int>}  $context
     */
    public function audit(Article $article, array $context = []): ArticleGeoAudit
    {
        $title = (string) $article->title;
        $content = (string) $article->content;
        $keyword = trim((string) ($context['keyword'] ?? $article->original_keyword ?? ''));
        $knowledgeBaseIds = $this->resolveKnowledgeBaseIds($article, $context);

        $titleKeyword = $this->scoreTitleKeyword($title, $content, $keyword);
        $structure = $this->scoreStructure($content);
        $kbCoverage = $this->scoreKbCoverage($knowledgeBaseIds, trim($title.' '.$keyword), $content);
        $dedup = $this->scoreDuplication($article, $keyword);

        $geoScore = $this->compositeScore(
            $titleKeyword['score'],
            $structure['score'],
            $kbCoverage['score'],
            100 - $dedup['ratio'],
        );

        $risks = $this->buildRiskNotes($titleKeyword['score'], $structure['score'], $kbCoverage['score'], $dedup['ratio']);
        $gate = $this->gateDecision($geoScore, (string) $article->review_status);

        return ArticleGeoAudit::query()->create([
            'tenant_id' => (int) ($article->tenant_id ?? 0) ?: null,
            'article_id' => (int) $article->id,
            'geo_score' => $geoScore,
            'title_keyword_match' => $titleKeyword['score'],
            'structure_score' => $structure['score'],
            'kb_coverage' => $kbCoverage['score'],
            'dup_ratio' => $dedup['ratio'],
            'word_count' => $this->wordCount($content),
            'gate_decision' => $gate,
            'suggestion' => $this->buildSuggestion($gate, $risks),
            'risk_notes' => $risks,
            'details' => [
                'title_keyword' => $titleKeyword,
                'structure' => $structure,
                'kb_coverage' => $kbCoverage,
                'dedup' => $dedup,
                'threshold' => (int) config('geoflow.geo_audit.pass_threshold', 70),
            ],
            'audited_at' => now(),
        ]);
    }

    /**
     * 评分 + 软闸口：根据评分决定是否把草稿打回人工审核或标记自动通过。
     *
     * @param  array{keyword?:string, knowledge_base_ids?:array<int,int>}  $context
     */
    public function auditAndApplyGate(int $articleId, array $context = []): ?ArticleGeoAudit
    {
        $article = Article::query()->find($articleId);
        if (! $article) {
            return null;
        }

        $audit = $this->audit($article, $context);

        $newReviewStatus = match ($audit->gate_decision) {
            ArticleGeoAudit::GATE_TO_REVIEW => 'pending',
            ArticleGeoAudit::GATE_AUTO_APPROVED => 'auto_approved',
            default => null,
        };

        if ($newReviewStatus !== null && $newReviewStatus !== (string) $article->review_status) {
            Article::query()->whereKey($article->id)->update(['review_status' => $newReviewStatus]);
        }

        return $audit;
    }

    /**
     * @param  array{keyword?:string, knowledge_base_ids?:array<int,int>}  $context
     * @return array<int,int>
     */
    private function resolveKnowledgeBaseIds(Article $article, array $context): array
    {
        if (isset($context['knowledge_base_ids']) && is_array($context['knowledge_base_ids'])) {
            return array_values(array_map('intval', $context['knowledge_base_ids']));
        }

        if (! $article->task_id) {
            return [];
        }

        $task = Task::query()->with('knowledgeBases:id')->find((int) $article->task_id);

        return $task ? $task->knowledgeBases->pluck('id')->map('intval')->all() : [];
    }

    /**
     * 维度一：标题↔关键词匹配（关键词是否出现在标题/首段/小标题）。
     *
     * @return array{score:int, in_title:bool, in_lead:bool, in_heading:bool}
     */
    private function scoreTitleKeyword(string $title, string $content, string $keyword): array
    {
        if ($keyword === '') {
            // 无关键词无法评估，给中性分。
            return ['score' => 60, 'in_title' => false, 'in_lead' => false, 'in_heading' => false];
        }

        $kw = mb_strtolower($keyword);
        $inTitle = mb_stripos($title, $kw) !== false;
        $lead = mb_substr(strip_tags($content), 0, 200);
        $inLead = mb_stripos($lead, $kw) !== false;

        $inHeading = false;
        foreach ($this->extractHeadings($content) as $heading) {
            if (mb_stripos($heading, $kw) !== false) {
                $inHeading = true;
                break;
            }
        }

        $score = ($inTitle ? 50 : 0) + ($inLead ? 25 : 0) + ($inHeading ? 25 : 0);

        return ['score' => min(100, $score), 'in_title' => $inTitle, 'in_lead' => $inLead, 'in_heading' => $inHeading];
    }

    /**
     * 维度二：内容结构完整度（小标题/列表/表格/段落）。
     *
     * @return array{score:int, headings:int, has_list:bool, has_table:bool, paragraphs:int}
     */
    private function scoreStructure(string $content): array
    {
        $headings = count($this->extractHeadings($content));
        $hasList = (bool) preg_match('/^\s*([-*+]|\d+\.)\s+/m', $content);
        $hasTable = str_contains($content, '|') && (bool) preg_match('/\|.*\|/', $content);
        $paragraphs = count(array_filter(preg_split('/\n\s*\n/', trim($content)) ?: [], static fn ($p): bool => trim((string) $p) !== ''));

        $score = 0;
        $score += $headings >= 1 ? 30 : 0;
        $score += $headings >= 2 ? 20 : 0;
        $score += $hasList ? 20 : 0;
        $score += $hasTable ? 15 : 0;
        $score += $paragraphs >= 3 ? 15 : 0;

        return [
            'score' => min(100, $score),
            'headings' => $headings,
            'has_list' => $hasList,
            'has_table' => $hasTable,
            'paragraphs' => $paragraphs,
        ];
    }

    /**
     * 维度三：知识库引用覆盖（复用现有 RAG 证据，算正文对证据的 token 覆盖）。
     * 无知识库或检索不可用时回退中性分，保证主链路与测试不受影响。
     *
     * @param  array<int,int>  $knowledgeBaseIds
     * @return array{score:int, evidence_count:int, note:string}
     */
    private function scoreKbCoverage(array $knowledgeBaseIds, string $query, string $content): array
    {
        if ($knowledgeBaseIds === [] || trim($query) === '') {
            return ['score' => 60, 'evidence_count' => 0, 'note' => 'no_knowledge_base'];
        }

        try {
            $evidence = $this->knowledgeRetrievalService->retrieveEvidenceFromMany($knowledgeBaseIds, $query, 8);
        } catch (Throwable $e) {
            return ['score' => 60, 'evidence_count' => 0, 'note' => 'retrieval_unavailable'];
        }

        if ($evidence === []) {
            return ['score' => 60, 'evidence_count' => 0, 'note' => 'no_evidence'];
        }

        $articleTokens = array_flip($this->tokenize($content));
        $count = 0;
        $coverageSum = 0.0;
        foreach (array_slice($evidence, 0, 5) as $row) {
            $evidenceTokens = $this->tokenize((string) ($row['content'] ?? ''));
            if ($evidenceTokens === []) {
                continue;
            }
            $hit = 0;
            foreach ($evidenceTokens as $token) {
                if (isset($articleTokens[$token])) {
                    $hit++;
                }
            }
            $coverageSum += $hit / count($evidenceTokens);
            $count++;
        }

        $score = $count > 0 ? (int) round(($coverageSum / $count) * 100) : 60;

        return ['score' => min(100, $score), 'evidence_count' => count($evidence), 'note' => 'computed'];
    }

    /**
     * 维度四：历史重复度（与窗口内其它文章的标题+关键词相似度，取最高；越高越差）。
     *
     * @return array{ratio:int, against:int|null, compared:int}
     */
    private function scoreDuplication(Article $article, string $keyword): array
    {
        $windowDays = (int) config('geoflow.geo_audit.dedup_window_days', 90);
        $selfTokens = $this->tokenize($article->title.' '.$keyword);
        if ($selfTokens === []) {
            return ['ratio' => 0, 'against' => null, 'compared' => 0];
        }

        $others = Article::query()
            ->where('id', '!=', (int) $article->id)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'title', 'original_keyword']);

        $maxRatio = 0.0;
        $against = null;
        foreach ($others as $other) {
            $otherTokens = $this->tokenize($other->title.' '.(string) $other->original_keyword);
            $similarity = $this->jaccard($selfTokens, $otherTokens);
            if ($similarity > $maxRatio) {
                $maxRatio = $similarity;
                $against = (int) $other->id;
            }
        }

        return ['ratio' => (int) round($maxRatio * 100), 'against' => $against, 'compared' => $others->count()];
    }

    private function compositeScore(int $titleKeyword, int $structure, int $kbCoverage, int $dedup): int
    {
        $weights = (array) config('geoflow.geo_audit.weights', []);
        $parts = [
            'title_keyword_match' => $titleKeyword,
            'structure' => $structure,
            'kb_coverage' => $kbCoverage,
            'dedup' => $dedup,
        ];

        $weightSum = array_sum(array_map('floatval', $weights));
        if ($weightSum <= 0) {
            return (int) round(array_sum($parts) / count($parts));
        }

        $weighted = 0.0;
        foreach ($parts as $key => $value) {
            $weighted += (float) ($weights[$key] ?? 0) * $value;
        }

        return max(0, min(100, (int) round($weighted / $weightSum)));
    }

    private function gateDecision(int $geoScore, string $currentReviewStatus): string
    {
        $threshold = (int) config('geoflow.geo_audit.pass_threshold', 70);

        if ($geoScore < $threshold) {
            return ArticleGeoAudit::GATE_TO_REVIEW;
        }

        if ($currentReviewStatus === 'approved') {
            return ArticleGeoAudit::GATE_AUTO_APPROVED;
        }

        return ArticleGeoAudit::GATE_PASSTHROUGH;
    }

    /**
     * @return list<string>
     */
    private function buildRiskNotes(int $titleKeyword, int $structure, int $kbCoverage, int $dupRatio): array
    {
        $risks = [];
        if ($titleKeyword < 60) {
            $risks[] = '标题/正文关键词覆盖不足';
        }
        if ($structure < 50) {
            $risks[] = '缺少小标题、列表或表格等 GEO 友好结构';
        }
        if ($kbCoverage < 50) {
            $risks[] = '知识库引用覆盖偏低';
        }
        if ($dupRatio > 40) {
            $risks[] = '与历史文章相似度偏高';
        }

        return $risks;
    }

    /**
     * @param  list<string>  $risks
     */
    private function buildSuggestion(string $gate, array $risks): string
    {
        $base = match ($gate) {
            ArticleGeoAudit::GATE_AUTO_APPROVED => 'GEO 评分达标，建议自动通过并收录。',
            ArticleGeoAudit::GATE_TO_REVIEW => 'GEO 评分低于阈值，建议转人工审核后再发布。',
            default => 'GEO 评分达标，按原审核流程处理。',
        };

        return $risks === [] ? $base : $base.' 短板：'.implode('；', $risks).'。';
    }

    /**
     * @return list<string>
     */
    private function extractHeadings(string $content): array
    {
        if (! preg_match_all('/^\s{0,3}#{1,6}\s+(.+?)\s*$/m', $content, $matches)) {
            return [];
        }

        return array_map('trim', $matches[1]);
    }

    private function wordCount(string $content): int
    {
        $cjk = preg_match_all('/\p{Han}/u', $content) ?: 0;
        $latin = preg_match_all('/[A-Za-z0-9]+/u', $content) ?: 0;

        return $cjk + $latin;
    }

    /**
     * 分词：拉丁词 + 中文二元组（bigram），用于覆盖率与相似度计算。
     *
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower(trim($text));
        $tokens = [];

        if (preg_match_all('/[a-z0-9]{2,}/u', $text, $latinMatches)) {
            foreach ($latinMatches[0] as $word) {
                $tokens[$word] = true;
            }
        }

        if (preg_match_all('/\p{Han}/u', $text, $hanMatches)) {
            $chars = $hanMatches[0];
            $length = count($chars);
            if ($length === 1) {
                $tokens[$chars[0]] = true;
            }
            for ($i = 0; $i + 1 < $length; $i++) {
                $tokens[$chars[$i].$chars[$i + 1]] = true;
            }
        }

        return array_keys($tokens);
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $setB = array_flip($b);
        $intersection = 0;
        foreach ($a as $token) {
            if (isset($setB[$token])) {
                $intersection++;
            }
        }

        $union = count($a) + count($b) - $intersection;

        return $union > 0 ? $intersection / $union : 0.0;
    }
}
