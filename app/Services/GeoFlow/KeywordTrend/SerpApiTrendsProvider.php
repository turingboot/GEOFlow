<?php

namespace App\Services\GeoFlow\KeywordTrend;

use App\Models\KeywordTrendSource;
use App\Support\GeoFlow\OutboundHttpProxy;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * SerpApi adapter (Google Trends). Free tier ~100 searches/month, then pay-as-you-go.
 * Uses RELATED_QUERIES (last 1 month) to surface rising + top queries for each seed —
 * a good free-ish fit for "recent high-heat keywords by category". Google Trends gives
 * relative interest only (no absolute search volume). Auth: api_key (stored encrypted).
 *
 * @see https://serpapi.com/google-trends-api
 */
class SerpApiTrendsProvider extends AbstractKeywordTrendProvider
{
    private const BASE = 'https://serpapi.com/search.json';

    public function fetchTrends(KeywordTrendSource $source, array $options = []): array
    {
        // Google Trends rejects query strings longer than 100 characters.
        $seeds = array_values(array_filter(
            $this->seeds($source),
            static fn (string $s): bool => mb_strlen($s) <= 100,
        ));
        if ($seeds === []) {
            throw new RuntimeException('关键词趋势：缺少有效种子词（Google Trends 单个查询需 ≤100 字符）');
        }

        $results = [];
        foreach (array_slice($seeds, 0, 5) as $seed) {
            $json = $this->search([
                'engine' => 'google_trends',
                'data_type' => 'RELATED_QUERIES',
                'q' => $seed,
                'geo' => (string) ($source->region ?: 'US'),
                'hl' => (string) ($source->language ?: 'en'),
                'date' => 'today 1-m',
            ], $source);

            $related = is_array($json['related_queries'] ?? null) ? $json['related_queries'] : [];
            array_push($results, ...$this->mapBucket($related['rising'] ?? [], true, $source));
            array_push($results, ...$this->mapBucket($related['top'] ?? [], false, $source));
        }

        return $results;
    }

    public function healthCheck(KeywordTrendSource $source): array
    {
        try {
            $this->search([
                'engine' => 'google_trends',
                'data_type' => 'RELATED_QUERIES',
                'q' => $this->seeds($source)[0] ?? 'seo',
                'geo' => (string) ($source->region ?: 'US'),
                'date' => 'today 1-m',
            ], $source);

            return ['ok' => true, 'message' => 'SerpApi 连接正常'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  mixed  $items
     * @return list<NormalizedTrend>
     */
    private function mapBucket($items, bool $rising, KeywordTrendSource $source): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $keyword = trim((string) ($item['query'] ?? ''));
            if ($keyword === '') {
                continue;
            }

            if ($rising) {
                $value = $item['value'] ?? '';
                $extracted = (int) ($item['extracted_value'] ?? 0);
                $isBreakout = is_string($value) && stripos($value, 'breakout') !== false;
                $heat = $isBreakout ? 100 : max(75, min(100, 75 + intdiv($extracted, 100)));
                $direction = 'rising';
                $delta = $isBreakout ? null : $extracted;
            } else {
                $heat = max(0, min(100, (int) ($item['extracted_value'] ?? $item['value'] ?? 0)));
                $direction = 'flat';
                $delta = null;
            }

            $out[] = new NormalizedTrend(
                keyword: $keyword,
                heat: $heat,
                searchVolume: null,
                trendDirection: $direction,
                delta: $delta,
                region: (string) $source->region,
                language: $source->language ? (string) $source->language : null,
                raw: $item,
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function search(array $params, KeywordTrendSource $source): array
    {
        $apiKey = $this->secretFor($source);
        if ($apiKey === '') {
            throw new RuntimeException('SerpApi 密钥缺失');
        }
        $params['api_key'] = $apiKey;

        $response = Http::timeout($this->httpTimeout())
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl(self::BASE))
            ->acceptJson()
            ->get(self::BASE, $params);

        if ($response->failed()) {
            $body = $response->json();
            $detail = is_array($body) && isset($body['error']) ? (string) $body['error'] : ('HTTP '.$response->status());
            throw new RuntimeException('SerpApi 请求失败：'.$detail);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('SerpApi 响应解析失败');
        }
        if (isset($json['error'])) {
            $err = (string) $json['error'];
            // "no results for this query" is benign for low-volume seeds: skip this seed
            // (return empty) instead of failing the whole run.
            if (stripos($err, 'returned any results') !== false || stripos($err, 'no results') !== false) {
                return [];
            }
            throw new RuntimeException('SerpApi 错误：'.$err);
        }

        return $json;
    }
}
