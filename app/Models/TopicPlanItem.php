<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicPlanItem extends Model
{
    /** 条目状态枚举。 */
    public const STATUSES = ['suggested', 'confirmed', 'rejected', 'dispatched'];

    protected $fillable = [
        'topic_plan_id',
        'title',
        'keyword',
        'secondary_keywords',
        'rationale',
        'heat_score',
        'kb_support',
        'dup_risk',
        'planned_publish_at',
        'status',
        'created_title_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'topic_plan_id' => 'integer',
            'secondary_keywords' => 'array',
            'heat_score' => 'integer',
            'planned_publish_at' => 'date',
            'created_title_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TopicPlan::class, 'topic_plan_id');
    }
}
