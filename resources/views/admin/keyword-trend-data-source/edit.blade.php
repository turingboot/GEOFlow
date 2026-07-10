@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0">
        <div class="admin-hero">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.ai.configurator') }}" class="text-white/70 hover:text-white">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h1 class="admin-hero-title">{{ __('admin.keyword_data_source.heading') }}</h1>
                    <p class="admin-hero-sub">{{ __('admin.keyword_data_source.subtitle') }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-8">
            <div class="admin-card">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-cyan-500 rounded-md flex items-center justify-center">
                                <i data-lucide="database-zap" class="w-5 h-5 text-white"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('admin.keyword_data_source.card_title') }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ __('admin.keyword_data_source.card_subtitle') }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-6">
                    <form method="POST" action="{{ route('admin.keyword-data-source.update') }}" class="space-y-4">
                        @csrf
                        <div>
                            <div class="text-sm font-medium text-gray-700">{{ __('admin.keyword_data_source.platform_label') }}</div>
                            <div class="mt-1 text-sm text-gray-900">{{ __('admin.keyword_data_source.platform_serpapi') }}</div>
                        </div>

                        <div>
                            <label for="api_key" class="block text-sm font-medium text-gray-700">{{ __('admin.keyword_data_source.api_key_field') }}</label>
                            <input type="password" name="api_key" id="api_key"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500 sm:text-sm"
                                   autocomplete="new-password"
                                   placeholder="{{ $hasApiKey ? __('admin.keyword_data_source.api_key_placeholder_keep') : __('admin.keyword_data_source.api_key_placeholder') }}">
                            @if (session('api_key_error'))
                                <p class="mt-2 text-sm text-red-600">{{ session('api_key_error') }}</p>
                            @else
                                @error('api_key')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            @endif
                            <p class="mt-2 text-sm text-gray-500">{{ __('admin.keyword_data_source.api_key_help') }}</p>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="admin-btn admin-btn-primary">
                                <i data-lucide="save" class="h-4 w-4"></i>
                                {{ __('admin.keyword_data_source.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i data-lucide="info" class="h-5 w-5 text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">{{ __('admin.keyword_data_source.help_title') }}</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>{{ __('admin.keyword_data_source.help_serpapi') }}</li>
                            <li>{{ __('admin.keyword_data_source.help_key') }}</li>
                            <li>{{ __('admin.keyword_data_source.help_apply') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
@endpush
