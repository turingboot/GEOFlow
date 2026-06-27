<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 谷歌搜录属性的加密凭据。secret_ciphertext 为 enc:v1（AES-256-CBC），
 * 按 secret_kind 区分存的是服务账号 JSON 还是 OAuth refresh token。
 */
class GscPropertySecret extends Model
{
    use BelongsToTenant;

    public const KIND_SERVICE_ACCOUNT = 'service_account_json';

    public const KIND_OAUTH_REFRESH = 'oauth_refresh_token';

    protected $fillable = [
        'tenant_id',
        'gsc_property_id',
        'key_id',
        'secret_kind',
        'secret_ciphertext',
        'status',
        'scopes',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'gsc_property_id' => 'integer',
            'scopes' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(GscProperty::class, 'gsc_property_id');
    }
}
