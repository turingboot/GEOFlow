# GEO 运营编排层 + GEO 质检层 — MVP 功能设计文档

> 状态:设计稿(纯功能设计,不含实现代码)
> 适用分支:`add-keyword-trends` 及其后续
> 设计原则:**外挂式扩展**。不改 GEOFlow 主链路(`Task → 文章生成 → 审核 → 发布 → 分发`)的任何业务逻辑,只在其上叠加两层,可独立上线、独立回滚。

---

## 1. 背景与目标

现有主链路已经能跑通"生成→审核→发布→分发",但缺两件运营侧的能力:

1. **写什么没人规划** —— 任务从标题库里取标题,标题怎么来、一个月写哪些主题、节奏如何,全靠人工事先塞标题库。
2. **质量没有量化参考** —— 文章生成后只有人工审核,没有一个"GEO 友好度 / AI 收录可能性"的量化评分来辅助决策。

本期目标:**在不碰主链路的前提下,补上"选题规划"和"GEO 质检"两层。**

- 选题规划层:读现有数据 → AI 生成月度选题池 → 人工确认 → 软排期投喂给现有任务机制。
- GEO 质检层:文章生成后自动评分 → 软闸口决定"建议自动通过"或"转人工审核"。

---

## 2. 设计原则:落在两个天然接缝上

| 扩展层 | 集成接缝(真实代码) | 是否改主链路 |
|--------|----------------------|--------------|
| 选题规划层 | 规划结果写入 `titles` 表(本就含 `keyword` 列)→ 现有 `WorkerExecutionService::pickTitle()` 自然消费;并通过 `TaskLifecycleService::createTask()` 建任务 | ❌ 零改动 |
| GEO 质检层 | 在 `WorkerExecutionService::executeTask()` 文章 `create` 之后挂 1 处钩子:落分 + 按软闸口调整该草稿的 `review_status`;复用 `KnowledgeRetrievalService::retrieveEvidence()` | ⚠️ 仅新增 1 处钩子调用,不改审核/发布逻辑 |

**对原架构的净改动 = 主链路逻辑 0 行,仅 GEO 质检挂 1 处钩子调用 + 全部为新增表/服务/页面。**

---

## 3. 总体流程图

```
                          ┌─────────────────── 选题规划层(新增,主链路之外)───────────────────┐
                          │                                                                  │
  关键词趋势 ┐            │  ① MonthlyTopicPlannerService                                    │
  关键词库   ├──读取──────┼─▶ 读 趋势/关键词库/知识库/历史文章                                 │
  知识库     │            │     └─ AI 生成「月度选题池」(JSON) ──▶ topic_plans/topic_plan_items │
  历史文章   ┘            │                                                                  │
                          │  ② 后台「选题确认/排期」页:人工勾选 + 设软排期参数                  │
                          │                                                                  │
                          │  ③ TopicPlanToTaskService(确认后触发):                           │
                          │     - 选中选题写入 titles 表(带 keyword)→ 挂到「计划标题库」        │
                          │     - 按软排期参数 createTask()(article_limit / publish_interval)  │
                          └──────────────────────────────┬───────────────────────────────────┘
                                                         │ 投喂(只写 titles + 建 Task)
                                                         ▼
  ┌──────────────────────────── 主链路(完全不变)────────────────────────────────────────┐
  │  geoflow:schedule-tasks ─▶ ProcessGeoFlowTaskJob ─▶ WorkerExecutionService             │
  │     pickTitle()(按 used_count 取) ─▶ RAG ─▶ AI 生成 ─▶ Article::create(status=draft) ──┼──┐
  │     publishDueDraftArticle()(发布 approved/auto_approved 到期草稿)─▶ 分发              │  │
  └───────────────────────────────────────────────────────────────────────────────────────┘  │
                                                         ▲                                     │
                          ┌──────────── GEO 质检层(新增,1 处钩子)──────────────────────────┐ │
                          │  ④ GeoArticleAuditService(在 Article::create 之后被调用)◀───────┼─┘
                          │     - 标题↔关键词匹配 / 结构完整 / 知识库引用覆盖 / 历史重复度       │
                          │     - 输出 geo_score + 建议 + 风险 ──▶ article_geo_audits          │
                          │     - 软闸口:score<阈值 → review_status=pending(转人工)            │
                          │                score≥阈值 → 维持/标记 auto_approved(建议自动通过)   │
                          │  ⑤ 后台「文章 GEO 评分面板」:看分、看建议、人工复核                  │
                          └────────────────────────────────────────────────────────────────────┘
```

