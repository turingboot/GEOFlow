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
        $seeds = is_array($source->seed_keywords) ? $source->seed_keywords : [];
        $seeds = array_values(array_filter(
            array_map(static fn ($s): string => trim((string) $s), $seeds),
            static fn (string $s): bool => $s !== '',
        ));

        if ($seeds === [] && trim((string) $source->category) !== '') {
            $seeds = [trim((string) $source->category)];
        }

        return $seeds;
    }
}
