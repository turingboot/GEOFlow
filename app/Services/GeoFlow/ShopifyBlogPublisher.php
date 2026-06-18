<?php

namespace App\Services\GeoFlow;

use App\Models\ArticleDistribution;
use App\Models\DistributionChannel;
use RuntimeException;

/**
 * Shopify 博客分发发布器：通过 Admin GraphQL API 发布/更新/删除博客文章。
 *
 * 远端身份使用 GID：remote_id 存 article GID，remote_meta 同时保存 blog/article GID 与 handle，
 * 供 update/delete 与 storefront URL 拼接复用。
 */
class ShopifyBlogPublisher implements DistributionPublisherInterface
{
    private const BLOGS_QUERY = 'query { blogs(first: 50) { nodes { id handle title } } }';

    private const HEALTH_QUERY = 'query { shop { name myshopifyDomain } blogs(first: 50) { nodes { id handle title } } }';

    private const ARTICLE_CREATE = 'mutation($article: ArticleCreateInput!) { articleCreate(article: $article) { article { id handle title isPublished blog { id handle } } userErrors { field message code } } }';

    private const ARTICLE_UPDATE = 'mutation($id: ID!, $article: ArticleUpdateInput!) { articleUpdate(id: $id, article: $article) { article { id handle title isPublished blog { id handle } } userErrors { field message code } } }';

    private const ARTICLE_DELETE = 'mutation($id: ID!) { articleDelete(id: $id) { deletedArticleId userErrors { field message code } } }';

    public function __construct(private readonly ShopifyGraphQlClient $client) {}

