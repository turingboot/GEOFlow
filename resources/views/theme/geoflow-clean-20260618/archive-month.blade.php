@extends('theme.geoflow-clean-20260618.layout')

@section('content')
    <div class="gc-container gc-page">
        <nav class="gc-breadcrumb">
            <a href="{{ route('site.home') }}">{{ __('front.nav.home') }}</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('site.archive') }}">{{ __('site.archive_title') }}</a>
            <span aria-hidden="true">/</span>
            <span class="gc-breadcrumb-current">{{ $periodLabel }}</span>
        </nav>

        <h1 class="gc-page-title gc-page-title-spaced">{{ __('site.archive_month_title', ['period' => $periodLabel]) }}</h1>

        @if($articles->isEmpty())
            <p class="gc-page-desc">{{ __('site.archive_empty') }}</p>
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
    </div>
@endsection
