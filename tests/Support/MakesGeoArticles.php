<?php

namespace Tests\Support;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;

/**
 * 测试辅助：快速构造一篇带分类/作者的文章（articles.category_id / author_id 均为 NOT NULL）。
 */
trait MakesGeoArticles
{
    protected function makeArticle(array $overrides = []): Article
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => $overrides['__category_slug'] ?? 'test-cat'],
            ['name' => $overrides['__category_name'] ?? '测试分类']
        );
        $author = Author::query()->firstOrCreate(['name' => $overrides['__author_name'] ?? 'GEOFlow 测试作者']);

        unset($overrides['__category_slug'], $overrides['__category_name'], $overrides['__author_name']);

        return Article::query()->create(array_merge([
            'title' => '测试文章',
            'slug' => 'test-article-'.uniqid(),
            'excerpt' => '',
            'content' => '正文内容',
            'category_id' => $category->id,
            'author_id' => $author->id,
            'original_keyword' => '',
            'keywords' => '',
            'status' => 'draft',
            'review_status' => 'pending',
            'is_ai_generated' => 1,
            'view_count' => 0,
        ], $overrides));
    }
}
