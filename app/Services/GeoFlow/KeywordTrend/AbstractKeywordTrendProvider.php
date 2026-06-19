<?php

namespace App\Services\GeoFlow\KeywordTrend;

use App\Models\KeywordTrendSource;
use App\Support\GeoFlow\ApiKeyCrypto;

abstract class AbstractKeywordTrendProvider implements KeywordTrendProviderInterface
{
    public function __construct(
        protected readonly ApiKeyCrypto $apiKeyCrypto,
        protected readonly KeywordHeatNormalizer $normalizer,
    ) {}

    /**
     * Decrypt the source's active secret (API key / password); empty string when absent.
     */
    protected function secretFor(KeywordTrendSource $source): string
    {
        $secret = $source->activeSecret;
        if ($secret === null) {
            return '';
        }

        return $this->apiKeyCrypto->decrypt((string) $secret->secret_ciphertext);
    }

    protected function httpTimeout(): int
    {
        return (int) config('geoflow.keyword_trends.http_timeout', 30);
    }

    /**
     * Seed keywords for the source; falls back to the category when no seeds set.
     *
     * @return list<string>
     */
    protected function seeds(KeywordTrendSource $source): array
    {
        $raw = is_array($source->seed_keywords) ? $source->seed_keywords : [];

        // Split each stored item on newlines / commas / semicolons (ASCII + fullwidth)
        // so semicolon- or comma-joined lists are robustly broken into individual seeds.
        $seeds = [];
        foreach ($raw as $item) {
            foreach (preg_split('/[\r\n,;，；]+/u', (string) $item) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $seeds[] = $part;
                }
            }
        }

        if ($seeds === [] && trim((string) $source->category) !== '') {
            $seeds = [trim((string) $source->category)];
        }

        return array_values(array_unique($seeds));
    }
}
