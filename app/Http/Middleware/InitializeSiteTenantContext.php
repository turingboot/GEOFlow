<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\DistributionChannel;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InitializeSiteTenantContext
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $channel = $this->resolveChannel($request);

        if ($channel instanceof DistributionChannel && (int) ($channel->tenant_id ?? 0) > 0) {
            TenantContext::set((int) $channel->tenant_id);
            $request->attributes->set('site_channel', $channel);
            $request->attributes->set('site_channel_settings', $channel->resolvedSiteSettings());
        } else {
            $admin = Auth::guard('admin')->user();
            if ($admin instanceof Admin && ! $admin->isSuperAdmin() && (int) ($admin->tenant_id ?? 0) > 0) {
                TenantContext::set((int) $admin->tenant_id);
            }
        }

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }

    private function resolveChannel(Request $request): ?DistributionChannel
    {
        $host = strtolower(trim((string) $request->getHost()));
        if ($host === '' || in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return null;
        }

        return DistributionChannel::withoutGlobalScopes()
            ->where('domain', $host)
            ->where('status', 'active')
            ->first();
    }
}
