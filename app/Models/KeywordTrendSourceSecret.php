<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordTrendSourceSecret extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'keyword_trend_source_id',
        'key_id',
        'secret_ciphertext',
        'status',
        'scopes',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'keyword_trend_source_id' => 'integer',
            'tenant_id' => 'integer',
            'scopes' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KeywordTrendSource::class, 'keyword_trend_source_id');
    }
}
