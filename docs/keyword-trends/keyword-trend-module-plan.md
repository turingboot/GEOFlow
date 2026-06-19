# 关键词趋势(Keyword Trends)模块 — 技术方案

> 状态:设计稿(待批准实现)。目标:用户设定行业品类后,整合国外主流 / 稳定的 SEO 关键词数据源,
> 拉取相关关键词热度,取**近 ~1 个月的高热度关键词**入现有关键词库,供内容生成任务直接使用。

## 1. 目标
- 用户配置一个「行业品类」(+ 地区 / 语言 / 种子词)。
- 接入多家**常用、稳定的 SEO / 关键词趋势数据源**(可插拔,按账号选用)。
- 拉取相关关键词的**热度 / 搜索量 / 趋势方向**。
- 过滤出**近 ~1 个月**的**高热度** TopN 关键词,预览后入现有 `KeywordLibrary`。

## 2. 范围与技术边界(只新增)
- **只做逻辑新增**,不改现有「关键词库 / 任务 / 分发」逻辑;新模块通过 `KeywordLibrary` 公共入口入库(同 Shopify 接入那次的边界)。
- 复用现有模式:Provider 抽象(镜像 `DistributionPublisherManager`)、加密密钥(`ApiKeyCrypto` enc:v1)、Redis 队列 + Horizon、`config('geoflow.*')`、出站代理白名单、三语 i18n、Pint + 测试。

## 3. 数据源选型(可插拔,均为常用 / 稳定来源)

| # | 平台 | 官方 API | 提供数据 | 鉴权 | 计费 | 定位 / 稳定性 | 优先级 |
|---|---|---|---|---|---|---|---|
| 1 | **DataForSEO** | ✅ REST | Google Ads 搜索量、**Google Trends**、关键词建议(keywords-for-keywords / ideas)、SERP | login+password(Basic)| 按量预付 | 一站式聚合,最省事、稳定 | **P0 主接入** |
| 2 | **Google Trends** | ❌(无官方)| 相对热度、**rising / top queries**、地区、近1月 | 经 DataForSEO / SerpApi 代理 | 随上游 | 「趋势 / 近期热词」核心;非官方端点脆弱不用 | **P0(经 P1 代理)** |
| 3 | **SerpApi** | ✅ | Google Trends API、Autocomplete、Related Searches / Questions | API key | 按量(检索次数)| 稳定的 Google 数据代理 | P1 |
| 4 | **Google Ads — Keyword Planner**(Ads API) | ✅ | 月搜索量、12 月趋势、竞争度 | OAuth + developer token(需审核)| 数据免费,接入门槛高 | 权威搜索量来源 | P1 |
| 5 | **Semrush API** | ✅ | 关键词概览(volume / CPC / 难度 / 12 月趋势)、相关词、分国家库 | API key | 订阅 + API units | 行业标准 | P1(有订阅即上)|
| 6 | **Ahrefs API v3** | ✅ | Keywords Explorer(volume / 难度 / clicks / 趋势)、matching terms | API token | 订阅 + 额度 | 行业标准 | P2 |
| 7 | **Keywords Everywhere** | ✅ | 搜索量、CPC、竞争、趋势 | API key | 信用点(便宜)| 性价比高、用户多 | P2 |
| 8 | **Moz API** | ✅ | 关键词 volume / 难度 | API token | 订阅 | 常用 | P3 |
| 9 | **Exploding Topics / Glimpse** | ✅(高阶)| 新兴 / 增长趋势 | API key | 订阅 | 新兴趋势补充 | P3(可选)|

**先实现建议**:`DataForSEO`(P0,一家覆盖「搜索量 + Trends + 关键词建议」,单接入即可跑通「品类→近1月高热词→入库」完整闭环)→ 再按需挂 `SerpApi`(趋势 / 便宜)、`Semrush` / `Ahrefs` / `Keyword Planner`。
> 各平台精确端点 / 配额 / 字段以**实现时**官方文档为准;此处为设计选型。

## 4. 总体架构(对齐现有 Distribution 模式)
```
KeywordTrendSource(配置:品类/provider/seeds/region/timeframe/目标库/schedule)
        │  立即抓取 / 调度命令
        ▼
FetchKeywordTrendsJob (queue: trends)  ──►  KeywordTrendProviderManager
                                                 └─ dispatch by source->provider
                                                    ├─ DataForSeoProvider
                                                    ├─ SerpApiTrendsProvider
                                                    ├─ SemrushProvider / AhrefsProvider
                                                    ├─ GoogleKeywordPlannerProvider
                                                    └─ GenericHttpApiProvider
        ▼
KeywordTrendOrchestrator: 抓取 → KeywordHeatNormalizer(→0-100 + 趋势方向)
        → 去重 → 过滤(近1月 + 热度阈值 + TopN + 语言/地区)→ 落 Snapshot + Trends
        ▼
后台预览 ──► KeywordTrendImportService ──► KeywordLibrary(去重入库)──► 内容生成任务可用
```
- `KeywordTrendProviderInterface`:`fetchTrends(KeywordTrendSource $s, array $opts): KeywordTrendResult[]`、`healthCheck($s)`。
- `KeywordTrendProviderManager`:按 `source->provider()` 分派(白名单 enum)。
- `KeywordHeatNormalizer`:各平台不同口径 → 统一 **0–100 热度** + `trend_direction`(rising/flat/falling)+ `delta`。
- (可选)用现有 **AI 层**把「行业品类」扩成种子词、并对结果做聚类 / 去噪。

