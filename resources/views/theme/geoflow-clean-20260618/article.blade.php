@extends('theme.geoflow-clean-20260618.layout')

@push('head')
    <meta property="og:title" content="{{ $article->title }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="{{ $siteTitle }}">
@endpush

@section('content')
    @php
        $pub = $article->published_at ?? $article->created_at;
    @endphp
    <div class="gc-container gc-page gc-article">
        <nav class="gc-breadcrumb" aria-label="breadcrumb">
            <a href="{{ route('site.home') }}">{{ __('front.nav.home') }}</a>
            <span aria-hidden="true">/</span>
            @if($article->category)
                <a href="{{ route('site.category', $article->category->slug) }}">{{ $article->category->name }}</a>
                <span aria-hidden="true">/</span>
            @endif
            <span class="gc-breadcrumb-current">{{ $article->title }}</span>
        </nav>

        <article class="gc-article-shell">
            <header class="gc-article-head">
                @if($article->category)
                    <a href="{{ route('site.category', $article->category->slug) }}" class="gc-pill">
                        <i data-lucide="folder" class="w-3.5 h-3.5"></i>
                        {{ $article->category->name }}
                    </a>
                @endif
                <h1 class="gc-article-title">{{ $article->title }}</h1>
                <div class="gc-article-meta">
                    <span class="gc-article-meta-item">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        {{ __('site.article_published_on', ['date' => $pub?->format('Y-m-d') ?? '']) }}
                    </span>
                </div>
                @if($excerptPlain !== '')
                    <div class="gc-article-kicker">{{ $excerptPlain }}</div>
                @endif
            </header>

            <div class="article-prose gc-article-prose max-w-none">
                {!! $contentHtml !!}
            </div>

            @if(count($tags) > 0)
                <div class="gc-article-tags">
                    @foreach($tags as $tag)
                        <span class="gc-pill">
                            <i data-lucide="tag" class="w-3 h-3"></i>
                            {{ $tag }}
                        </span>
                    @endforeach
                </div>
            @endif
        </article>

        @if($relatedArticles->isNotEmpty())
            <section class="gc-article-shell gc-related">
                <div class="gc-related-head">
                    <i data-lucide="bookmark" class="w-4 h-4 text-gray-500"></i>
                    <h3>{{ __('site.article_related') }}</h3>
                </div>
                <ul class="gc-related-list">
                    @foreach($relatedArticles as $index => $related)
                        <li class="gc-related-item">
                            <span class="gc-related-rank">{{ $index + 1 }}</span>
                            <a href="{{ route('site.article', $related->slug) }}" class="gc-related-link">{{ $related->title }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($stickyAd)
            <aside id="articleStickyAd" class="article-sticky-ad" data-ad-id="{{ $stickyAd['id'] }}">
                <div class="article-sticky-ad__inner">
                    <button type="button" class="article-sticky-ad__close" id="articleStickyAdClose" aria-label="{{ __('site.article_ad_close') }}">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                    <div class="article-sticky-ad__content">
                        @if($stickyAd['badge'] !== '')
                            <div class="article-sticky-ad__badge">{{ $stickyAd['badge'] }}</div>
                        @endif
                        @if($stickyAd['title'] !== '')
                            <h3 class="article-sticky-ad__title">{{ $stickyAd['title'] }}</h3>
                        @endif
                        <p class="article-sticky-ad__copy">{{ $stickyAd['copy'] }}</p>
                    </div>
                    <a href="{{ $stickyAd['button_url'] }}" class="article-sticky-ad__button">
                        {{ $stickyAd['button_text'] }}
                        <i data-lucide="arrow-up-right" class="w-4 h-4 ml-2"></i>
                    </a>
                </div>
            </aside>
        @endif
    </div>
@endsection

@if($stickyAd)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const stickyAd = document.getElementById('articleStickyAd');
                const closeButton = document.getElementById('articleStickyAdClose');
                if (!stickyAd || !closeButton) {
                    return;
                }
                const storageKey = 'articleStickyAdDismissed:' + (stickyAd.dataset.adId || 'default');
                if (window.localStorage && localStorage.getItem(storageKey) === '1') {
                    stickyAd.remove();
                    return;
                }
                closeButton.addEventListener('click', function () {
                    if (window.localStorage) {
                        localStorage.setItem(storageKey, '1');
                    }
                    stickyAd.remove();
                });
            });
        </script>
    @endpush
@endif
