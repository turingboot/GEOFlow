# Shopify 博客分发渠道接入方案（GraphQL Admin API）

> 状态：已实现（additive-only）。新增渠道类型 `shopify_blog`，复用既有分发抽象，通过 Shopify Admin **GraphQL** API 发布 / 更新 / 删除博客文章。

## 0. 技术边界（硬约束）

- **只做逻辑新增，不删除 / 改写既有逻辑**。对 `channelType()` 白名单、`validateChannel()` 的 `in:` 规则、`DistributionPublisherManager::match`、`normalizeChannelConfig()`、`store()`/`update()` 等，**只追加分支与取值**，绝不移除原有 WordPress / Generic / GeoFlow Agent 行为。
- 不修改数据库结构（`channel_type` 是 `string(60)`、`channel_config` 是 nullable JSON，无需迁移）。
- 不引入新的 Composer 依赖（直接用 Laravel `Http` 客户端裸调 GraphQL）。

## 1. 联网核实结论（决定技术选型）

| 核实项 | 结论 | 来源 |
|---|---|---|
| article 系列 GraphQL mutation 引入版本 | **2024-10**（`Article`/`Blog`/`Page`/`Comment` 取代旧 `OnlineStore*` 类型，`articleCreate/articleUpdate/articleDelete` + `article(s)`/`blog(s)` 查询自此可用） | Shopify changelog: "new APIs for Pages, Articles, Blogs, and Comments now available in 2024-10" |
| 当前状态 | GA / 稳定（非 RC） | shopify.dev `mutations/articleCreate` |
| `articleCreate` 入参 | 必填 `blogId`、`title`、`body`；可选 `author`、`handle`、`tags`、`summary`、`image`、`isPublished`、`publishDate` | 同上 |
| 写权限 scope | `write_content` **或** `write_online_store_pages`；读 blog 需 `read_content` | 同上 |
| ID 形态 | GID，如 `gid://shopify/Blog/389767568`、`gid://shopify/Article/...` | shopify.dev `queries/blogs` |
| REST 现状 | 2024-10-01 起 REST 标记 legacy；2025-04-01 起新公共 App 必须纯 GraphQL；自建 App 的 REST 仍可用但不再有新功能 | Shopify REST deprecation guide |

**结论**：选用 GraphQL；GraphQL 版本下限锁定 **`2024-10`**，默认 `2025-10`，后台可改、保存时校验下限。

## 2. 鉴权与默认决策

支持两种鉴权模式（`shopify_auth_mode`），统一最终都用 header `X-Shopify-Access-Token` 调 GraphQL；App 需授 `write_content` + `read_content`：

- **`client_credentials`（推荐，新店唯一可用）**：Shopify 已停止在后台创建旧版自建应用,新应用走 **Dev Dashboard**,只给 **Client ID + Client Secret**。后台存 Client ID(`channel_config`)+ Client Secret(加密存 `DistributionChannelSecret`);`ShopifyGraphQlClient` 调用前用 client credentials grant 换取 **24h 短期 token** 并按 `expires_in - 300s` 缓存复用,到期自动重换。
  - token 端点:`POST https://{shop}.myshopify.com/admin/oauth/access_token`(form 编码,`client_id`/`client_secret`/`grant_type=client_credentials`)→ `{access_token, expires_in:86399, scope}`。
  - 约束:应用必须已安装到该店、且与店铺**同属一个 Shopify organization**;域名须用 `.myshopify.com`(admin/oauth 不在自定义域名上)。
- **`access_token`（兜底/旧店）**：旧版自建应用或 CLI 拿到的长期 `shpat_` 固定 token,密文本身即 token,直接用(沿用 WordPress Application Password 的留空保持/填新值替换模式)。

- token/secret 经 `ApiKeyCrypto`（`enc:v1`）加密存 `DistributionChannelSecret`，scope 标签 `['shopify.admin']`。
- 默认 `shopify_auth_mode=client_credentials`（表单默认）、`shopify_published=true`、图片策略 `hero_as_featured`、tag 策略 `keywords_to_tags`、summary 策略 `excerpt`。

> 参考:Shopify「You can no longer create new custom apps in the Shopify admin」+ Dev Dashboard 仅提供 client credentials(24h token,同组织自有店)。

## 3. 数据模型与配置

`channel_type = 'shopify_blog'`，配置存进 `channel_config` JSON 列，经 `DistributionChannel::resolvedShopifyConfig()` 读出（带 array-shape PHPDoc）：

| 字段 | 取值 | 说明 |
|---|---|---|
| `shopify_api_version` | 默认 `2025-10` | 形如 `YYYY-MM`，校验 `>= 2024-10` |
| `shopify_blog_strategy` | `fixed` / `match_handle` / `first_blog` | 目标 blog 定位方式 |
| `shopify_blog_id` | 数字或 GID | strategy=fixed 时用 |
| `shopify_blog_handle` | string | strategy=match_handle 时用 |
| `shopify_published` | bool（默认 true） | 发布 vs 草稿 → `isPublished` |
| `shopify_author` | string（可空） | 空则取文章作者名 |
| `shopify_tag_strategy` | `keywords_to_tags` / `disabled` | keywords 拆成 `tags[]` |
| `shopify_image_strategy` | `hero_as_featured` / `disabled` | 封面图 → `image.url` |
| `shopify_summary_strategy` | `excerpt` / `meta_description` / `disabled` | → `summary` |

**远端身份（关键）**：Shopify 用 GID，update/delete 仅需 article GID。
- `ArticleDistribution.remote_id` = article GID（字符串列已支持；`wordpressPostId()` 用 `ctype_digit` 判断，GID 不会误命中）。
- `remote_meta` = `{ shopify_blog_id, shopify_article_id, shopify_handle, shopify_blog_handle }`。
- 新增 `ArticleDistribution::shopifyArticleReference(): ?array{article_gid, blog_gid, handle, blog_handle}`（类比 `wordpressPostId()`）。

