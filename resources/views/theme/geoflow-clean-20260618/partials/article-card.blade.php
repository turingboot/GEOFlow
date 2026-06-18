@php
    /** @var \App\Models\Article $article */
    $summary = $cardSummaries[$article->id] ?? '';
    $pub = $article->published_at ?? $article->created_at;
@endphp
<article class="gc-card">
    <div class="gc-card-body">
        <div class="gc-card-meta">
            <div class="gc-card-tags">
                @if(!empty($showFeaturedBadge))
                    <span class="gc-pill gc-pill-featured">
                        <i data-lucide="star" class="w-3 h-3"></i>
                        {{ __('site.home_featured_badge') }}
                    </span>
                @endif
                @if($article->category)
                    <a href="{{ route('site.category', $article->category->slug) }}" class="gc-pill">
                        {{ $article->category->name }}
                    </a>
                @endif
            </div>
            <time class="gc-card-date" datetime="{{ $pub?->toAtomString() }}">{{ $pub?->format('Y-m-d') }}</time>
        </div>

        <h2 class="gc-card-title">
            <a href="{{ route('site.article', $article->slug) }}">{{ $article->title }}</a>
        </h2>

        <p class="gc-card-summary">{{ $summary }}</p>

        <a href="{{ route('site.article', $article->slug) }}" class="gc-read-more">
            {{ __('site.home_read_more') }}
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </a>
    </div>
</article>
