<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteThemeReplicationVersion extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'replication_id',
        'version',
        'prompt_hash',
        'feedback',
        'blueprint_json',
        'files_json',
        'compliance_report_json',
        'draft_views_path',
        'draft_assets_path',
    ];

    protected function casts(): array
    {
        return [
            'replication_id' => 'integer',
            'tenant_id' => 'integer',
            'version' => 'integer',
            'blueprint_json' => 'array',
            'files_json' => 'array',
            'compliance_report_json' => 'array',
        ];
    }

    public function replication(): BelongsTo
    {
        return $this->belongsTo(SiteThemeReplication::class, 'replication_id');
    }
}
