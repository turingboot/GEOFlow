<?php

namespace Tests\Unit;

use App\Services\GeoFlow\KeywordTrend\KeywordHeatNormalizer;
use Tests\TestCase;

class KeywordHeatNormalizerTest extends TestCase
{
    public function test_heat_from_volume_is_log_scaled(): void
    {
        $n = new KeywordHeatNormalizer;

        $this->assertSame(0, $n->heatFromVolume(0));
        $this->assertSame(0, $n->heatFromVolume(1));
        $this->assertSame(50, $n->heatFromVolume(1000));
        $this->assertSame(100, $n->heatFromVolume(1_000_000));
        $this->assertGreaterThanOrEqual(0, $n->heatFromVolume(-5));
    }

    public function test_direction_detects_rising_falling_flat(): void
    {
        $n = new KeywordHeatNormalizer;

        [$rising, $deltaUp] = $n->direction([10, 10, 10, 30]);
        $this->assertSame('rising', $rising);
        $this->assertGreaterThan(0, $deltaUp);

        [$falling] = $n->direction([100, 100, 100, 10]);
        $this->assertSame('falling', $falling);

        [$flat, $delta] = $n->direction([50]);
        $this->assertSame('flat', $flat);
        $this->assertNull($delta);
    }

    public function test_blend_adjusts_and_clamps(): void
    {
        $n = new KeywordHeatNormalizer;

        $this->assertSame(62, $n->blend(50, 'rising'));
        $this->assertSame(38, $n->blend(50, 'falling'));
        $this->assertSame(50, $n->blend(50, 'flat'));
        $this->assertSame(100, $n->blend(95, 'rising'));
    }
}
