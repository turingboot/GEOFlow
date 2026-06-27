<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 一次谷歌搜录拉取的快照（按 type 区分搜索表现 / 收录状态 / sitemap）。
 */
class GscSnapshot extends Model
{
    use BelongsToTenant;

    public const TYPE_SEARCH_ANALYTICS = 'search_analytics';

    public const TYPE_URL_INSPECTION = 'url_inspection';

    public const TYPE_SITEMAPS = 'sitemaps';

    protected $fillable = [
        'tenant_id',
        'gsc_property_id',
        'type',
        'status',
        'fetched_count',
        'stats',
        'error',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'gsc_property_id' => 'integer',
            'fetched_count' => 'integer',
            'stats' => 'array',
            'ran_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(GscProperty::class, 'gsc_property_id');
    }

    public function searchMetrics(): HasMany
    {
        return $this->hasMany(GscSearchMetric::class, 'gsc_snapshot_id');
    }

    public function urlInspections(): HasMany
    {
        return $this->hasMany(GscUrlInspection::class, 'gsc_snapshot_id');
    }
}
