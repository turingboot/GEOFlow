@extends('admin.layouts.app')

@section('content')
    <div>
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ __('admin.topic_plans.create_title') }}</h1>
                <p class="admin-hero-sub">{{ __('admin.topic_plans.create_subtitle') }}</p>
            </div>
            <div class="admin-hero-actions">
                <a href="{{ route('admin.topic-plans.index') }}" class="admin-btn">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    {{ __('admin.topic_plans.button.back') }}
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.topic-plans.store') }}" class="admin-card">
            @csrf
            <div class="space-y-6 p-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.name') }}</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="2026-07 选题规划">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.ai_model') }}</label>
                        <select name="ai_model_id" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">—</option>
                            @foreach ($chatModels as $model)
                                <option value="{{ $model->id }}" @selected((int) old('ai_model_id') === (int) $model->id)>{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.period_start') }}</label>
                        <input type="date" name="period_start" value="{{ old('period_start') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.period_end') }}</label>
                        <input type="date" name="period_end" value="{{ old('period_end') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.target_count') }}</label>
                        <input type="number" name="target_count" value="{{ old('target_count', 30) }}" min="1" max="100" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.keyword_libraries') }}</label>
                        <select name="keyword_library_ids[]" multiple size="5" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach ($keywordLibraries as $library)
                                <option value="{{ $library->id }}">{{ $library->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.knowledge_bases') }}</label>
                        <select name="knowledge_base_ids[]" multiple size="5" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach ($knowledgeBases as $base)
                                <option value="{{ $base->id }}">{{ $base->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.topic_plans.field.trend_sources') }}</label>
                        <select name="trend_source_ids[]" multiple size="5" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach ($trendSources as $source)
                                <option value="{{ $source->id }}">{{ $source->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end border-t border-gray-100 px-6 py-4">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i data-lucide="sparkles" class="h-4 w-4"></i>
                    {{ __('admin.topic_plans.button.generate') }}
                </button>
            </div>
        </form>
    </div>
@endsection
