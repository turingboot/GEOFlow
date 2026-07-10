<?php

namespace Tests\Unit;

use App\Support\AdminBasePathManager;
use Tests\TestCase;

class AdminBasePathManagerTest extends TestCase
{
    public function test_admin_base_path_is_fixed_to_geo(): void
    {
        $this->assertSame('geo', AdminBasePathManager::normalize('/geo_admin/'));
        $this->assertSame('geo', AdminBasePathManager::normalize('admin-panel'));
        $this->assertSame('geo', AdminBasePathManager::normalize('../admin'));
    }

    public function test_persist_keeps_fixed_admin_base_path(): void
    {
        $this->assertSame('geo', AdminBasePathManager::persist('api'));
    }
}
