#!/usr/bin/env sh
set -e

# APP_KEY が無い場合は自動生成する
if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force
fi

exec "$@"

