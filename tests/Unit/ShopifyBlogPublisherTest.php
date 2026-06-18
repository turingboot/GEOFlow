<?php

namespace Tests\Unit;

use App\Models\ArticleDistribution;
use App\Models\DistributionChannel;
use App\Models\DistributionChannelSecret;
use App\Services\GeoFlow\DistributionRetryPolicy;
use App\Services\GeoFlow\ShopifyBlogPublisher;
use App\Services\GeoFlow\ShopifyGraphQlClient;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ShopifyBlogPublisherTest extends TestCase
{
    private const GRAPHQL_URL = 'https://test-store.myshopify.com/admin/api/2025-10/graphql.json';

    private function publisher(): ShopifyBlogPublisher
    {
        return new ShopifyBlogPublisher(new ShopifyGraphQlClient(app(ApiKeyCrypto::class)));
    }

    /**
     * @param  array<string,mixed>  $config
     */
    private function channel(array $config = []): DistributionChannel
    {
        $channel = new DistributionChannel([
            'name' => 'Shopify Store',
            'domain' => 'test-store.myshopify.com',
            'endpoint_url' => 'https://test-store.myshopify.com',
            'channel_type' => 'shopify_blog',
            'channel_config' => array_merge([
                'shopify_api_version' => '2025-10',
                'shopify_blog_strategy' => 'fixed',
                'shopify_blog_id' => '123',
                'shopify_published' => true,
            ], $config),
            'status' => 'active',
        ]);

        $secret = new DistributionChannelSecret([
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt('shpat_test_token'),
            'status' => 'active',
        ]);
        $channel->setRelation('activeSecret', $secret);

        return $channel;
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    private function distribution(DistributionChannel $channel, string $action = 'publish', array $meta = []): ArticleDistribution
    {
        $distribution = new ArticleDistribution([
            'action' => $action,
            'status' => 'sending',
            'remote_meta' => $meta,
        ]);
        $distribution->setRelation('channel', $channel);

        return $distribution;
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(): array
    {
        return [
            'article' => [
                'title' => 'Hello GEO',
                'slug' => 'hello-geo',
                'excerpt' => 'A short excerpt',
                'content_html' => '<p>Body</p>',
                'keywords' => 'geo, seo',
                'meta_description' => 'meta desc',
                'hero_image_url' => 'https://test-store.myshopify.com/img/hero.png',
                'author' => ['name' => 'Jane'],
            ],
        ];
    }

    public function test_publish_with_fixed_blog_sends_article_create_and_maps_result(): void
    {
        Http::fake([
            self::GRAPHQL_URL => Http::response([
                'data' => [
                    'articleCreate' => [
                        'article' => [
                            'id' => 'gid://shopify/Article/555',
                            'handle' => 'hello-geo',
                            'blog' => ['id' => 'gid://shopify/Blog/123', 'handle' => 'news'],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->publisher()->publish($this->distribution($this->channel()), $this->payload());

        $this->assertSame('gid://shopify/Article/555', $result['remote_id']);
        $this->assertSame('https://test-store.myshopify.com/blogs/news/hello-geo', $result['remote_url']);
        $this->assertSame('gid://shopify/Blog/123', $result['remote_meta']['shopify_blog_id']);
        $this->assertSame('gid://shopify/Article/555', $result['remote_meta']['shopify_article_id']);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            $input = $data['variables']['article'] ?? [];

            return str_contains((string) ($data['query'] ?? ''), 'articleCreate')
                && ($input['blogId'] ?? null) === 'gid://shopify/Blog/123'
                && ($input['title'] ?? null) === 'Hello GEO'
                && ($input['body'] ?? null) === '<p>Body</p>'
                && ($input['isPublished'] ?? null) === true
                && ($input['tags'] ?? null) === ['geo', 'seo']
                && ($input['summary'] ?? null) === 'A short excerpt'
                && ($input['image']['url'] ?? null) === 'https://test-store.myshopify.com/img/hero.png';
        });
    }

    public function test_publish_resolves_first_blog_via_blogs_query(): void
    {
        Http::fakeSequence(self::GRAPHQL_URL)
            ->push(['data' => ['blogs' => ['nodes' => [
                ['id' => 'gid://shopify/Blog/900', 'handle' => 'main', 'title' => 'Main'],
            ]]]], 200)
            ->push(['data' => ['articleCreate' => [
                'article' => ['id' => 'gid://shopify/Article/9', 'handle' => 'hello-geo', 'blog' => ['id' => 'gid://shopify/Blog/900', 'handle' => 'main']],
                'userErrors' => [],
            ]]], 200);

        $result = $this->publisher()->publish(
            $this->distribution($this->channel(['shopify_blog_strategy' => 'first_blog', 'shopify_blog_id' => ''])),
            $this->payload()
        );

        $this->assertSame('gid://shopify/Blog/900', $result['remote_meta']['shopify_blog_id']);
    }

    public function test_user_errors_raise_exception(): void
    {
        Http::fake([
            self::GRAPHQL_URL => Http::response([
                'data' => ['articleCreate' => [
                    'article' => null,
                    'userErrors' => [['field' => ['title'], 'message' => 'Title is invalid', 'code' => 'INVALID']],
                ]],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Title is invalid/');

        $this->publisher()->publish($this->distribution($this->channel()), $this->payload());
    }

    public function test_throttled_error_is_retryable(): void
    {
        Http::fake([
            self::GRAPHQL_URL => Http::response([
                'errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]],
            ], 200),
        ]);

        try {
            $this->publisher()->publish($this->distribution($this->channel()), $this->payload());
            $this->fail('Expected a throttling exception.');
        } catch (RuntimeException $e) {
            $this->assertTrue((new DistributionRetryPolicy)->shouldRetry($e, 1, 3));
        }
    }

    public function test_http_unauthorized_is_not_retryable(): void
    {
        Http::fake([
            self::GRAPHQL_URL => Http::response(['errors' => [['message' => 'Unauthorized']]], 401),
        ]);

        try {
            $this->publisher()->publish($this->distribution($this->channel()), $this->payload());
            $this->fail('Expected an unauthorized exception.');
        } catch (RuntimeException $e) {
            $this->assertFalse((new DistributionRetryPolicy)->shouldRetry($e, 1, 3));
        }
    }

    public function test_delete_without_reference_returns_missing_marker(): void
    {
        Http::fake([self::GRAPHQL_URL => Http::response([], 200)]);

        $result = $this->publisher()->delete($this->distribution($this->channel(), 'delete'));

        $this->assertTrue($result['deleted']);
        $this->assertSame('missing_remote_id', $result['message']);
        Http::assertNothingSent();
    }

    public function test_delete_with_reference_calls_article_delete(): void
    {
        Http::fake([
            self::GRAPHQL_URL => Http::response([
                'data' => ['articleDelete' => ['deletedArticleId' => 'gid://shopify/Article/555', 'userErrors' => []]],
            ], 200),
        ]);

        $distribution = $this->distribution($this->channel(), 'delete', [
            'shopify_article_id' => 'gid://shopify/Article/555',
        ]);

        $result = $this->publisher()->delete($distribution);

        $this->assertTrue($result['deleted']);
        $this->assertSame('gid://shopify/Article/555', $result['remote_id']);
        Http::assertSent(fn (Request $request): bool => str_contains((string) ($request->data()['query'] ?? ''), 'articleDelete')
            && ($request->data()['variables']['id'] ?? null) === 'gid://shopify/Article/555');
    }

    public function test_update_without_reference_falls_back_to_publish(): void
    {
        Http::fake([
            self::GRAPHQL_URL => Http::response([
                'data' => ['articleCreate' => [
                    'article' => ['id' => 'gid://shopify/Article/777', 'handle' => 'hello-geo', 'blog' => ['id' => 'gid://shopify/Blog/123', 'handle' => 'news']],
                    'userErrors' => [],
                ]],
            ], 200),
        ]);

        $result = $this->publisher()->update($this->distribution($this->channel(), 'update'), $this->payload());

        $this->assertSame('gid://shopify/Article/777', $result['remote_id']);
        Http::assertSent(fn (Request $request): bool => str_contains((string) ($request->data()['query'] ?? ''), 'articleCreate'));
    }

    public function test_client_credentials_mode_fetches_token_then_calls_graphql(): void
    {
        Cache::flush();
        Http::fake([
            '*/admin/oauth/access_token' => Http::response(['access_token' => 'shpat_runtime', 'expires_in' => 86399], 200),
            '*/graphql.json' => Http::response([
                'data' => ['articleCreate' => [
                    'article' => ['id' => 'gid://shopify/Article/1', 'handle' => 'h', 'blog' => ['id' => 'gid://shopify/Blog/123', 'handle' => 'news']],
                    'userErrors' => [],
                ]],
            ], 200),
        ]);

        $channel = new DistributionChannel([
            'name' => 'Shopify Store',
            'domain' => 'test-store.myshopify.com',
            'endpoint_url' => 'https://test-store.myshopify.com',
            'channel_type' => 'shopify_blog',
            'channel_config' => [
                'shopify_api_version' => '2025-10',
                'shopify_auth_mode' => 'client_credentials',
                'shopify_client_id' => 'client-abc',
                'shopify_blog_strategy' => 'fixed',
                'shopify_blog_id' => '123',
                'shopify_published' => true,
            ],
            'status' => 'active',
        ]);
        $channel->setRelation('activeSecret', new DistributionChannelSecret([
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt('client-secret-xyz'),
            'status' => 'active',
        ]));

        $result = $this->publisher()->publish($this->distribution($channel), $this->payload());

        $this->assertSame('gid://shopify/Article/1', $result['remote_id']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/admin/oauth/access_token')
            && ($request['grant_type'] ?? null) === 'client_credentials'
            && ($request['client_id'] ?? null) === 'client-abc'
            && ($request['client_secret'] ?? null) === 'client-secret-xyz');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/graphql.json')
            && $request->hasHeader('X-Shopify-Access-Token', 'shpat_runtime'));
    }

    public function test_client_credentials_mode_requires_client_id(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response([], 200)]);

        $channel = new DistributionChannel([
            'domain' => 'test-store.myshopify.com',
            'endpoint_url' => 'https://test-store.myshopify.com',
            'channel_type' => 'shopify_blog',
            'channel_config' => [
                'shopify_auth_mode' => 'client_credentials',
                'shopify_client_id' => '',
                'shopify_blog_strategy' => 'fixed',
                'shopify_blog_id' => '123',
            ],
        ]);
        $channel->setRelation('activeSecret', new DistributionChannelSecret([
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt('client-secret-xyz'),
            'status' => 'active',
        ]));

        $this->expectException(RuntimeException::class);
        $this->publisher()->publish($this->distribution($channel), $this->payload());
    }

    public function test_sync_site_settings_is_noop(): void
    {
        $result = $this->publisher()->syncSiteSettings($this->channel());

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['skipped']);
    }
}
