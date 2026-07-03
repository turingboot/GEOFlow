<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    protected $fillable = [
        'name',
        'article_monthly_limit',
        'knowledge_base_limit',
        'price',
        'is_custom',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'article_monthly_limit' => 'integer',
            'knowledge_base_limit' => 'integer',
            'price' => 'decimal:2',
            'is_custom' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }
}
