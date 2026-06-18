@php
    $adminBrandName = $adminBrandName ?? \App\Support\AdminWeb::siteName();
    $currentAdmin = auth('admin')->user();
    $isSuperAdmin = $currentAdmin && method_exists($currentAdmin, 'isSuperAdmin') && $currentAdmin->isSuperAdmin();
    $menu = [
        'dashboard' => ['route' => 'admin.dashboard', 'name' => __('admin.nav.dashboard')],
        'analytics' => ['route' => 'admin.analytics', 'name' => __('admin.nav.analytics')],
        'tasks' => ['route' => 'admin.tasks.index', 'name' => __('admin.nav.tasks')],
        'distribution' => ['route' => 'admin.distribution.index', 'name' => __('admin.nav.distribution')],
        'articles' => ['route' => 'admin.articles.index', 'name' => __('admin.nav.articles')],
        'materials' => ['route' => 'admin.materials.index', 'name' => __('admin.nav.materials')],
        'ai_config' => ['route' => 'admin.ai.configurator', 'name' => __('admin.nav.ai_config')],
        'site_settings' => ['route' => 'admin.site-settings.index', 'name' => __('admin.nav.site_settings')],
    ];
    if ($isSuperAdmin) {
        $menu['admin_users'] = ['route' => 'admin.admin-users.index', 'name' => __('admin.nav.admin_users')];
    }
    $menuIcons = [
        'dashboard' => 'layout-dashboard',
        'analytics' => 'bar-chart-3',
        'tasks' => 'list-checks',
        'distribution' => 'share-2',
        'articles' => 'file-text',
        'materials' => 'folder',
        'ai_config' => 'bot',
        'site_settings' => 'settings',
        'admin_users' => 'users',
    ];
    $subMap = [
        'admin.analytics' => 'analytics',
        'admin.system-updates.index' => 'dashboard',
        'admin.system-updates.check' => 'dashboard',
        'admin.system-updates.plan' => 'dashboard',
        'admin.system-updates.backup' => 'dashboard',
        'admin.tasks.create' => 'tasks',
        'admin.tasks.edit' => 'tasks',
        'admin.distribution.index' => 'distribution',
        'admin.distribution.create' => 'distribution',
        'admin.distribution.store' => 'distribution',
        'admin.distribution.edit' => 'distribution',
        'admin.distribution.update' => 'distribution',
        'admin.distribution.show' => 'distribution',
        'admin.distribution.jobs' => 'distribution',
        'admin.distribution.retry' => 'distribution',
        'admin.distribution.health' => 'distribution',
        'admin.distribution.pause' => 'distribution',
        'admin.distribution.activate' => 'distribution',
        'admin.distribution.rotate-secret' => 'distribution',
        'admin.articles.create' => 'articles',
        'admin.articles.edit' => 'articles',
        'admin.categories.index' => 'materials',
        'admin.categories.create' => 'materials',
        'admin.categories.edit' => 'materials',
        'admin.authors.index' => 'materials',
        'admin.authors.create' => 'materials',
        'admin.authors.edit' => 'materials',
        'admin.authors.detail' => 'materials',
        'admin.keyword-libraries.index' => 'materials',
        'admin.keyword-libraries.create' => 'materials',
        'admin.keyword-libraries.edit' => 'materials',
        'admin.keyword-libraries.detail' => 'materials',
        'admin.keyword-libraries.detail.update' => 'materials',
        'admin.keyword-libraries.keywords.store' => 'materials',
        'admin.keyword-libraries.keywords.delete' => 'materials',
        'admin.keyword-libraries.import' => 'materials',
        'admin.title-libraries.index' => 'materials',
        'admin.title-libraries.create' => 'materials',
        'admin.title-libraries.edit' => 'materials',
        'admin.title-libraries.detail' => 'materials',
        'admin.title-libraries.titles.store' => 'materials',
        'admin.title-libraries.titles.delete' => 'materials',
        'admin.title-libraries.import' => 'materials',
        'admin.title-libraries.ai-generate' => 'materials',
        'admin.title-libraries.ai-generate.submit' => 'materials',
        'admin.image-libraries.index' => 'materials',
        'admin.image-libraries.create' => 'materials',
        'admin.image-libraries.edit' => 'materials',
        'admin.image-libraries.detail' => 'materials',
        'admin.image-libraries.images.upload' => 'materials',
        'admin.image-libraries.images.delete' => 'materials',
        'admin.image-libraries.detail.update' => 'materials',
        'admin.knowledge-bases.index' => 'materials',
        'admin.knowledge-bases.create' => 'materials',
        'admin.knowledge-bases.edit' => 'materials',
        'admin.knowledge-bases.detail' => 'materials',
        'admin.knowledge-bases.upload' => 'materials',
        'admin.knowledge-bases.detail.update' => 'materials',
        'admin.url-import' => 'materials',
        'admin.ai-models.index' => 'ai_config',
        'admin.ai-prompts' => 'ai_config',
        'admin.site-settings.sensitive-words' => 'site_settings',
        'admin.site-settings.sensitive-words.store' => 'site_settings',
        'admin.site-settings.sensitive-words.delete' => 'site_settings',
        'admin.security-settings.index' => 'site_settings',
        'admin.security-settings.words.store' => 'site_settings',
        'admin.security-settings.words.delete' => 'site_settings',
        'admin.api-tokens.index' => 'admin_users',
        'admin.api-tokens.store' => 'admin_users',
        'admin.api-tokens.revoke' => 'admin_users',
        'admin.admin-activity-logs' => 'admin_users',
    ];
    $routeName = request()->route()?->getName();
    $resolvedActive = $activeMenu ?? '';
    if ($resolvedActive === '' && $routeName && isset($subMap[$routeName])) {
        $resolvedActive = $subMap[$routeName];
    }
    $appVersion = (string) config('geoflow.app_version', '2.0');
@endphp
<aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 -translate-x-full transform flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:static lg:z-auto lg:translate-x-0">
    <div class="flex h-16 shrink-0 items-center gap-2 border-b border-gray-100 px-5">
        <a href="{{ route('admin.dashboard') }}" class="truncate text-lg font-semibold text-gray-900">{{ $adminBrandName }}</a>
    </div>
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 [scrollbar-width:thin]">
        @foreach ($menu as $key => $item)
            <a href="{{ route($item['route']) }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors duration-200 @if($resolvedActive === $key) bg-blue-50 text-blue-600 font-medium @else text-gray-600 hover:bg-gray-50 hover:text-gray-900 @endif">
                <i data-lucide="{{ $menuIcons[$key] ?? 'circle' }}" class="h-5 w-5 shrink-0"></i>
                <span class="truncate">{{ $item['name'] }}</span>
            </a>
        @endforeach
    </nav>
    <div class="shrink-0 border-t border-gray-100 px-5 py-3 text-xs text-gray-400">
        {{ __('admin.footer.version', ['version' => $appVersion]) }}
    </div>
</aside>
<div id="admin-sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 z-30 hidden bg-black/30 lg:hidden"></div>
