<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 被监控的 GSC 站点/属性，归属于某个 Google 连接（凭据在连接上）。
 */
class GscProperty extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'gsc_connection_id',
        'name',
        'site_url',
        'schedule',
        'status',
        'created_by_admin_id',
        'last_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'gsc_connection_id' => 'integer',
            'created_by_admin_id' => 'integer',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GscConnection::class, 'gsc_connection_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(GscSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(GscSnapshot::class)->latestOfMany();
    }

    public function searchMetrics(): HasMany
    {
        return $this->hasMany(GscSearchMetric::class);
    }

    public function urlInspections(): HasMany
    {
        return $this->hasMany(GscUrlInspection::class);
    }
}
