#!/usr/bin/env bash
set -e

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8000}"
DB_NAME="${DB_NAME:-oyun_portali}"
DB_USER="${DB_USER:-oyun_user}"
DB_PASS="${DB_PASS:-'CompScience10#'}"

cd "$APP_DIR"

if ! command -v php >/dev/null 2>&1; then
  echo "PHP bulunamadı."
  exit 1
fi

if ! command -v mysql >/dev/null 2>&1; then
  echo "MySQL istemcisi bulunamadı."
  exit 1
fi

if [ ! -f ".env" ]; then
  if [ -f ".env.example" ]; then
    cp .env.example .env
    echo ".env.example -> .env kopyalandı. Gerekirse düzenle."
  else
    echo ".env veya .env.example bulunamadı."
    exit 1
  fi
fi

echo "Veritabanı kontrol ediliyor..."
mysql -u "$DB_USER" -p"$DB_PASS" -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if [ -f "database/schema.sql" ]; then
  echo "Schema içe aktarılıyor..."
  mysql -u "$DB_USER" -p"$DB_PASS" -h 127.0.0.1 "$DB_NAME" < database/schema.sql
fi

echo "Sunucu başlatılıyor: http://$HOST:$PORT"
php -S "$HOST:$PORT" -t public