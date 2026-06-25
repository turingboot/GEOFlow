<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteThemeReplicationLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'replication_id',
        'level',
        'step',
        'message',
        'context_json',
    ];

    protected function casts(): array
    {
        return [
            'replication_id' => 'integer',
            'tenant_id' => 'integer',
            'context_json' => 'array',
        ];
    }

    public function replication(): BelongsTo
    {
        return $this->belongsTo(SiteThemeReplication::class, 'replication_id');
    }
}
