<?php

namespace App\Support\Tenancy;

use LogicException;

class TenantStoragePath
{
    public static function prefix(string $path): string
    {
        $tenantId = TenantContext::id();
        if ($tenantId === null || $tenantId <= 0) {
            throw new LogicException('Tenant storage path requires an active tenant context.');
        }

        return 'tenants/'.$tenantId.'/'.ltrim($path, '/');
    }
}
