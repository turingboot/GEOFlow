<?php

namespace App\Services\GeoFlow\KeywordTrend;

use App\Models\KeywordTrendSource;

interface KeywordTrendProviderInterface
{
    /**
     * Fetch + normalize trend keywords for a source.
     *
     * @param  array<string, mixed>  $options
     * @return list<NormalizedTrend>
     */
    public function fetchTrends(KeywordTrendSource $source, array $options = []): array;

    /**
     * Lightweight connectivity / credential check.
     *
     * @return array{ok: bool, message: string}
     */
    public function healthCheck(KeywordTrendSource $source): array;
}
