@extends('site.legal.layout')

@section('title', '隐私政策 Privacy Policy')

@section('content')
    <h1>隐私政策 · Privacy Policy</h1>
    <p class="legal-meta">生效日期 / Effective date: {{ $effectiveDate }} · 适用于 / Applies to: {{ $companyName }}（{{ $siteName }}）</p>

    <p>{{ $companyName }}（以下简称"我们"）通过 {{ $siteName }} 平台向用户提供 GEO 内容工程与 Google Search Console（以下简称"GSC"）数据监控服务。本政策说明我们如何收集、使用、存储和保护通过 Google 账号授权获取的信息。</p>
    <p class="en">{{ $companyName }} ("we", "us") operates the {{ $siteName }} platform, which provides GEO content engineering and Google Search Console ("GSC") monitoring. This Privacy Policy explains how we collect, use, store and protect information obtained when you connect your Google account.</p>

    <h2>1. 我们访问的数据 · Data We Access</h2>
    <p>当您选择连接 Google 账号以启用 GSC 监控时，我们仅在您明确授权后，通过 Google OAuth 请求以下最小必要范围：</p>
    <p class="en">When you choose to connect your Google account for GSC monitoring, and only after your explicit consent, we request the following minimal OAuth scopes:</p>
    <ul>
        <li><code>openid</code> / <code>email</code> —— 您的 Google 账号电子邮箱与基础标识，用于识别所连接的账号。<span class="en">Your Google account email and basic identifier, used to identify the connected account.</span></li>
        <li><code>webmasters.readonly</code> —— <b>只读</b>访问您在 Search Console 中已验证的站点列表、搜索表现数据（点击量、展示量、点击率、平均排名）以及网址收录 / 编入索引状态。<span class="en">Read-only access to your verified sites, search performance metrics (clicks, impressions, CTR, average position) and URL indexing status.</span></li>
    </ul>
    <div class="callout">
        我们<b>不会</b>请求任何写入或修改权限，<b>无法</b>更改您的 Search Console 设置、站点或数据。<br>
        <span class="en">We request <b>no</b> write access and <b>cannot</b> modify your Search Console settings, properties or data.</span>
    </div>

    <h2>2. 我们如何使用这些数据 · How We Use the Data</h2>
    <p>从 Google API 获取的数据<b>仅</b>用于在您自己的账户后台内，向您本人展示与分析上述搜索表现和收录情况，并据此产出关键词与搜索表现洞察，帮助您优化本平台上的 GEO 内容策略（即把搜索侧的关键词表现"回流"到您的内容生产与优化流程中）。我们不会将其用于广告、不出售、不用于训练机器学习 / AI 模型。</p>
    <p class="en">Data obtained from Google APIs is used <b>solely</b> to display and analyze your own search performance and indexing status inside your own dashboard, and to surface keyword and search-performance insights that help you optimize your GEO content strategy on our platform (feeding search-side keyword signals back into your content workflow). We do not use it for advertising, do not sell it, and do not use it to train machine-learning or AI models.</p>

    <h2>3. 有限使用（Google API 服务）· Limited Use</h2>
    <div class="callout">
        <p style="margin-top:0"><b>{{ $companyName }}'s use and transfer to any other app of information received from Google APIs will adhere to the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements.</b></p>
        <p style="margin-bottom:0" class="en">{{ $companyName }}（{{ $siteName }}）对从 Google API 获取的信息的使用与传输，均遵守《Google API 服务用户数据政策》，包括其中的"有限使用（Limited Use）"要求。</p>
    </div>

    <h2>4. 数据共享 · Data Sharing</h2>
    <p>我们不会向第三方出售、出租或交易您的 Google 用户数据。仅在下列有限情形下可能涉及数据处理：</p>
    <p class="en">We do not sell, rent or trade your Google user data. Data may be processed only in the following limited cases:</p>
    <ul>
        <li>为提供本服务所必需的基础设施（如托管服务器、数据库），且这些服务商受合同约束仅按我们的指示处理数据。<span class="en">Infrastructure strictly necessary to operate the service (hosting, database), bound by contract to act only on our instructions.</span></li>
        <li>在获得您明确同意、或为解决您主动发起的技术支持请求时。<span class="en">With your explicit consent, or to resolve a support request you initiated.</span></li>
        <li>法律法规明确要求时。<span class="en">Where required by applicable law.</span></li>
    </ul>
    <p>除上述情形外，任何人（包括我们的员工）不会读取您的 Google 用户数据。</p>
    <p class="en">Except as above, no human — including our staff — reads your Google user data.</p>

    <h2>5. 存储、保留与删除 · Storage, Retention & Deletion</h2>
    <ul>
        <li>OAuth 令牌（含 refresh token）以加密方式存储；同步得到的搜索表现与收录数据仅保存在您所属的租户空间内。<span class="en">OAuth tokens (including refresh tokens) are stored encrypted; synced GSC metrics are kept only within your own tenant workspace.</span></li>
        <li>您可随时在后台「谷歌搜录」页面点击「断开连接」，我们将删除对应的授权令牌并停止一切数据访问。<span class="en">You can click "Disconnect" on the Google Search Console page at any time; we then delete the stored tokens and stop all access.</span></li>
        <li>您也可前往 <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">Google 账号 → 第三方访问权限</a> 直接撤销对本应用的授权。<span class="en">You may also revoke access directly at <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">your Google Account &rarr; Third-party access</a>.</span></li>
        <li>如需彻底删除已同步数据，请通过下方邮箱联系我们，我们将在合理期限内处理。<span class="en">To request full deletion of synced data, contact us at the email below and we will act within a reasonable time.</span></li>
    </ul>

    <h2>6. 数据安全 · Security</h2>
    <p>我们采用加密存储敏感凭据、传输层加密（HTTPS）、最小权限访问控制等措施保护您的数据；但请理解没有任何一种网络传输或存储方式是绝对安全的。</p>
    <p class="en">We protect your data with encrypted credential storage, encryption in transit (HTTPS) and least-privilege access controls. No method of transmission or storage is, however, completely secure.</p>

    <h2>7. 未成年人 · Children</h2>
    <p>本服务面向企业与专业用户，不面向 16 周岁以下未成年人，我们不会有意收集其数据。</p>
    <p class="en">The service targets businesses and professionals; it is not directed to children under 16, and we do not knowingly collect their data.</p>

    <h2>8. 政策变更 · Changes to This Policy</h2>
    <p>我们可能不时更新本政策。重大变更将在本页面更新生效日期并以适当方式告知。</p>
    <p class="en">We may update this policy from time to time. Material changes will be reflected by the effective date above and announced where appropriate.</p>

    <h2>9. 联系我们 · Contact</h2>
    <p>如对本政策或您的数据有任何疑问，请联系：<a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>
    <p class="en">For any question about this policy or your data, contact us at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.</p>

    <hr>
    <p class="legal-meta">另见 <a href="{{ route('site.legal.terms') }}">服务条款 / Terms of Service</a>。</p>
@endsection
