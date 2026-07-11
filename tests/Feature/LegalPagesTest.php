<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 平台级法律页面（隐私政策 / 服务条款）测试：
 * 供 Google OAuth 应用发布验证使用，须始终可公开访问且包含合规必需内容。
 */
class LegalPagesTest extends TestCase
{
    public function test_privacy_page_is_public_and_contains_required_google_disclosure(): void
    {
        config()->set('geoflow.legal.company_name', 'Acme GEO Ltd');
        config()->set('geoflow.legal.contact_email', 'privacy@acme.example');

        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            // Google 审核硬性要求：Limited Use 声明与政策链接
            ->assertSee('Google API Services User Data Policy')
            ->assertSee('Limited Use')
            // 仅声明只读范围
            ->assertSee('webmasters.readonly')
            ->assertSee('Acme GEO Ltd')
            ->assertSee('privacy@acme.example')
            // 左上角品牌图标与后台侧边栏对齐（明暗双版本 logo）
            ->assertSee('assets/brand/tavix-logo.png', false)
            ->assertSee('assets/brand/tavix-logo-light.png', false);
    }

    public function test_terms_page_is_public_and_links_privacy(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee(route('site.legal.privacy'));
    }

    public function test_legal_routes_are_registered_independently_of_front_site_toggle(): void
    {
        // 前台总开关关闭时，法律页仍应命中路由（无条件注册），Google 三个 URL 恒可访问。
        config()->set('geoflow.public_site_enabled', false);

        $this->assertTrue(app('router')->has('site.legal.privacy'));
        $this->assertTrue(app('router')->has('site.legal.terms'));
        $this->get('/privacy')->assertOk();
        $this->get('/terms')->assertOk();
    }
}
