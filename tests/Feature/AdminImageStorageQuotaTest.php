<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\MembershipPlan;
use App\Services\Admin\MembershipService;
use App\Support\Tenancy\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminImageStorageQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_library_upload_is_blocked_when_membership_image_storage_quota_is_exceeded(): void
    {
        Storage::fake('public');

        $admin = Admin::query()->create([
            'username' => 'image_storage_quota_admin',
            'password' => 'secret-123',
            'role' => 'admin',
            'status' => 'active',
        ]);
        app(TenantProvisioner::class)->ensureForAdmin($admin);

        $plan = MembershipPlan::query()->create([
            'name' => '图片容量测试套餐',
            'article_monthly_limit' => 10,
            'knowledge_base_limit' => 10,
            'image_storage_limit_bytes' => 100,
            'is_active' => true,
            'sort_order' => 100,
        ]);
        app(MembershipService::class)->assignMembership((int) $admin->tenant_id, (int) $plan->id, 'month', 'reopen', null, '', null);

        $library = ImageLibrary::query()->create([
            'name' => '图片容量测试库',
            'description' => '',
            'image_count' => 0,
            'used_task_count' => 0,
        ]);
        Image::query()->create([
            'library_id' => (int) $library->id,
            'filename' => 'used.png',
            'original_name' => 'used.png',
            'file_name' => 'used.png',
            'file_path' => 'storage/uploads/images/used.png',
            'file_size' => 95,
            'mime_type' => 'image/png',
            'width' => 1,
            'height' => 1,
            'tags' => '',
            'used_count' => 0,
            'usage_count' => 0,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.image-libraries.images.upload', ['libraryId' => (int) $library->id]), [
                'images' => [UploadedFile::fake()->create('new-image.jpg', 1, 'image/jpeg')],
            ])
            ->assertSessionHasErrors('membership');

        $this->assertSame(1, Image::query()->where('library_id', (int) $library->id)->count());
    }
}
