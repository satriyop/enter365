#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

ensure_ssh_alias
fail=0

check_http() {
    local url="$1" want="$2"
    local code
    code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "$url" || echo 000)"
    if [[ "$code" == "$want" ]]; then
        green "OK  ${url} → ${code}"
    else
        red "FAIL ${url} → ${code} (want ${want})"
        fail=1
    fi
}

info "public HTTPS"
check_http "https://${DOMAIN}/up" 200
check_http "https://${DOMAIN}/api/health/live" 200
check_http "https://${DOMAIN}/login" 200

info "aidev process"
ssh_aidev "DOMAIN='${DOMAIN}' APP_DIR='${APP_DIR}' bash -s" <<'REMOTE' || fail=1
set -euo pipefail
echo "host=$(hostname) disk=$(df -h / | awk 'NR==2{print $5}')"
systemctl is-active caddy >/dev/null && echo "caddy=active" || echo "caddy=DOWN"
systemctl is-active supervisor >/dev/null && echo "supervisor=active" || echo "supervisor=DOWN"
supervisorctl status enter365-queue enter365-scheduler 2>/dev/null || true
if [[ -f "${APP_DIR}/artisan" ]]; then
    sudo -u www-data php "${APP_DIR}/artisan" --version
    curl -sS -o /dev/null -w "local /up %{http_code}\n" --max-time 5 -H "Host: ${DOMAIN}" http://127.0.0.1/up || true
fi
REMOTE

if [[ "$fail" -ne 0 ]]; then
    die "health checks failed"
fi
green "healthy"