要点:
- 选题规划层**只往主链路"喂数据"**(写 titles + 建 Task),不参与生成。
- GEO 质检层**只在生成那一刻挂钩**,通过现有 `review_status` 字段influence发布,不新增发布判断逻辑。

---

## 4. 选题规划层

### 4.1 职责
读现有数据,生成"未来一个月要写什么",人工确认后软排期投喂现有任务机制。**不直接改写任务,不直接生成文章。**

### 4.2 数据来源(全部复用现有模型)

| 用途 | 模型 / 表 | 取什么 |
|------|-----------|--------|
| 热点输入 | `KeywordTrend` / `KeywordTrendSnapshot` | 最近快照里 `heat`/`search_volume`/`trend_direction` 高的关键词 |
| 既有词库 | `KeywordLibrary` / `Keyword` | 业务关键词,作为选题约束/对齐 |
| 选题素材 | `KnowledgeBase` / `KnowledgeChunk` | 通过 `KnowledgeRetrievalService` 评估"哪些主题有知识支撑" |
| 去重依据 | `Article`(`original_keyword`/`keywords`/`title`/`published_at`) | 避免规划出与近 N 天历史文章重复的选题 |

### 4.3 表结构(新增)

**`topic_plans`(月度选题计划)**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint pk | |
| name | string | 计划名,如「2026-07 选题规划」 |
| period_start / period_end | date | 计划覆盖区间(默认一个月) |
| status | string | `draft`(AI 刚生成) / `confirmed`(人工已确认) / `dispatched`(已投喂任务) / `archived` |
| source_summary | json | 本次规划读取的数据快照摘要(趋势快照 id、词库 id、知识库 id、历史文章统计),便于复现 |
| ai_model_id | bigint nullable | 生成本计划所用的 `AiModel` |
| target_title_library_id | bigint nullable | 确认后写入的「计划标题库」id |
| created_by_admin_id | bigint | |
| timestamps | | |

**`topic_plan_items`(选题条目)**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint pk | |
| topic_plan_id | bigint fk | |
| title | string | 拟定标题 |
| keyword | string | 主关键词(写入 `titles.keyword`) |
| secondary_keywords | json nullable | 次要关键词 |
| rationale | text nullable | AI 给的选题理由 |
| heat_score | integer nullable | 来自趋势的热度参考 |
| kb_support | string nullable | 知识库支撑度标记 `strong/weak/none`(规划时预评估) |
| dup_risk | string nullable | 与历史文章重复风险 `low/medium/high` |
| planned_publish_at | date nullable | 软排期参考日(MVP 仅作展示/排序,不强约束发布) |
| status | string | `suggested`(AI 建议) / `confirmed`(人工选中) / `rejected`(人工剔除) / `dispatched`(已生成 title/task) |
| created_title_id | bigint nullable | 投喂后回填的 `titles.id`,便于追溯 |
| sort_order | integer | 时间轴排序 |
| timestamps | | |

> 说明:**软排期下 `planned_publish_at` 只用于展示与排序**,真正的发布节奏由投喂出的 Task 的 `publish_interval` 决定(见 4.5)。

### 4.4 服务(新增)

**`MonthlyTopicPlannerService`**
- `generatePlan(array $params): TopicPlan`
  - 入参:计划区间、趋势来源/快照范围、参与的关键词库 id、知识库 id、历史去重窗口天数、`ai_model_id`、目标选题数(默认 30)。
  - 流程:聚合趋势词 → 拉历史文章关键词集合做去重 → 对候选主题用 `KnowledgeRetrievalService::retrieveEvidenceFromMany()` 预评估知识支撑 → 组 prompt 调 AI(**要求输出 JSON 数组**)→ `json_decode` + 校验 → 落 `topic_plans` + `topic_plan_items`(status=`suggested`/计划=`draft`)。
