@extends('admin.layouts.app')

@section('content')
    <div>
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ __('admin.keyword_trends.page_title') }}</h1>
                <p class="admin-hero-sub">{{ __('admin.keyword_trends.page_subtitle') }}</p>
            </div>
            <div class="admin-hero-actions">
                <a href="{{ route('admin.keyword-trends.create') }}" class="admin-btn admin-btn-primary">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    {{ __('admin.keyword_trends.button.create') }}
                </a>
            </div>
        </div>

        <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
            <div class="admin-vstat grad-indigo">
                <span class="admin-vstat-icon"><i data-lucide="radar" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.keyword_trends.stats.total') }}</div>
                    <div class="admin-vstat-value">{{ $stats['total'] }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-emerald">
                <span class="admin-vstat-icon"><i data-lucide="circle-check" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.keyword_trends.stats.active') }}</div>
                    <div class="admin-vstat-value">{{ $stats['active'] }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-amber">
                <span class="admin-vstat-icon"><i data-lucide="import" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.keyword_trends.stats.auto') }}</div>
                    <div class="admin-vstat-value">{{ $stats['auto'] }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-sky">
                <span class="admin-vstat-icon"><i data-lucide="trending-up" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.keyword_trends.stats.imported') }}</div>
                    <div class="admin-vstat-value">{{ $stats['imported'] }}</div>
                </div>
            </div>
        </div>

        @if ($sources->isEmpty())
            <div class="admin-empty">
                <div class="admin-empty-icon"><i data-lucide="trending-up" class="h-7 w-7"></i></div>
                <div class="admin-empty-title">{{ __('admin.keyword_trends.list_title') }}</div>
                <div class="admin-empty-desc">{{ __('admin.keyword_trends.empty.sources') }}</div>
            </div>
        @else
            <div class="admin-card overflow-hidden">
                <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.keyword_trends.list_title') }}</span></div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.keyword_trends.field.name') }}</th>
                            <th>{{ __('admin.keyword_trends.field.provider') }}</th>
                            <th>{{ __('admin.keyword_trends.field.category') }}</th>
                            <th>{{ __('admin.keyword_trends.field.target_library') }}</th>
                            <th>{{ __('admin.keyword_trends.field.schedule') }}</th>
                            <th>{{ __('admin.keyword_trends.snapshot.title') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sources as $source)
                            <tr>
                                <td><a href="{{ route('admin.keyword-trends.show', $source->id) }}" class="font-semibold text-blue-600 hover:underline">{{ $source->name }}</a></td>
                                <td>{{ __('admin.keyword_trends.provider.'.$source->provider) }}</td>
                                <td>{{ $source->category }}</td>
                                <td>{{ optional($source->targetLibrary)->name ?? __('admin.keyword_trends.help.none_library') }}</td>
                                <td><span class="admin-badge is-neutral">{{ __('admin.keyword_trends.schedule.'.($source->schedule ?: 'manual')) }}</span></td>
                                <td>
                                    @if ($source->latestSnapshot)
                                        <span class="admin-badge {{ $source->latestSnapshot->status === 'success' ? 'is-success' : ($source->latestSnapshot->status === 'failed' ? 'is-danger' : 'is-warning') }}">
                                            {{ __('admin.keyword_trends.snapshot.'.$source->latestSnapshot->status) }}
                                        </span>
                                        <span class="ml-1 text-xs text-gray-500">{{ optional($source->latestSnapshot->ran_at)->format('Y-m-d H:i') }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
