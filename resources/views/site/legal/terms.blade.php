@extends('site.legal.layout')

@section('title', '服务条款 Terms of Service')

@section('content')
    <h1>服务条款 · Terms of Service</h1>
    <p class="legal-meta">生效日期 / Effective date: {{ $effectiveDate }} · 适用于 / Applies to: {{ $companyName }}（{{ $siteName }}）</p>

    <p>欢迎使用 {{ $siteName }}（由 {{ $companyName }} 提供，以下简称"本服务"）。使用本服务即表示您同意本条款；若不同意，请勿使用。</p>
    <p class="en">Welcome to {{ $siteName }}, provided by {{ $companyName }} (the "Service"). By using the Service you agree to these Terms; if you do not agree, do not use the Service.</p>

    <h2>1. 服务说明 · The Service</h2>
    <p>本服务提供 AI 内容生成、知识库检索、内容审核与多站点分发，以及基于 Google Search Console 的搜索表现与收录监控。</p>
    <p class="en">The Service provides AI content generation, knowledge-base retrieval, content review and multi-site distribution, plus search-performance and indexing monitoring based on Google Search Console.</p>

    <h2>2. Google 账号与授权 · Google Account &amp; Authorization</h2>
    <ul>
        <li>GSC 监控功能需您主动通过 Google OAuth 授权。我们仅以<b>只读</b>范围访问您的 Search Console 数据，用途见<a href="{{ route('site.legal.privacy') }}">隐私政策</a>。<span class="en">GSC monitoring requires your Google OAuth consent; we access your Search Console data read-only, as described in the <a href="{{ route('site.legal.privacy') }}">Privacy Policy</a>.</span></li>
        <li>您可随时断开连接或在 Google 账号中撤销授权。<span class="en">You may disconnect or revoke access at any time.</span></li>
        <li>您须对所连接的 Google 账号及其数据拥有合法权限。<span class="en">You must have lawful rights over the Google account and data you connect.</span></li>
    </ul>

    <h2>3. 使用规范 · Acceptable Use</h2>
    <p>您同意不利用本服务从事以下行为：违反法律法规；生成违法、侵权、虚假或有害内容；干扰、破坏或未经授权访问系统；侵犯他人知识产权或隐私。您对通过本服务生成、发布和分发的内容负责。</p>
    <p class="en">You agree not to use the Service to: break the law; generate illegal, infringing, deceptive or harmful content; interfere with, disrupt or gain unauthorized access to systems; or infringe others' intellectual-property or privacy rights. You are responsible for content you generate, publish and distribute.</p>

    <h2>4. 数据与内容 · Data &amp; Content</h2>
    <p>您上传的素材、知识库及生成内容归您所有。您授予我们为运行本服务所必需的处理许可（如存储、检索、分发到您指定的渠道）。我们按<a href="{{ route('site.legal.privacy') }}">隐私政策</a>处理您的 Google 用户数据。</p>
    <p class="en">Your uploads, knowledge bases and generated content remain yours. You grant us the license necessary to operate the Service (storage, retrieval, distribution to channels you configure). We handle your Google user data per the <a href="{{ route('site.legal.privacy') }}">Privacy Policy</a>.</p>

    <h2>5. 免责声明与责任限制 · Disclaimer &amp; Limitation of Liability</h2>
    <p>本服务按"现状"提供，不对可用性、准确性（含 AI 生成结果与第三方数据）作任何明示或默示保证。在法律允许的最大范围内，我们不对间接、偶发或后果性损失承担责任。</p>
    <p class="en">The Service is provided "as is", without warranties of any kind as to availability or accuracy (including AI-generated output and third-party data). To the maximum extent permitted by law, we are not liable for indirect, incidental or consequential damages.</p>

    <h2>6. 终止 · Termination</h2>
    <p>您可随时停止使用本服务。若您违反本条款，我们可暂停或终止您的访问。终止后，与数据删除相关的安排见隐私政策。</p>
    <p class="en">You may stop using the Service at any time. We may suspend or terminate access if you breach these Terms. Data deletion after termination follows the Privacy Policy.</p>

    <h2>7. 条款变更 · Changes</h2>
    <p>我们可能更新本条款，重大变更将更新本页生效日期。变更后继续使用即视为接受。</p>
    <p class="en">We may update these Terms; material changes will update the effective date above. Continued use after changes constitutes acceptance.</p>

    <h2>8. 联系我们 · Contact</h2>
    <p>如对本条款有任何疑问，请联系：<a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>
    <p class="en">For any question about these Terms, contact us at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.</p>

    <hr>
    <p class="legal-meta">另见 <a href="{{ route('site.legal.privacy') }}">隐私政策 / Privacy Policy</a>。</p>
@endsection
