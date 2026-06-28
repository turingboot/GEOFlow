# 部署到 Railway（单容器方案 B）

GEOFlow 在 Railway 上采用**单容器**形态：一个 App 服务里用 supervisor 同时跑
`nginx + php-fpm + queue worker + scheduler`，挂一个持久卷到 `storage/`；
另配 **Postgres(pgvector)** 与 **Redis** 两个服务。上传文件仍走本地磁盘（落在持久卷），无需对象存储。

> 相关文件：`docker/Dockerfile.railway`、`docker/nginx/railway.conf.template`、
> `docker/supervisord.conf`、`docker/entrypoint.railway.sh`、`railway.json`。

## 0. 前置
- 代码已推到 GitHub（合并到 `main` 或让 Railway 跟随你的分支）。
- 本地生成一个 APP_KEY：`php artisan key:generate --show`（拿到 `base64:...`，稍后填进 Railway 变量）。

## 1. 建 Postgres(pgvector) 服务
GEOFlow 迁移需要 `CREATE EXTENSION vector`，**必须用带 pgvector 的镜像**。
- New → **Empty Service → Deploy from Docker Image** → 镜像填 `pgvector/pgvector:pg17`。
- 该服务加一个 **Volume**，挂到 `/var/lib/postgresql/data`。
- 变量：`POSTGRES_USER=geo_user`、`POSTGRES_PASSWORD=<强密码>`、`POSTGRES_DB=geo_flow`。

## 2. 建 Redis 服务
- New → **Database → Add Redis**（官方插件即可）。

## 3. 建 App 服务（GEOFlow）
- New → **GitHub Repo** → 选本仓库。Railway 会读 `railway.json`，用 `docker/Dockerfile.railway` 构建。
- 该服务加一个 **Volume**，挂到 `/var/www/html/storage`（持久化上传/日志）。
- **Settings → Networking → Generate Domain**，拿到公网域名，回填到 `APP_URL`。

### App 服务的环境变量
```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:<上面生成的>
APP_URL=https://<你的域名>

DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.POSTGRES_DB}}
DB_USERNAME=${{Postgres.POSTGRES_USER}}
DB_PASSWORD=${{Postgres.POSTGRES_PASSWORD}}

QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=${{Redis.REDISHOST}}
REDIS_PORT=${{Redis.REDISPORT}}
REDIS_PASSWORD=${{Redis.REDISPASSWORD}}

CACHE_STORE=database
SESSION_DRIVER=database
BROADCAST_CONNECTION=null        # 单容器先不开 Reverb 实时；任务进度走轮询兜底
FILESYSTEM_DISK=local

GEOFLOW_ADMIN_USERNAME=admin
GEOFLOW_ADMIN_EMAIL=you@example.com
GEOFLOW_ADMIN_PASSWORD=<强密码>

# 首发开自动迁移+安装；跑通后建议改 false，避免每次部署重复执行
AUTO_WAIT_FOR_DB=true
AUTO_MIGRATE=true
AUTO_INSTALL_ONCE=true
AUTO_OPTIMIZE=true
```
> `${{Postgres.*}}` / `${{Redis.*}}` 是 Railway 的服务变量引用；如名字不同，用各服务“Variables”页里实际的键。

## 4. 部署
- 触发 Deploy。入口脚本会自动：渲染 nginx 端口 → 等 DB → `migrate` → `geoflow:install` → `optimize` → 起 supervisor。
- 看 Logs 应能看到 `migrate`、`geoflow:install`、各进程启动。

## 5. 验证
- 访问 `https://<域名>/` 看前台；`https://<域名>/geo_admin` 用 `admin / 你设的密码` 登录后台。
- **谷歌搜录**：到 Google Cloud 把 OAuth 回调改/加为
  `https://<域名>/geo_admin/google-search-console/oauth/callback`，再在后台设置里填 Client ID/Secret。

## 6. 收尾
- 跑通后把 `AUTO_MIGRATE`、`AUTO_INSTALL_ONCE` 设为 `false`（迁移在以后版本升级时再临时打开）。

## 注意 / 限制
- **持久卷**：必须给 App 和 Postgres 各挂卷，否则重新部署会丢上传文件 / 数据库。
- **Reverb 实时进度**：单容器内不暴露第二个公网端口；如需任务进度实时推送，另起一个 Reverb 服务（独立域名 + 一组 `REVERB_*` 变量），并把 `BROADCAST_CONNECTION=reverb`。不开也能用（前端轮询兜底）。
- **扩展规模**：流量大了想把 web/worker/scheduler 拆成多服务时，需把上传改到 S3/R2（Railway 卷不能跨服务共享）。
