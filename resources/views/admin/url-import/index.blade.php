@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0 space-y-8">
        <div class="admin-hero">
            <div class="flex items-start gap-4">
                <a href="{{ route('admin.materials.index') }}" class="mt-1 text-white/70 hover:text-white">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h1 class="admin-hero-title">{{ __('admin.url_import.page_heading') }}</h1>
                    <p class="admin-hero-sub">{{ __('admin.url_import.page_subtitle') }}</p>
                </div>
            </div>
            <div class="admin-hero-actions">
                <a href="{{ route('admin.url-import.history') }}" class="admin-btn admin-btn-secondary">
                    <i data-lucide="history" class="w-4 h-4"></i>
                    {{ __('admin.url_import.button.view_history') }}
                </a>
                <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn-secondary">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    {{ __('admin.url_import.button.back_to_materials') }}
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="admin-vstat grad-indigo">
                <span class="admin-vstat-icon"><i data-lucide="database" class="h-6 w-6"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.url_import.stats.knowledge_bases') }}</div>
                    <div class="admin-vstat-value">{{ __('admin.url_import.value.count_units', ['count' => (int) $stats['knowledge_bases']]) }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-emerald">
                <span class="admin-vstat-icon"><i data-lucide="tags" class="h-6 w-6"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.url_import.stats.keyword_libraries') }}</div>
                    <div class="admin-vstat-value">{{ __('admin.url_import.value.count_units', ['count' => (int) $stats['keyword_libraries']]) }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-amber">
                <span class="admin-vstat-icon"><i data-lucide="heading" class="h-6 w-6"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.url_import.stats.title_libraries') }}</div>
                    <div class="admin-vstat-value">{{ __('admin.url_import.value.count_units', ['count' => (int) $stats['title_libraries']]) }}</div>
                </div>
            </div>
        </div>

        @if (! $aiModelReady)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5 text-amber-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center text-base font-semibold">
                            <i data-lucide="triangle-alert" class="mr-2 h-5 w-5"></i>
                            {{ __('admin.url_import.ai_required.title') }}
                        </div>
                        <p class="mt-2 text-sm leading-6 text-amber-800">{{ __('admin.url_import.ai_required.desc') }}</p>
                    </div>
                    <a href="{{ $aiModelConfigUrl }}" class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-amber-700">
                        <i data-lucide="settings" class="mr-2 h-4 w-4"></i>
                        {{ __('admin.url_import.ai_required.button') }}
                    </a>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.url-import.store') }}" class="bg-white shadow rounded-2xl overflow-hidden">
            @csrf
            <div class="px-6 py-6 lg:px-8 border-b border-gray-200">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center rounded-full bg-cyan-50 px-3 py-1 text-sm font-medium text-cyan-700">
                        <i data-lucide="sparkles" class="w-4 h-4 mr-2"></i>
                        {{ __('admin.materials.url_import') }}
                    </span>
                </div>
                <h2 class="mt-5 text-2xl font-bold text-gray-900">{{ __('admin.url_import.section.new_job') }}</h2>
                <p class="mt-2 text-sm text-gray-600">{{ __('admin.url_import.section.new_job_desc') }}</p>
            </div>

            <div class="p-6 lg:p-8 space-y-7">
                    <div>
                        <label for="url" class="block text-sm font-semibold text-gray-800">{{ __('admin.url_import.field.url') }}</label>
                        <input
                            id="url"
                            name="url"
                            type="text"
                            required
                            value="{{ old('url') }}"
                            placeholder="{{ __('admin.materials.url_import_placeholder') }}"
                            class="mt-3 block min-h-14 w-full rounded-xl border-gray-300 px-5 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                        <p class="mt-2 text-sm text-gray-500">{{ __('admin.url_import.help.url_optional_scheme') }}</p>
                        @error('url')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.url_import.field.project_name') }}</label>
                            <input name="project_name" value="{{ old('project_name') }}" placeholder="{{ __('admin.url_import.placeholder.project_name') }}" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.url_import.field.source_label') }}</label>
                            <input name="source_label" value="{{ old('source_label') }}" placeholder="{{ __('admin.url_import.placeholder.source_label') }}" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.url_import.field.content_language') }}</label>
                            <select name="content_language" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">{{ __('admin.url_import.option.auto_detect') }}</option>
                                <option value="zh-CN">中文</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.url_import.field.author') }}</label>
                            <select disabled class="mt-2 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm sm:text-sm">
                                <option>{{ __('admin.url_import.option.not_specified') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('admin.url_import.field.notes') }}</label>
                        <textarea name="notes" rows="3" placeholder="{{ __('admin.url_import.placeholder.notes') }}" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('notes') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach (['knowledge', 'keywords', 'titles'] as $output)
                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4">
                                <input type="checkbox" name="outputs[]" value="{{ $output }}" checked class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ __('admin.url_import.output.' . $output) }}</span>
                                    <span class="block mt-1 text-xs text-gray-500">{{ __('admin.url_import.option.create_or_later') }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <label class="flex items-start gap-3">
                                <input type="checkbox" name="crawl_secondary" value="1" {{ old('crawl_secondary', '1') ? 'checked' : '' }} class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ __('admin.url_import.field.crawl_secondary') }}</span>
                                    <span class="block mt-1 text-xs text-gray-500">{{ __('admin.url_import.option.crawl_secondary_hint') }}</span>
                                </span>
                            </label>
                            <div class="mt-3">
                                <label class="block text-xs font-medium text-gray-700">{{ __('admin.url_import.field.max_secondary_pages') }}</label>
                                <input type="number" name="max_secondary_pages" min="1" max="50" value="{{ old('max_secondary_pages', 20) }}" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <label class="flex items-start gap-3">
                                <input type="checkbox" name="download_images" value="1" {{ old('download_images', '1') ? 'checked' : '' }} class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ __('admin.url_import.field.download_images') }}</span>
                                    <span class="block mt-1 text-xs text-gray-500">{{ __('admin.url_import.option.download_images_hint') }}</span>
                                </span>
                            </label>
                            <div class="mt-3">
                                <label class="block text-xs font-medium text-gray-700">{{ __('admin.url_import.field.max_images') }}</label>
                                <input type="number" name="max_images" min="1" max="200" value="{{ old('max_images', 50) }}" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                        <h3 class="text-sm font-semibold text-blue-900">{{ __('admin.url_import.section.next_flow') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-blue-800">{{ __('admin.url_import.recommendation.copy') }}</p>
                    </div>

                    <button type="submit" class="inline-flex min-h-12 justify-center items-center rounded-xl border border-transparent bg-blue-600 px-6 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                        <i data-lucide="play" class="w-4 h-4 mr-2"></i>
                        {{ __('admin.url_import.button.start') }}
                    </button>
            </div>
        </form>
    </div>
@endsection
