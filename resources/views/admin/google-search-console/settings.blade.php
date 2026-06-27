@extends('admin.layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ __('admin.gsc.settings_heading') }}</h1>
                <p class="admin-hero-sub">{{ __('admin.gsc.settings_subtitle') }}</p>
            </div>
        </div>

        <div class="admin-card p-6">
            <p class="mb-4 rounded-md bg-blue-50 px-3 py-2 text-xs text-blue-700">{{ __('admin.gsc.help.settings_steps') }}</p>
            <form method="POST" action="{{ route('admin.google-search-console.settings.save') }}" class="space-y-5">
                @csrf
                <div class="admin-field">
                    <label class="admin-label" for="client_id">{{ __('admin.gsc.field.client_id') }}</label>
                    <input class="admin-input" type="text" id="client_id" name="client_id" value="{{ old('client_id', $clientId) }}" required>
                </div>
                <div class="admin-field">
                    <label class="admin-label" for="client_secret">{{ __('admin.gsc.field.client_secret') }}</label>
                    <input class="admin-input" type="password" id="client_secret" name="client_secret" autocomplete="new-password" placeholder="{{ $hasSecret ? '••••••••（留空保持原值）' : '' }}">
                </div>
                <div class="admin-field">
                    <label class="admin-label" for="redirect_uri">{{ __('admin.gsc.field.redirect_uri') }}</label>
                    <input class="admin-input" type="text" id="redirect_uri" name="redirect_uri" value="{{ old('redirect_uri', $redirectUri) }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.gsc.help.redirect_uri') }}</p>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('admin.google-search-console.index') }}" class="admin-btn admin-btn-secondary">{{ __('admin.gsc.button.cancel') }}</a>
                    <button type="submit" class="admin-btn admin-btn-primary"><i data-lucide="save" class="h-4 w-4"></i>{{ __('admin.gsc.button.save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
