@extends('theme.geoflow-clean-20260618.layout')

@section('content')
    <div class="gc-container gc-page">
        @if($search === '' && ! $category && ! $categoryMissing && (int) request('page', 1) === 1)
            <section class="gc-hero">
                <h1 class="gc-hero-title">{{ $siteTitle }}</h1>
                <p class="gc-hero-copy">
                    {{ $siteSubtitle !== '' ? $siteSubtitle : ($siteDescription !== '' ? $siteDescription : __('site.home_hero_fallback')) }}
                </p>
                <form method="get" action="{{ route('site.home') }}" class="gc-search">
                    <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('site.search_placeholder') }}" class="gc-search-input">
                    <button type="submit" class="gc-btn gc-btn-primary">{{ __('site.search_button') }}</button>
                </form>
            </section>
        @endif

        @if($search === '' && ! $category && ! $categoryMissing && (int) request('page', 1) === 1 && $featuredArticles->isNotEmpty())
            <div class="gc-section-label">
                <i data-lucide="star" class="w-4 h-4 text-amber-400"></i>
                <span>{{ __('site.home_featured') }}</span>
            </div>
            <section class="gc-list">
                @foreach($featuredArticles as $article)
                    @include('theme.geoflow-clean-20260618.partials.article-card', ['article' => $article, 'showFeaturedBadge' => true])
                @endforeach
            </section>
            <div class="gc-section-label gc-section-label-spaced">
                <i data-lucide="list" class="w-4 h-4 text-gray-400"></i>
                <span>{{ __('site.home_latest') }}</span>
            </div>
        @endif

        @if($search !== '')
            <nav class="gc-breadcrumb">
                <a href="{{ route('site.home') }}">{{ __('front.nav.home') }}</a>
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                <span class="gc-breadcrumb-current">{{ __('site.search_breadcrumb', ['term' => $search]) }}</span>
            </nav>
        @elseif($category)
            <div class="gc-page-head">
                <h1 class="gc-page-title">{{ $category->name }}</h1>
                @if(trim((string) $category->description) !== '')
                    <p class="gc-page-desc">{{ $category->description }}</p>
                @endif
            </div>
        @elseif($categoryMissing)
            <div class="gc-page-head">
                <h1 class="gc-page-title">{{ __('site.category_not_found') }}</h1>
            </div>
        @endif

        <section>
            @if($articles->isEmpty())
                <div class="gc-empty">
                    <div class="gc-empty-icon">
                        <i data-lucide="file-text" class="w-8 h-8 text-gray-400"></i>
                    </div>
                    <h3 class="gc-empty-title">{{ $search !== '' ? __('site.search_empty_title') : __('site.home_empty_title') }}</h3>
                    <p class="gc-empty-desc">{{ $search !== '' ? __('site.search_empty_desc') : __('site.home_empty_desc') }}</p>
                    <a href="{{ route('site.home') }}" class="gc-btn gc-btn-primary">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        {{ __('site.back_home') }}
                    </a>
                </div>
            @else
                <div class="gc-list">
                    @foreach($articles as $article)
                        @include('theme.geoflow-clean-20260618.partials.article-card', ['article' => $article, 'showFeaturedBadge' => false])
                    @endforeach
                </div>
                @if($articles->hasPages())
                    <div class="gc-pagination">{{ $articles->onEachSide(1)->links() }}</div>
                @endif
            @endif
        </section>

        @if($search !== '' || $category || $categoryMissing)
            <form method="get" action="{{ route('site.home') }}" class="gc-search gc-search-spaced">
                <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('site.search_placeholder') }}" class="gc-search-input">
                <button type="submit" class="gc-btn gc-btn-primary">{{ __('site.search_button') }}</button>
            </form>
        @endif
    </div>
@endsection
