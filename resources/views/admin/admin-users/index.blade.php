@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0">
        <div class="admin-hero">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.site-settings.index') }}" class="text-white/70 hover:text-white">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h1 class="admin-hero-title">{{ __('admin.admin_users.page_title') }}</h1>
                    <p class="admin-hero-sub">{{ __('admin.admin_users.page_subtitle') }}</p>
                </div>
            </div>
            <div class="admin-hero-actions">
                <a href="{{ route('admin.admin-activity-logs') }}" class="admin-btn admin-btn-secondary">
                    <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                    {{ __('admin.admin_users.view_logs') }}
                </a>
                <button type="button" onclick="showCreateAdminModal()" class="admin-btn admin-btn-primary">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    {{ __('admin.admin_users.add_admin') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="admin-vstat grad-indigo">
                <span class="admin-vstat-icon"><i data-lucide="users" class="h-6 w-6"></i></span>
                <div class="min-w-0">
                    <dt class="admin-vstat-label">{{ __('admin.admin_users.total_admins') }}</dt>
                    <dd class="admin-vstat-value">{{ $stats['total_admins'] }}</dd>
                </div>
            </div>
            <div class="admin-vstat grad-emerald">
                <span class="admin-vstat-icon"><i data-lucide="badge-check" class="h-6 w-6"></i></span>
                <div class="min-w-0">
                    <dt class="admin-vstat-label">{{ __('admin.admin_users.active_admins') }}</dt>
                    <dd class="admin-vstat-value">{{ $stats['active_admins'] }}</dd>
                </div>
            </div>
            <div class="admin-vstat grad-amber">
                <span class="admin-vstat-icon"><i data-lucide="shield-check" class="h-6 w-6"></i></span>
                <div class="min-w-0">
                    <dt class="admin-vstat-label">{{ __('admin.admin_users.super_admins') }}</dt>
                    <dd class="admin-vstat-value">{{ $stats['super_admins'] }}</dd>
                </div>
            </div>
        </div>

        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <i data-lucide="info" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                <div class="text-sm text-blue-900">
                    {{ __('admin.admin_users.permission_notice') }}
                </div>
            </div>
        </div>

        <div class="admin-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">{{ __('admin.admin_users.list_title') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.admin_users.column_account') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.admin_users.column_role') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.admin_users.column_status') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.admin_users.column_last_login') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.admin_users.column_created') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.admin_users.column_activity') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($admins as $admin)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $admin['display_name'] !== '' ? $admin['display_name'] : $admin['username'] }}</div>
                                    <div class="text-sm text-gray-500">{{ $admin['username'] }}</div>
                                    @if ($admin['email'] !== '')
                                        <div class="text-xs text-gray-400">{{ $admin['email'] }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($admin['is_super_admin'])
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ __('admin.admin_users.role_super_admin') }}</span>
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ __('admin.admin_users.role_admin') }}</span>
                                        <div class="mt-2 text-xs leading-5 text-gray-500">
                                            <div class="font-medium text-gray-700">{{ $admin['membership_plan_name'] }}</div>
                                            @if ($admin['membership_status'] === 'active')
                                                <div>到期：{{ $admin['membership_ends_at'] !== '' ? $admin['membership_ends_at'] : '-' }}</div>
                                            @elseif ($admin['membership_status'] === 'expiring')
                                                <div>{{ $admin['membership_remaining_days'] ?? 0 }} 天后到期</div>
                                            @elseif ($admin['membership_status'] === 'expired')
                                                <div>已到期：{{ $admin['membership_ends_at'] !== '' ? $admin['membership_ends_at'] : '-' }}</div>
                                            @elseif ($admin['membership_status'] === 'disabled')
                                                <div>会员已停用</div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($admin['status'] === 'active')
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ __('admin.admin_users.status_active') }}</span>
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ __('admin.admin_users.status_inactive') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $admin['last_login'] !== '' ? $admin['last_login'] : __('admin.admin_users.none_last_login') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div>{{ $admin['created_at'] }}</div>
                                    <div class="text-xs text-gray-400">
                                        {{ __('admin.admin_users.created_by', ['value' => $admin['creator_username'] !== '' ? $admin['creator_username'] : __('admin.admin_users.system_init')]) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ __('admin.admin_users.activity_count', ['count' => $admin['activity_count']]) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    @if ($admin['id'] === $currentAdminId)
                                        <button
                                            type="button"
                                            onclick="showEditAdminModal({{ \Illuminate\Support\Js::from($admin) }})"
                                            class="text-blue-600 hover:text-blue-800"
                                        >
                                            {{ __('admin.button.edit') }}
                                        </button>
                                    @elseif (! $admin['is_super_admin'])
                                        <div class="inline-flex items-center justify-end gap-3">
                                            <button
                                                type="button"
                                                onclick="showEditAdminModal({{ \Illuminate\Support\Js::from($admin) }})"
                                                class="text-blue-600 hover:text-blue-800"
                                            >
                                                {{ __('admin.button.edit') }}
                                            </button>
                                            <button
                                                type="button"
                                                onclick="showAssignMembershipModal({{ \Illuminate\Support\Js::from($admin) }})"
                                                class="text-slate-600 hover:text-slate-800"
                                            >
                                                管理会员
                                            </button>
                                            <form method="POST" action="{{ route('admin.admin-users.toggle-status', ['adminId' => $admin['id']]) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="next_status" value="{{ $admin['status'] === 'active' ? 'inactive' : 'active' }}">
                                                <button type="submit" class="{{ $admin['status'] === 'active' ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800' }}">
                                                    {{ $admin['status'] === 'active' ? __('admin.admin_users.action_disable') : __('admin.admin_users.action_enable') }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.admin-users.delete', ['adminId' => $admin['id']]) }}" class="inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('admin.admin_users.confirm_delete', ['username' => $admin['username']])) }})">
                                                @csrf
                                                <button type="submit" class="text-red-600 hover:text-red-800">
                                                    {{ __('admin.button.delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-gray-300">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="create-admin-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('admin.admin_users.modal_create') }}</h3>
                    <button type="button" onclick="hideCreateAdminModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.admin-users.store') }}" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.field_username') }}</label>
                        <input type="text" name="username" id="username" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ __('admin.admin_users.placeholder_username') }}" value="{{ old('username') }}">
                    </div>

                    <div>
                        <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.field_display_name') }}</label>
                        <input type="text" name="display_name" id="display_name" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ __('admin.admin_users.placeholder_display_name') }}" value="{{ old('display_name') }}">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.field_email') }}</label>
                        <input type="email" name="email" id="email" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ __('admin.admin_users.placeholder_email') }}" value="{{ old('email') }}">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.field_password') }}</label>
                            <input type="password" name="password" id="password" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.field_confirm_password') }}</label>
                            <input type="password" name="confirm_password" id="confirm_password" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-md p-3 text-sm text-gray-600">
                        {{ __('admin.admin_users.create_help') }}
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" onclick="hideCreateAdminModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">{{ __('admin.button.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md text-white bg-indigo-600 hover:bg-indigo-700">{{ __('admin.admin_users.create_admin_submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="edit-admin-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('admin.admin_users.modal_edit') }}</h3>
                    <button type="button" onclick="hideEditAdminModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form id="edit-admin-form" method="POST" action="#" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <label for="edit_username" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.field_username') }}</label>
                        <input type="text" name="username" id="edit_username" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label for="edit_display_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.field_display_name') }}</label>
                        <input type="text" name="display_name" id="edit_display_name" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.field_email') }}</label>
                        <input type="email" name="email" id="edit_email" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.column_status') }}</label>
                        <input type="hidden" name="status" id="edit_status_hidden" disabled>
                        <select name="status" id="edit_status" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="active">{{ __('admin.admin_users.status_active') }}</option>
                            <option value="inactive">{{ __('admin.admin_users.status_inactive') }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.field_new_password') }}</label>
                            <input type="password" name="password" id="edit_password" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="edit_confirm_password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_users.field_confirm_new_password') }}</label>
                            <input type="password" name="confirm_password" id="edit_confirm_password" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-md p-3 text-sm text-gray-600">
                        {{ __('admin.admin_users.edit_help') }}
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" onclick="hideEditAdminModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">{{ __('admin.button.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md text-white bg-indigo-600 hover:bg-indigo-700">{{ __('admin.admin_users.update_admin_submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="assign-membership-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">分配会员</h3>
                    <button type="button" onclick="hideAssignMembershipModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form id="assign-membership-form" method="POST" action="#" class="px-6 py-5 space-y-4">
                    @csrf
                    <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                        <div>当前用户：<span id="membership_admin_name" class="font-medium text-gray-900"></span></div>
                        <div class="mt-1">当前会员：<span id="membership_current_plan" class="font-medium text-gray-900"></span></div>
                    </div>

                    <div>
                        <label for="membership_plan_id" class="block text-sm font-medium text-gray-700 mb-1">会员等级</label>
                        <select id="membership_plan_id" name="membership_plan_id" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach ($membershipPlans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}（文章 {{ $plan->article_monthly_limit > 0 ? $plan->article_monthly_limit : '不限量' }} / 知识库 {{ $plan->knowledge_base_limit > 0 ? $plan->knowledge_base_limit : '不限量' }}）</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="membership_period" class="block text-sm font-medium text-gray-700 mb-1">开通周期</label>
                            <select id="membership_period" name="period" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" onchange="updateMembershipPreviewDates()">
                                <option value="month">1个月</option>
                                <option value="quarter">1季度</option>
                                <option value="year">1年</option>
                                <option value="custom">自定义</option>
                            </select>
                        </div>
                        <div>
                            <label for="membership_effective_mode" class="block text-sm font-medium text-gray-700 mb-1">生效方式</label>
                            <select id="membership_effective_mode" name="effective_mode" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" onchange="updateMembershipPreviewDates()">
                                <option value="renew">从当前到期时间续期</option>
                                <option value="reopen">从今天重新开通</option>
                            </select>
                        </div>
                    </div>

                    <div id="membership_custom_ends_wrap" class="hidden">
                        <label for="membership_custom_ends_at" class="block text-sm font-medium text-gray-700 mb-1">自定义到期时间</label>
                        <input id="membership_custom_ends_at" name="ends_at" type="date" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" onchange="updateMembershipPreviewDates()">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="rounded-md bg-gray-50 px-3 py-2">
                            <div class="text-gray-500">开始时间</div>
                            <div id="membership_preview_start" class="mt-1 font-semibold text-gray-900">-</div>
                        </div>
                        <div class="rounded-md bg-gray-50 px-3 py-2">
                            <div class="text-gray-500">到期时间</div>
                            <div id="membership_preview_end" class="mt-1 font-semibold text-gray-900">-</div>
                        </div>
                    </div>

                    <div>
                        <label for="membership_remark" class="block text-sm font-medium text-gray-700 mb-1">备注</label>
                        <textarea id="membership_remark" name="remark" rows="2" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="例如：手动开通一个月"></textarea>
                    </div>

                </form>
                <form id="disable-membership-form" method="POST" action="#" class="hidden" onsubmit="return confirm('确定停用该会员吗？');">
                    @csrf
                </form>
                <div class="flex items-center justify-between gap-3 px-6 pb-5 pt-2">
                    <button id="disable-membership-button" type="submit" form="disable-membership-form" class="hidden px-4 py-2 border border-red-200 rounded-md text-red-600 bg-white hover:bg-red-50">停用</button>
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" onclick="hideAssignMembershipModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">{{ __('admin.button.cancel') }}</button>
                        <button type="submit" form="assign-membership-form" class="px-4 py-2 border border-transparent rounded-md text-white bg-indigo-600 hover:bg-indigo-700">保存</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const updateAdminRouteTemplate = @json(route('admin.admin-users.update', ['adminId' => '__ADMIN_ID__']));
        const assignMembershipRouteTemplate = @json(route('admin.admin-users.membership', ['adminId' => '__ADMIN_ID__']));
        const disableMembershipRouteTemplate = @json(route('admin.admin-users.membership.disable', ['adminId' => '__ADMIN_ID__']));
        const currentAdminId = @json($currentAdminId);
        let membershipModalAdmin = null;

        function showCreateAdminModal() {
            document.getElementById('create-admin-modal').classList.remove('hidden');
        }

        function hideCreateAdminModal() {
            document.getElementById('create-admin-modal').classList.add('hidden');
        }

        function showEditAdminModal(admin) {
            const form = document.getElementById('edit-admin-form');
            const statusSelect = document.getElementById('edit_status');
            const statusHidden = document.getElementById('edit_status_hidden');
            const isSelf = Number(admin.id) === Number(currentAdminId);
            form.action = updateAdminRouteTemplate.replace('__ADMIN_ID__', admin.id);
            document.getElementById('edit_username').value = admin.username || '';
            document.getElementById('edit_display_name').value = admin.display_name || '';
            document.getElementById('edit_email').value = admin.email || '';
            statusSelect.value = admin.status || 'active';
            statusSelect.disabled = isSelf;
            statusHidden.disabled = !isSelf;
            statusHidden.value = admin.status || 'active';
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_confirm_password').value = '';
            document.getElementById('edit-admin-modal').classList.remove('hidden');
        }

        function hideEditAdminModal() {
            document.getElementById('edit-admin-modal').classList.add('hidden');
        }

        function showAssignMembershipModal(admin) {
            membershipModalAdmin = admin;
            const form = document.getElementById('assign-membership-form');
            const disableForm = document.getElementById('disable-membership-form');
            const disableButton = document.getElementById('disable-membership-button');
            form.action = assignMembershipRouteTemplate.replace('__ADMIN_ID__', admin.id);
            disableForm.action = disableMembershipRouteTemplate.replace('__ADMIN_ID__', admin.id);
            document.getElementById('membership_admin_name').textContent = admin.display_name || admin.username || '';
            document.getElementById('membership_current_plan').textContent = admin.membership_plan_name || '未开通会员';
            document.getElementById('membership_period').value = 'month';
            document.getElementById('membership_effective_mode').value = 'renew';
            document.getElementById('membership_custom_ends_at').value = '';
            document.getElementById('membership_remark').value = '';
            disableButton.classList.toggle('hidden', !['active', 'expiring'].includes(admin.membership_status || ''));
            updateMembershipPreviewDates();
            document.getElementById('assign-membership-modal').classList.remove('hidden');
        }

        function hideAssignMembershipModal() {
            document.getElementById('assign-membership-modal').classList.add('hidden');
        }

        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function addMonthsNoOverflow(date, months) {
            const next = new Date(date.getTime());
            const day = next.getDate();
            next.setDate(1);
            next.setMonth(next.getMonth() + months);
            const lastDay = new Date(next.getFullYear(), next.getMonth() + 1, 0).getDate();
            next.setDate(Math.min(day, lastDay));
            return next;
        }

        function updateMembershipPreviewDates() {
            const period = document.getElementById('membership_period').value;
            const mode = document.getElementById('membership_effective_mode').value;
            const customWrap = document.getElementById('membership_custom_ends_wrap');
            customWrap.classList.toggle('hidden', period !== 'custom');

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            let start = today;
            if (mode === 'renew' && membershipModalAdmin?.membership_ends_at) {
                const currentEnd = new Date(`${membershipModalAdmin.membership_ends_at}T00:00:00`);
                if (currentEnd > today) {
                    start = currentEnd;
                }
            }

            let end = addMonthsNoOverflow(start, 1);
            if (period === 'quarter') {
                end = addMonthsNoOverflow(start, 3);
            } else if (period === 'year') {
                end = addMonthsNoOverflow(start, 12);
            } else if (period === 'custom') {
                const customValue = document.getElementById('membership_custom_ends_at').value;
                end = customValue ? new Date(`${customValue}T00:00:00`) : start;
            }

            document.getElementById('membership_preview_start').textContent = formatDate(start);
            document.getElementById('membership_preview_end').textContent = formatDate(end);
        }
    </script>
@endpush
