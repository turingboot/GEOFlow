<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ApiIdempotencyKey extends Model
{
    use BelongsToTenant;

    protected $table = 'api_idempotency_keys';

    protected $fillable = [
        'tenant_id',
        'idempotency_key',
        'route_key',
        'request_hash',
        'response_body',
        'response_status',
    ];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'tenant_id' => 'integer',
        ];
    }
}
