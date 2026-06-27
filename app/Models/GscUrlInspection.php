<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 单个 URL 的收录状态明细（urlInspection.index.inspect 的归一化结果）。
 */
class GscUrlInspection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'gsc_snapshot_id',
        'gsc_property_id',
        'url',
        'coverage_state',
        'verdict',
        'indexing_state',
        'robots_state',
        'google_canonical',
        'last_crawl_time',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'gsc_snapshot_id' => 'integer',
            'gsc_property_id' => 'integer',
            'last_crawl_time' => 'datetime',
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
