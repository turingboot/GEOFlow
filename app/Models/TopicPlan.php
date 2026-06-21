<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopicPlan extends Model
{
    /** 计划状态枚举。 */
    public const STATUSES = ['draft', 'confirmed', 'dispatched', 'archived'];

    protected $fillable = [
        'name',
        'period_start',
        'period_end',
        'status',
        'source_summary',
        'ai_model_id',
        'target_title_library_id',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'source_summary' => 'array',
            'ai_model_id' => 'integer',
            'target_title_library_id' => 'integer',
            'created_by_admin_id' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(TopicPlanItem::class)->orderBy('sort_order');
    }
}
