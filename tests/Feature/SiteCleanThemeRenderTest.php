<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\SiteSetting;
use App\Support\Site\SiteSettingsBag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteCleanThemeRenderTest extends TestCase
{
    use RefreshDatabase;

    private function activateTheme(): void
    {
        SiteSetting::query()->updateOrCreate(
            ['setting_key' => 'active_theme'],
            ['setting_value' => 'geoflow-clean-20260618']
        );
        SiteSettingsBag::forget();
    }

    private function seedArticle(): Article
    {
        $category = Category::query()->create(['name' => '科技资讯', 'slug' => 'tech']);
        $author = Author::query()->create(['name' => 'GEOFlow']);

        return Article::query()->create([
            'title' => 'Clean 主题渲染测试',
            'slug' => 'clean-theme-render-test',
            'excerpt' => '这是一段摘要',
            'content' => "## 小节\n\n正文内容。",
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);
    }

    public function test_clean_theme_renders_home_with_its_layout_and_assets(): void
    {
        $this->activateTheme();
        $article = $this->seedArticle();

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('themes/geoflow-clean-20260618/theme.css', false)
            ->assertSee('gc-accent-bar', false)
            ->assertSee('gc-card', false)
            ->assertSee($article->title);
    }

    public function test_clean_theme_renders_article_category_and_archive(): void
    {
        $this->activateTheme();
        $article = $this->seedArticle();

        $this->get(route('site.article', $article->slug))
            ->assertOk()
            ->assertSee('gc-article-title', false)
            ->assertSee('article-prose', false)
            ->assertSee($article->title);

        $this->get(route('site.category', 'tech'))
            ->assertOk()
            ->assertSee('gc-card', false)
            ->assertSee($article->title);

        $this->get(route('site.archive'))
            ->assertOk()
            ->assertSee('gc-page-title', false);
    }
}
