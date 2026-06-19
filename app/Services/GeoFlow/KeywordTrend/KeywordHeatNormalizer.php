<?php

namespace App\Services\GeoFlow\KeywordTrend;

/**
 * Maps heterogeneous platform metrics onto a unified 0-100 heat + trend direction.
 * Heat is a relative indicator only (platforms are not directly comparable).
 */
class KeywordHeatNormalizer
{
    /**
     * Search volume -> 0-100 heat on a log scale (1 .. 1,000,000 => 0 .. 100).
     */
    public function heatFromVolume(?int $volume): int
    {
        $v = max(1, (int) $volume);
        $heat = (int) round(log10($v) / 6 * 100);

        return max(0, min(100, $heat));
    }

    /**
     * Trend direction + percent delta from a chronological value series.
     *
     * @param  list<int|float|null>  $series
     * @return array{0: string, 1: int|null} [direction, deltaPercent]
     */
    public function direction(array $series): array
    {
        $series = array_values(array_filter($series, static fn ($x): bool => $x !== null));
        if (count($series) < 2) {
            return ['flat', null];
        }

        $last = (float) $series[count($series) - 1];
        $prior = array_slice($series, 0, -1);
        $mean = array_sum($prior) / max(1, count($prior));

        if ($mean <= 0.0) {
            $delta = $last > 0 ? 100 : 0;
        } else {
            $delta = (int) round(($last - $mean) / $mean * 100);
        }

        $direction = $delta >= 10 ? 'rising' : ($delta <= -10 ? 'falling' : 'flat');

        return [$direction, $delta];
    }

    /**
     * Blend volume heat with recent trend into a final 0-100 heat.
     */
    public function blend(int $volumeHeat, string $direction): int
    {
        $adjust = match ($direction) {
            'rising' => 12,
            'falling' => -12,
            default => 0,
        };

        return max(0, min(100, $volumeHeat + $adjust));
    }
}
