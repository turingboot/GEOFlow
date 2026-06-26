<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleImage extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'article_images';

    protected $fillable = [
        'tenant_id',
        'article_id',
        'image_id',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'article_id' => 'integer',
            'tenant_id' => 'integer',
            'image_id' => 'integer',
            'position' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'image_id');
    }
}
