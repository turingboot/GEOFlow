<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'owner_admin_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'owner_admin_id' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'owner_admin_id');
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class, 'tenant_id');
    }
}
