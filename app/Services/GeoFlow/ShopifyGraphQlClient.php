<?php

namespace App\Services\GeoFlow;

use App\Models\DistributionChannel;
use App\Models\DistributionChannelSecret;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OutboundHttpProxy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shopify Admin GraphQL 客户端：统一封装鉴权、请求与三层错误判定。
 *
 * 错误模型（GraphQL 逻辑失败也返回 HTTP 200）：
 * 1. HTTP 层失败（401/403/5xx）——消息含状态码，交由 DistributionRetryPolicy 判断重试；
 * 2. 顶层 errors[]——其中 THROTTLED 抛出含 "429" 的消息以命中重试策略的可重试分支；
 * 3. data.<mutation>.userErrors[]——确定性业务错误，默认不重试。
 */
class ShopifyGraphQlClient
{
    public function __construct(private readonly ApiKeyCrypto $apiKeyCrypto) {}

    /**
     * 执行一次 GraphQL 调用，返回 data 节点。
     *
     * @param  array<string,mixed>  $variables
     * @return array<string,mixed>
     */
    public function execute(
        DistributionChannel $channel,
        string $query,
        array $variables = [],
        ?string $mutationKey = null,
        string $operation = 'Shopify GraphQL',
        int $timeout = 30
    ): array {
        $token = $this->resolveAccessToken($channel, $timeout);

        $response = Http::timeout($timeout)
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl($channel->shopifyGraphqlUrl()))
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-Shopify-Access-Token' => $token])
            ->post($channel->shopifyGraphqlUrl(), [
                'query' => $query,
                // 空变量需序列化为 JSON 对象 {} 而非数组 []；非空关联数组本身即编码为对象。
                'variables' => $variables === [] ? (object) [] : $variables,
            ]);

        return $this->parse($response, $mutationKey, $operation);
    }

    /**
     * 解析渠道实际使用的 Admin API access token。
     *
     * - access_token 模式：存储的密文本身即长期 token（旧版自建应用 / CLI token）。
     * - client_credentials 模式：用 Client ID + Client Secret 向 token 端点换取 24h 短期 token，
     *   按 expires_in 缓存复用（留 5 分钟安全边界），到期自动重新换取。
     */
    private function resolveAccessToken(DistributionChannel $channel, int $timeout): string
    {
        $channel->loadMissing('activeSecret');
        $secret = $channel->activeSecret;
        if (! $secret instanceof DistributionChannelSecret) {
            throw new RuntimeException('Shopify 渠道缺少访问凭据。');
        }

        $secretValue = $this->apiKeyCrypto->decrypt((string) $secret->secret_ciphertext);
        if ($secretValue === '') {
            throw new RuntimeException('Shopify 访问凭据解密失败。');
        }

        $config = $channel->resolvedShopifyConfig();
        if ($config['shopify_auth_mode'] !== 'client_credentials') {
            return $secretValue;
        }

        $clientId = (string) $config['shopify_client_id'];
        if ($clientId === '') {
            throw new RuntimeException('Shopify client credentials 模式缺少 Client ID。');
        }

        $cacheKey = 'geoflow:shopify_cc_token:'.(int) $channel->id;
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::timeout($timeout)
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl($channel->shopifyOAuthTokenUrl()))
            ->asForm()
            ->acceptJson()
            ->post($channel->shopifyOAuthTokenUrl(), [
                'client_id' => $clientId,
                'client_secret' => $secretValue,
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Shopify 获取访问令牌失败：HTTP '.$response->status().$this->summarizeBody($response));
        }

        $json = $response->json();
        $token = is_array($json) ? trim((string) ($json['access_token'] ?? '')) : '';
        if ($token === '') {
            throw new RuntimeException('Shopify 访问令牌响应缺少 access_token。');
        }

        $expiresIn = is_array($json) ? (int) ($json['expires_in'] ?? 86399) : 86399;
        Cache::put($cacheKey, $token, max(60, $expiresIn - 300));

        return $token;
    }

    /**
     * @return array<string,mixed>
     */
    private function parse(Response $response, ?string $mutationKey, string $operation): array
    {
        if ($response->failed()) {
            throw new RuntimeException($operation.'失败：HTTP '.$response->status().$this->summarizeBody($response));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException($operation.'失败：响应不是有效 JSON。');
        }

        $errors = is_array($json['errors'] ?? null) ? $json['errors'] : [];
        if ($errors !== []) {
            if ($this->isThrottled($errors)) {
                // 含 "429" 以命中 DistributionRetryPolicy 的可重试分支（无需修改该策略）。
                throw new RuntimeException($operation.'被限流（THROTTLED，等同 HTTP 429）：'.$this->joinTopLevelErrors($errors));
            }

            throw new RuntimeException($operation.'失败：'.$this->joinTopLevelErrors($errors));
        }

        $data = is_array($json['data'] ?? null) ? $json['data'] : [];

        if ($mutationKey !== null) {
            $node = is_array($data[$mutationKey] ?? null) ? $data[$mutationKey] : [];
            $userErrors = is_array($node['userErrors'] ?? null) ? $node['userErrors'] : [];
            if ($userErrors !== []) {
                throw new RuntimeException($operation.'失败：'.$this->joinUserErrors($userErrors));
            }
        }

        return $data;
    }

    /**
     * @param  list<array<string,mixed>>  $errors
     */
    private function isThrottled(array $errors): bool
    {
        foreach ($errors as $error) {
            $extensions = is_array($error['extensions'] ?? null) ? $error['extensions'] : [];
            if (strtoupper((string) ($extensions['code'] ?? '')) === 'THROTTLED') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string,mixed>>  $errors
     */
    private function joinTopLevelErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $error) {
            $message = trim((string) ($error['message'] ?? ''));
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        return $messages === [] ? '未知错误' : implode('；', array_slice($messages, 0, 5));
    }

    /**
     * @param  list<array<string,mixed>>  $userErrors
     */
    private function joinUserErrors(array $userErrors): string
    {
        $messages = [];
        foreach ($userErrors as $error) {
            $field = is_array($error['field'] ?? null) ? implode('.', array_map('strval', $error['field'])) : (string) ($error['field'] ?? '');
            $message = trim((string) ($error['message'] ?? ''));
            if ($message === '') {
                continue;
            }
            $messages[] = $field !== '' ? $field.': '.$message : $message;
        }

        return $messages === [] ? '未知业务错误' : implode('；', array_slice($messages, 0, 5));
    }

    private function summarizeBody(Response $response): string
    {
        $body = preg_replace('/\s+/', ' ', trim(strip_tags((string) $response->body())));
        if (! is_string($body) || $body === '') {
            return '';
        }

        return ' '.(mb_strlen($body) > 300 ? mb_substr($body, 0, 300).'...' : $body);
    }
}
