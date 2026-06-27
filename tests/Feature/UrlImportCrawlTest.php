<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeChunk;
use App\Models\UrlImportJob;
use App\Services\GeoFlow\UrlImportProcessingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class UrlImportCrawlTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_html_extracts_nav_tab_links_and_images_before_stripping(): void
    {
        $html = '<html><head><meta property="og:image" content="/og.png"></head>'
            .'<body><header><nav>'
            .'<a href="/products">Products</a>'
            .'<a href="/about#team">About</a>'
            .'<a href="https://external.com/x">External</a>'
            .'</nav></header>'
            .'<main><h1>Seed</h1><p>Seed body text here.</p>'
            .'<img src="/img/a.jpg"><img data-src="https://cdn.example.com/b.png"></main>'
            .'<footer><a href="/legal">Legal</a></footer></body></html>';

        $parsed = $this->invoke('parseHtml', [$html, 'https://example.com/']);

        // 导航 tab 链接在删 nav 之前已抽取;fragment 去除;外站过滤。
        $this->assertContains('https://example.com/products', $parsed['links']);
        $this->assertContains('https://example.com/about', $parsed['links']);
        $this->assertContains('https://example.com/legal', $parsed['links']);
        $this->assertNotContains('https://external.com/x', $parsed['links']);

        // 图片解析为绝对地址,含懒加载与 og:image。
        $this->assertContains('https://example.com/img/a.jpg', $parsed['images']);
        $this->assertContains('https://cdn.example.com/b.png', $parsed['images']);
        $this->assertContains('https://example.com/og.png', $parsed['images']);

        // 正文仍正常抽取(nav/footer 已删)。
        $this->assertStringContainsString('Seed body text here', $parsed['text']);
    }

    public function test_resolve_url_handles_relative_protocol_and_dotdot(): void
    {
        $this->assertSame('https://example.com/a/b', $this->invoke('resolveUrl', ['/a/b', 'https://example.com/x/y']));
        $this->assertSame('https://example.com/x/c', $this->invoke('resolveUrl', ['c', 'https://example.com/x/y']));
        $this->assertSame('https://example.com/c', $this->invoke('resolveUrl', ['../c', 'https://example.com/x/y']));
        $this->assertSame('https://cdn.test/a.png', $this->invoke('resolveUrl', ['//cdn.test/a.png', 'https://example.com/']));
        $this->assertSame('', $this->invoke('resolveUrl', ['mailto:a@b.com', 'https://example.com/']));
        $this->assertSame('', $this->invoke('resolveUrl', ['#top', 'https://example.com/']));
    }

    public function test_crawl_aggregates_secondary_pages_and_dedupes_images(): void
    {
        Http::fake([
            'https://example.com/products' => Http::response('<html><body><main><h1>Products</h1><p>Our products list AAA.</p><img src="https://cdn.example.com/p.png"></main></body></html>'),
            'https://example.com/about' => Http::response('<html><body><main><h1>About</h1><p>About company BBB.</p><img src="https://cdn.example.com/p.png"></main></body></html>'),
        ]);

        $job = $this->makeJob(['crawl_secondary' => true, 'max_secondary_pages' => 20, 'download_images' => true, 'max_images' => 50]);
        $seedParsed = [
            'title' => 'Seed',
            'text' => 'Seed body CCC.',
            'summary' => '',
            'links' => ['https://example.com/products', 'https://example.com/about'],
            'images' => ['https://cdn.example.com/seed.png'],
        ];

        $crawl = $this->invoke('crawlSecondaryPages', [$job, 'https://example.com/', $seedParsed]);

        $this->assertCount(3, $crawl['pages']); // 种子 + 2 个二级页
        $this->assertStringContainsString('## 来源: https://example.com/products', $crawl['kb_corpus']);
        $this->assertStringContainsString('Our products list AAA', $crawl['kb_corpus']);

        // 图片去重:seed.png + p.png(出现两次)=> 2 个唯一 URL。
        $this->assertContains('https://cdn.example.com/seed.png', $crawl['image_urls']);
        $this->assertContains('https://cdn.example.com/p.png', $crawl['image_urls']);
        $this->assertCount(2, $crawl['image_urls']);
    }

    public function test_crawl_disabled_keeps_single_page_and_no_images(): void
    {
        $job = $this->makeJob(['crawl_secondary' => false, 'download_images' => false]);
        $seedParsed = [
            'title' => 'Seed',
            'text' => 'Seed body.',
            'summary' => '',
            'links' => ['https://example.com/products'],
            'images' => ['https://cdn.example.com/seed.png'],
        ];

        $crawl = $this->invoke('crawlSecondaryPages', [$job, 'https://example.com/', $seedParsed]);

        $this->assertCount(1, $crawl['pages']);
        $this->assertSame([], $crawl['image_urls']); // download_images 关 => 不收集
        Http::assertNothingSent();
    }

    public function test_commit_builds_knowledge_chunks_and_image_library(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available.');
        }

        Storage::fake('public');
        $bigPng = $this->pngBytes(300, 300);
        $smallPng = $this->pngBytes(50, 50);
        Http::fake([
            'https://cdn.example.com/big.png' => Http::response($bigPng, 200, ['Content-Type' => 'image/png']),
            'https://cdn.example.com/small.png' => Http::response($smallPng, 200, ['Content-Type' => 'image/png']),
            'https://cdn.example.com/dup.png' => Http::response($bigPng, 200, ['Content-Type' => 'image/png']),
        ]);

        $result = [
            'source' => ['normalized_url' => 'https://example.com/', 'domain' => 'example.com'],
            'page' => ['title' => 'Example Site', 'text' => 'seed text'],
            'analysis' => [
                'summary' => 'A summary',
                'library_name' => 'Example Site',
                'knowledge_markdown' => "# Example\n\nKnowledge content about the example site and products.",
                'keywords' => ['alpha', 'beta', 'gamma'],
                'titles' => ['Title one', 'Title two'],
            ],
            'crawl' => [
                'enabled' => true,
                'download_images' => true,
                'image_urls' => [
                    'https://cdn.example.com/big.png',
                    'https://cdn.example.com/small.png',
                    'https://cdn.example.com/dup.png',
                ],
                'corpus' => "## 来源: https://example.com/\nseed text\n\n## 来源: https://example.com/products\nproducts text",
            ],
            'import' => ['status' => 'preview', 'summary' => null],
        ];

        $job = $this->makeJob([], $result);

        $summary = app(UrlImportProcessingService::class)->commit($job);

        // 知识库 + 切片(入库即激活)。
        $this->assertGreaterThan(0, $summary['knowledge_base']);
        $kb = KnowledgeBase::query()->findOrFail($summary['knowledge_base']);
        $this->assertStringContainsString('采集正文(多页聚合)', (string) $kb->content);
        $this->assertStringContainsString('products text', (string) $kb->content);
        $this->assertGreaterThan(0, $summary['chunks']);
        $this->assertGreaterThan(0, KnowledgeChunk::query()->where('knowledge_base_id', $kb->id)->count());

        // 图片库:big 入库;small 滤(<200px);dup 与 big 同字节去重 => 1 张。
        $this->assertGreaterThan(0, $summary['image_library']);
        $this->assertSame(1, $summary['images']);
        $library = ImageLibrary::query()->findOrFail($summary['image_library']);
        $this->assertSame(1, (int) $library->image_count);
        $this->assertSame(1, Image::query()->where('library_id', $library->id)->count());

        $image = Image::query()->where('library_id', $library->id)->firstOrFail();
        $this->assertSame(300, (int) $image->width);
        $this->assertStringStartsWith('storage/tenants/'.TenantContext::id().'/uploads/images/', (string) $image->file_path);
    }

    private function invoke(string $method, array $args): mixed
    {
        $service = app(UrlImportProcessingService::class);
        $reflection = new ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($service, ...$args);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $result
     */
    private function makeJob(array $options, array $result = []): UrlImportJob
    {
        return UrlImportJob::query()->create([
            'url' => 'https://example.com/',
            'normalized_url' => 'https://example.com/',
            'source_domain' => 'example.com',
            'page_title' => '',
            'status' => 'queued',
            'current_step' => 'queued',
            'progress_percent' => 0,
            'options_json' => json_encode($options, JSON_UNESCAPED_UNICODE),
            'result_json' => $result === [] ? '' : (string) json_encode($result, JSON_UNESCAPED_UNICODE),
            'error_message' => '',
            'created_by' => 'tester',
        ]);
    }

    private function pngBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
