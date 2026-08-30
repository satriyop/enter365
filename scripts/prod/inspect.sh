#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

ensure_ssh_alias
ssh_aidev 'bash -s' <<'REMOTE'
set -euo pipefail
echo "===== host ====="
hostname; uname -a
. /etc/os-release 2>/dev/null && echo "$PRETTY_NAME" || true
echo "===== disk/mem ====="
df -h / | awk 'NR==1 || NR==2'
free -h | head -2
echo "===== caddy ====="
caddy version 2>/dev/null || true
echo "----- Caddyfile -----"
head -120 /etc/caddy/Caddyfile 2>/dev/null || true
echo "----- sites -----"
ls -la /etc/caddy/sites 2>/dev/null || true
echo "===== www ====="
ls -la /var/www 2>/dev/null || true
echo "===== php ====="
php -v 2>/dev/null | head -1
ls /etc/php 2>/dev/null || true
ls /run/php 2>/dev/null || true
systemctl is-active php8.4-fpm php8.3-fpm 2>/dev/null || true
echo "===== postgres ====="
systemctl is-active postgresql 2>/dev/null || true
sudo -u postgres psql -c '\l' 2>/dev/null | head -25 || true
echo "===== listen ====="
ss -lntp 2>/dev/null | grep -E ':80|:443|:22|:5432' || true
echo "===== enter365 ====="
ls -la /var/www/enter365 2>/dev/null | head || echo "(not installed)"
REMOTE
