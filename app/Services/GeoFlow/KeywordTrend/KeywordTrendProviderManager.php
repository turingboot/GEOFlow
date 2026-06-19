<?php

namespace App\Services\GeoFlow\KeywordTrend;

use App\Models\KeywordTrendSource;
use RuntimeException;

/**
 * Dispatches a source to its keyword-trend provider adapter by `sourceType()`.
 * Mirrors DistributionPublisherManager. Add new providers to the constructor +
 * the match arm as adapters are implemented.
 */
class KeywordTrendProviderManager
{
    public function __construct(
        private readonly DataForSeoProvider $dataForSeoProvider,
        private readonly SerpApiTrendsProvider $serpApiTrendsProvider,
    ) {}

    public function forSource(KeywordTrendSource $source): KeywordTrendProviderInterface
    {
        return match ($source->sourceType()) {
            'dataforseo' => $this->dataForSeoProvider,
            'serpapi' => $this->serpApiTrendsProvider,
            default => throw new RuntimeException('暂不支持的关键词趋势数据源：'.(string) $source->provider),
        };
    }
}
