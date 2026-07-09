<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * 平台产品落地页（应用说明）。
 *
 * 当前台文章站关闭（geoflow.public_site_enabled=false）时，根路径 `/` 渲染此页，
 * 取代原先直接 302 到后台登录的行为：向访客与 Google OAuth 审核员说明本平台是什么、
 * 为什么需要接入 Google Search Console，并提供登录入口与隐私/条款链接。
 * 文案随 config('geoflow.*') 填充，跨部署无需改模板。
 */
class LandingController extends Controller
{
    public function index(): View
    {
        $siteName = (string) config('geoflow.site_name', config('app.name', 'GEOFlow'));

        return view('site.landing', [
            'companyName' => (string) config('geoflow.legal.company_name', $siteName),
            'siteName' => $siteName,
            'contactEmail' => (string) config('geoflow.legal.contact_email', ''),
            'siteUrl' => rtrim((string) config('geoflow.site_url', ''), '/'),
            'effectiveYear' => substr((string) config('geoflow.legal.effective_date', '2026'), 0, 4),
        ]);
    }
}
