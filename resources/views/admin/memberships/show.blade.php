@extends('admin.layouts.app')

@php
    $articleStatus = $membership['article_over_limit'] ? '异常' : '正常';
    $knowledgeStatus = $membership['knowledge_over_limit'] ? '异常' : '正常';
    $statusClass = static fn (bool $isOverLimit): string => $isOverLimit
        ? 'inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-600'
        : 'inline-flex rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700';
@endphp

@section('content')
    <div class="space-y-6">
        <section class="admin-hero">
            <div class="flex items-start gap-4">
                <a href="{{ route('admin.dashboard') }}" class="mt-1 text-white/70 hover:text-white">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h1 class="admin-hero-title">会员详情</h1>
                    <p class="admin-hero-sub">查看当前会员状态、到期时间和核心资源额度。</p>
                </div>
            </div>
            <div class="admin-hero-actions">
                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700">
                    {{ $membership['status_label'] }}
                </span>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="admin-card overflow-hidden">
                <div class="border-b border-gray-100 px-6 py-5">
                    <p class="text-sm font-medium text-gray-500">当前套餐</p>
                    <h2 class="mt-2 text-3xl font-semibold text-gray-900">{{ $membership['plan_name'] }}</h2>
                    <div class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-lg bg-gray-50 px-4 py-3">
                            <div class="text-gray-500">开始时间</div>
                            <div class="mt-1 font-semibold text-gray-900">{{ $membership['starts_at']?->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-4 py-3">
                            <div class="text-gray-500">到期时间</div>
                            <div class="mt-1 whitespace-nowrap font-semibold text-gray-900">{{ $membership['ends_at']?->format('Y-m-d') ?? '-' }}</div>
                            @if ($membership['status'] === 'disabled')
                                <div class="mt-1 text-xs font-medium text-red-600">会员状态：已停用</div>
                            @endif
                        </div>
                        <div class="rounded-lg bg-gray-50 px-4 py-3">
                            <div class="text-gray-500">剩余天数</div>
                            <div class="mt-1 font-semibold text-gray-900">{{ $membership['remaining_days'] !== null ? $membership['remaining_days'].' 天' : '-' }}</div>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-5">
                    <h3 class="text-base font-semibold text-gray-900">额度使用</h3>
                    <p class="mt-1 text-sm text-gray-500">文章额度按月统计，知识库按当前总数统计。</p>
                    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">资源</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">已用</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">上限</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">状态</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">本月发布文章</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ $membership['article_used'] }} 篇</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ $membership['article_limit'] > 0 ? $membership['article_limit'].' 篇' : '不限量' }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="{{ $statusClass($membership['article_over_limit']) }}">{{ $articleStatus }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">知识库数量</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ $membership['knowledge_used'] }} 个</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ $membership['knowledge_limit'] > 0 ? $membership['knowledge_limit'].' 个' : '不限量' }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="{{ $statusClass($membership['knowledge_over_limit']) }}">{{ $knowledgeStatus }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <aside class="admin-card p-5">
                <h3 class="text-base font-semibold text-gray-900">说明</h3>
                <p class="mt-3 text-sm leading-6 text-gray-600">
                    {{ $membership['message'] !== '' ? $membership['message'] : '当前会员可正常使用发布文章和创建知识库功能。' }}
                </p>
            </aside>
        </section>
    </div>
@endsection
