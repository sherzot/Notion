#!/usr/bin/env sh
set -e

# APP_KEY が無い場合は起動を止める（.env が無い環境では key:generate が書けないため）
if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is required (set it as an environment variable)." >&2
  exit 1
fi

exec "$@"

