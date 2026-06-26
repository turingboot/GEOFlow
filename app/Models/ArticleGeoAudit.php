<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleGeoAudit extends Model
{
    use BelongsToTenant;

    /** 软闸口决定枚举。 */
    public const GATE_AUTO_APPROVED = 'auto_approved';

    public const GATE_TO_REVIEW = 'to_review';

    public const GATE_PASSTHROUGH = 'passthrough';

    protected $fillable = [
        'tenant_id',
        'article_id',
        'geo_score',
        'title_keyword_match',
        'structure_score',
        'kb_coverage',
        'dup_ratio',
        'word_count',
        'gate_decision',
        'suggestion',
        'risk_notes',
        'details',
        'ai_model_id',
        'audited_at',
    ];

    protected function casts(): array
    {
        return [
            'article_id' => 'integer',
            'tenant_id' => 'integer',
            'geo_score' => 'integer',
            'title_keyword_match' => 'integer',
            'structure_score' => 'integer',
            'kb_coverage' => 'integer',
            'dup_ratio' => 'integer',
            'word_count' => 'integer',
            'ai_model_id' => 'integer',
            'risk_notes' => 'array',
            'details' => 'array',
            'audited_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }
}
