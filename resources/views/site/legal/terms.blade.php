@extends('site.legal.layout')

@section('title', 'Terms of Service')

@section('content')
    <h1>Terms of Service</h1>
    <p class="legal-meta">Effective date: {{ $effectiveDate }} · Applies to: {{ $companyName }}</p>

    <p>Welcome to {{ $companyName }} (the "Service"). By using the Service you agree to these Terms; if you do not agree, do not use the Service.</p>

    <h2>1. The Service</h2>
    <p>{{ $companyName }} provides AI content generation, knowledge-base retrieval, content review and multi-site distribution, plus search-performance and indexing monitoring based on Google Search Console.</p>

    <h2>2. Google Account &amp; Authorization</h2>
    <ul>
        <li>GSC monitoring requires your Google OAuth consent; we access your Search Console data on a read-only basis, as described in the <a href="{{ route('site.legal.privacy') }}">Privacy Policy</a>.</li>
        <li>You may disconnect or revoke access at any time.</li>
        <li>You must have lawful rights over the Google account and data you connect.</li>
    </ul>

    <h2>3. Acceptable Use</h2>
    <p>You agree not to use the Service to: break the law; generate illegal, infringing, deceptive or harmful content; interfere with, disrupt or gain unauthorized access to systems; or infringe others' intellectual-property or privacy rights. You are responsible for content you generate, publish and distribute.</p>

    <h2>4. Data &amp; Content</h2>
    <p>Your uploads, knowledge bases and generated content remain yours. You grant us the license necessary to operate the Service (storage, retrieval, distribution to channels you configure). We handle your Google user data per the <a href="{{ route('site.legal.privacy') }}">Privacy Policy</a>.</p>

    <h2>5. Disclaimer &amp; Limitation of Liability</h2>
    <p>The Service is provided "as is", without warranties of any kind as to availability or accuracy (including AI-generated output and third-party data). To the maximum extent permitted by law, we are not liable for indirect, incidental or consequential damages.</p>

    <h2>6. Termination</h2>
    <p>You may stop using the Service at any time. We may suspend or terminate access if you breach these Terms. Data deletion after termination follows the Privacy Policy.</p>

    <h2>7. Changes</h2>
    <p>We may update these Terms; material changes will update the effective date above. Continued use after changes constitutes acceptance.</p>

    <h2>8. Contact</h2>
    <p>For any question about these Terms, contact us at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.</p>

    <hr>
    <p class="legal-meta">See also our <a href="{{ route('site.legal.privacy') }}">Privacy Policy</a>.</p>
@endsection
