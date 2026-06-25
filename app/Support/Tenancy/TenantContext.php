<?php

namespace App\Support\Tenancy;

use App\Models\Admin;
use Closure;

class TenantContext
{
    private static ?int $tenantId = null;

    private static bool $bypass = false;

    public static function set(?int $tenantId, bool $bypass = false): void
    {
        self::$tenantId = $tenantId !== null && $tenantId > 0 ? $tenantId : null;
        self::$bypass = $bypass;
    }

    public static function fromAdmin(?Admin $admin): void
    {
        if (! $admin) {
            self::clear();

            return;
        }

        self::set($admin->tenant_id, $admin->isSuperAdmin());
    }

    public static function id(): ?int
    {
        return self::$tenantId;
    }

    public static function shouldBypass(): bool
    {
        return self::$bypass || self::$tenantId === null;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
        self::$bypass = false;
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(?int $tenantId, Closure $callback, bool $bypass = false): mixed
    {
        $previousTenantId = self::$tenantId;
        $previousBypass = self::$bypass;

        self::set($tenantId, $bypass);

        try {
            return $callback();
        } finally {
            self::$tenantId = $previousTenantId;
            self::$bypass = $previousBypass;
        }
    }
}
