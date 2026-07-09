@extends('site.legal.layout')

@section('title', 'Privacy Policy')

@section('content')
    <h1>Privacy Policy</h1>
    <p class="legal-meta">Effective date: {{ $effectiveDate }} · Applies to: {{ $companyName }}</p>

    <p>{{ $companyName }} ("we", "us") operates a GEO (Generative Engine Optimization) content-engineering and search-monitoring platform. This Privacy Policy explains how we collect, use, store and protect the information we obtain when you connect your Google account to enable Google Search Console ("GSC") monitoring.</p>

    <h2>1. Data We Access</h2>
    <p>When you choose to connect your Google account, and only after your explicit consent, we request the following minimal OAuth scopes:</p>
    <ul>
        <li><code>openid</code> / <code>.../auth/userinfo.email</code> — your Google account email and basic identifier, used to identify the connected account.</li>
        <li><code>.../auth/webmasters.readonly</code> — <b>read-only</b> access to your verified Search Console sites, search performance metrics (clicks, impressions, CTR, average position), and URL indexing / coverage status.</li>
    </ul>
    <div class="callout">
        We request <b>no</b> write access and <b>cannot</b> modify your Search Console settings, properties or data.
    </div>

    <h2>2. How We Use the Data</h2>
    <p>Data obtained from Google APIs is used <b>solely</b> to display and analyze your own search performance and indexing status inside your own dashboard, and to surface keyword and search-performance insights that help you optimize your GEO content strategy on our platform (feeding search-side keyword signals back into your content workflow). We do not use it for advertising, do not sell it, and do not use it to train machine-learning or AI models.</p>

    <h2>3. Limited Use</h2>
    <div class="callout">
        <p style="margin:0"><b>{{ $companyName }}'s use and transfer to any other app of information received from Google APIs will adhere to the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements.</b></p>
    </div>

    <h2>4. Data Sharing</h2>
    <p>We do not sell, rent or trade your Google user data. Data may be processed only in the following limited cases:</p>
    <ul>
        <li>Infrastructure strictly necessary to operate the service (hosting, database), where providers are bound by contract to act only on our instructions.</li>
        <li>With your explicit consent, or to resolve a support request you initiated.</li>
        <li>Where required by applicable law.</li>
    </ul>
    <p>Except as above, no human — including our staff — reads your Google user data.</p>

    <h2>5. Storage, Retention &amp; Deletion</h2>
    <ul>
        <li>OAuth tokens (including refresh tokens) are stored encrypted; synced GSC metrics are kept only within your own tenant workspace.</li>
        <li>You can click "Disconnect" on the Google Search Console page at any time; we then delete the stored tokens and stop all access.</li>
        <li>You may also revoke access directly at <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">your Google Account &rarr; Third-party access</a>.</li>
        <li>To request full deletion of synced data, contact us at the email below and we will act within a reasonable time.</li>
    </ul>

    <h2>6. Security</h2>
    <p>We protect your data with encrypted credential storage, encryption in transit (HTTPS) and least-privilege access controls. No method of transmission or storage is, however, completely secure.</p>

    <h2>7. Children</h2>
    <p>The service targets businesses and professionals; it is not directed to children under 16, and we do not knowingly collect their data.</p>

    <h2>8. Changes to This Policy</h2>
    <p>We may update this policy from time to time. Material changes will be reflected by the effective date above and announced where appropriate.</p>

    <h2>9. Contact</h2>
    <p>For any question about this policy or your data, contact us at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.</p>

    <hr>
    <p class="legal-meta">See also our <a href="{{ route('site.legal.terms') }}">Terms of Service</a>.</p>
@endsection
