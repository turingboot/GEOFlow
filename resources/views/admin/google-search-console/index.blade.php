@extends('admin.layouts.app')

@section('content')
    <div>
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ __('admin.gsc.page_title') }}</h1>
                <p class="admin-hero-sub">{{ __('admin.gsc.page_subtitle') }}</p>
            </div>
            <div class="admin-hero-actions">
                <a href="{{ route('admin.google-search-console.create') }}" class="admin-btn admin-btn-primary">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    {{ __('admin.gsc.button.create') }}
                </a>
            </div>
        </div>

        <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
            <div class="admin-vstat grad-indigo">
                <span class="admin-vstat-icon"><i data-lucide="globe" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.gsc.stats.total') }}</div>
                    <div class="admin-vstat-value">{{ $stats['total'] }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-emerald">
                <span class="admin-vstat-icon"><i data-lucide="circle-check" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.gsc.stats.active') }}</div>
                    <div class="admin-vstat-value">{{ $stats['active'] }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-sky">
                <span class="admin-vstat-icon"><i data-lucide="key-round" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.gsc.stats.oauth') }}</div>
                    <div class="admin-vstat-value">{{ $stats['oauth'] }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-amber">
                <span class="admin-vstat-icon"><i data-lucide="shield-check" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.gsc.stats.service_account') }}</div>
                    <div class="admin-vstat-value">{{ $stats['service_account'] }}</div>
                </div>
            </div>
        </div>

        @if ($properties->isEmpty())
            <div class="admin-empty">
                <div class="admin-empty-icon"><i data-lucide="search" class="h-7 w-7"></i></div>
                <div class="admin-empty-title">{{ __('admin.gsc.list_title') }}</div>
                <div class="admin-empty-desc">{{ __('admin.gsc.empty.properties') }}</div>
            </div>
        @else
            <div class="admin-card overflow-hidden">
                <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.gsc.list_title') }}</span></div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.gsc.field.name') }}</th>
                            <th>{{ __('admin.gsc.field.site_url') }}</th>
                            <th>{{ __('admin.gsc.field.auth_type') }}</th>
                            <th>{{ __('admin.gsc.field.schedule') }}</th>
                            <th>{{ __('admin.gsc.snapshot.title') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($properties as $property)
                            <tr>
                                <td><a href="{{ route('admin.google-search-console.show', $property->id) }}" class="font-semibold text-blue-600 hover:underline">{{ $property->name }}</a></td>
                                <td><span class="font-mono text-xs text-gray-600">{{ $property->site_url }}</span></td>
                                <td><span class="admin-badge is-neutral">{{ __('admin.gsc.auth.'.$property->auth_type) }}</span></td>
                                <td><span class="admin-badge is-neutral">{{ __('admin.gsc.schedule.'.($property->schedule ?: 'manual')) }}</span></td>
                                <td>
                                    @if ($property->latestSnapshot)
                                        <span class="admin-badge {{ $property->latestSnapshot->status === 'success' ? 'is-success' : ($property->latestSnapshot->status === 'failed' ? 'is-danger' : 'is-warning') }}">
                                            {{ __('admin.gsc.snapshot.'.$property->latestSnapshot->status) }}
                                        </span>
                                        <span class="ml-1 text-xs text-gray-500">{{ optional($property->latestSnapshot->ran_at)->format('Y-m-d H:i') }}</span>
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
