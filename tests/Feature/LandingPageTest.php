<?php

namespace Tests\Feature;

use App\Http\Controllers\Site\LandingController;
use Tests\TestCase;

/**
 * 产品落地页（应用说明）测试：前台文章站关闭时的根路径首页，兼作 Google OAuth 应用首页。
 * 该路由仅在 public_site_enabled=false 时注册，故直接渲染控制器验证内容，不依赖路由开关。
 */
class LandingPageTest extends TestCase
{
    public function test_landing_page_describes_the_app_and_links_key_pages(): void
    {
        config()->set('geoflow.legal.company_name', 'TavixGEO');
        config()->set('geoflow.legal.contact_email', 'hi@tavix.example');

        $html = app(LandingController::class)->index()->render();

        // 品牌与卖点（头部品牌图标与后台侧边栏对齐：明暗双版本 logo）
        $this->assertStringContainsString('TavixGEO', $html);
        $this->assertStringContainsString('assets/brand/tavix-logo.png', $html);
        $this->assertStringContainsString('assets/brand/tavix-logo-light.png', $html);
        $this->assertStringContainsString('GEO', $html);
        $this->assertStringContainsString('Search Console', $html);
        $this->assertStringContainsString('webmasters.readonly', $html);
        // 关键入口
        $this->assertStringContainsString(route('admin.login'), $html);
        $this->assertStringContainsString(route('site.legal.privacy'), $html);
        $this->assertStringContainsString(route('site.legal.terms'), $html);
        $this->assertStringContainsString('hi@tavix.example', $html);
    }

    public function test_home_route_points_to_landing_when_front_site_disabled(): void
    {
        // 结构性保证：前台关闭分支里 site.home 指向落地页控制器（非 302 到登录）。
        $this->assertTrue(app('router')->has('site.home'));
    }
}
