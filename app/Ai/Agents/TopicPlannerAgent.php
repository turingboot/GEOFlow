<?php

namespace App\Ai\Agents;

use App\Services\GeoFlow\TopicPlan\MonthlyTopicPlannerService;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * 选题规划专用 Agent（外挂式扩展）。
 *
 * 与 {@see MarkdownContentWriterAgent} 同构，仅替换 instructions：要求模型基于趋势词、
 * 关键词库、知识库与历史文章，输出**严格 JSON 数组**的月度选题池，由
 * {@see MonthlyTopicPlannerService} 解析。
 */
#[Timeout(180)]
class TopicPlannerAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    public const DEFAULT_INSTRUCTIONS = <<<'PROMPT'
你是资深内容运营与 GEO（生成式引擎优化）选题策划。请基于给定的关键词趋势、关键词库、知识库摘要与历史文章，
规划一批高质量、避免与历史重复、且有知识库支撑的选题。

严格只输出一个 JSON 数组，不要输出任何解释或 Markdown 代码块围栏。数组每个元素形如：
{
  "title": "拟定标题（中文，吸引点击且包含核心关键词）",
  "keyword": "主关键词",
  "secondary_keywords": ["次要关键词1", "次要关键词2"],
  "rationale": "一句话选题理由",
  "heat_score": 0-100 的整数（参考趋势热度，无则给 50）,
  "kb_support": "strong | weak | none（该选题被知识库支撑的程度）",
  "dup_risk": "low | medium | high（与历史文章重复风险）"
}
PROMPT;

    /**
     * @param  iterable<int, mixed>  $messages
     * @param  iterable<int, mixed>  $tools
     */
    public function __construct(
        public string $instructions = self::DEFAULT_INSTRUCTIONS,
        public iterable $messages = [],
        public iterable $tools = [],
        public ?int $maxTokens = null,
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function messages(): iterable
    {
        return $this->messages;
    }

    public function tools(): iterable
    {
        return $this->tools;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        if (is_null($this->maxTokens) || $this->maxTokens <= 0) {
            return [];
        }

        $providerKey = $provider instanceof Lab ? $provider->value : $provider;

        return match ($providerKey) {
            'gemini' => ['maxOutputTokens' => $this->maxTokens],
            'openai' => ['max_output_tokens' => $this->maxTokens],
            default => ['max_tokens' => $this->maxTokens],
        };
    }
}
