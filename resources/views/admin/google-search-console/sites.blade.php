@extends('admin.layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ __('admin.gsc.sites_heading') }}</h1>
                <p class="admin-hero-sub">{{ $connection->name }}</p>
            </div>
        </div>

        <div class="admin-card p-6">
            @if ($listError)
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ __('admin.gsc.message.list_failed') }}<br><span class="text-xs">{{ $listError }}</span></div>
            @elseif (empty($verified))
                <p class="text-sm text-gray-500">{{ __('admin.gsc.sites_empty') }}</p>
            @else
                <p class="mb-4 text-sm text-gray-600">{{ __('admin.gsc.sites_intro') }}</p>
                <form method="POST" action="{{ route('admin.google-search-console.add-sites', $connection->id) }}" class="space-y-3">
                    @csrf
                    @foreach ($verified as $site)
                        @php($already = in_array($site['siteUrl'], $existing, true))
                        <label class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2 {{ $already ? 'opacity-60' : '' }}">
                            <input type="checkbox" name="sites[]" value="{{ $site['siteUrl'] }}" @checked($already) @disabled($already)>
                            <span class="font-mono text-sm">{{ $site['siteUrl'] }}</span>
                            <span class="ml-auto text-xs text-gray-400">{{ $already ? __('admin.gsc.sites_already') : $site['permissionLevel'] }}</span>
                        </label>
                    @endforeach
                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.google-search-console.index') }}" class="admin-btn admin-btn-secondary">{{ __('admin.gsc.button.back') }}</a>
                        <button type="submit" class="admin-btn admin-btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>{{ __('admin.gsc.button.add_selected') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
