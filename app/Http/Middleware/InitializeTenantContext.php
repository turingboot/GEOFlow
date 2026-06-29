<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Support\Tenancy\AdminTenantContext;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantContext
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();
        AdminTenantContext::applyForAdmin($admin instanceof Admin ? $admin : null);

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
