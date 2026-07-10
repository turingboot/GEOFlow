<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Services\Admin\MembershipService;
use App\Support\AdminWeb;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipService $membershipService) {}

    public function index(): View
    {
        return view('admin.memberships.index', [
            'pageTitle' => '会员管理',
            'activeMenu' => 'memberships',
            'adminSiteName' => AdminWeb::siteName(),
            'plans' => MembershipPlan::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatePlan($request);

        MembershipPlan::query()->create($payload);

        return redirect()->route('admin.memberships.index')->with('message', '会员等级已创建。');
    }

    public function update(Request $request, int $planId): RedirectResponse
    {
        $plan = MembershipPlan::query()->whereKey($planId)->firstOrFail();
        $plan->update($this->validatePlan($request));

        return redirect()->route('admin.memberships.index')->with('message', '会员等级已更新。');
    }

    public function destroy(int $planId): RedirectResponse
    {
        $plan = MembershipPlan::query()->withCount('memberships')->whereKey($planId)->firstOrFail();

        if ((int) $plan->memberships_count > 0) {
            return back()->withErrors('该会员等级已有用户使用，请先停用。');
        }

        $plan->delete();

        return redirect()->route('admin.memberships.index')->with('message', '会员等级已删除。');
    }

    public function show(): View
    {
        return view('admin.memberships.show', [
            'pageTitle' => '会员详情',
            'adminSiteName' => AdminWeb::siteName(),
            'membership' => $this->membershipService->summaryForTenant(TenantContext::id()),
        ]);
    }

    /**
     * @return array{name:string,article_monthly_limit:int,knowledge_base_limit:int,image_storage_limit_bytes:int,price:float,is_custom:bool,is_active:bool,sort_order:int}
     */
    private function validatePlan(Request $request): array
    {
        $this->normalizeIntegerFields($request, [
            'article_monthly_limit',
            'knowledge_base_limit',
            'image_storage_limit_mb',
            'sort_order',
        ]);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'article_monthly_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'knowledge_base_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'image_storage_limit_mb' => ['required', 'integer', 'min:0', 'max:10485760'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_custom' => ['nullable', 'boolean'],
            'is_active' => ['required', Rule::in(['0', '1'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        return [
            'name' => trim((string) $payload['name']),
            'article_monthly_limit' => (int) $payload['article_monthly_limit'],
            'knowledge_base_limit' => (int) $payload['knowledge_base_limit'],
            'image_storage_limit_bytes' => (int) $payload['image_storage_limit_mb'] * 1024 * 1024,
            'price' => (float) ($payload['price'] ?? 0),
            'is_custom' => (bool) ($payload['is_custom'] ?? false),
            'is_active' => (bool) ((int) $payload['is_active']),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ];
    }

    /**
     * @param  list<string>  $fields
     */
    private function normalizeIntegerFields(Request $request, array $fields): void
    {
        $updates = [];
        foreach ($fields as $field) {
            $value = $request->input($field);
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if (! preg_match('/^\d+$/', $trimmed)) {
                continue;
            }

            $normalized = ltrim($trimmed, '0');
            $updates[$field] = $normalized !== '' ? $normalized : '0';
        }

        if ($updates !== []) {
            $request->merge($updates);
        }
    }
}