## 5. 数据模型 / 迁移(新表,无需 pgvector)
- `keyword_trend_sources`:`name, provider, category, seed_keywords(json), region, language, timeframe, heat_threshold, top_n, target_keyword_library_id, schedule, auto_import(bool), status, config(json), timestamps`
- `keyword_trend_source_secrets`:加密 API Key(`ApiKeyCrypto` enc:v1,镜像 `DistributionChannelSecret`)`(source_id, key_id, secret_encrypted, scope)`
- `keyword_trend_snapshots`:`source_id, ran_at, status, fetched_count, kept_count, stats(json), error, timestamps`
- `keyword_trends`:`snapshot_id, source_id, keyword, heat(0-100), search_volume, trend_direction, delta, region, language, captured_at, raw(json), imported(bool), keyword_library_item_id(nullable)`
- 关联现有 `KeywordLibrary` / 其条目表。

## 6. 抓取与调度
- `geoflow:fetch-keyword-trends`(命令)+ scheduler 扫描到期 source 入队(镜像 `geoflow:schedule-tasks`);后台「立即抓取」按钮直接派 `FetchKeywordTrendsJob`。
- 队列:新增 `trends` 队列(Horizon supervisor 监管),与 `geoflow`/`distribution` 并列。
- **近 1 个月**:Google Trends `timeframe=today 1-m` 或显式日期范围;搜索量类平台取最近月 + 12 月趋势的最后一段算 `delta`。
- **高热度过滤**:`heat >= heat_threshold`(默认 60)或 `trend_direction=rising`;按 `heat` 降序取 `top_n`;按语言 / 地区过滤;`keyword` 归一化去重。
- 速率限制 + 重试 + 结果缓存(避免重复计费),失败可重试键参考 `DistributionRetryPolicy`(429/5xx/timeout 可重试,401/403/422 不可)。

## 7. 入库(与 KeywordLibrary 打通)
- `KeywordTrendImportService`:把选中 / 自动命中的 `keyword_trends` 写入指定 `KeywordLibrary`,**对已存在关键词去重**;回写 `imported=true` + `keyword_library_item_id`。
- 支持「手动勾选导入」与 source 上的「`auto_import` 自动入库」开关。
- 可带元数据(热度 / 搜索量 / 来源 / 抓取时间)进库或入备注,供任务挑词时参考。

## 8. 后台 UI(套新 Tavix 蓝视觉:Hero / 彩色统计块 / 浮卡)
侧栏新增「关键词趋势」(挂「素材」下或独立)。三页:
1. **列表**:各 source + 最新热度概览(彩色统计块:抓取词数 / 高热词 / 已入库 / 上次抓取)。
2. **新建 / 编辑**:provider、行业品类、种子词、地区 / 语言、时间窗、热度阈值 / TopN、目标关键词库、`auto_import`、API Key(加密录入,只显示一次)。
3. **详情**:最新快照、关键词热度表(热度条 + 趋势 sparkline + 升降标记)、勾选导入 / 一键导入高热、立即抓取、健康检查。

## 9. 配置 / 安全 / 代理 / i18n
- `config/geoflow.php`:启用的 providers、默认 region / timeframe、`heat_threshold`、`top_n`、调度周期。
- API Key 加密(`ApiKeyCrypto`),**绝不直接 `env()`**;crypto root 只经 `config('geoflow.api_key_crypto_roots')`。
- 出站代理白名单 `geoflow.outbound_proxy_hosts` 增加平台域名:`api.dataforseo.com`、`serpapi.com`、`api.semrush.com`、`apiv2.ahrefs.com`、`api.keywordseverywhere.com`、`googleads.googleapis.com`、`trends.google.com`(国内访问国外平台需走代理)。
- 三语文案 `lang/{zh_CN,en,pt_BR}/admin.php`。

## 10. REST API(可选,v1)
- `GET /api/v1/keyword-trends`、`POST /api/v1/keyword-trends/{source}/fetch`、`POST /api/v1/keyword-trends/{id}/import`,沿用 Sanctum scope + 统一 JSON 信封 + 幂等。

## 11. 测试策略
- 单测:每个 Provider Adapter(用 `Http::fake` 喂样例响应)、`KeywordHeatNormalizer`(各平台→0-100)、过滤 / 去重逻辑、`KeywordTrendImportService` 去重入库。
- Feature:后台三页渲染 + 契约(name/route/data-*)、命令入队、健康检查。
- Pint `--dirty`;不新增翻译 key 须同补 en + pt_BR。

## 12. 风险与合规
- **Google Trends 无官方 API** → 走 DataForSEO / SerpApi(稳定付费),非官方端点脆弱 / 限流 / 违 ToS,不用于生产。
- 各平台**成本 / 配额 / ToS** 不同 → 缓存 + 限速 + 重试,按用户已有账号选 provider。
- **热度口径不可比** → 统一 0–100 仅作相对参考,标注来源。
- 遵守各平台 ToS,不超额 / 不滥抓。

## 13. 落地顺序(批准后)
1. 迁移 + 4 张表 + 模型。
2. `KeywordTrendProviderInterface` + `KeywordTrendProviderManager` + **DataForSeoProvider(P0)**。
3. `KeywordHeatNormalizer` + `KeywordTrendOrchestrator` + `KeywordTrendImportService`。
4. `FetchKeywordTrendsJob` + `geoflow:fetch-keyword-trends` + scheduler + `trends` 队列。
5. 后台三页(新视觉)+ config / i18n / 代理白名单。
6. 测试 + Pint + 文档。
7. 再增 `SerpApi` / `Semrush` / `Ahrefs` / `Keyword Planner` Adapter + 自动调度。

> **最小闭环(MVP)**:`DataForSEO + 手动抓取 + 预览入库`,先跑通「品类→近1月高热词→入库」,再扩平台与自动化。
