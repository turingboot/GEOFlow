@php
    $isEdit = isset($property) && $property;
    $action = $isEdit ? route('admin.google-search-console.update', $property->id) : route('admin.google-search-console.store');
@endphp
<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div class="admin-field">
            <label class="admin-label" for="name">{{ __('admin.gsc.field.name') }}</label>
            <input class="admin-input" type="text" id="name" name="name" value="{{ old('name', $isEdit ? $property->name : '') }}" required>
        </div>
        <div class="admin-field">
            <label class="admin-label" for="schedule">{{ __('admin.gsc.field.schedule') }}</label>
            <select class="admin-select" id="schedule" name="schedule">
                @foreach ($schedules as $s)
                    <option value="{{ $s }}" @selected(old('schedule', $isEdit ? $property->schedule : 'daily') === $s)>{{ __('admin.gsc.schedule.'.$s) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="admin-field">
        <label class="admin-label" for="site_url">{{ __('admin.gsc.field.site_url') }}</label>
        <input class="admin-input" type="text" id="site_url" name="site_url" value="{{ old('site_url', $isEdit ? $property->site_url : '') }}" placeholder="sc-domain:example.com" required>
        <p class="mt-1 text-xs text-gray-500">{{ __('admin.gsc.help.site_url') }}</p>
    </div>

    <div class="admin-field">
        <label class="admin-label" for="auth_type">{{ __('admin.gsc.field.auth_type') }}</label>
        <select class="admin-select" id="auth_type" name="auth_type">
            @foreach ($authTypes as $type)
                <option value="{{ $type }}" @selected(old('auth_type', $isEdit ? $property->auth_type : 'service_account') === $type)>{{ __('admin.gsc.auth.'.$type) }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">{{ __('admin.gsc.help.auth_type') }}</p>
    </div>

    <div class="admin-field" data-auth-only="service_account">
        <label class="admin-label" for="service_account_json">{{ __('admin.gsc.field.service_account_json') }}</label>
        <textarea class="admin-textarea font-mono text-xs" id="service_account_json" name="service_account_json" rows="6" placeholder='{ "type": "service_account", "client_email": "...", "private_key": "..." }'></textarea>
        <p class="mt-1 text-xs text-gray-500">{{ __('admin.gsc.help.service_account_json') }}</p>
    </div>

    <div class="admin-field" data-auth-only="oauth">
        <p class="rounded-md bg-blue-50 px-3 py-2 text-xs text-blue-700">{{ __('admin.gsc.help.oauth_after_save') }}</p>
    </div>

    <div class="flex justify-end gap-3 pt-2">
        <a href="{{ route('admin.google-search-console.index') }}" class="admin-btn admin-btn-secondary">{{ __('admin.gsc.button.cancel') }}</a>
        <button type="submit" class="admin-btn admin-btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i>
            {{ __('admin.gsc.button.save') }}
        </button>
    </div>
</form>

<script>
    (function () {
        var authSelect = document.getElementById('auth_type');
        if (! authSelect) {
            return;
        }
        function syncAuthFields() {
            var current = authSelect.value;
            document.querySelectorAll('[data-auth-only]').forEach(function (el) {
                el.classList.toggle('hidden', el.getAttribute('data-auth-only') !== current);
            });
        }
        authSelect.addEventListener('change', syncAuthFields);
        syncAuthFields();
    })();
</script>
