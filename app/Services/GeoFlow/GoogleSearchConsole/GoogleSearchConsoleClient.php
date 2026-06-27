<?php

namespace App\Services\GeoFlow\GoogleSearchConsole;

use App\Models\GscConnection;
use App\Models\GscProperty;
use App\Support\GeoFlow\OutboundHttpProxy;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Search Console REST 调用封装（只读监控）。鉴权用连接换取的 access token，
 * 出站走 OutboundHttpProxy 白名单代理。
 */
class GoogleSearchConsoleClient
{
    private const BASE = 'https://searchconsole.googleapis.com';

    public function __construct(
        private readonly GscAuthResolver $auth
    ) {}

    /**
     * 列出该连接在 GSC 中已验证所有权的全部站点。
     *
     * @return list<array{siteUrl:string, permissionLevel:string}>
     */
    public function listSites(GscConnection $connection): array
    {
        $data = $this->request($connection, 'get', self::BASE.'/webmasters/v3/sites', []);
        $entries = is_array($data['siteEntry'] ?? null) ? $data['siteEntry'] : [];

        $sites = [];
        foreach ($entries as $entry) {
            if (! is_array($entry) || empty($entry['siteUrl'])) {
                continue;
            }
            $sites[] = [
                'siteUrl' => (string) $entry['siteUrl'],
                'permissionLevel' => (string) ($entry['permissionLevel'] ?? ''),
            ];
        }

        return $sites;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function searchAnalytics(GscProperty $property, array $body): array
    {
        $url = self::BASE.'/webmasters/v3/sites/'.rawurlencode((string) $property->site_url).'/searchAnalytics/query';

        return $this->request($this->connectionFor($property), 'post', $url, $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectUrl(GscProperty $property, string $inspectionUrl): array
    {
        return $this->request($this->connectionFor($property), 'post', self::BASE.'/v1/urlInspection/index:inspect', [
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

        return $this->request($this->connectionFor($property), 'get', $url, []);
    }

    private function connectionFor(GscProperty $property): GscConnection
    {
        $connection = $property->connection;
        if (! $connection instanceof GscConnection) {
            throw new RuntimeException('该站点未关联 Google 连接');
        }

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(GscConnection $connection, string $method, string $url, array $body): array
    {
        $token = $this->auth->accessTokenFor($connection);

        $pending = Http::withToken($token)
            ->timeout($this->httpTimeout())
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl($url))
            ->acceptJson();

        $response = $method === 'get' ? $pending->get($url) : $pending->post($url, $body);

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
