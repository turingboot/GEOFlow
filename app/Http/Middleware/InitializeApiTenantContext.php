<?php

namespace App\Http\Middleware;

use App\Http\ApiAuthContext;
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
        $tenantId = $apiAuth instanceof ApiAuthContext ? $apiAuth->tenantId : null;

        TenantContext::set($tenantId);

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
