<?php

namespace App\Services\GeoFlow\GoogleSearchConsole;

use App\Models\GscConnection;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\GscOauthAppConfig;
use App\Support\GeoFlow\OutboundHttpProxy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 把连接上加密存储的凭据换成可用的 Google OAuth2 access token（零第三方依赖）。
 * - 服务账号：用 SA JSON 私钥本地签 RS256 JWT，再向 token 端点换 access token；
 * - OAuth：用 refresh token + 平台 OAuth 应用凭据向 token 端点换 access token。
 */
class GscAuthResolver
{
    public const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly GscOauthAppConfig $oauthApp,
    ) {}

    public function accessTokenFor(GscConnection $connection): string
    {
        $cacheKey = sprintf(
            'gsc:token:%d:%s',
            (int) $connection->getKey(),
            (string) optional($connection->updated_at)?->timestamp
        );

        $token = Cache::remember($cacheKey, 50 * 60, fn (): string => $this->resolveToken($connection));

        if ($token === '') {
            throw new RuntimeException('获取 Google access token 失败');
        }

        return $token;
    }

    private function resolveToken(GscConnection $connection): string
    {
        $plain = $this->apiKeyCrypto->decrypt((string) $connection->secret_ciphertext);
        if ($plain === '') {
            throw new RuntimeException('凭据解密失败或为空');
        }

        return $connection->secret_kind === GscConnection::KIND_OAUTH_REFRESH
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
        if (! $this->oauthApp->isConfigured()) {
            throw new RuntimeException('平台尚未配置 Google OAuth 应用');
        }

        $response = Http::asForm()
            ->timeout($this->httpTimeout())
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl(self::TOKEN_ENDPOINT))
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->oauthApp->clientId(),
                'client_secret' => $this->oauthApp->clientSecret(),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OAuth 刷新 token 失败：'.$response->body());
        }

        return (string) $response->json('access_token', '');
    }

    /**
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
}