- `regenerateItems(TopicPlan $plan, array $params): void` —— 不满意可重生成条目(保留人工已 confirmed 的)。

**`TopicPlanToTaskService`**(人工确认后触发,软排期)
- `dispatch(TopicPlan $plan, array $scheduleParams): Task`
  - 取 `status=confirmed` 的条目 → 逐条写入 `titles` 表(`library_id` = 计划标题库,`keyword` = 条目关键词,`is_ai_generated=1`)→ 回填 `created_title_id`。
  - 调 `TaskLifecycleService::createTask()` 建 **1 个任务**,指向该「计划标题库」,用 `scheduleParams` 设 `article_limit`(= 确认条目数)、`publish_interval`、`need_review`、`category_mode`、`publish_scope`、`distribution_strategy`。
  - 计划 `status=dispatched`,条目 `status=dispatched`。
  - **不碰** `pickTitle()` / `geoflow:schedule-tasks` / `ProcessGeoFlowTaskJob`。

### 4.5 软排期如何落到"时间轴 + 定时发布"

- 整月选题 → 一个「计划标题库」→ 一个 Task。
- Task 的 `article_limit` = 确认条目数;`publish_interval`(秒)由排期参数算出(如 30 条 / 30 天 ≈ 每天 1 篇)。
- 现有 `geoflow:schedule-tasks` + `publishDueDraftArticle()` 负责按节奏生成与发布。
- `pickTitle()` 按 `used_count` 最小取,**配合"每个标题写一条"天然实现轮流消费**;条目页面的 `planned_publish_at` 仅作运营可视化。
- 精确到日发布属于 v2(需扩 `pickTitle` 读计划队列),本期不做。

### 4.6 后台页面

**页面 A：月度选题规划(列表 + 新建)**
```
┌ 月度选题规划 ───────────────────────────────────────────────┐
│ [+ 新建规划]                                                  │
│ ┌─ 计划名 ─── 区间 ──── 状态 ──── 条目数 ── 操作 ──────────┐  │
│ │ 2026-07 选题  07/01-07/31  draft     30   [查看][确认][删]│  │
│ └──────────────────────────────────────────────────────────┘  │
│ 新建表单:区间 / 趋势来源 / 关键词库 / 知识库 / 去重窗口 /     │
│           目标条目数 / AI 模型 → [生成选题池]                   │
└──────────────────────────────────────────────────────────────┘
```

**页面 B：选题确认 / 排期(计划详情)**
```
┌ 2026-07 选题规划 ─ 确认与排期 ─────────────────────────────────┐
│ 勾选  标题            关键词    热度  知识支撑 重复风险 排期日   │
│  ☑   AI客服怎么选   ai客服    87    strong   low     07-02    │
│  ☑   ...            ...       ...   weak     medium  07-03    │
│  ☐   ...(剔除)      ...                                       │
│  ...                                                          │
│ 排期参数:[每日发布数] [need_review] [分类模式] [分发策略]      │
│                                  [确认并投喂任务 →]            │
└──────────────────────────────────────────────────────────────┘
```

---

## 5. GEO 质检层

### 5.1 职责
文章生成后、发布前,自动评分并给出 AI 收录建议;**不替换人工审核**,只补一个量化参考,并用软闸口把低分稿件转人工。

### 5.2 挂载点(关键:仅 1 处钩子)

| 时机 | 真实位置 | 动作 |
|------|----------|------|
| 文章生成完成 | `WorkerExecutionService::executeTask()` 中 `Article::create([...])` 之后(草稿落库点) | 调 `GeoArticleAuditService::audit($article, $context)`:写 `article_geo_audits` + 按软闸口调整该草稿 `review_status` |

> 不用 Eloquent Observer:项目无 Observer 习惯,且发布走批量 `update()` 不触发 `updated` 事件,service 内挂钩最可靠。
> 不需要第二处发布闸口:在生成时就把 `review_status` 调好,现有 `publishDueDraftArticle()`(只发 `approved`/`auto_approved`)自然放行或拦截。

### 5.3 软闸口逻辑(完全复用现有 `review_status` 枚举)

