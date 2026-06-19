@extends('admin.layouts.app')

@section('content')
    <div>
        <div class="admin-hero">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.keyword-trends.index') }}" class="text-white/70 hover:text-white"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
                <div>
                    <h1 class="admin-hero-title">{{ $source->name }}</h1>
                    <p class="admin-hero-sub">{{ __('admin.keyword_trends.provider.'.$source->provider) }} · {{ $source->category }}</p>
                </div>
            </div>
            <div class="admin-hero-actions">
                <a href="{{ route('admin.keyword-trends.edit', $source->id) }}" class="admin-btn admin-btn-secondary"><i data-lucide="pencil" class="h-4 w-4"></i>{{ __('admin.keyword_trends.button.edit') }}</a>
                <form method="POST" action="{{ route('admin.keyword-trends.fetch', $source->id) }}">@csrf
                    <button type="submit" class="admin-btn admin-btn-secondary"><i data-lucide="refresh-cw" class="h-4 w-4"></i>{{ __('admin.keyword_trends.button.fetch_now') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.keyword-trends.import', $source->id) }}">@csrf
                    <button type="submit" class="admin-btn admin-btn-primary"><i data-lucide="import" class="h-4 w-4"></i>{{ __('admin.keyword_trends.button.import') }}</button>
                </form>
            </div>
        </div>

        @if (! empty($revealedSecret))
            <div class="admin-card mb-6"><div class="admin-card-body text-sm"><span class="font-semibold">{{ __('admin.keyword_trends.secret_revealed') }}</span> <code class="rounded bg-gray-100 px-2 py-1">{{ $revealedSecret }}</code></div></div>
        @endif

        @if ($snapshot)
            <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="admin-vstat grad-indigo"><div class="min-w-0"><div class="admin-vstat-label">{{ __('admin.keyword_trends.snapshot.status') }}</div><div class="admin-vstat-value text-xl">{{ __('admin.keyword_trends.snapshot.'.$snapshot->status) }}</div></div></div>
                <div class="admin-vstat grad-sky"><div class="min-w-0"><div class="admin-vstat-label">{{ __('admin.keyword_trends.snapshot.fetched') }}</div><div class="admin-vstat-value">{{ $snapshot->fetched_count }}</div></div></div>
                <div class="admin-vstat grad-emerald"><div class="min-w-0"><div class="admin-vstat-label">{{ __('admin.keyword_trends.snapshot.kept') }}</div><div class="admin-vstat-value">{{ $snapshot->kept_count }}</div></div></div>
                <div class="admin-vstat grad-amber"><div class="min-w-0"><div class="admin-vstat-label">{{ __('admin.keyword_trends.snapshot.imported') }}</div><div class="admin-vstat-value">{{ $snapshot->imported_count }}</div></div></div>
            </div>
        @endif

        @if ($trends->isEmpty())
            <div class="admin-empty">
                <div class="admin-empty-icon"><i data-lucide="search" class="h-7 w-7"></i></div>
                <div class="admin-empty-desc">{{ $snapshot ? __('admin.keyword_trends.empty.trends') : __('admin.keyword_trends.snapshot.none') }}</div>
            </div>
        @else
            <div class="admin-card overflow-hidden">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.keyword_trends.table.keyword') }}</th>
                            <th>{{ __('admin.keyword_trends.table.heat') }}</th>
                            <th>{{ __('admin.keyword_trends.table.volume') }}</th>
                            <th>{{ __('admin.keyword_trends.table.trend') }}</th>
                            <th>{{ __('admin.keyword_trends.table.imported') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trends as $t)
                            <tr>
                                <td class="font-medium text-gray-900">{{ $t->keyword }}</td>
                                <td>
                                    <span class="inline-flex items-center gap-2">
                                        <span class="block h-2 w-24 overflow-hidden rounded-full bg-gray-100"><span class="block h-2 rounded-full bg-blue-600" style="width: {{ max(2, min(100, (int) $t->heat)) }}%"></span></span>
                                        <span class="text-xs font-semibold text-gray-700">{{ (int) $t->heat }}</span>
                                    </span>
                                </td>
                                <td>{{ $t->search_volume !== null ? number_format($t->search_volume) : '—' }}</td>
                                <td><span class="admin-badge {{ $t->trend_direction === 'rising' ? 'is-success' : ($t->trend_direction === 'falling' ? 'is-danger' : 'is-neutral') }}">{{ __('admin.keyword_trends.trend.'.($t->trend_direction ?: 'flat')) }}</span></td>
                                <td>@if ($t->imported)<span class="admin-badge is-info">✓</span>@else<span class="text-gray-300">—</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($source->activeSecret)
            <form method="POST" action="{{ route('admin.keyword-trends.reveal-secret', $source->id) }}" class="mt-8 flex items-end gap-3">
                @csrf
                <div class="admin-field mb-0">
                    <label class="admin-label" for="password">{{ __('admin.keyword_trends.button.reveal_secret') }}</label>
                    <input class="admin-input" type="password" name="password" id="password" placeholder="••••••••">
                </div>
                <button type="submit" class="admin-btn admin-btn-secondary"><i data-lucide="key-round" class="h-4 w-4"></i>{{ __('admin.keyword_trends.button.reveal_secret') }}</button>
            </form>
        @endif
    </div>
@endsection
