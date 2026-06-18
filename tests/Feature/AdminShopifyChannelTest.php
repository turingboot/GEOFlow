<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DistributionChannel;
use App\Models\DistributionChannelSecret;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopifyChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_shows_shopify_fields(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.distribution.create'))
            ->assertOk()
            ->assertSee(__('admin.distribution.channel_type.shopify_blog'))
            ->assertSee(__('admin.distribution.shopify.section_title'))
            ->assertSee('value="shopify_blog"', false)
            ->assertSee('name="shopify_access_token"', false)
            ->assertSee('name="shopify_api_version"', false)
            ->assertSee('name="shopify_blog_strategy"', false)
            ->assertSee('name="shopify_published"', false);
    }

    public function test_admin_can_create_shopify_blog_channel(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.distribution.store'), [
                'name' => 'Shopify 博客',
                'domain' => 'test-store.myshopify.com',
                'endpoint_url' => 'https://test-store.myshopify.com',
                'channel_type' => 'shopify_blog',
                'shopify_access_token' => 'shpat_secret_token',
                'shopify_api_version' => '2025-10',
                'shopify_blog_strategy' => 'first_blog',
                'shopify_published' => '1',
                'shopify_tag_strategy' => 'keywords_to_tags',
                'shopify_image_strategy' => 'hero_as_featured',
                'shopify_summary_strategy' => 'excerpt',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionMissing('distribution_secret');

        $channel = DistributionChannel::query()->where('name', 'Shopify 博客')->firstOrFail();
        $this->assertSame('shopify_blog', $channel->channelType());
        $config = $channel->resolvedShopifyConfig();
        $this->assertSame('2025-10', $config['shopify_api_version']);
        $this->assertSame('first_blog', $config['shopify_blog_strategy']);
        $this->assertTrue($config['shopify_published']);

        $secret = DistributionChannelSecret::query()
            ->where('distribution_channel_id', (int) $channel->id)
            ->where('status', 'active')
            ->firstOrFail();
        $this->assertStringStartsWith('shopify_', (string) $secret->key_id);
        $this->assertSame(['shopify.admin'], $secret->scopes);
        $this->assertSame('shpat_secret_token', app(ApiKeyCrypto::class)->decrypt((string) $secret->secret_ciphertext));
    }

    public function test_admin_can_create_shopify_channel_with_client_credentials(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.distribution.store'), [
                'name' => 'Shopify CC',
                'domain' => 'test-store.myshopify.com',
                'endpoint_url' => 'https://test-store.myshopify.com',
                'channel_type' => 'shopify_blog',
                'shopify_auth_mode' => 'client_credentials',
                'shopify_client_id' => 'client-abc',
                'shopify_client_secret' => 'client-secret-xyz',
                'shopify_api_version' => '2025-10',
                'shopify_blog_strategy' => 'first_blog',
                'shopify_published' => '1',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionMissing('distribution_secret');

        $channel = DistributionChannel::query()->where('name', 'Shopify CC')->firstOrFail();
        $config = $channel->resolvedShopifyConfig();
        $this->assertSame('client_credentials', $config['shopify_auth_mode']);
        $this->assertSame('client-abc', $config['shopify_client_id']);

        $secret = DistributionChannelSecret::query()
            ->where('distribution_channel_id', (int) $channel->id)
            ->where('status', 'active')
            ->firstOrFail();
        $this->assertSame(['shopify.admin'], $secret->scopes);
        $this->assertSame('client-secret-xyz', app(ApiKeyCrypto::class)->decrypt((string) $secret->secret_ciphertext));
    }

    public function test_client_credentials_mode_requires_client_id_and_secret(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.distribution.store'), $this->validPayload([
                'shopify_auth_mode' => 'client_credentials',
                'shopify_client_id' => '',
                'shopify_client_secret' => 'client-secret-xyz',
                'shopify_access_token' => '',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('shopify_client_id');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.distribution.store'), $this->validPayload([
                'shopify_auth_mode' => 'client_credentials',
                'shopify_client_id' => 'client-abc',
                'shopify_client_secret' => '',
                'shopify_access_token' => '',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('shopify_client_secret');
    }

    public function test_shopify_channel_requires_access_token_on_create(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.distribution.store'), $this->validPayload(['shopify_access_token' => '']))
            ->assertRedirect()
            ->assertSessionHasErrors('shopify_access_token');
    }

    public function test_shopify_channel_rejects_api_version_below_floor(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.distribution.store'), $this->validPayload(['shopify_api_version' => '2024-04']))
            ->assertRedirect()
            ->assertSessionHasErrors('shopify_api_version');
    }

    public function test_shopify_channel_fixed_strategy_requires_blog_id(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.distribution.store'), $this->validPayload([
                'shopify_blog_strategy' => 'fixed',
                'shopify_blog_id' => '',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('shopify_blog_id');
    }

    public function test_shopify_channel_edit_shows_locked_type_and_token_update_help(): void
    {
        $channel = DistributionChannel::query()->create([
            'name' => 'Shopify 博客',
            'domain' => 'test-store.myshopify.com',
            'endpoint_url' => 'https://test-store.myshopify.com',
            'channel_type' => 'shopify_blog',
            'channel_config' => [
                'shopify_api_version' => '2025-10',
                'shopify_blog_strategy' => 'first_blog',
                'shopify_published' => true,
            ],
            'status' => 'active',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.distribution.edit', ['channelId' => (int) $channel->id]))
            ->assertOk()
            ->assertSee(__('admin.distribution.channel_type.shopify_blog'))
            ->assertSee(__('admin.distribution.help.channel_type_locked'))
            ->assertSee(__('admin.distribution.shopify.section_title'))
            ->assertSee('name="channel_type" value="shopify_blog"', false)
            ->assertSee(__('admin.distribution.shopify.access_token_update_help'));
    }

    public function test_shopify_channel_detail_page_renders(): void
    {
        $channel = DistributionChannel::query()->create([
            'name' => 'Shopify 博客',
            'domain' => 'test-store.myshopify.com',
            'endpoint_url' => 'https://test-store.myshopify.com',
            'channel_type' => 'shopify_blog',
            'channel_config' => [
                'shopify_api_version' => '2025-10',
                'shopify_blog_strategy' => 'first_blog',
            ],
            'status' => 'active',
        ]);
        DistributionChannelSecret::query()->create([
            'distribution_channel_id' => (int) $channel->id,
            'key_id' => 'shopify_detail',
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt('shpat_detail'),
            'status' => 'active',
            'scopes' => ['shopify.admin'],
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.distribution.show', ['channelId' => (int) $channel->id]))
            ->assertOk()
            ->assertSee('Shopify 博客')
            ->assertSee(__('admin.distribution.channel_type.shopify_blog'));
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Shopify 博客',
            'domain' => 'test-store.myshopify.com',
            'endpoint_url' => 'https://test-store.myshopify.com',
            'channel_type' => 'shopify_blog',
            'shopify_access_token' => 'shpat_secret_token',
            'shopify_api_version' => '2025-10',
            'shopify_blog_strategy' => 'first_blog',
            'shopify_published' => '1',
            'status' => 'active',
        ], $overrides);
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'shopify_admin',
            'password' => 'secret-123',
            'email' => 'shopify-admin@example.com',
            'display_name' => 'Shopify Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
