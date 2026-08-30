#!/usr/bin/env bash
# Runs ON aidev after rsync. Never migrate:fresh.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/enter365}"
APP_USER="${APP_USER:-www-data}"
cd "${APP_DIR}"

if [[ ! -f .env ]]; then
    echo "missing ${APP_DIR}/.env — run ./scripts/prod.sh env-init first" >&2
    exit 1
fi

if grep -Eq '^APP_ENV=local' .env || grep -Eq '^APP_DEBUG=true' .env; then
    echo "refusing release: APP_ENV must be production and APP_DEBUG=false" >&2
    exit 1
fi

as_app() {
    sudo -u "${APP_USER}" -H env COMPOSER_HOME=/var/www/.composer "$@"
}

mkdir -p /var/www/.composer
chown -R "${APP_USER}:${APP_USER}" /var/www/.composer

as_app composer install --no-dev --optimize-autoloader --no-interaction --working-dir="${APP_DIR}"

chgrp -R "${APP_USER}" storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

as_app php artisan storage:link --force >/dev/null 2>&1 || true
as_app php artisan route:clear
as_app php artisan config:clear
as_app php artisan migrate --force
as_app php artisan optimize
as_app php artisan queue:restart || true

if command -v supervisorctl >/dev/null; then
    supervisorctl restart enter365-queue:* 2>/dev/null || supervisorctl start enter365-queue 2>/dev/null || true
    supervisorctl restart enter365-scheduler:* 2>/dev/null || supervisorctl start enter365-scheduler 2>/dev/null || true
fi

if command -v php-fpm8.4 >/dev/null || [[ -d /etc/php/8.4/fpm ]]; then
    systemctl reload php8.4-fpm || true
fi
if [[ -d /etc/php/8.3/fpm ]]; then
    systemctl reload php8.3-fpm || true
fi

echo "RELEASE_OK $(php artisan --version 2>/dev/null | head -1)"