## 4. GraphQL 操作映射（`ShopifyBlogPublisher`）

统一端点：`POST {domain}/admin/api/{version}/graphql.json`，body `{query, variables}`。

**health()** — 验 token + 列 blog + 解析目标 blog：
```graphql
query { shop { name myshopifyDomain } blogs(first: 50) { nodes { id handle title } } }
```

**publish()** — `articleCreate`：
```graphql
mutation($article: ArticleCreateInput!) {
  articleCreate(article: $article) {
    article { id handle title isPublished blog { id handle } }
    userErrors { field message code }
  }
}
```
variables：`{ article: { blogId, title, body:<content_html>, author?:{name}, handle:<slug>, tags?:[...], summary?, image?:{url, altText}, isPublished } }`
→ `remote_id = article.id`（GID）、`remote_url = https://{domain}/blogs/{blog.handle}/{article.handle}`、`remote_meta` 写入 blog/article GID 与 handle。

**update()** — `articleUpdate(id:<article_gid>, article: ArticleUpdateInput!)`；无 `shopifyArticleReference()` 则降级 `publish()`（对齐 WordPress）。

**delete()** — `articleDelete(id:<article_gid>) { deletedArticleId userErrors{...} }`；无引用返回 `deleted=true, message=missing_remote_id`。

**syncSiteSettings()** — 良性 no-op，返回 `['ok'=>true,'skipped'=>true]`（店铺级设置不归我们覆盖，保证 controller 更新后的自动同步不报错）。

> 嵌套 input 形状（`author{name}`、`image{url,altText}`）按 2024-10+ schema 实现；若切换到更老 / 特殊版本，请按该版本 introspection 复核字段名。

## 5. 错误处理（GraphQL 唯一难点，封装在 `ShopifyGraphQlClient`）

GraphQL 逻辑失败也返回 HTTP 200，必须三层判定：
1. **HTTP 层**：`$response->failed()`（401/403 token 失效、5xx）→ 抛错（message 含状态码，交由 `DistributionRetryPolicy` 判断重试）。
2. **顶层 `errors[]`**：查询/权限错误；`extensions.code === 'THROTTLED'` → 抛**含 "429" 的消息**，从而命中现有重试策略的可重试分支（**无需修改 `DistributionRetryPolicy`**）。
3. **业务 `data.<mutation>.userErrors[]`**：非空 → 抛错并带 `field/message`（确定性失败，默认不重试）。

这样 `ProcessArticleDistributionJob` 的成功/失败/重试判定保持不变。

## 6. 边界与注意事项

- **图片必须公网可达**：Shopify 靠 `image.url` 主动抓取本站图片；内网 / localhost 部署封面图会失败，演示环境建议 `shopify_image_strategy=disabled`。正文内联图同理。
- 正文用 GraphQL `body`（HTML），复用 `DistributionPayloadBuilder` 的 `content_html`，不改 payload builder。
- 保存时校验 `shopify_api_version >= 2024-10`、token 必填、按 strategy 校验 blog_id/handle。
- health 中 `blogs` 因权限失败时，提示「请为 App 授予 write_content / read_content」。

## 7. 文件改动清单（全部 additive）

**新增**
- `app/Services/GeoFlow/ShopifyGraphQlClient.php`：封装 GraphQL 请求 + 三层错误判定。
- `app/Services/GeoFlow/ShopifyBlogPublisher.php`：`DistributionPublisherInterface` 实现 + blog 解析。
- `tests/Unit/ShopifyBlogPublisherTest.php`、`tests/Feature/AdminShopifyChannelTest.php`。

**改动（只追加分支/取值）**
- `app/Models/DistributionChannel.php`：`channelType()` 白名单 + `isShopifyBlog()`、`resolvedShopifyConfig()`、`shopifyGraphqlUrl()`。
- `app/Models/ArticleDistribution.php`：`shopifyArticleReference()`。
- `app/Services/GeoFlow/DistributionPublisherManager.php`：注入 + `match` 加 `shopify_blog`。
- `app/Http/Controllers/Admin/DistributionController.php`：`validateChannel()`/`store()`/`update()`/`normalizeChannelConfig()`/`revealSecret()` 追加 shopify 分支 + `createShopifySecret()`。
- `resources/views/admin/distribution/create.blade.php`、`edit.blade.php`：渠道单选第 4 项 + Shopify 面板（切换 JS 通用，不改）。
- `lang/{zh_CN,en,ja,es,ru,pt_BR}/admin.php`：`channel_type.shopify_blog(_desc)` + `shopify.*` + `help/validation/message` 键。
- `tests/Unit/DistributionPublisherManagerTest.php`：追加 `shopify_blog` 解析断言。

**无需改动**：数据库迁移、`DistributionPayloadBuilder`、`DistributionOrchestrator`、`ProcessArticleDistributionJob`、`DistributionRetryPolicy`、`DistributionChannelSecret`。

## 8. 测试

- `DistributionPublisherManagerTest`：`shopify_blog` → `ShopifyBlogPublisher`。
- `ShopifyBlogPublisherTest`（`Http::fake()`）：publish/update/delete 的 query+variables 形状、GID 提取、`remote_meta` 写入；错误三态（200+userErrors / 200+THROTTLED 可重试 / 401 不重试）；blog 三策略；token 缺失。
- `AdminShopifyChannelTest`：经 controller 建/改渠道 → `channel_config` 落库 + secret 创建；version 下限、token 必填、strategy 校验失败路径。
