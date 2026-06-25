<?php

namespace App\Http\Middleware;

use App\Http\ApiAuthContext;
use App\Models\Admin;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeApiTenantContext
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiAuth = $request->attributes->get('api_auth');
        $adminId = $apiAuth instanceof ApiAuthContext ? $apiAuth->auditAdminId : null;

        $admin = $adminId !== null && $adminId > 0
            ? Admin::query()->whereKey($adminId)->first()
            : null;

        TenantContext::fromAdmin($admin);

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