    public function health(DistributionChannel $channel): array
    {
        $data = $this->client->execute($channel, self::HEALTH_QUERY, [], null, 'Shopify 健康检查', 10);
        $shop = is_array($data['shop'] ?? null) ? $data['shop'] : [];
        $nodes = is_array($data['blogs']['nodes'] ?? null) ? $data['blogs']['nodes'] : [];

        $blogs = [];
        foreach (array_slice($nodes, 0, 20) as $node) {
            if (! is_array($node)) {
                continue;
            }
            $blogs[] = [
                'id' => (string) ($node['id'] ?? ''),
                'handle' => (string) ($node['handle'] ?? ''),
                'title' => (string) ($node['title'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'channel_type' => 'shopify_blog',
            'graphql_url' => $channel->shopifyGraphqlUrl(),
            'shop_name' => (string) ($shop['name'] ?? ''),
            'myshopify_domain' => (string) ($shop['myshopifyDomain'] ?? ''),
            'blog_count' => count($nodes),
            'blogs' => $blogs,
        ];
    }

    public function publish(ArticleDistribution $distribution, array $payload): array
    {
        $distribution->loadMissing('channel');
        $channel = $this->channel($distribution);
        $config = $channel->resolvedShopifyConfig();
        $blogGid = $this->resolveBlogGid($channel, $config);
        $input = $this->articleInput($payload, $config, $blogGid);

        $data = $this->client->execute($channel, self::ARTICLE_CREATE, ['article' => $input], 'articleCreate', 'Shopify 文章发布');
        $article = is_array($data['articleCreate']['article'] ?? null) ? $data['articleCreate']['article'] : [];

        return $this->articleResult($channel, $article);
    }

    public function update(ArticleDistribution $distribution, array $payload): array
    {
        $distribution->loadMissing('channel');
        $channel = $this->channel($distribution);
        $reference = $distribution->shopifyArticleReference();
        if ($reference === null) {
            return $this->publish($distribution, $payload);
        }

        $config = $channel->resolvedShopifyConfig();
        $input = $this->articleInput($payload, $config, null);

        $data = $this->client->execute($channel, self::ARTICLE_UPDATE, [
            'id' => $reference['article_gid'],
            'article' => $input,
        ], 'articleUpdate', 'Shopify 文章更新');
        $article = is_array($data['articleUpdate']['article'] ?? null) ? $data['articleUpdate']['article'] : [];

        return $this->articleResult($channel, $article, $reference);
    }

    public function delete(ArticleDistribution $distribution): array
    {
        $distribution->loadMissing('channel');
        $channel = $this->channel($distribution);
        $reference = $distribution->shopifyArticleReference();
        if ($reference === null) {
            return [
                'deleted' => true,
                'remote_id' => null,
                'remote_url' => null,
                'message' => 'missing_remote_id',
            ];
        }

        $data = $this->client->execute($channel, self::ARTICLE_DELETE, [
            'id' => $reference['article_gid'],
        ], 'articleDelete', 'Shopify 文章删除');
        $deletedId = (string) ($data['articleDelete']['deletedArticleId'] ?? $reference['article_gid']);

        return [
            'deleted' => true,
            'remote_id' => $deletedId,
            'remote_url' => null,
        ];
    }

    public function syncSiteSettings(DistributionChannel $channel): array
    {
        // Shopify 店铺级设置不归 GEOFlow 覆盖；返回良性跳过，保证 controller 更新后的自动同步不报错。
        return [
            'ok' => true,
            'skipped' => true,
            'reason' => 'shopify_blog_no_site_settings',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $config
     * @return array<string,mixed>
     */
    private function articleInput(array $payload, array $config, ?string $blogGid): array
    {
        $article = is_array($payload['article'] ?? null) ? $payload['article'] : [];

        $input = [
            'title' => (string) ($article['title'] ?? ''),
            'body' => (string) ($article['content_html'] ?? ''),
            'isPublished' => (bool) $config['shopify_published'],
        ];

        if ($blogGid !== null && $blogGid !== '') {
            $input['blogId'] = $blogGid;
        }

        $handle = trim((string) ($article['slug'] ?? ''));
        if ($handle !== '') {
            $input['handle'] = $handle;
        }

        $authorName = $config['shopify_author'] !== ''
            ? (string) $config['shopify_author']
            : (string) ($article['author']['name'] ?? '');
        $authorName = trim($authorName);
        if ($authorName !== '') {
            $input['author'] = ['name' => $authorName];
        }

        if ($config['shopify_tag_strategy'] === 'keywords_to_tags') {
            $tags = $this->splitKeywords((string) ($article['keywords'] ?? ''));
            if ($tags !== []) {
                $input['tags'] = $tags;
            }
        }

        if ($config['shopify_summary_strategy'] !== 'disabled') {
            $summary = $config['shopify_summary_strategy'] === 'meta_description'
                ? (string) ($article['meta_description'] ?? '')
                : (string) ($article['excerpt'] ?? '');
            $summary = trim($summary);
            if ($summary !== '') {
                $input['summary'] = $summary;
            }
        }

        if ($config['shopify_image_strategy'] === 'hero_as_featured') {
            $hero = trim((string) ($article['hero_image_url'] ?? ''));
            if ($hero !== '') {
                $input['image'] = [
                    'url' => $hero,
                    'altText' => (string) ($article['title'] ?? ''),
                ];
            }
        }

        return $input;
    }

    /**
     * @param  array<string,mixed>  $article
     * @param  array{article_gid:string, blog_gid:?string, handle:?string, blog_handle:?string}|null  $fallbackReference
     * @return array<string,mixed>
     */
    private function articleResult(DistributionChannel $channel, array $article, ?array $fallbackReference = null): array
    {
        $articleGid = (string) ($article['id'] ?? ($fallbackReference['article_gid'] ?? ''));
        $handle = (string) ($article['handle'] ?? ($fallbackReference['handle'] ?? ''));
        $blogGid = (string) ($article['blog']['id'] ?? ($fallbackReference['blog_gid'] ?? ''));
        $blogHandle = (string) ($article['blog']['handle'] ?? ($fallbackReference['blog_handle'] ?? ''));

        return [
            'remote_id' => $articleGid,
            'remote_url' => $this->storefrontUrl($channel, $blogHandle, $handle),
            'remote_meta' => [
                'shopify_article_id' => $articleGid,
                'shopify_blog_id' => $blogGid,
                'shopify_handle' => $handle,
                'shopify_blog_handle' => $blogHandle,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $config
     */
    private function resolveBlogGid(DistributionChannel $channel, array $config): string
    {
        $strategy = (string) $config['shopify_blog_strategy'];

        if ($strategy === 'fixed') {
            $gid = $this->normalizeBlogGid((string) $config['shopify_blog_id']);
            if ($gid === '') {
                throw new RuntimeException('Shopify 固定 Blog 策略缺少有效的 Blog ID。');
            }

            return $gid;
        }

        $data = $this->client->execute($channel, self::BLOGS_QUERY, [], null, 'Shopify 博客列表', 10);
        $nodes = is_array($data['blogs']['nodes'] ?? null) ? $data['blogs']['nodes'] : [];

        if ($strategy === 'match_handle') {
            $handle = (string) $config['shopify_blog_handle'];
            foreach ($nodes as $node) {
                if (is_array($node) && (string) ($node['handle'] ?? '') === $handle && $handle !== '') {
                    return (string) ($node['id'] ?? '');
                }
            }

            throw new RuntimeException('Shopify 未找到 handle 为「'.$handle.'」的 Blog。');
        }

        // first_blog
        $first = $nodes[0] ?? null;
        if (! is_array($first) || (string) ($first['id'] ?? '') === '') {
            throw new RuntimeException('Shopify 店铺没有可用的 Blog，请先在 Shopify 后台创建博客。');
        }

        return (string) $first['id'];
    }

    private function normalizeBlogGid(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, 'gid://shopify/Blog/')) {
            return $value;
        }

        return ctype_digit($value) ? 'gid://shopify/Blog/'.$value : '';
    }

    private function storefrontUrl(DistributionChannel $channel, string $blogHandle, string $articleHandle): string
    {
        if ($blogHandle === '' || $articleHandle === '') {
            return '';
        }

        $host = trim((string) $channel->domain);
        if ($host === '') {
            $host = (string) parse_url((string) $channel->endpoint_url, PHP_URL_HOST);
        }
        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = rtrim((string) $host, '/');
        if ($host === '') {
            return '';
        }

        return 'https://'.$host.'/blogs/'.$blogHandle.'/'.$articleHandle;
    }

    /**
     * @return list<string>
     */
    private function splitKeywords(string $keywords): array
    {
        $parts = preg_split('/[,，;；\s]+/u', $keywords) ?: [];
        $tags = [];
        foreach ($parts as $part) {
            $tag = trim((string) $part);
            if ($tag !== '' && ! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return array_slice($tags, 0, 50);
    }

    private function channel(ArticleDistribution $distribution): DistributionChannel
    {
        if (! $distribution->channel instanceof DistributionChannel) {
            throw new RuntimeException('分发记录缺少 Shopify 渠道。');
        }

        return $distribution->channel;
    }
}
