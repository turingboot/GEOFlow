<?php

namespace App\Support\Tenancy;

use App\Models\Admin;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantProvisioner
{
    public function ensureForAdmin(Admin $admin): Tenant
    {
        if ($admin->tenant_id) {
            return Tenant::query()->whereKey((int) $admin->tenant_id)->firstOrFail();
        }

        $tenant = Tenant::query()->create([
            'name' => $this->tenantName($admin),
            'slug' => $this->uniqueSlug((string) $admin->username),
            'owner_admin_id' => (int) $admin->id,
            'status' => 'active',
        ]);

        $admin->forceFill(['tenant_id' => (int) $tenant->id])->save();

        return $tenant;
    }

    private function tenantName(Admin $admin): string
    {
        $displayName = trim((string) $admin->display_name);

        return $displayName !== '' ? $displayName : (string) $admin->username;
    }

    private function uniqueSlug(string $username): string
    {
        $baseSlug = Str::slug($username) ?: 'tenant';
        $slug = $baseSlug;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
