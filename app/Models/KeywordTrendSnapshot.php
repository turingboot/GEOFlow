<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeywordTrendSnapshot extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'keyword_trend_source_id',
        'status',
        'fetched_count',
        'kept_count',
        'imported_count',
        'stats',
        'error',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'keyword_trend_source_id' => 'integer',
            'tenant_id' => 'integer',
            'fetched_count' => 'integer',
            'kept_count' => 'integer',
            'imported_count' => 'integer',
            'stats' => 'array',
            'ran_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KeywordTrendSource::class, 'keyword_trend_source_id');
    }

    public function trends(): HasMany
    {
        return $this->hasMany(KeywordTrend::class, 'keyword_trend_snapshot_id');
    }
}
