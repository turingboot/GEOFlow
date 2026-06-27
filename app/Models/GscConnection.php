<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 一个租户对一个 Google 账号 / 服务账号的连接，持加密凭据，名下可挂多个被监控站点。
 */
class GscConnection extends Model
{
    use BelongsToTenant;

    public const PROVIDER_OAUTH = 'oauth';

    public const PROVIDER_SERVICE_ACCOUNT = 'service_account';

    public const KIND_OAUTH_REFRESH = 'oauth_refresh_token';

    public const KIND_SERVICE_ACCOUNT = 'service_account_json';

    protected $fillable = [
        'tenant_id',
        'name',
        'provider',
        'email',
        'secret_kind',
        'secret_ciphertext',
        'status',
        'scopes',
        'last_used_at',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'created_by_admin_id' => 'integer',
        ];
    }

    public function isOauth(): bool
    {
        return $this->provider === self::PROVIDER_OAUTH;
    }

    public function properties(): HasMany
    {
        return $this->hasMany(GscProperty::class);
    }
}
