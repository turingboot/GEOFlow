@extends('admin.layouts.app')

@section('content')
    <div>
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ __('admin.topic_plans.page_title') }}</h1>
                <p class="admin-hero-sub">{{ __('admin.topic_plans.page_subtitle') }}</p>
            </div>
            <div class="admin-hero-actions">
                <a href="{{ route('admin.topic-plans.create') }}" class="admin-btn admin-btn-primary">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    {{ __('admin.topic_plans.button.create') }}
                </a>
            </div>
        </div>

        @if (session('message'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('message') }}</div>
        @endif

        @if ($plans->isEmpty())
            <div class="admin-empty">
                <div class="admin-empty-icon"><i data-lucide="calendar-clock" class="h-7 w-7"></i></div>
                <div class="admin-empty-title">{{ __('admin.topic_plans.list_title') }}</div>
                <div class="admin-empty-desc">{{ __('admin.topic_plans.empty') }}</div>
            </div>
        @else
            <div class="admin-card overflow-hidden">
                <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.topic_plans.list_title') }}</span></div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.topic_plans.field.name') }}</th>
                            <th>{{ __('admin.topic_plans.field.period') }}</th>
                            <th>{{ __('admin.topic_plans.field.status') }}</th>
                            <th>{{ __('admin.topic_plans.field.items') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            @php
                                $statusClass = match ($plan->status) {
                                    'dispatched' => 'is-success',
                                    'confirmed' => 'is-neutral',
                                    'archived' => 'is-danger',
                                    default => 'is-warning',
                                };
                            @endphp
                            <tr>
                                <td><a href="{{ route('admin.topic-plans.show', $plan->id) }}" class="font-semibold text-blue-600 hover:underline">{{ $plan->name }}</a></td>
                                <td><span class="text-xs text-gray-500">{{ optional($plan->period_start)->format('Y-m-d') }} ~ {{ optional($plan->period_end)->format('Y-m-d') }}</span></td>
                                <td><span class="admin-badge {{ $statusClass }}">{{ __('admin.topic_plans.status.'.$plan->status) }}</span></td>
                                <td>{{ $plan->items_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
