<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipUsagePeriod extends Model
{
    protected $fillable = [
        'tenant_id',
        'tenant_membership_id',
        'period_key',
        'period_starts_at',
        'period_ends_at',
        'article_published_used',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'tenant_membership_id' => 'integer',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'article_published_used' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'tenant_membership_id');
    }
}