现有枚举:`review_status ∈ pending / approved / rejected / auto_approved`;`publishDueDraftArticle()` 只发布 `approved` / `auto_approved`。

```
生成时现状:review_status = task.need_review==1 ? 'pending' : 'approved'

GEO 软闸口叠加(在钩子里):
  if geo_score <  阈值:  review_status := 'pending'      # 强制转人工,无论任务是否要审
  if geo_score >= 阈值:
        若原本 'approved'(任务免审) → 升级 'auto_approved'   # 标记「AI 建议自动通过」
        若原本 'pending'(任务要审)   → 维持 'pending'         # 仍走人工,但面板给高分参考
```
- 低分稿不会进入自动发布队列(`pending` 不被选中),实现"软拦截"。
- 高分免审稿标记 `auto_approved`,可与人工免审区分统计。
- **阈值可配置**(放 `config/geoflow.php`,如 `geoflow.geo_audit.pass_threshold`,默认 70),不硬编码。

### 5.4 评分维度(MVP 四项,与思维导图一致)

| 维度 | 算法(MVP) | 数据来源 |
|------|-----------|----------|
| 标题↔关键词匹配 | 关键词是否出现在标题/首段/H 标题;覆盖率打分 | `Article.title` / `content` / `original_keyword` |
| 内容结构完整 | 是否有 H2/H3 层级、段落数、是否有结论/列表/表格等 GEO 友好结构;规则打分 | 解析 `content`(Markdown) |
| 知识库引用覆盖 | 复用 `KnowledgeRetrievalService::retrieveEvidence()` 取证据,算正文对高分证据的覆盖率 | `KnowledgeChunk` 检索 |
| 历史重复度 | 与近 N 天 `Article` 的标题/关键词/正文 shingle 相似度,取最高 | `Article`(已发布/草稿) |

- 四项加权 → `geo_score`(0–100)。权重放 config,便于调。
- **GEO 收录建议**:综合分 + 维度短板 → 一句话建议(由规则模板生成,或附加一段 AI 文本建议,二选一,MVP 先规则)。
- 维度 1/2/4 纯本地规则(确定性、可测);维度 3 复用现成 RAG。**MVP 评分不强依赖 AI 调用**(AI 仅用于可选的"建议文本"),降低成本与波动。

### 5.5 表结构(新增)

**`article_geo_audits`(一篇文章一条最新评分,可保留历史多条)**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint pk | |
| article_id | bigint fk | |
| geo_score | integer | 综合分 0–100 |
| title_keyword_match | integer | 维度分 |
| structure_score | integer | 维度分 |
| kb_coverage | integer | 维度分 |
| dup_ratio | integer | 重复度(越高越差) |
| word_count | integer | 字数(顺带补 Article 缺失的统计) |
| gate_decision | string | `auto_approved` / `to_review` / `passthrough`(记录软闸口当时的决定) |
| suggestion | text nullable | AI 收录建议 / 规则建议 |
| risk_notes | json nullable | 风险提示列表(短板项) |
| details | json nullable | 各维度原始计算明细,便于面板展开 |
| ai_model_id | bigint nullable | 若用了 AI 建议 |
| audited_at | timestamp | |
| timestamps | | |

> Article 表保持不变;字数/评分等全部落侧表,符合"不改老表含义"。

### 5.6 服务(新增)

**`GeoArticleAuditService`**
- `audit(Article $article, array $context = []): ArticleGeoAudit`
  - `$context` 复用生成阶段已有的 `knowledge_base_ids` / `keyword` / `titleRow`,避免重复检索。
  - 计算四维 → 综合分 → 落 `article_geo_audits` → 返回。
  - **不在内部改 `review_status`**;由钩子调用方根据返回的 `geo_score` + 阈值决定是否调整(职责单一,便于测试)。
- `scoreOnly(Article $article): array` —— 后台面板"重新评分"按钮用,只算分不落软闸口。

### 5.7 后台页面

