# AGENTS.md

This file provides guidance to Codex (Codex.ai/code) when working with code in this repository.

GEOFlow is a Laravel 12 application for GEO (Generative Engine Optimization) content engineering and multi-site distribution: it runs AI content generation, a knowledge-base RAG pipeline, a review/publish workflow, a public article site, and distribution to external sites (GeoFlow Agent packages, WordPress REST, generic HTTP APIs). The codebase and comments are predominantly in Chinese; match the surrounding language when editing.

## Laravel Boost

This repo uses Laravel Boost (MCP server in `.mcp.json`). **Read `.boost/guidelines.md` before any Laravel/PHP/Tailwind/Horizon/AI-SDK change** — it carries the authoritative conventions for PHP style, Pint, testing, and the version-pinned package set, and is not repeated here. Activate the matching Boost skill (`laravel-best-practices`, `ai-sdk-development`, `configuring-horizon`, `tailwindcss-development`) when working in that domain. Prefer Boost MCP tools (`search-docs`, `database-query`, `database-schema`, `route:list`) over ad-hoc shell/SQL.

## Commands

```bash
composer test                              # config:clear + full php artisan test suite (PHPUnit, sqlite :memory:)
php artisan test --compact tests/Feature/AdminTasksPageTest.php   # one file
php artisan test --compact --filter=testName                     # one test
vendor/bin/pint --dirty --format agent     # format changed PHP before finalizing (required)
npm run build                              # build Vite assets (Tailwind v4, vditor); npm run dev for watch
composer run dev                           # all-in-one: serve + queue:listen + pail + vite
```

Tests are **PHPUnit, not Pest** (see `phpunit.xml`). Most are Feature tests under `tests/Feature/`.

### Running the app (local, non-Docker)
Requires PHP 8.2+ with `pdo_pgsql` + `redis`, PostgreSQL (pgvector), Redis, Composer 2. After `composer install`, `php artisan key:generate`, `php artisan migrate --force`, `php artisan storage:link`:

```bash
php artisan serve --host=127.0.0.1 --port=8080
php artisan queue:work redis --queue=geoflow,distribution,default --sleep=1 --tries=1 --timeout=300
php artisan schedule:work
php artisan reverb:start
```

Docker is the primary deploy path: `docker compose up -d` (dev, services: postgres/redis/init/app/queue/scheduler/reverb) or `docker-compose.prod.yml` (prod, Nginx + php-fpm). See `README.md` and `docs/deployment/DEPLOYMENT.md`.

### Project-specific Artisan commands (`app/Console/Commands/`)
- `geoflow:schedule-tasks` — scans active tasks and enqueues due jobs; **run every minute by the scheduler** (`routes/console.php`). This is the cron entry point for content generation.
- `geoflow:admin-unlock <username>` — unlock an admin locked after 5 failed logins.
- `geoflow:process-url-import` — URL import worker.
- `geoflow:worker` — **deprecated**, use `queue:work redis --queue=geoflow` instead.

## Architecture

### Three entry surfaces, one domain core
- **Public site** (`routes/web.php` top group, `App\Http\Controllers\Site\*`) — Blade article site: home, archive, category, article. Locale fixed to `geoflow.public_locale` (default `zh_CN`) via `site.locale` middleware; `site.view_log` records page views for analytics.
- **Admin backend** (`routes/web.php`, `App\Http\Controllers\Admin\*`) — Blade admin under a configurable prefix `config('geoflow.admin_base_path')` (default `/geo_admin`). Session auth via the `admin` guard; middleware `admin.auth`, `admin.locale`, `admin.activity`, and `admin.super` (super-admin-only sections: admin users, activity logs, API tokens).
- **REST API v1** (`routes/api.php`, `App\Http\Controllers\Api\V1\*`) — `/api/v1/*`, Sanctum bearer tokens with per-route ability scopes (`api.scope:tasks:write`, etc.). `api.request_id` propagates `X-Request-Id`; errors render as a uniform JSON envelope via `ApiException` + `App\Support\ApiResponse` (exception rendering wired in `bootstrap/app.php`). Writes are idempotent via `IdempotencyService`.

