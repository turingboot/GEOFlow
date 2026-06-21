@extends('admin.layouts.app')

@section('content')
    <div>
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ __('admin.geo_audit.page_title') }}</h1>
                <p class="admin-hero-sub">{{ __('admin.geo_audit.page_subtitle') }}</p>
            </div>
        </div>

        <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
            <div class="admin-vstat grad-indigo">
                <span class="admin-vstat-icon"><i data-lucide="gauge" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.geo_audit.stats.total') }}</div>
                    <div class="admin-vstat-value">{{ $stats['total'] }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-emerald">
                <span class="admin-vstat-icon"><i data-lucide="circle-check" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.geo_audit.stats.auto') }}</div>
                    <div class="admin-vstat-value">{{ $stats['auto'] }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-amber">
                <span class="admin-vstat-icon"><i data-lucide="user-check" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.geo_audit.stats.review') }}</div>
                    <div class="admin-vstat-value">{{ $stats['review'] }}</div>
                </div>
            </div>
            <div class="admin-vstat grad-sky">
                <span class="admin-vstat-icon"><i data-lucide="sigma" class="h-5 w-5"></i></span>
                <div class="min-w-0">
                    <div class="admin-vstat-label">{{ __('admin.geo_audit.stats.avg') }}</div>
                    <div class="admin-vstat-value">{{ $stats['avg'] }}</div>
                </div>
            </div>
        </div>

        @if ($audits->isEmpty())
            <div class="admin-empty">
                <div class="admin-empty-icon"><i data-lucide="gauge" class="h-7 w-7"></i></div>
                <div class="admin-empty-title">{{ __('admin.geo_audit.list_title') }}</div>
                <div class="admin-empty-desc">{{ __('admin.geo_audit.empty') }}</div>
            </div>
        @else
            <div class="admin-card overflow-hidden">
                <div class="admin-card-head"><span class="admin-card-title">{{ __('admin.geo_audit.list_title') }}</span></div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.geo_audit.field.article') }}</th>
                            <th>{{ __('admin.geo_audit.field.score') }}</th>
                            <th>{{ __('admin.geo_audit.field.title_keyword') }}</th>
                            <th>{{ __('admin.geo_audit.field.structure') }}</th>
                            <th>{{ __('admin.geo_audit.field.kb_coverage') }}</th>
                            <th>{{ __('admin.geo_audit.field.dup_ratio') }}</th>
                            <th>{{ __('admin.geo_audit.field.gate') }}</th>
                            <th>{{ __('admin.geo_audit.field.audited_at') }}</th>
                            <th class="text-right">{{ __('admin.geo_audit.field.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audits as $audit)
                            @php
                                $scoreClass = $audit->geo_score >= $threshold ? 'is-success' : ($audit->geo_score >= $threshold - 20 ? 'is-warning' : 'is-danger');
                                $gateClass = $audit->gate_decision === 'auto_approved' ? 'is-success' : ($audit->gate_decision === 'to_review' ? 'is-warning' : 'is-neutral');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.geo-audits.show', $audit->article_id) }}" class="font-semibold text-blue-600 hover:underline">
                                        {{ optional($audit->article)->title ?? ('#'.$audit->article_id) }}
                                    </a>
                                </td>
                                <td><span class="admin-badge {{ $scoreClass }}">{{ $audit->geo_score }}</span></td>
                                <td>{{ $audit->title_keyword_match }}</td>
                                <td>{{ $audit->structure_score }}</td>
                                <td>{{ $audit->kb_coverage }}</td>
                                <td>{{ $audit->dup_ratio }}</td>
                                <td><span class="admin-badge {{ $gateClass }}">{{ __('admin.geo_audit.gate.'.$audit->gate_decision) }}</span></td>
                                <td><span class="text-xs text-gray-500">{{ optional($audit->audited_at)->format('Y-m-d H:i') }}</span></td>
                                <td class="text-right">
                                    @if ($audit->geo_score < $threshold)
                                        <form method="POST" action="{{ route('admin.geo-audits.optimize', $audit->article_id) }}" onsubmit="return confirm('{{ __('admin.geo_audit.optimize_confirm') }}')" class="inline">
                                            @csrf
                                            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">
                                                <i data-lucide="wand-sparkles" class="h-3.5 w-3.5"></i>
                                                {{ __('admin.geo_audit.button.optimize') }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
