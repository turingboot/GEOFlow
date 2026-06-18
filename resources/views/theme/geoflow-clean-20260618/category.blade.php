@extends('theme.geoflow-clean-20260618.layout')

@section('content')
    <div class="gc-container gc-page">
        <div class="gc-page-head">
            <h1 class="gc-page-title">{{ $category->name }}</h1>
            @if(trim((string) $category->description) !== '')
                <p class="gc-page-desc">{{ $category->description }}</p>
            @endif
        </div>

        <section>
            @if($articles->isEmpty())
                <div class="gc-empty">
                    <h3 class="gc-empty-title">{{ __('site.home_empty_title') }}</h3>
                    <p class="gc-empty-desc">{{ __('site.home_empty_desc') }}</p>
                    <a href="{{ route('site.home') }}" class="gc-btn gc-btn-primary">{{ __('site.back_home') }}</a>
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
    </div>
@endsection
