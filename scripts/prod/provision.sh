#!/usr/bin/env bash
# First-time aidev host setup for Enter365. Additive: does not replace Caddyfile,
# Postgres, or other sites (sipamungkas / OpenClaw stay).
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

ensure_ssh_alias
info "Provisioning ${DOMAIN} on ${AIDEV_HOST} (additive)"

confirm "Install PHP/Caddy site/Postgres DB for ${DOMAIN} on aidev?" || die "aborted"

# Upload templates to a scratch dir, then run remote provision.
REMOTE_TMP="/tmp/enter365-provision-$$"
ssh_aidev "mkdir -p ${REMOTE_TMP}"
scp_aidev \
    "${PROD_SCRIPTS}/templates/Caddyfile.site" \
    "${PROD_SCRIPTS}/templates/php-fpm-pool.conf" \
    "${PROD_SCRIPTS}/templates/supervisor-enter365.conf" \
    "${PROD_SCRIPTS}/templates/env.production.example" \
    "${AIDEV_HOST}:${REMOTE_TMP}/"

ssh_aidev "DOMAIN='${DOMAIN}' APP_DIR='${APP_DIR}' SPA_DIR='${SPA_DIR}' DB_NAME='${DB_NAME}' DB_USER='${DB_USER}' REMOTE_TMP='${REMOTE_TMP}' bash -s" <<'REMOTE'
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

if [[ ${EUID} -ne 0 ]]; then
    echo "must run as root" >&2
    exit 1
fi

echo "==> packages"
apt-get update -y
apt-get install -y ca-certificates curl gnupg unzip git rsync acl \
    supervisor postgresql postgresql-contrib

if ! command -v caddy >/dev/null; then
    echo "Caddy is missing. aidev already serves sipamungkas via Caddy — install it before continuing." >&2
    exit 1
fi

PHP_VER=""
for v in 8.4 8.3; do
    if command -v "php${v}" >/dev/null 2>&1 || apt-cache policy "php${v}-fpm" 2>/dev/null | grep -q Candidate; then
        PHP_VER="$v"
        break
    fi
done
if [[ -z "${PHP_VER}" ]]; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
    PHP_VER="8.4"
fi

apt-get install -y \
    "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-pgsql" \
    "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" \
    "php${PHP_VER}-zip" "php${PHP_VER}-bcmath" "php${PHP_VER}-intl" \
    "php${PHP_VER}-gd" "php${PHP_VER}-readline"

if ! command -v composer >/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# SPA is built on the laptop. Do not install Node on this 2GB host.

echo "==> dirs"
mkdir -p "${APP_DIR}" "${SPA_DIR}" /var/log/enter365 /etc/caddy/sites /var/backups/enter365
chown -R www-data:www-data "${APP_DIR}" "${SPA_DIR}" /var/log/enter365
chmod 750 /var/log/enter365

CADDY_USER="$(ps -o user= -C caddy 2>/dev/null | awk 'NF{print; exit}')"
CADDY_USER="${CADDY_USER:-caddy}"
id "${CADDY_USER}" >/dev/null 2>&1 || CADDY_USER=www-data
PHP_SOCK="/run/php/php${PHP_VER}-fpm-enter365.sock"

POOL_DIR="/etc/php/${PHP_VER}/fpm/pool.d"
mkdir -p "${POOL_DIR}"
sed -e "s|__PHP_SOCK__|${PHP_SOCK}|g" -e "s|__CADDY_USER__|${CADDY_USER}|g" \
    "${REMOTE_TMP}/php-fpm-pool.conf" >"${POOL_DIR}/enter365.conf"

sed -e "s|__APP_DIR__|${APP_DIR}|g" \
    "${REMOTE_TMP}/supervisor-enter365.conf" >/etc/supervisor/conf.d/enter365.conf

