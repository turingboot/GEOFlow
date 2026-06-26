<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin.tasks.tenant.{tenantId}', function (Admin $admin, int $tenantId): bool {
    if ((string) ($admin->status ?? '') !== 'active') {
        return false;
    }

    return $admin->isSuperAdmin() || (int) ($admin->tenant_id ?? 0) === $tenantId;
}, ['guards' => ['admin']]);
