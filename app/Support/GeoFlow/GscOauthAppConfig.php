<?php

namespace App\Support\GeoFlow;

use App\Models\SystemState;

/**
 * 平台级 Google OAuth 应用凭据（所有租户共用）。
 *
 * 优先读后台设置（SystemState['gsc_oauth_app']，client_secret 以 enc:v1 加密），
 * 缺失时回退到 config/env，从而无需在 .env 配置即可一键授权。
 */
class GscOauthAppConfig
{
    public const STATE_KEY = 'gsc_oauth_app';

    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto
    ) {}

    public function clientId(): string
    {
        $stored = trim((string) ($this->stored()['client_id'] ?? ''));

        return $stored !== '' ? $stored : trim((string) config('geoflow.google_search_console.oauth_client_id', ''));
    }

    public function clientSecret(): string
    {
        $cipher = (string) ($this->stored()['client_secret'] ?? '');
        if ($cipher !== '') {
            $plain = $this->apiKeyCrypto->decrypt($cipher);
            if ($plain !== '') {
                return $plain;
            }
        }

        return trim((string) config('geoflow.google_search_console.oauth_client_secret', ''));
    }

    public function redirectUri(): string
    {
        $stored = trim((string) ($this->stored()['redirect_uri'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $configured = trim((string) config('geoflow.google_search_console.oauth_redirect_uri', ''));

        return $configured !== '' ? $configured : route('admin.google-search-console.oauth-callback');
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    /**
     * 后台保存 OAuth 应用凭据；client_secret 留空表示保持原值。
     */
    public function update(string $clientId, string $clientSecret, string $redirectUri): void
    {
        $current = $this->stored();
        $cipher = (string) ($current['client_secret'] ?? '');
        if (trim($clientSecret) !== '') {
            $cipher = $this->apiKeyCrypto->encrypt(trim($clientSecret));
        }

        SystemState::query()->updateOrCreate(
            ['key' => self::STATE_KEY],
            ['value' => [
                'client_id' => trim($clientId),
                'client_secret' => $cipher,
                'redirect_uri' => trim($redirectUri),
            ]],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        $row = SystemState::query()->where('key', self::STATE_KEY)->first();
        $value = $row?->value;

        return is_array($value) ? $value : [];
    }
}
