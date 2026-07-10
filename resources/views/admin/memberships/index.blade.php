@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0">
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">会员管理</h1>
                <p class="admin-hero-sub">设置不同会员等级对应的文章、知识库和图片容量额度。</p>
            </div>
            <div class="admin-hero-actions">
                <button type="button" onclick="showMembershipPlanModal(null)" class="admin-btn admin-btn-primary">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    新增会员等级
                </button>
            </div>
        </div>

        <div class="admin-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">会员等级列表</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">等级名称</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">每月文章数</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">知识库总数</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">价格</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">图片容量</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($plans as $plan)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $plan->name }}</div>
                                    @if ($plan->is_custom)
                                        <div class="text-xs text-gray-500">企业定制套餐</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $plan->article_monthly_limit > 0 ? $plan->article_monthly_limit.' 篇' : '不限量' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $plan->knowledge_base_limit > 0 ? $plan->knowledge_base_limit.' 个' : '不限量' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">￥{{ number_format((float) $plan->price, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ \App\Services\Admin\MembershipService::formatLimitBytes((int) $plan->image_storage_limit_bytes) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $plan->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $plan->is_active ? '启用' : '停用' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="inline-flex items-center justify-end gap-3">
                                        <button type="button" class="text-blue-600 hover:text-blue-800" onclick="showMembershipPlanModal({{ \Illuminate\Support\Js::from([
                                            'id' => (int) $plan->id,
                                            'name' => (string) $plan->name,
                                            'article_monthly_limit' => (int) $plan->article_monthly_limit,
                                            'knowledge_base_limit' => (int) $plan->knowledge_base_limit,
                                            'image_storage_limit_mb' => (int) ceil(((int) $plan->image_storage_limit_bytes) / 1024 / 1024),
                                            'price' => (float) $plan->price,
                                            'is_custom' => (bool) $plan->is_custom,
                                            'is_active' => (bool) $plan->is_active,
                                            'sort_order' => (int) $plan->sort_order,
                                        ]) }})">编辑</button>
                                        <form method="POST" action="{{ route('admin.memberships.delete', ['planId' => $plan->id]) }}" onsubmit="return confirm('确定删除该会员等级吗？');">
                                            @csrf
                                            <button type="submit" class="text-red-600 hover:text-red-800">删除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="membership-plan-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 id="membership-plan-modal-title" class="text-lg font-medium text-gray-900">新增会员等级</h3>
                    <button type="button" onclick="hideMembershipPlanModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form id="membership-plan-form" method="POST" action="{{ route('admin.memberships.store') }}" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="plan_name">等级名称</label>
                        <input id="plan_name" name="name" type="text" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="plan_article_limit_value">每月文章数</label>
                            <input id="plan_article_limit" name="article_monthly_limit" type="hidden" value="0">
                            <div class="space-y-2 rounded-md border border-gray-200 p-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input id="plan_article_limit_unlimited" type="checkbox" class="rounded border-gray-300 text-indigo-600" onchange="syncMembershipLimitControl('article')">
                                    不限量
                                </label>
                                <input id="plan_article_limit_value" type="number" min="1" placeholder="输入每月文章数" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" oninput="syncMembershipLimitControl('article')">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="plan_knowledge_limit_value">知识库总数</label>
                            <input id="plan_knowledge_limit" name="knowledge_base_limit" type="hidden" value="0">
                            <div class="space-y-2 rounded-md border border-gray-200 p-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input id="plan_knowledge_limit_unlimited" type="checkbox" class="rounded border-gray-300 text-indigo-600" onchange="syncMembershipLimitControl('knowledge')">
                                    不限量
                                </label>
                                <input id="plan_knowledge_limit_value" type="number" min="1" placeholder="输入知识库总数" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" oninput="syncMembershipLimitControl('knowledge')">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="plan_image_storage_limit_value">图片容量（MB）</label>
                            <input id="plan_image_storage_limit" name="image_storage_limit_mb" type="hidden" value="0">
                            <div class="space-y-2 rounded-md border border-gray-200 p-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input id="plan_image_storage_limit_unlimited" type="checkbox" class="rounded border-gray-300 text-indigo-600" onchange="syncMembershipLimitControl('imageStorage')">
                                    不限量
                                </label>
                                <input id="plan_image_storage_limit_value" type="number" min="1" placeholder="输入图片容量 MB" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" oninput="syncMembershipLimitControl('imageStorage')">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="plan_price">价格</label>
                        <input id="plan_price" name="price" type="number" min="0" step="0.01" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input id="plan_is_custom" type="checkbox" name="is_custom" value="1" class="rounded border-gray-300 text-indigo-600">
                            企业定制套餐
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="plan_is_active">状态</label>
                            <select id="plan_is_active" name="is_active" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="1">启用</option>
                                <option value="0">停用</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="plan_sort_order">显示顺序</label>
                        <input id="plan_sort_order" name="sort_order" type="number" min="0" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" onclick="hideMembershipPlanModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">{{ __('admin.button.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md text-white bg-indigo-600 hover:bg-indigo-700">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const membershipPlanStoreRoute = @json(route('admin.memberships.store'));
        const membershipPlanUpdateRouteTemplate = @json(route('admin.memberships.update', ['planId' => '__PLAN_ID__']));
        const membershipLimitControls = {
            article: {
                hidden: 'plan_article_limit',
                value: 'plan_article_limit_value',
                unlimited: 'plan_article_limit_unlimited',
            },
            knowledge: {
                hidden: 'plan_knowledge_limit',
                value: 'plan_knowledge_limit_value',
                unlimited: 'plan_knowledge_limit_unlimited',
            },
            imageStorage: {
                hidden: 'plan_image_storage_limit',
                value: 'plan_image_storage_limit_value',
                unlimited: 'plan_image_storage_limit_unlimited',
            },
        };

        function showMembershipPlanModal(plan) {
            const form = document.getElementById('membership-plan-form');
            document.getElementById('membership-plan-modal-title').textContent = plan ? '编辑会员等级' : '新增会员等级';
            form.action = plan ? membershipPlanUpdateRouteTemplate.replace('__PLAN_ID__', plan.id) : membershipPlanStoreRoute;
            document.getElementById('plan_name').value = plan?.name || '';
            setMembershipLimitControl('article', plan?.article_monthly_limit ?? 0);
            setMembershipLimitControl('knowledge', plan?.knowledge_base_limit ?? 0);
            setMembershipLimitControl('imageStorage', plan?.image_storage_limit_mb ?? 0);
            document.getElementById('plan_price').value = plan?.price ?? 0;
            document.getElementById('plan_is_custom').checked = Boolean(plan?.is_custom);
            document.getElementById('plan_is_active').value = plan && !plan.is_active ? '0' : '1';
            document.getElementById('plan_sort_order').value = plan?.sort_order ?? 0;
            document.getElementById('membership-plan-modal').classList.remove('hidden');
        }

        function hideMembershipPlanModal() {
            document.getElementById('membership-plan-modal').classList.add('hidden');
        }

        function setMembershipLimitControl(type, limit) {
            const controls = membershipLimitControls[type];
            const hiddenInput = document.getElementById(controls.hidden);
            const valueInput = document.getElementById(controls.value);
            const unlimitedInput = document.getElementById(controls.unlimited);
            const normalizedLimit = Number(limit || 0);
            const isUnlimited = normalizedLimit <= 0;

            unlimitedInput.checked = isUnlimited;
            valueInput.value = isUnlimited ? '' : normalizedLimit;
            valueInput.disabled = isUnlimited;
            valueInput.required = !isUnlimited;
            valueInput.classList.toggle('bg-gray-50', isUnlimited);
            hiddenInput.value = isUnlimited ? '0' : String(normalizedLimit);
        }

        function syncMembershipLimitControl(type) {
            const controls = membershipLimitControls[type];
            const hiddenInput = document.getElementById(controls.hidden);
            const valueInput = document.getElementById(controls.value);
            const unlimitedInput = document.getElementById(controls.unlimited);
            const isUnlimited = unlimitedInput.checked;

            valueInput.disabled = isUnlimited;
            valueInput.required = !isUnlimited;
            valueInput.classList.toggle('bg-gray-50', isUnlimited);
            hiddenInput.value = isUnlimited ? '0' : (valueInput.value || '');
        }
    </script>
@endpush
