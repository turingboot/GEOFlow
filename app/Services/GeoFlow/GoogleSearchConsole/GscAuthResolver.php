<?php

namespace App\Services\GeoFlow\GoogleSearchConsole;

use App\Models\GscProperty;
use App\Models\GscPropertySecret;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OutboundHttpProxy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 把属性上加密存储的凭据换成可用的 Google OAuth2 access token。
 *
 * 零第三方依赖：
 * - 服务账号：用 SA JSON 里的私钥本地签 RS256 JWT，再向 token 端点换 access token；
 * - OAuth：用 refresh token + 全局应用 client 凭据向 token 端点换 access token。
 *
 * access token 缓存到接近过期（默认按 50 分钟，Google token 通常 60 分钟）。
 */
class GscAuthResolver
{
    /** GSC 只读权限，覆盖 searchAnalytics / urlInspection / sitemaps 的读取。 */
    public const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto
    ) {}

    public function accessTokenFor(GscProperty $property): string
    {
        $secret = $property->activeSecret;
        if (! $secret instanceof GscPropertySecret) {
            throw new RuntimeException('该属性尚未配置 Google 凭据');
        }

        $cacheKey = sprintf(
            'gsc:token:%d:%d:%s',
            (int) $property->getKey(),
            (int) $secret->getKey(),
            (string) optional($secret->updated_at)?->timestamp
        );

        $token = Cache::remember($cacheKey, $this->cacheTtlSeconds(), function () use ($secret): string {
            return $this->resolveToken($secret);
        });

        if ($token === '') {
            throw new RuntimeException('获取 Google access token 失败');
        }

        return $token;
    }

    private function resolveToken(GscPropertySecret $secret): string
    {
        $plain = $this->apiKeyCrypto->decrypt((string) $secret->secret_ciphertext);
        if ($plain === '') {
            throw new RuntimeException('凭据解密失败或为空');
        }

        return $secret->secret_kind === GscPropertySecret::KIND_OAUTH_REFRESH
            ? $this->tokenFromRefreshToken($plain)
            : $this->tokenFromServiceAccount($plain);
    }

    private function tokenFromServiceAccount(string $json): string
    {
        $sa = json_decode($json, true);
        if (! is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            throw new RuntimeException('服务账号 JSON 不完整（缺 client_email / private_key）');
        }

        $tokenUri = (string) ($sa['token_uri'] ?? self::TOKEN_ENDPOINT);
        $now = time();
        $assertion = $this->signJwt(
            ['alg' => 'RS256', 'typ' => 'JWT'],
            [
                'iss' => (string) $sa['client_email'],
                'scope' => self::SCOPE,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ],
            (string) $sa['private_key']
        );

        $response = Http::asForm()
            ->timeout($this->httpTimeout())
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl($tokenUri))
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('服务账号换取 token 失败：'.$response->body());
        }

        return (string) $response->json('access_token', '');
    }

    private function tokenFromRefreshToken(string $refreshToken): string
    {
        $clientId = (string) config('geoflow.google_search_console.oauth_client_id', '');
        $clientSecret = (string) config('geoflow.google_search_console.oauth_client_secret', '');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('未配置 Google OAuth 应用凭据（GOOGLE_OAUTH_CLIENT_ID / SECRET）');
        }

        $response = Http::asForm()
            ->timeout($this->httpTimeout())
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl(self::TOKEN_ENDPOINT))
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OAuth 刷新 token 失败：'.$response->body());
        }

        return (string) $response->json('access_token', '');
    }

    /**
     * 组装并用 RS256 私钥签名 JWT（header.payload.signature，均为 base64url）。
     *
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $claims
     */
    private function signJwt(array $header, array $claims, string $privateKey): string
    {
        $segments = [
            $this->base64Url((string) json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64Url((string) json_encode($claims, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('JWT 签名失败：服务账号私钥无效');
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function httpTimeout(): int
    {
        return max(5, (int) config('geoflow.google_search_console.http_timeout', 30));
    }

    private function cacheTtlSeconds(): int
    {
        return 50 * 60;
    }
}
