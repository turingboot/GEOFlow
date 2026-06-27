@extends('admin.layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ __('admin.gsc.sa_heading') }}</h1>
                <p class="admin-hero-sub">{{ __('admin.gsc.sa_subtitle') }}</p>
            </div>
        </div>

        <div class="admin-card p-6">
            <p class="mb-4 rounded-md bg-blue-50 px-3 py-2 text-xs text-blue-700">{{ __('admin.gsc.help.sa_steps') }}</p>
            <form method="POST" action="{{ route('admin.google-search-console.service-account.store') }}" class="space-y-5">
                @csrf
                <div class="admin-field">
                    <label class="admin-label" for="name">{{ __('admin.gsc.field.connection_name') }}</label>
                    <input class="admin-input" type="text" id="name" name="name" value="{{ old('name') }}" placeholder="{{ __('admin.gsc.field.connection_name_placeholder') }}">
                </div>
                <div class="admin-field">
                    <label class="admin-label" for="service_account_json">{{ __('admin.gsc.field.service_account_json') }}</label>
                    <textarea class="admin-textarea font-mono text-xs" id="service_account_json" name="service_account_json" rows="8" required placeholder='{ "type": "service_account", "client_email": "...", "private_key": "..." }'>{{ old('service_account_json') }}</textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('admin.google-search-console.index') }}" class="admin-btn admin-btn-secondary">{{ __('admin.gsc.button.cancel') }}</a>
                    <button type="submit" class="admin-btn admin-btn-primary"><i data-lucide="save" class="h-4 w-4"></i>{{ __('admin.gsc.button.save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
