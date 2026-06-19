<?php

namespace App\Services\GeoFlow\KeywordTrend;

use App\Models\KeywordTrendSource;
use App\Support\GeoFlow\OutboundHttpProxy;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * DataForSEO adapter — one API covers search volume + Google Trends + keyword ideas.
 * Auth: Basic (login + API password). The API password is stored encrypted as the
 * source's active secret; the login is kept in the source config.
 *
 * @see https://docs.dataforseo.com/v3/dataforseo_labs/google/keyword_ideas/
 */
class DataForSeoProvider extends AbstractKeywordTrendProvider
{
    private const BASE = 'https://api.dataforseo.com';

    private const IDEAS_PATH = '/v3/dataforseo_labs/google/keyword_ideas/live';

    public function fetchTrends(KeywordTrendSource $source, array $options = []): array
    {
        $seeds = $this->seeds($source);
        if ($seeds === []) {
            throw new RuntimeException('关键词趋势：缺少行业品类 / 种子词');
        }

        $config = $source->resolvedConfig();
        $limit = max(1, (int) ($source->top_n ?: 50)) * 3; // over-fetch, filtered downstream

        $json = $this->post(self::IDEAS_PATH, [[
            'keywords' => array_slice($seeds, 0, 20),
            'location_name' => (string) ($config['location_name'] ?? 'United States'),
            'language_code' => (string) ($source->language ?: ($config['language_code'] ?? 'en')),
            'limit' => min(1000, $limit),
            'include_serp_info' => false,
        ]], $source);

        $items = $json['tasks'][0]['result'][0]['items'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $results = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $keyword = trim((string) ($item['keyword'] ?? ''));
            if ($keyword === '') {
                continue;
            }

            $info = is_array($item['keyword_info'] ?? null) ? $item['keyword_info'] : [];
            $volume = isset($info['search_volume']) ? (int) $info['search_volume'] : null;

            $monthly = [];
            foreach ((array) ($info['monthly_searches'] ?? []) as $m) {
                if (is_array($m) && isset($m['search_volume'])) {
                    $monthly[] = (int) $m['search_volume'];
                }
            }

            [$direction, $delta] = $this->normalizer->direction($monthly);
            $heat = $this->normalizer->blend($this->normalizer->heatFromVolume($volume), $direction);

            $results[] = new NormalizedTrend(
                keyword: $keyword,
                heat: $heat,
                searchVolume: $volume,
                trendDirection: $direction,
                delta: $delta,
                region: (string) $source->region,
                language: $source->language ? (string) $source->language : null,
                raw: $item,
            );
        }

        return $results;
    }

    public function healthCheck(KeywordTrendSource $source): array
    {
        try {
            $this->post(self::IDEAS_PATH, [[
                'keywords' => $this->seeds($source) ?: ['seo'],
                'location_name' => (string) ($source->resolvedConfig()['location_name'] ?? 'United States'),
                'language_code' => (string) ($source->language ?: 'en'),
                'limit' => 1,
            ]], $source);

            return ['ok' => true, 'message' => 'DataForSEO 连接正常'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body, KeywordTrendSource $source): array
    {
        $login = trim((string) ($source->resolvedConfig()['login'] ?? ''));
        $password = $this->secretFor($source);
        if ($login === '' || $password === '') {
            throw new RuntimeException('DataForSEO 凭证缺失（需 login + API 密码）');
        }

        $url = self::BASE.$path;
        $response = Http::timeout($this->httpTimeout())
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl($url))
            ->withBasicAuth($login, $password)
            ->acceptJson()
            ->post($url, $body);

        if ($response->failed()) {
            throw new RuntimeException('DataForSEO 请求失败：HTTP '.$response->status());
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('DataForSEO 响应解析失败');
        }

        $statusCode = (int) ($json['tasks'][0]['status_code'] ?? $json['status_code'] ?? 0);
        if ($statusCode !== 20000) {
            $message = (string) ($json['tasks'][0]['status_message'] ?? $json['status_message'] ?? '未知错误');
            throw new RuntimeException('DataForSEO 任务错误（'.$statusCode.'）：'.$message);
        }

        return $json;
    }
}
