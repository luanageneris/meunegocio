#!/bin/sh
set -e

APP_SRC_DIR="/opt/app-src"
APP_RUNTIME_DIR="/var/www/html"

if [ ! -f "$APP_RUNTIME_DIR/artisan" ]; then
  mkdir -p "$APP_RUNTIME_DIR"
  cp -a "$APP_SRC_DIR"/. "$APP_RUNTIME_DIR"/
fi

cd "$APP_RUNTIME_DIR"

mkdir -p storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

php artisan storage:link >/dev/null 2>&1 || true

if [ "${APP_ENV}" = "production" ]; then
  php artisan config:cache >/dev/null 2>&1 || true
  php artisan route:cache >/dev/null 2>&1 || true
  php artisan view:cache >/dev/null 2>&1 || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi

exec "$@"
