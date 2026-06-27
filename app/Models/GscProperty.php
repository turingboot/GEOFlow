<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 谷歌搜录监控的「站点/属性」。一个属性对应 GSC 中一个已验证站点 + 一套凭据。
 */
class GscProperty extends Model
{
    use BelongsToTenant;

    /**
     * 支持的认证方式。
     *
     * @var list<string>
     */
    public const AUTH_TYPES = [
        'service_account',
        'oauth',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'site_url',
        'auth_type',
        'oauth_email',
        'schedule',
        'status',
        'config',
        'created_by_admin_id',
        'last_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'config' => 'array',
            'created_by_admin_id' => 'integer',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function isOauth(): bool
    {
        return $this->auth_type === 'oauth';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedConfig(): array
    {
        return is_array($this->config) ? $this->config : [];
    }

    public function secrets(): HasMany
    {
        return $this->hasMany(GscPropertySecret::class);
    }

    public function activeSecret(): HasOne
    {
        return $this->hasOne(GscPropertySecret::class)->where('status', 'active')->latestOfMany();
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(GscSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(GscSnapshot::class)->latestOfMany();
    }

    public function searchMetrics(): HasMany
    {
        return $this->hasMany(GscSearchMetric::class);
    }

    public function urlInspections(): HasMany
    {
        return $this->hasMany(GscUrlInspection::class);
    }
}
