<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 搜索表现明细行（searchAnalytics.query 的一条：query/page + 点击/曝光/CTR/排名）。
 */
class GscSearchMetric extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'gsc_snapshot_id',
        'gsc_property_id',
        'query',
        'page',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'date_start',
        'date_end',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'gsc_snapshot_id' => 'integer',
            'gsc_property_id' => 'integer',
            'clicks' => 'integer',
            'impressions' => 'integer',
            'ctr' => 'float',
            'position' => 'float',
            'date_start' => 'date',
            'date_end' => 'date',
            'raw' => 'array',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(GscSnapshot::class, 'gsc_snapshot_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(GscProperty::class, 'gsc_property_id');
    }
}
