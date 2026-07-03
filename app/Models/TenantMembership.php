<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembership extends Model
{
    protected $fillable = [
        'tenant_id',
        'membership_plan_id',
        'starts_at',
        'ends_at',
        'status',
        'remark',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'membership_plan_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'assigned_by' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_by');
    }
}
