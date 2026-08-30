#!/usr/bin/env bash
# Generate APP_KEY on aidev if missing. Does not overwrite an existing key.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

ensure_ssh_alias

ssh_aidev "APP_DIR='${APP_DIR}' APP_USER='${APP_USER}' bash -s" <<'REMOTE'
set -euo pipefail
cd "${APP_DIR}"
[[ -f .env ]] || { echo "missing .env — run provision first" >&2; exit 1; }

if ! grep -Eq '^APP_KEY=base64:' .env; then
    if [[ -f artisan && -d vendor ]]; then
        sudo -u "${APP_USER}" -H php artisan key:generate --force
    else
        KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
        sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" .env
        echo "APP_KEY generated (openssl); artisan was not on disk yet"
    fi
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R "${APP_USER}:${APP_USER}" storage bootstrap/cache "${APP_DIR}/.env"
chmod -R ug+rwx storage bootstrap/cache
chmod 640 .env

echo "ENV_OK APP_KEY set"
if grep -Eq '^DB_PASSWORD=$' .env; then
    if [[ -f /root/enter365-db-pass.txt ]]; then
        DB_PASS="$(cat /root/enter365-db-pass.txt)"
        sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
        echo "DB_PASSWORD copied from /root/enter365-db-pass.txt"
    else
        echo "WARN: DB_PASSWORD empty"
    fi
fi
REMOTE
