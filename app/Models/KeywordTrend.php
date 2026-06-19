<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordTrend extends Model
{
    protected $fillable = [
        'keyword_trend_snapshot_id',
        'keyword_trend_source_id',
        'keyword',
        'heat',
        'search_volume',
        'trend_direction',
        'delta',
        'region',
        'language',
        'captured_at',
        'raw',
        'imported',
        'keyword_id',
    ];

    protected function casts(): array
    {
        return [
            'keyword_trend_snapshot_id' => 'integer',
            'keyword_trend_source_id' => 'integer',
            'heat' => 'integer',
            'search_volume' => 'integer',
            'delta' => 'integer',
            'keyword_id' => 'integer',
            'imported' => 'boolean',
            'captured_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(KeywordTrendSnapshot::class, 'keyword_trend_snapshot_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KeywordTrendSource::class, 'keyword_trend_source_id');
    }
}
