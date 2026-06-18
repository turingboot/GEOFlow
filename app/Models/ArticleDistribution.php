<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleDistribution extends Model
{
    protected $fillable = [
        'article_id',
        'distribution_channel_id',
        'action',
        'status',
        'remote_id',
        'remote_url',
        'remote_meta',
        'idempotency_key',
        'attempt_count',
        'next_retry_at',
        'last_attempt_at',
        'last_error_message',
        'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'article_id' => 'integer',
            'distribution_channel_id' => 'integer',
            'attempt_count' => 'integer',
            'next_retry_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'remote_meta' => 'array',
        ];
    }

    public function wordpressPostId(): ?int
    {
        if ($this->remote_id !== null && ctype_digit((string) $this->remote_id)) {
            return (int) $this->remote_id;
        }

        $meta = is_array($this->remote_meta) ? $this->remote_meta : [];
        $postId = $meta['wordpress_post_id'] ?? null;

        return is_numeric($postId) ? (int) $postId : null;
    }

    /**
     * Shopify 远端文章引用（GID 复合键），供 update/delete 复用；缺失时返回 null。
     *
     * @return array{article_gid:string, blog_gid:?string, handle:?string, blog_handle:?string}|null
     */
    public function shopifyArticleReference(): ?array
    {
        $meta = is_array($this->remote_meta) ? $this->remote_meta : [];
        $articleGid = trim((string) ($meta['shopify_article_id'] ?? ''));
        if ($articleGid === '' && is_string($this->remote_id) && str_starts_with((string) $this->remote_id, 'gid://shopify/Article/')) {
            $articleGid = (string) $this->remote_id;
        }
        if ($articleGid === '') {
            return null;
        }

        $blogGid = trim((string) ($meta['shopify_blog_id'] ?? ''));
        $handle = trim((string) ($meta['shopify_handle'] ?? ''));
        $blogHandle = trim((string) ($meta['shopify_blog_handle'] ?? ''));

        return [
            'article_gid' => $articleGid,
            'blog_gid' => $blogGid !== '' ? $blogGid : null,
            'handle' => $handle !== '' ? $handle : null,
            'blog_handle' => $blogHandle !== '' ? $blogHandle : null,
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(DistributionChannel::class, 'distribution_channel_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DistributionLog::class, 'article_distribution_id');
    }
}
