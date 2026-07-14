#!/usr/bin/env bash
# Script de deploy - executado no servidor Hostinger após git pull.
# Uso: bash deploy.sh
# Requisitos: PHP >= 8.3, composer disponível.

set -euo pipefail

cd "$(dirname "$0")"

echo "== Composer install (produção) =="
composer install --no-dev --optimize-autoloader --no-interaction

echo "== Migrations =="
php artisan migrate --force

echo "== Cache configs =="
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "== Symlinks / storage =="
php artisan storage:link || true

echo "== Deploy concluído. =="
