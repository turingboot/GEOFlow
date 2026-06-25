<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'site_settings';

    protected $fillable = [
        'tenant_id',
        'setting_key',
        'setting_value',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
        ];
    }
}
