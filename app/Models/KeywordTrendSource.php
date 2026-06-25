<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KeywordTrendSource extends Model
{
    use BelongsToTenant;

    /**
     * Supported keyword-trend data providers (pluggable adapters).
     *
     * @var list<string>
     */
    public const PROVIDERS = [
        'dataforseo',
        'serpapi',
        'semrush',
        'ahrefs',
        'google_keyword_planner',
        'keywords_everywhere',
        'generic_http_api',
    ];

    protected $fillable = [
        'name',
        'tenant_id',
        'provider',
        'category',
        'seed_keywords',
        'region',
        'language',
        'timeframe',
        'heat_threshold',
        'top_n',
        'target_keyword_library_id',
        'auto_import',
        'ai_relevance',
        'schedule',
        'status',
        'config',
        'created_by_admin_id',
        'last_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'seed_keywords' => 'array',
            'tenant_id' => 'integer',
            'config' => 'array',
            'auto_import' => 'boolean',
            'ai_relevance' => 'boolean',
            'heat_threshold' => 'integer',
            'top_n' => 'integer',
            'target_keyword_library_id' => 'integer',
            'created_by_admin_id' => 'integer',
            'last_fetched_at' => 'datetime',
        ];
    }

    /**
     * Whitelisted provider key (empty string when unknown) — the manager dispatches on this.
     */
    public function sourceType(): string
    {
        $provider = (string) $this->provider;

        return in_array($provider, self::PROVIDERS, true) ? $provider : '';
    }

    public function isAutoImport(): bool
    {
        return (bool) $this->auto_import;
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
        return $this->hasMany(KeywordTrendSourceSecret::class);
    }

    public function activeSecret(): HasOne
    {
        return $this->hasOne(KeywordTrendSourceSecret::class)->where('status', 'active')->latestOfMany();
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(KeywordTrendSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(KeywordTrendSnapshot::class)->latestOfMany();
    }

    public function trends(): HasMany
    {
        return $this->hasMany(KeywordTrend::class);
    }

    public function targetLibrary(): BelongsTo
    {
        return $this->belongsTo(KeywordLibrary::class, 'target_keyword_library_id');
    }
}