Middleware aliases are registered in `bootstrap/app.php` (Laravel 12 — no `Http/Kernel.php`). Service providers in `bootstrap/providers.php`.

### Generation pipeline (the heart of the system)
`geoflow:schedule-tasks` (cron) → enqueues `ProcessGeoFlowTaskJob` on the `geoflow` queue → `WorkerExecutionService` (`app/Services/GeoFlow/`) does the real work: resolves the task's title/keyword/image/author/prompt material, runs RAG retrieval, calls the model via the AI layer, and persists an `Article` through the review/publish workflow (`App\Support\GeoFlow\ArticleWorkflow`). If the task targets distribution channels, it hands off to `DistributionOrchestrator`.

### AI layer
Built on `laravel/ai` plus a project provider `App\Support\GeoFlow\OpenAiRuntimeProvider` (handles OpenAI-compatible + Gemini-native chat/embedding, provider-URL adaptation). Agents live in `app/Ai/Agents/` (e.g. `MarkdownContentWriterAgent`). Models are configured in the admin UI and stored in the `AiModel` model. **API keys are encrypted** (`App\Support\GeoFlow\ApiKeyCrypto`, `enc:v1`); the crypto root reads `APP_KEY` only through `config('geoflow.api_key_crypto_roots')` — application code must **never** call `env()` directly for it.

### Knowledge base / RAG
`KnowledgeBase` uploads are sliced into `KnowledgeChunk` rows (structured rules + optional LLM semantic planning with stable fallback). `KnowledgeChunkSyncService` writes pgvector embeddings; `KnowledgeRetrievalService` recalls relevant chunks at generation time. pgvector is required — use the matching Postgres image.

### Distribution
`DistributionPublisherManager` dispatches by `channel->channelType()` to one of three publishers implementing `DistributionPublisherInterface`: `GeoFlowAgentPublisher` (`geoflow_agent` — generates a self-contained PHP agent package with home/detail/sitemap/`llms.txt`), `WordPressRestPublisher` (`wordpress_rest`), `GenericHttpApiPublisher` (`generic_http_api`). `ProcessArticleDistributionJob` runs on the `distribution` queue; `DistributionOrchestrator`, `DistributionSigningService`, and `DistributionRetryPolicy` coordinate signing, retries, and logging.

### Queues & realtime
Redis-backed. Queues: `geoflow` (generation), `distribution` (publishing), `default`. Production uses **Horizon** (`config/horizon.php`, supervisor watches `geoflow` + `distribution`); `horizon:snapshot` is scheduled every 5 min. Reverb (WebSockets) + laravel-echo power live task progress (`TaskRealtimeBroadcastService`); `routes/channels.php`.

### Services layout (`app/Services/`)
- `GeoFlow/` — core domain: generation, RAG, distribution, tasks, material library.
- `Admin/` — admin-only subsystems: `SystemUpdate*` (in-app update center — plan/backup/apply/rollback, gated behind `geoflow.update_*` flags, default execution **off**), `SiteThemeReplication*` (AI site-theme cloning, runs via `RunSiteThemeReplicationJob`/`IterateSiteThemeReplicationJob`), `Analytics/`.
- `Api/` — `ApiTokenService`, `ApiAdminAuthService`, `IdempotencyService`.

### Configuration
`config/geoflow.php` is the central business config (site identity, admin path, upload paths, login lockout, RAG/chunking limits, outbound proxy allowlist, content `max_tokens` fallback, update-center flags). Most values are env-driven; after editing env run `php artisan config:clear`. Outbound HTTP proxy (`geoflow.outbound_proxy_hosts`) is scoped to AI provider hosts only by default so WordPress/agent traffic isn't intercepted.

## Conventions specific to this repo
- Default admin (after `db:seed`, `AdminUserSeeder`): username `GEOFLOW_ADMIN_USERNAME` (default `admin`), password `password` in dev / `GEOFLOW_ADMIN_PASSWORD` in prod. Seeder never overwrites an existing account.
- Don't reach for `env()` outside config files — read `config('geoflow.*')` instead (enforced for the crypto root, expected everywhere).
- The Boost guidelines require running `vendor/bin/pint --dirty --format agent` and adding/updating a test for every change; honor both before finishing.
