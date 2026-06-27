<?php

namespace Tests\Feature;

use App\Jobs\FetchGscJob;
use App\Models\Admin;
use App\Models\GscProperty;
use App\Models\GscPropertySecret;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminGscPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_guest_is_redirected_from_gsc_pages(): void
    {
        $this->get(route('admin.google-search-console.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_index_and_create_pages(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.index'))
            ->assertOk()
            ->assertSee(__('admin.gsc.page_title'))
            ->assertSee(__('admin.gsc.empty.properties'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.google-search-console.create'))
            ->assertOk()
            ->assertSee(__('admin.gsc.create_heading'));
    }

    public function test_admin_can_create_service_account_property_with_encrypted_secret(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.google-search-console.store'), [
                'name' => '示例站点',
                'site_url' => 'sc-domain:example.com',
                'auth_type' => 'service_account',
                'schedule' => 'daily',
                'service_account_json' => '{"type":"service_account","client_email":"svc@p.iam.gserviceaccount.com","private_key":"x"}',
            ])
            ->assertRedirect();

        $property = GscProperty::query()->where('site_url', 'sc-domain:example.com')->firstOrFail();
        $this->assertSame((int) $admin->tenant_id, (int) $property->tenant_id);

        $secret = GscPropertySecret::query()->where('gsc_property_id', $property->id)->where('status', 'active')->firstOrFail();
        $this->assertSame(GscPropertySecret::KIND_SERVICE_ACCOUNT, $secret->secret_kind);
        $this->assertStringStartsWith('enc:v1:', (string) $secret->secret_ciphertext);
        $this->assertStringContainsString('svc@p.iam.gserviceaccount.com', app(ApiKeyCrypto::class)->decrypt((string) $secret->secret_ciphertext));
    }

    public function test_fetch_dispatches_job_on_trends_queue(): void
    {
        Queue::fake();
        $admin = $this->makeAdmin();
        $property = GscProperty::query()->create([
            'name' => '站点',
            'site_url' => 'sc-domain:example.com',
            'auth_type' => 'oauth',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.google-search-console.fetch', $property->id))
            ->assertRedirect(route('admin.google-search-console.show', $property->id));

        Queue::assertPushedOn('trends', FetchGscJob::class);
    }

    public function test_reveal_secret_requires_super_admin(): void
    {
        $admin = $this->makeAdmin('admin');
        $property = GscProperty::query()->create([
            'name' => '站点',
            'site_url' => 'sc-domain:example.com',
            'auth_type' => 'service_account',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.google-search-console.reveal-secret', $property->id), ['password' => 'secret-123'])
            ->assertForbidden();
    }

    private function makeAdmin(string $role = 'admin'): Admin
    {
        return Admin::query()->create([
            'username' => 'gsc_'.$role.'_'.bin2hex(random_bytes(3)),
            'password' => 'secret-123',
            'email' => 'gsc-'.bin2hex(random_bytes(3)).'@example.com',
            'display_name' => 'GSC Admin',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
