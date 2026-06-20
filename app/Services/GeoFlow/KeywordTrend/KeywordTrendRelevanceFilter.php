<?php

namespace App\Services\GeoFlow\KeywordTrend;

use App\Models\AiModel;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Throwable;

use function Laravel\Ai\agent;

/**
 * Uses the active chat model to drop keywords that aren't topically relevant to the
 * source's industry category. Google Trends "related queries" are behaviorally related
 * (co-search), not semantic, so they often drift off-topic; this filter trims them.
 *
 * Fail-open by design: if there is no active chat model, the model call fails, or the
 * response can't be parsed, the original list is returned unchanged (never drops all).
 */
class KeywordTrendRelevanceFilter
{
    public function __construct(private readonly ApiKeyCrypto $apiKeyCrypto) {}

    /**
     * @param  list<NormalizedTrend>  $trends
     * @return list<NormalizedTrend>
     */
    public function filter(string $category, array $trends): array
    {
        $category = trim($category);
        if ($trends === [] || $category === '') {
            return $trends;
        }

        $model = $this->activeChatModel();
        if ($model === null) {
            return $trends;
        }

        try {
            $keep = $this->askModel($model, $category, array_map(static fn (NormalizedTrend $t): string => $t->keyword, $trends));
        } catch (Throwable) {
            return $trends;
        }

        if ($keep === null || $keep === []) {
            return $trends;
        }

        $keepSet = array_map(static fn (string $k): string => mb_strtolower(trim($k)), $keep);
        $filtered = array_values(array_filter(
            $trends,
            static fn (NormalizedTrend $t): bool => in_array(mb_strtolower(trim($t->keyword)), $keepSet, true),
        ));

        // If parsing matched nothing (likely a formatting glitch), keep the original list.
        return $filtered === [] ? $trends : $filtered;
    }

    private function activeChatModel(): ?AiModel
    {
        return AiModel::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('model_type')->orWhere('model_type', '')->orWhere('model_type', 'chat');
            })
            ->orderBy('failover_priority')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>|null
     */
    private function askModel(AiModel $model, string $category, array $keywords): ?array
    {
        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) ($model->api_url ?? ''));
        $apiKey = $this->apiKeyCrypto->decrypt((string) ($model->getRawOriginal('api_key') ?? ''));
        if ($providerUrl === '' || $apiKey === '') {
            return null;
        }

        $driver = OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) ($model->model_id ?? ''));
        $providerName = OpenAiRuntimeProvider::registerProvider('keyword_trend_relevance', $driver, $providerUrl, $apiKey);

        $system = 'You filter keyword lists by topical relevance to a given product / industry category. '
            .'Return ONLY a JSON array of the keywords (verbatim, exactly as given) that are genuinely relevant '
            .'to that category. Drop unrelated keywords. No explanation, no extra text.';
        $userPrompt = 'Category: '.$category."\nKeywords:\n".implode("\n", $keywords)
            ."\n\nReturn a JSON array of only the relevant keywords.";

        $response = agent($system)->prompt($userPrompt, [], $providerName, (string) ($model->model_id ?? ''));
        $text = OpenAiRuntimeProvider::normalizeGeneratedText((string) ($response->text ?? ''));

        return $this->parseList($text);
    }

    /**
     * @return list<string>|null
     */
    private function parseList(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Prefer a JSON array (possibly wrapped in prose / code fences).
        $json = $text;
        if (preg_match('/\[[\s\S]*\]/', $text, $matches) === 1) {
            $json = $matches[0];
        }
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $out = array_values(array_filter(
                array_map(static fn ($x): string => is_string($x) ? trim($x) : '', $decoded),
                static fn (string $s): bool => $s !== '',
            ));

            return $out === [] ? null : $out;
        }

        // Fallback: one keyword per line (strip bullets / numbering / quotes).
        $out = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/^[\s\-*\d.)]+/', '', $line));
            $line = trim($line, "\"',");
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out === [] ? null : $out;
    }
}