sed -e "s|__DOMAIN__|${DOMAIN}|g" \
    -e "s|__APP_DIR__|${APP_DIR}|g" \
    -e "s|__SPA_DIR__|${SPA_DIR}|g" \
    -e "s|__PHP_SOCK__|${PHP_SOCK}|g" \
    "${REMOTE_TMP}/Caddyfile.site" >/etc/caddy/sites/${DOMAIN}.caddy

if ! grep -q 'import /etc/caddy/sites/\*\.caddy' /etc/caddy/Caddyfile 2>/dev/null; then
    cp -a /etc/caddy/Caddyfile "/etc/caddy/Caddyfile.bak.enter365.$(date -u +%Y%m%dT%H%M%SZ)"
    printf '\n# Enter365 sites\nimport /etc/caddy/sites/*.caddy\n' >>/etc/caddy/Caddyfile
fi

echo "==> postgres"
systemctl enable --now postgresql
MEM_KB="$(awk '/MemTotal/ {print $2}' /proc/meminfo)"
if [[ "${MEM_KB}" -lt 3000000 ]]; then
    PG_CONF_DIR="$(ls -d /etc/postgresql/*/main/conf.d 2>/dev/null | head -1 || true)"
    if [[ -n "${PG_CONF_DIR}" ]]; then
        cat >"${PG_CONF_DIR}/enter365-tinyram.conf" <<'PGTUNE'
# aidev is 2GB and already runs OpenClaw + Sipamungkas
shared_buffers = 64MB
effective_cache_size = 256MB
work_mem = 4MB
maintenance_work_mem = 32MB
max_connections = 40
PGTUNE
        systemctl restart postgresql
    fi
fi
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1; then
    DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 32)"
    sudo -u postgres psql -v ON_ERROR_STOP=1 \
        -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';"
    echo "${DB_PASS}" >/root/enter365-db-pass.txt
    chmod 600 /root/enter365-db-pass.txt
    echo "DB password written to /root/enter365-db-pass.txt (not echoed)"
else
    echo "role ${DB_USER} already exists"
fi
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1; then
    sudo -u postgres psql -v ON_ERROR_STOP=1 \
        -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"
    sudo -u postgres psql -v ON_ERROR_STOP=1 -d "${DB_NAME}" \
        -c "GRANT ALL ON SCHEMA public TO ${DB_USER};"
fi

echo "==> php-fpm + caddy + supervisor"
systemctl enable --now "php${PHP_VER}-fpm"
systemctl reload "php${PHP_VER}-fpm" || systemctl restart "php${PHP_VER}-fpm"

caddy validate --config /etc/caddy/Caddyfile
systemctl reload caddy || systemctl restart caddy

systemctl enable --now supervisor
supervisorctl reread
supervisorctl update
# workers start after first deploy (artisan exists)

if [[ ! -f "${APP_DIR}/.env" ]]; then
    cp "${REMOTE_TMP}/env.production.example" "${APP_DIR}/.env"
    if [[ -f /root/enter365-db-pass.txt ]]; then
        DB_PASS="$(cat /root/enter365-db-pass.txt)"
        sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" "${APP_DIR}/.env"
    fi
    chown www-data:www-data "${APP_DIR}/.env"
    chmod 640 "${APP_DIR}/.env"
    echo "Wrote ${APP_DIR}/.env — generate APP_KEY after first rsync: ./scripts/prod.sh env-init"
fi

rm -rf "${REMOTE_TMP}"
echo "PROVISION_OK php=${PHP_VER} sock=${PHP_SOCK} caddy_user=${CADDY_USER}"
echo "TLS: if Cloudflare is orange-cloud and Caddy cannot get a cert, grey-cloud the A record for 2 minutes, reload Caddy, then proxy again. SSL mode Full (strict)."
REMOTE

green "Provision finished. Next:"
echo "  ./scripts/prod.sh env-init    # APP_KEY + db password if missing"
echo "  ./scripts/prod.sh deploy      # rsync code + composer + migrate"
echo "  ./scripts/prod.sh seed-pos    # first Kopitiam catalog only"
echo "  ./scripts/prod.sh health"
