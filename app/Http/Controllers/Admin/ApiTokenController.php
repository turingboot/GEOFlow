<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Api\ApiTokenService;
use App\Support\AdminWeb;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * API Token 管理控制器（超级管理员）。
 *
 * 对齐 bak/admin/api-tokens.php 的核心能力：
 * 1. 创建 Token（一次性明文回显）；
 * 2. 列表查看 Token 元数据；
 * 3. 撤销已发放 Token。
 */
class ApiTokenController extends Controller
{
    public function __construct(
        private readonly ApiTokenService $apiTokenService
    ) {}

    /**
     * API Token 管理页。
     */
    public function index(): View
    {
        return view('admin.api-tokens.index', [
            'pageTitle' => __('admin.api_tokens.page_title'),
            'activeMenu' => 'admin_users',
            'adminSiteName' => AdminWeb::siteName(),
            'tokens' => $this->apiTokenService->listTokens(),
            'tenants' => $this->tokenTenantOptions(),
            'availableScopes' => $this->apiTokenService->getAvailableScopes(),
            'defaultExpiresAtInput' => $this->apiTokenService->defaultExpiresAtInputValue(),
        ]);
    }

    /**
     * 创建 API Token。
     */
    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string'],
            'tenant_id' => ['required', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $tenantId = $this->resolveTokenTenantId((int) $payload['tenant_id']);
            $created = TenantContext::run($tenantId, fn (): array => $this->apiTokenService->createToken(
                (string) $payload['name'],
                is_array($payload['scopes']) ? $payload['scopes'] : [],
                auth('admin')->id() !== null ? (int) auth('admin')->id() : null,
                (string) ($payload['expires_at'] ?? '')
            ));

            return redirect()
                ->route('admin.api-tokens.index')
                ->with('message', __('admin.api_tokens.message.created'))
                ->with('new_api_token', (string) $created['token']);
        } catch (ApiException $exception) {
            return back()->withErrors($exception->getMessage())->withInput();
        } catch (Throwable $exception) {
            return back()->withErrors(__('admin.api_tokens.error.operation_failed', ['message' => $exception->getMessage()]))->withInput();
        }
    }

    /**
     * 撤销 API Token。
     */
    public function revoke(int $tokenId): RedirectResponse
    {
        if ($tokenId <= 0) {
            return back()->withErrors(__('admin.api_tokens.error.operation_failed', ['message' => 'Token ID 无效']));
        }

        try {
            $this->apiTokenService->revokeToken($tokenId);

            return redirect()
                ->route('admin.api-tokens.index')
                ->with('message', __('admin.api_tokens.message.revoked'));
        } catch (ApiException $exception) {
            return back()->withErrors($exception->getMessage());
        } catch (Throwable $exception) {
            return back()->withErrors(__('admin.api_tokens.error.operation_failed', ['message' => $exception->getMessage()]));
        }
    }

    /**
     * @return list<array{id:int,name:string,slug:string}>
     */
    private function tokenTenantOptions(): array
    {
        $admin = auth('admin')->user();
        $query = Tenant::query()
            ->select(['id', 'name', 'slug'])
            ->orderBy('name')
            ->orderBy('id');

        if (! $admin?->isSuperAdmin()) {
            $query->whereKey((int) ($admin->tenant_id ?? 0));
        }

        return $query->get()
            ->map(static fn (Tenant $tenant): array => [
                'id' => (int) $tenant->id,
                'name' => (string) $tenant->name,
                'slug' => (string) $tenant->slug,
            ])
            ->all();
    }

    private function resolveTokenTenantId(int $tenantId): int
    {
        $admin = auth('admin')->user();
        if (! $admin?->isSuperAdmin() && (int) ($admin->tenant_id ?? 0) !== $tenantId) {
            throw new ApiException('tenant_forbidden', 'Token tenant is forbidden', 403);
        }

        $exists = Tenant::query()->whereKey($tenantId)->exists();
        if (! $exists) {
            throw new ApiException('tenant_not_found', 'Token tenant not found', 404);
        }

        return $tenantId;
    }
}
