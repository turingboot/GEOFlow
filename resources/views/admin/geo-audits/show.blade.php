@extends('admin.layouts.app')

@php
    $scoreClass = $audit->geo_score >= $threshold ? 'is-success' : ($audit->geo_score >= $threshold - 20 ? 'is-warning' : 'is-danger');
    $gateClass = $audit->gate_decision === 'auto_approved' ? 'is-success' : ($audit->gate_decision === 'to_review' ? 'is-warning' : 'is-neutral');
    $dimensions = [
        ['label' => __('admin.geo_audit.field.title_keyword'), 'value' => $audit->title_keyword_match],
        ['label' => __('admin.geo_audit.field.structure'), 'value' => $audit->structure_score],
        ['label' => __('admin.geo_audit.field.kb_coverage'), 'value' => $audit->kb_coverage],
        ['label' => __('admin.geo_audit.field.dup_ratio'), 'value' => $audit->dup_ratio],
    ];
@endphp

@section('content')
    <div>
        <div class="admin-hero">
            <div class="min-w-0">
                <h1 class="admin-hero-title truncate">{{ optional($audit->article)->title ?? ('#'.$audit->article_id) }}</h1>
                <p class="admin-hero-sub">{{ __('admin.geo_audit.detail_subtitle') }}</p>
            </div>
            <div class="admin-hero-actions">
                <a href="{{ route('admin.articles.edit', $audit->article_id) }}" class="admin-btn">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    {{ __('admin.geo_audit.button.back') }}
                </a>
                <form method="POST" action="{{ route('admin.geo-audits.reaudit', $audit->article_id) }}">
                    @csrf
                    <button type="submit" class="admin-btn">
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                        {{ __('admin.geo_audit.button.reaudit') }}
                    </button>
                </form>
                @if (! empty($optimizing))
                    <button type="button" disabled class="admin-btn admin-btn-primary opacity-70 cursor-not-allowed">
                        <i data-lucide="loader-circle" class="h-4 w-4 animate-spin"></i>
                        {{ __('admin.geo_audit.button.optimizing') }}
                    </button>
                @else
                    <form method="POST" action="{{ route('admin.geo-audits.optimize', $audit->article_id) }}" onsubmit="return confirm('{{ __('admin.geo_audit.optimize_confirm') }}')">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn-primary">
                            <i data-lucide="wand-sparkles" class="h-4 w-4"></i>
                            {{ __('admin.geo_audit.button.optimize') }}
                        </button>
                    </form>
                @endif
                <a href="{{ route('admin.articles.edit', $audit->article_id) }}" class="admin-btn">
                    <i data-lucide="pencil" class="h-4 w-4"></i>
                    {{ __('admin.geo_audit.button.review') }}
                </a>
            </div>
        </div>

        @if (session('message'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('message') }}</div>
        @endif
        @if (! empty($optimizing))
            <div class="mb-6 flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">
                <i data-lucide="loader-circle" class="h-5 w-5 animate-spin"></i>
                <span>{{ __('admin.geo_audit.optimizing_banner') }}</span>
            </div>
        @elseif (! empty($optimizeError))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ __('admin.geo_audit.message.optimize_failed', ['error' => $optimizeError]) }}</div>
        @endif

        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="admin-card lg:col-span-1">
                <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.geo_audit.field.score') }}</span></div>
                <div class="p-6 text-center">
                    <div class="text-5xl font-bold {{ $audit->geo_score >= $threshold ? 'text-emerald-600' : 'text-amber-600' }}">{{ $audit->geo_score }}</div>
                    <div class="mt-2 text-xs text-gray-500">{{ __('admin.geo_audit.threshold_label', ['threshold' => $threshold]) }}</div>
                    <div class="mt-4"><span class="admin-badge {{ $gateClass }}">{{ __('admin.geo_audit.gate.'.$audit->gate_decision) }}</span></div>
                    <div class="mt-2 text-xs text-gray-500">{{ __('admin.geo_audit.field.word_count') }}: {{ $audit->word_count }}</div>
                </div>
            </div>

            <div class="admin-card lg:col-span-2">
                <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.geo_audit.dimensions_title') }}</span></div>
                <div class="space-y-4 p-6">
                    @foreach ($dimensions as $dimension)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="text-gray-600">{{ $dimension['label'] }}</span>
                                <span class="font-semibold text-gray-900">{{ $dimension['value'] }}</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                <div class="h-2 rounded-full bg-blue-500" style="width: {{ max(0, min(100, (int) $dimension['value'])) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mb-6 admin-card">
            <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.geo_audit.suggestion_title') }}</span></div>
            <div class="p-6">
                <p class="text-sm leading-6 text-gray-700">{{ $audit->suggestion ?: '—' }}</p>
                @if (!empty($audit->risk_notes))
                    <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-amber-700">
                        @foreach ($audit->risk_notes as $risk)
                            <li>{{ $risk }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        @if ($history->count() > 1)
            <div class="admin-card overflow-hidden">
                <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.geo_audit.history_title') }}</span></div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.geo_audit.field.score') }}</th>
                            <th>{{ __('admin.geo_audit.field.gate') }}</th>
                            <th>{{ __('admin.geo_audit.field.audited_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $row)
                            <tr>
                                <td>{{ $row->geo_score }}</td>
                                <td>{{ __('admin.geo_audit.gate.'.$row->gate_decision) }}</td>
                                <td><span class="text-xs text-gray-500">{{ optional($row->audited_at)->format('Y-m-d H:i') }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@if (! empty($optimizing))
    @push('scripts')
        <script>
            // AI 优化在后台队列异步执行；优化中时本页定时自动刷新，完成后即显示新评分。
            setTimeout(function () { window.location.reload(); }, 8000);
        </script>
    @endpush
@endif
