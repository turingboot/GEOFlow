<?php

namespace App\Services\GeoFlow\GoogleSearchConsole;

use App\Models\GscProperty;
use App\Support\GeoFlow\OutboundHttpProxy;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Search Console REST 调用封装（只读监控）：
 * - searchAnalytics.query  搜索表现（词/页/点击/曝光/CTR/排名）
 * - urlInspection.index.inspect  单 URL 收录状态
 * - sitemaps.list  站点 sitemap 提交/已索引概览
 *
 * 鉴权交给 GscAuthResolver；出站走 OutboundHttpProxy 白名单代理。
 */
class GoogleSearchConsoleClient
{
    private const BASE = 'https://searchconsole.googleapis.com';

    public function __construct(
        private readonly GscAuthResolver $auth
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function searchAnalytics(GscProperty $property, array $body): array
    {
        $url = self::BASE.'/webmasters/v3/sites/'.rawurlencode((string) $property->site_url).'/searchAnalytics/query';

        return $this->request($property, 'post', $url, $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectUrl(GscProperty $property, string $inspectionUrl): array
    {
        $url = self::BASE.'/v1/urlInspection/index:inspect';

        return $this->request($property, 'post', $url, [
            'inspectionUrl' => $inspectionUrl,
            'siteUrl' => (string) $property->site_url,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listSitemaps(GscProperty $property): array
    {
        $url = self::BASE.'/webmasters/v3/sites/'.rawurlencode((string) $property->site_url).'/sitemaps';

        return $this->request($property, 'get', $url, []);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(GscProperty $property, string $method, string $url, array $body): array
    {
        $token = $this->auth->accessTokenFor($property);

        $pending = Http::withToken($token)
            ->timeout($this->httpTimeout())
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl($url))
            ->acceptJson();

        $response = $method === 'get'
            ? $pending->get($url)
            : $pending->post($url, $body);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('GSC API 调用失败 (%d)：%s', $response->status(), $response->body()));
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function httpTimeout(): int
    {
        return max(5, (int) config('geoflow.google_search_console.http_timeout', 30));
    }
}
