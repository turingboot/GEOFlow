@extends('admin.layouts.app')

@php
    $statusClass = match ($plan->status) {
        'dispatched' => 'is-success',
        'confirmed' => 'is-neutral',
        'archived' => 'is-danger',
        default => 'is-warning',
    };
    $dispatched = $plan->status === 'dispatched';
@endphp

@section('content')
    <div>
        <div class="admin-hero">
            <div class="min-w-0">
                <h1 class="admin-hero-title truncate">{{ $plan->name }}</h1>
                <p class="admin-hero-sub">{{ __('admin.topic_plans.detail_subtitle') }}</p>
            </div>
            <div class="admin-hero-actions">
                <span class="admin-badge {{ $statusClass }}">{{ __('admin.topic_plans.status.'.$plan->status) }}</span>
                <a href="{{ route('admin.topic-plans.index') }}" class="admin-btn">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    {{ __('admin.topic_plans.button.back') }}
                </a>
            </div>
        </div>

        @if (session('message'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('message') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @if ($plan->items->isEmpty())
            <div class="admin-empty">
                <div class="admin-empty-icon"><i data-lucide="list-x" class="h-7 w-7"></i></div>
                <div class="admin-empty-title">{{ __('admin.topic_plans.items_title') }}</div>
                <div class="admin-empty-desc">{{ __('admin.topic_plans.empty_items') }}</div>
            </div>
        @else
            <form method="POST" action="{{ route('admin.topic-plans.confirm', $plan->id) }}" class="mb-6 admin-card overflow-hidden">
                @csrf
                <div class="admin-card-head flex items-center justify-between">
                    <span class="admin-card-title">{{ __('admin.topic_plans.items_title') }}</span>
                    @unless ($dispatched)
                        <button type="submit" class="admin-btn admin-btn-primary">
                            <i data-lucide="check" class="h-4 w-4"></i>
                            {{ __('admin.topic_plans.button.confirm') }}
                        </button>
                    @endunless
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="w-10">{{ __('admin.topic_plans.field.select') }}</th>
                            <th>{{ __('admin.topic_plans.field.title') }}</th>
                            <th>{{ __('admin.topic_plans.field.keyword') }}</th>
                            <th>{{ __('admin.topic_plans.field.heat') }}</th>
                            <th>{{ __('admin.topic_plans.field.kb_support') }}</th>
                            <th>{{ __('admin.topic_plans.field.dup_risk') }}</th>
                            <th>{{ __('admin.topic_plans.field.item_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plan->items as $item)
                            <tr>
                                <td>
                                    <input type="checkbox" name="item_ids[]" value="{{ $item->id }}"
                                        @checked(in_array($item->status, ['suggested', 'confirmed', 'dispatched'], true))
                                        @disabled($dispatched)
                                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </td>
                                <td class="font-medium text-gray-900">{{ $item->title }}</td>
                                <td>{{ $item->keyword }}</td>
                                <td>{{ $item->heat_score ?? '—' }}</td>
                                <td>{{ $item->kb_support ? __('admin.topic_plans.kb_support.'.$item->kb_support) : '—' }}</td>
                                <td>{{ $item->dup_risk ? __('admin.topic_plans.dup_risk.'.$item->dup_risk) : '—' }}</td>
                                <td><span class="admin-badge is-neutral">{{ __('admin.topic_plans.item_status.'.$item->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </form>

            @unless ($dispatched)
                <form method="POST" action="{{ route('admin.topic-plans.dispatch', $plan->id) }}" class="admin-card">
                    @csrf
                    <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.topic_plans.dispatch_title') }}</span></div>
                    <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.content_prompt') }}</label>
                            <select name="prompt_id" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">—</option>
                                @foreach ($contentPrompts as $prompt)
                                    <option value="{{ $prompt->id }}">{{ $prompt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.writer_model') }}</label>
                            <select name="ai_model_id" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">—</option>
                                @foreach ($chatModels as $model)
                                    <option value="{{ $model->id }}">{{ $model->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.publish_interval') }}</label>
                            <input type="number" name="publish_interval" value="86400" min="60" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.category_mode') }}</label>
                            <select name="category_mode" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="smart">{{ __('admin.topic_plans.category_mode.smart') }}</option>
                                <option value="fixed">{{ __('admin.topic_plans.category_mode.fixed') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.publish_scope') }}</label>
                            <select name="publish_scope" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="local_only">{{ __('admin.topic_plans.publish_scope.local_only') }}</option>
                                <option value="local_and_distribution">{{ __('admin.topic_plans.publish_scope.local_and_distribution') }}</option>
                                <option value="distribution_only">{{ __('admin.topic_plans.publish_scope.distribution_only') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.task_status') }}</label>
                            <select name="status" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="paused">{{ __('admin.topic_plans.task_status.paused') }}</option>
                                <option value="active">{{ __('admin.topic_plans.task_status.active') }}</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700 md:col-span-3">
                            <input type="hidden" name="need_review" value="0">
                            <input type="checkbox" name="need_review" value="1" checked class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>{{ __('admin.topic_plans.field.need_review') }}</span>
                        </label>
                    </div>
                    <div class="flex justify-end border-t border-gray-100 px-6 py-4">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            <i data-lucide="send" class="h-4 w-4"></i>
                            {{ __('admin.topic_plans.button.dispatch') }}
                        </button>
                    </div>
                </form>
            @endunless
        @endif
    </div>
@endsection
