@php
    $path = request()->path();
    $isHome = $path === '' || $path === '/';
@endphp
<div class="gc-accent-bar" aria-hidden="true"></div>
<header class="gc-header">
    <div class="gc-container gc-header-inner">
        <a href="{{ route('site.home') }}" class="gc-brand">
            @if(!empty($siteLogo))
                <img src="{{ $siteLogo }}" alt="{{ $siteName }}" class="gc-brand-logo">
            @else
                <span class="gc-brand-name">{{ $siteName }}</span>
            @endif
        </a>

        <nav class="gc-nav">
            <a href="{{ route('site.home') }}" class="gc-nav-link {{ $isHome ? 'is-active' : '' }}">
                <i data-lucide="home" class="w-4 h-4"></i>
                {{ __('front.nav.home') }}
            </a>
            <div class="gc-dropdown" id="gcCategoryDropdown">
                <button type="button" class="gc-nav-link" onclick="gcToggleCategoryDropdown()">
                    <i data-lucide="folder" class="w-4 h-4"></i>
                    {{ __('front.nav.categories') }}
                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                </button>
                <div id="gcCategoryDropdownMenu" class="gc-dropdown-menu hidden">
                    <a href="{{ route('site.home') }}" class="gc-dropdown-item">
                        <i data-lucide="layout-grid" class="w-4 h-4"></i>
                        {{ __('front.nav.all_articles') }}
                    </a>
                    @foreach($navCategories as $categoryItem)
                        <a href="{{ route('site.category', $categoryItem->slug) }}" class="gc-dropdown-item">
                            <i data-lucide="folder" class="w-4 h-4"></i>
                            <span class="truncate">{{ $categoryItem->name }}</span>
                            <span class="gc-dropdown-count">{{ (int) ($categoryItem->published_count ?? 0) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </nav>

        <button type="button" class="gc-mobile-toggle md:hidden" onclick="gcToggleMobileMenu()" aria-label="{{ __('front.nav.categories') }}">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
    </div>

    <div id="gcMobileMenu" class="gc-mobile-menu hidden md:hidden">
        <div class="gc-container py-3 space-y-1">
            <a href="{{ route('site.home') }}" class="gc-mobile-link {{ $isHome ? 'is-active' : '' }}">
                <i data-lucide="home" class="w-4 h-4"></i>
                {{ __('front.nav.home') }}
            </a>
            @foreach($navCategories as $categoryItem)
                <a href="{{ route('site.category', $categoryItem->slug) }}" class="gc-mobile-link">
                    <i data-lucide="folder" class="w-4 h-4"></i>
                    {{ $categoryItem->name }}
                </a>
            @endforeach
        </div>
    </div>
</header>

<script>
    function gcToggleCategoryDropdown() {
        var menu = document.getElementById('gcCategoryDropdownMenu');
        if (menu) {
            menu.classList.toggle('hidden');
        }
    }
    function gcToggleMobileMenu() {
        var menu = document.getElementById('gcMobileMenu');
        if (menu) {
            menu.classList.toggle('hidden');
        }
    }
    document.addEventListener('click', function (event) {
        var dropdown = document.getElementById('gcCategoryDropdown');
        var menu = document.getElementById('gcCategoryDropdownMenu');
        if (dropdown && menu && !dropdown.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });
</script>
