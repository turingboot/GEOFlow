<?php

namespace App\Services\GeoFlow\KeywordTrend;

/**
 * Provider-agnostic normalized trend keyword (one row).
 */
final class NormalizedTrend
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $keyword,
        public readonly int $heat,
        public readonly ?int $searchVolume = null,
        public readonly string $trendDirection = 'flat',
        public readonly ?int $delta = null,
        public readonly ?string $region = null,
        public readonly ?string $language = null,
        public readonly array $raw = [],
    ) {}
}
