#!/usr/bin/env sh
# Railway 单容器入口：不依赖 .env 文件（环境变量由 Railway 注入），
# 渲染 nginx 端口 → 准备存储目录 → 等待数据库 → 迁移/安装/优化 → 交给 supervisor。
set -eu
cd /var/www/html

# 1) 渲染 nginx 监听端口（Railway 注入 $PORT，未注入则用 8080）。
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/templates/railway.conf.template > /etc/nginx/conf.d/default.conf

# 2) 准备存储目录（持久卷首挂时为空）并修权限。
mkdir -p \
  storage/app/public/uploads/images \
  storage/app/tmp \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# 3) public/storage 软链（卷重挂后可能丢失，幂等重建）。
[ -e public/storage ] || php artisan storage:link --force --no-interaction || true

# 4) 等待 Postgres 就绪。
if [ "${AUTO_WAIT_FOR_DB:-true}" = "true" ] && [ "${DB_CONNECTION:-pgsql}" = "pgsql" ]; then
  echo "[railway] waiting for postgres at ${DB_HOST:-postgres}:${DB_PORT:-5432}"
  until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-postgres}" >/dev/null 2>&1; do
    sleep 2
  done
fi

# 5) 迁移 + 首次安装 + 优化（首发开；跑通后可在 Railway 关掉 AUTO_MIGRATE/AUTO_INSTALL_ONCE）。
if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
  echo "[railway] php artisan migrate --force"
  php artisan migrate --force --no-interaction
fi
if [ "${AUTO_INSTALL_ONCE:-true}" = "true" ]; then
  echo "[railway] php artisan geoflow:install"
  php artisan geoflow:install --no-interaction || true
fi
if [ "${AUTO_OPTIMIZE:-true}" = "true" ]; then
  php artisan optimize --no-interaction || true
fi

exec "$@"
