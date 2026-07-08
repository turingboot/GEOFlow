<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * 平台级法律页面（隐私政策 / 服务条款）。
 *
 * 供 Google OAuth 应用发布验证与对外接入使用：
 * - 独立于租户前台主题，即便 geoflow.public_site_enabled=false 也可访问；
 * - 与 GSC OAuth 回调（google-search-console.oauth-callback）挂在同一域名下，
 *   满足 Google「首页 / 隐私政策 / 服务条款」须位于同一已验证域名的要求。
 *
 * 页面文案随 config('geoflow.legal.*') 与站点信息自动填充，跨部署无需改动模板。
 */
class LegalController extends Controller
{
    public function privacy(): View
    {
        return view('site.legal.privacy', $this->sharedData());
    }

    public function terms(): View
    {
        return view('site.legal.terms', $this->sharedData());
    }

    /**
     * 法律页面共享的展示数据（主体名称、联系邮箱、生效日期与站点链接）。
     *
     * @return array<string, string>
     */
    private function sharedData(): array
    {
        $siteName = (string) config('geoflow.site_name', config('app.name', 'GEOFlow'));

        return [
            'companyName' => (string) config('geoflow.legal.company_name', $siteName),
            'siteName' => $siteName,
            'contactEmail' => (string) config('geoflow.legal.contact_email', ''),
            'effectiveDate' => (string) config('geoflow.legal.effective_date', ''),
            'siteUrl' => rtrim((string) config('geoflow.site_url', ''), '/'),
        ];
    }
}
