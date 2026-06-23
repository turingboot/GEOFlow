<?php

namespace Tests\Feature;

use App\Models\UrlImportJob;
use App\Services\GeoFlow\UrlImportProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class UrlImportProcessingServiceLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_language_option_follows_source_language(): void
    {
        $job = $this->makeJob('');

        $this->assertSame('auto', $this->resolveOutputLanguage($job));

        $directive = $this->languageDirective('auto');
        $this->assertStringContainsString('源页面', $directive);
        $this->assertStringContainsString('英文就全部用英文', $directive);
    }

    public function test_english_option_forces_english_output(): void
    {
        $job = $this->makeJob('en');

        $this->assertSame('en', $this->resolveOutputLanguage($job));
        $this->assertStringContainsString('in English', $this->languageDirective('en'));
        $this->assertStringContainsString('do NOT translate into Chinese', $this->languageDirective('en'));
    }

    public function test_chinese_option_forces_chinese_output(): void
    {
        $job = $this->makeJob('zh-CN');

        $this->assertSame('zh-CN', $this->resolveOutputLanguage($job));
        $this->assertStringContainsString('简体中文', $this->languageDirective('zh-CN'));
    }

    public function test_unknown_language_option_falls_back_to_auto(): void
    {
        $this->assertSame('auto', $this->resolveOutputLanguage($this->makeJob('fr')));
        $this->assertSame('auto', $this->resolveOutputLanguage($this->makeJob('  ')));
    }

    private function makeJob(string $contentLanguage): UrlImportJob
    {
        return UrlImportJob::query()->create([
            'url' => 'https://example.com',
            'normalized_url' => 'https://example.com',
            'source_domain' => 'example.com',
            'page_title' => '',
            'status' => 'queued',
            'current_step' => 'queued',
            'progress_percent' => 0,
            'options_json' => json_encode([
                'content_language' => $contentLanguage,
                'outputs' => ['knowledge', 'keywords', 'titles'],
            ], JSON_UNESCAPED_UNICODE),
            'result_json' => '',
            'error_message' => '',
            'created_by' => 'tester',
        ]);
    }

    private function resolveOutputLanguage(UrlImportJob $job): string
    {
        $service = app(UrlImportProcessingService::class);
        $method = new ReflectionMethod($service, 'resolveOutputLanguage');
        $method->setAccessible(true);

        return $method->invoke($service, $job);
    }

    private function languageDirective(string $language): string
    {
        $service = app(UrlImportProcessingService::class);
        $method = new ReflectionMethod($service, 'languageDirective');
        $method->setAccessible(true);

        return $method->invoke($service, $language);
    }
}
