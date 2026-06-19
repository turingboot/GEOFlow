@php
    $isEdit = isset($source) && $source;
    $action = $isEdit ? route('admin.keyword-trends.update', $source->id) : route('admin.keyword-trends.store');
    $cfg = $isEdit ? $source->resolvedConfig() : [];
    $seedText = $isEdit && is_array($source->seed_keywords) ? implode("\n", $source->seed_keywords) : old('seed_keywords');
@endphp
<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div class="admin-field">
            <label class="admin-label" for="name">{{ __('admin.keyword_trends.field.name') }}</label>
            <input class="admin-input" type="text" id="name" name="name" value="{{ old('name', $isEdit ? $source->name : '') }}" required>
        </div>
        <div class="admin-field">
            <label class="admin-label" for="provider">{{ __('admin.keyword_trends.field.provider') }}</label>
            <select class="admin-select" id="provider" name="provider">
                @foreach ($providers as $p)
                    <option value="{{ $p }}" @selected(old('provider', $isEdit ? $source->provider : 'dataforseo') === $p)>{{ __('admin.keyword_trends.provider.'.$p) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="admin-field">
        <label class="admin-label" for="category">{{ __('admin.keyword_trends.field.category') }}</label>
        <input class="admin-input" type="text" id="category" name="category" value="{{ old('category', $isEdit ? $source->category : '') }}" required>
        <p class="mt-1 text-xs text-gray-500">{{ __('admin.keyword_trends.help.category') }}</p>
    </div>

    <div class="admin-field">
        <label class="admin-label" for="seed_keywords">{{ __('admin.keyword_trends.field.seed_keywords') }}</label>
        <textarea class="admin-textarea" id="seed_keywords" name="seed_keywords" rows="3">{{ $seedText }}</textarea>
        <p class="mt-1 text-xs text-gray-500">{{ __('admin.keyword_trends.help.seed_keywords') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
        <div class="admin-field">
            <label class="admin-label" for="region">{{ __('admin.keyword_trends.field.region') }}</label>
            <input class="admin-input" type="text" id="region" name="region" value="{{ old('region', $isEdit ? $source->region : config('geoflow.keyword_trends.default_region', 'US')) }}">
        </div>
        <div class="admin-field">
            <label class="admin-label" for="language">{{ __('admin.keyword_trends.field.language') }}</label>
            <input class="admin-input" type="text" id="language" name="language" value="{{ old('language', $isEdit ? $source->language : config('geoflow.keyword_trends.default_language', 'en')) }}">
        </div>
        <div class="admin-field" data-provider-only="dataforseo">
            <label class="admin-label" for="location_name">{{ __('admin.keyword_trends.field.location_name') }}</label>
            <input class="admin-input" type="text" id="location_name" name="location_name" value="{{ old('location_name', $cfg['location_name'] ?? 'United States') }}">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
        <div class="admin-field">
            <label class="admin-label" for="heat_threshold">{{ __('admin.keyword_trends.field.heat_threshold') }}</label>
            <input class="admin-input" type="number" min="0" max="100" id="heat_threshold" name="heat_threshold" value="{{ old('heat_threshold', $isEdit ? $source->heat_threshold : config('geoflow.keyword_trends.heat_threshold', 60)) }}">
            <p class="mt-1 text-xs text-gray-500">{{ __('admin.keyword_trends.help.heat_threshold') }}</p>
        </div>
        <div class="admin-field">
            <label class="admin-label" for="top_n">{{ __('admin.keyword_trends.field.top_n') }}</label>
            <input class="admin-input" type="number" min="1" id="top_n" name="top_n" value="{{ old('top_n', $isEdit ? $source->top_n : config('geoflow.keyword_trends.top_n', 50)) }}">
        </div>
        <div class="admin-field">
            <label class="admin-label" for="schedule">{{ __('admin.keyword_trends.field.schedule') }}</label>
            <select class="admin-select" id="schedule" name="schedule">
                @foreach ($schedules as $s)
                    <option value="{{ $s }}" @selected(old('schedule', $isEdit ? $source->schedule : 'manual') === $s)>{{ __('admin.keyword_trends.schedule.'.$s) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div class="admin-field">
            <label class="admin-label" for="target_keyword_library_id">{{ __('admin.keyword_trends.field.target_library') }}</label>
            <select class="admin-select" id="target_keyword_library_id" name="target_keyword_library_id">
                <option value="">{{ __('admin.keyword_trends.help.none_library') }}</option>
                @foreach ($libraries as $lib)
                    <option value="{{ $lib->id }}" @selected((int) old('target_keyword_library_id', $isEdit ? $source->target_keyword_library_id : 0) === (int) $lib->id)>{{ $lib->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="admin-field" data-provider-only="dataforseo">
            <label class="admin-label" for="dataforseo_login">{{ __('admin.keyword_trends.field.dataforseo_login') }}</label>
            <input class="admin-input" type="text" id="dataforseo_login" name="dataforseo_login" value="{{ old('dataforseo_login', $cfg['login'] ?? '') }}">
        </div>
    </div>

    <div class="admin-field">
        <label class="admin-label" for="api_key">{{ __('admin.keyword_trends.field.api_key') }}</label>
        <input class="admin-input" type="password" id="api_key" name="api_key" autocomplete="new-password" placeholder="{{ $isEdit ? '••••••••' : '' }}">
        <p class="mt-1 text-xs text-gray-500">{{ __('admin.keyword_trends.help.api_key') }}</p>
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="hidden" name="auto_import" value="0">
        <input type="checkbox" name="auto_import" value="1" @checked(old('auto_import', $isEdit ? $source->auto_import : false))>
        {{ __('admin.keyword_trends.field.auto_import') }}
    </label>

    <div class="flex justify-end gap-3 pt-2">
        <a href="{{ route('admin.keyword-trends.index') }}" class="admin-btn admin-btn-secondary">{{ __('admin.keyword_trends.button.cancel') }}</a>
        <button type="submit" class="admin-btn admin-btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i>
            {{ __('admin.keyword_trends.button.save') }}
        </button>
    </div>
</form>

<script>
    (function () {
        var providerSelect = document.getElementById('provider');
        if (! providerSelect) {
            return;
        }
        function syncProviderFields() {
            var current = providerSelect.value;
            document.querySelectorAll('[data-provider-only]').forEach(function (el) {
                var allowed = (el.getAttribute('data-provider-only') || '').split(',');
                el.classList.toggle('hidden', allowed.indexOf(current) === -1);
            });
        }
        providerSelect.addEventListener('change', syncProviderFields);
        syncProviderFields();
    })();
</script>