**页面 C：文章 GEO 评分面板**
```
┌ 文章 GEO 评分 ──────────────────────────────────────────────┐
│ 标题            综合分  匹配 结构 引用 重复  闸口决定  操作    │
│ AI客服怎么选     82     90   80   75   12   auto_approved 详情 │
│ ...(低分高亮)    58     60   40   30   45   to_review    复核 │
│ 详情抽屉:四维雷达 + 短板提示 + 建议文本 + [重新评分][去审核] │
└──────────────────────────────────────────────────────────────┘
```
- 与现有「文章列表/审核」并列,不替换它;低分(`to_review`)条目可一键跳到现有审核页。

---

## 6. AI 调用约定(两处新 AI 共用范式)

照 `app/Ai/Agents/MarkdownContentWriterAgent.php` 复制 Agent 类、改 `instructions`:
- 复用 `OpenAiRuntimeProvider::registerProvider(...)` + `$agent->prompt($prompt, [], $providerName, $model->model_id)`。
- 模型从 `AiModel` 选取;建议用其已有的 `model_type` 字段区分用途(如 `topic_planner` / `geo_audit`),后台模型管理沿用现有页面。
- **本版 ai-sdk 无结构化输出**(无 `withSchema/asStructured`):prompt 内明确要求输出 JSON → 取 `$response->text` → 参考 `OpenAiRuntimeProvider::normalizeGeneratedText()` 清洗 → `json_decode` → 自行 schema 校验,失败有兜底(选题:跳过坏条目;评分:回退纯规则分)。

---

## 7. 后台登记清单(三个页面整体照 `keyword-trends` 抄)

每个后台模块需在 5 处登记:
1. **路由** `routes/web.php` —— 放登录后段(已含 `admin.auth`/`admin.activity`),照 keyword-trends 的 `Route::prefix(...)->name(...)->group(...)`。
2. **控制器** `app/Http/Controllers/Admin/` —— 照 `KeywordTrendController`(index/create/store/edit/update/show + 自定义 action 如 generate/confirm/dispatch/re-audit)。
3. **侧边栏** `resources/views/admin/partials/sidebar.blade.php` —— `$menu` 加项、`$menuIcons` 配 Lucide 图标、子路由高亮映射。
4. **权限中间件** —— 普通后台页继承 `admin.auth` 即可;若设为超管专属,放 `admin.super` 组。
5. **i18n** `lang/*/admin.php` —— 加 `nav.*` 与页面文案键。

> 三个页面均为普通后台 CRUD,放 `admin.auth` 段即可;是否限超管由你定。

---

## 8. MVP 范围

**本期做(对齐思维导图净新增部分):**
1. 月度选题规划:读趋势/词库/知识库/历史 → AI 生成 30 条候选 → 落库。
2. 后台确认/排期:人工勾选 + 设软排期参数。
3. 确认后投喂:写 `titles` + 建 1 个 Task(软排期)。
4. 文章生成后自动 GEO 评分(四维)+ 软闸口(低分转人工)。
5. GEO 评分面板。

**复用现有、不重复造:**
- 关键词趋势对接、建站/分发对接、文章生成、定时发布 —— 全部用现成的。

**明确不在本期(v2+):**
- 精确到日的发布编排(扩 `pickTitle`)。
- 硬闸口(低分直接 rejected)。
- 选题/查重的向量化(Article 暂无 embedding)。
- 跨站点选题协同、A/B 选题实验。

---

## 9. 风险与回滚

- **零主链路改动**:选题层不碰任何主链路文件;质检层仅在 `WorkerExecutionService::executeTask()` 加 1 处钩子调用。
- **可独立回滚**:摘掉质检钩子调用 + 隐藏三个后台菜单,即恢复原行为;新增表与服务不影响既有数据。
- **降级**:AI 不可用时,选题层提示重试、质检层回退纯规则分,主链路不受影响。
- **软闸口边界**:阈值与权重全部走 config,可热调;上线初期可把阈值设很低(近似纯参考),观察后再收紧。

---

## 10. 待办(进入实现前需确认的小项)

- [ ] config 项命名:`geoflow.geo_audit.pass_threshold`、维度权重、历史去重窗口天数。
- [ ] 「计划标题库」是每月新建一个,还是固定一个滚动使用(影响 `pickTitle` 的 used_count 语义)。
- [ ] 评分维度的具体权重与结构规则细则(可在实现期用样例文章标定)。
- [ ] 是否给三个页面加 `admin.super` 限制。
