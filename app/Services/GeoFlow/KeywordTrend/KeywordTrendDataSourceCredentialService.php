<?php

namespace App\Services\GeoFlow\KeywordTrend;

use App\Models\SiteSetting;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Support\Facades\DB;

class KeywordTrendDataSourceCredentialService
{
    private const SERPAPI_KEY = 'keyword_trends_serpapi_api_key';

    public function __construct(private readonly ApiKeyCrypto $apiKeyCrypto) {}

    public function serpApiApiKey(): string
    {
        $ciphertext = (string) ($this->globalSettingValue() ?? '');

        if ($ciphertext === '') {
            return '';
        }

        return $this->apiKeyCrypto->decrypt($ciphertext);
    }

    public function hasSerpApiApiKey(): bool
    {
        return (string) ($this->globalSettingValue() ?? '') !== '';
    }

    public function saveSerpApiApiKey(string $apiKey): void
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return;
        }

        $table = (new SiteSetting)->getTable();
        $now = now();
        $encryptedApiKey = $this->apiKeyCrypto->encrypt($apiKey);

        $updated = DB::table($table)
            ->whereNull('tenant_id')
            ->where('setting_key', self::SERPAPI_KEY)
            ->update([
                'setting_value' => $encryptedApiKey,
                'updated_at' => $now,
            ]);

        if ($updated > 0) {
            return;
        }

        DB::table($table)->insert([
            'tenant_id' => null,
            'setting_key' => self::SERPAPI_KEY,
            'setting_value' => $encryptedApiKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function globalSettingValue(): ?string
    {
        return DB::table((new SiteSetting)->getTable())
            ->whereNull('tenant_id')
            ->where('setting_key', self::SERPAPI_KEY)
            ->value('setting_value');
    }
}
