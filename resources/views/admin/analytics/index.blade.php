@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0">
        <div class="mb-8">
            <div class="admin-hero">
                <div>
                    <h1 class="admin-hero-title">{{ __('admin.analytics.heading') }}</h1>
                    <p class="admin-hero-sub">{{ __('admin.analytics.subtitle') }}</p>
                </div>
                <div class="admin-hero-actions items-center">
                    <span class="text-sm text-white/80">{{ __('admin.analytics.last_updated', ['time' => now()->format('Y-m-d H:i:s')]) }}</span>
                    <button type="button" onclick="location.reload()" class="admin-btn admin-btn-secondary">
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                        {{ __('admin.analytics.refresh') }}
                    </button>
                </div>
            </div>
        </div>

        @include('admin.analytics._filters', ['filters' => $filters, 'filterOptions' => $filterOptions])
        @include('admin.analytics._global-overview', ['globalOverview' => $globalOverview])
        @include('admin.analytics._single-site-section')
        @include('admin.analytics._distribution-section')
        @include('admin.analytics._log-section', ['logSummary' => $logSummary])
    </div>
@endsection
