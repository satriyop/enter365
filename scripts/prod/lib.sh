#!/usr/bin/env bash
# Shared helpers for laptop → aidev Enter365 ops.
# shellcheck disable=SC2034

set -euo pipefail

PROD_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PROD_SCRIPTS="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRONTEND_DIR="${FRONTEND_DIR:-$(cd "${PROD_ROOT}/../front-end-enter365" 2>/dev/null && pwd || true)}"

# Load optional local deploy environment overrides (gitignored)
if [[ -f "${PROD_ROOT}/.env.deploy" ]]; then
    # shellcheck source=/dev/null
    source "${PROD_ROOT}/.env.deploy"
elif [[ -f "${PROD_SCRIPTS}/.env.deploy" ]]; then
    # shellcheck source=/dev/null
    source "${PROD_SCRIPTS}/.env.deploy"
fi

DOMAIN="${DOMAIN:-}"
AIDEV_HOST="${AIDEV_HOST:-root@aidev}"
AIDEV_IP="${AIDEV_IP:-}"
APP_DIR="${APP_DIR:-/var/www/enter365}"
SPA_DIR="${SPA_DIR:-/var/www/enter365-spa}"
APP_USER="${APP_USER:-www-data}"
DB_NAME="${DB_NAME:-enter365}"
DB_USER="${DB_USER:-enter365}"
PHP_VERSION="${PHP_VERSION:-}"
SSH_KEY_FILE="${SSH_KEY_FILE:-}"

CTRL_PATH="${CTRL_PATH:-/tmp/enter365-aidev.sock}"

red() { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }
yellow() { printf '\033[33m%s\033[0m\n' "$*"; }
info() { printf '==> %s\n' "$*"; }

die() {
    red "error: $*"
    exit 1
}

require_cmd() {
    command -v "$1" >/dev/null 2>&1 || die "missing command: $1"
}

require_prod_env() {
    local missing=()
    [[ -z "${AIDEV_IP:-}" ]] && missing+=("AIDEV_IP")
    [[ -z "${DOMAIN:-}" ]] && missing+=("DOMAIN")
    [[ -z "${AIDEV_HOST:-}" ]] && missing+=("AIDEV_HOST")
    if [[ ${#missing[@]} -gt 0 ]]; then
        die "Missing required deploy environment variable(s): ${missing[*]}. Define them in .env.deploy or your shell."
    fi
}

ensure_ssh_alias() {
    require_prod_env
    local cfg="${HOME}/.ssh/config"
    mkdir -p "${HOME}/.ssh"
    chmod 700 "${HOME}/.ssh"
    if [[ -f "$cfg" ]] && grep -Eq '^Host[[:space:]]+aidev([[:space:]]|$)' "$cfg"; then
        return 0
    fi
    info "Adding Host aidev to ${cfg}"
    {
        echo ""
        echo "# Enter365 prod (added by scripts/prod.sh ssh-config)"
        echo "Host aidev"
        echo "    HostName ${AIDEV_IP}"
        echo "    User root"
        echo "    IdentityFile ~/.ssh/id_ed25519"
        echo "    IdentityFile ~/.ssh/id_rsa"
        if [[ -n "${SSH_KEY_FILE:-}" ]]; then
            echo "    IdentityFile ${SSH_KEY_FILE}"
        fi
        echo "    AddKeysToAgent yes"
        echo "    UseKeychain yes"
        echo "    ServerAliveInterval 60"
        echo "    ServerAliveCountMax 3"
        echo "    ControlMaster auto"
        echo "    ControlPath ${CTRL_PATH}"
        echo "    ControlPersist 10m"
    } >>"$cfg"
    chmod 600 "$cfg"
}

ssh_opts() {
    echo -o BatchMode=yes \
        -o ConnectTimeout=20 \
        -o ServerAliveInterval=15 \
        -o ServerAliveCountMax=4 \
        -o ControlMaster=auto \
        -o ControlPath="${CTRL_PATH}" \
        -o ControlPersist=10m
}

ssh_aidev() {
    # Burst SSH trips fail2ban on aidev. Reuse one ControlMaster.
    if [[ -e "${CTRL_PATH}" ]]; then
        ssh -O check -o ControlPath="${CTRL_PATH}" "${AIDEV_HOST}" >/dev/null 2>&1 \
            || rm -f "${CTRL_PATH}"
    fi
    local attempt=1 max=5
    while true; do
        if ssh $(ssh_opts) "${AIDEV_HOST}" "$@"; then
            return 0
        fi
        local rc=$?
        if [[ $attempt -ge $max ]]; then
            return "$rc"
        fi
        yellow "ssh failed (rc=${rc}), retry ${attempt}/${max} in $((attempt * 8))s (fail2ban on aidev is aggressive)"
        sleep $((attempt * 8))
        attempt=$((attempt + 1))
    done
}

scp_aidev() {
    scp $(ssh_opts) "$@"
}

rsync_aidev() {
    rsync -az --human-readable \
        -e "ssh $(ssh_opts | tr '\n' ' ')" \
        "$@"
}

confirm() {
    local prompt="${1:-Continue?}"
    if [[ "${PROD_YES:-}" == "1" ]]; then
        return 0
    fi
    read -r -p "${prompt} [y/N] " ans
    [[ "${ans}" == "y" || "${ans}" == "Y" ]]
}

refuse_destructive_artisan() {
    local joined="$*"
    if echo "$joined" | grep -Eqi 'migrate:fresh|migrate:refresh|db:wipe|db:seed([[:space:]]|$)|seed:demo'; then
        die "refusing destructive artisan on production: $joined
Use: ./scripts/prod.sh seed-pos   (first catalog only)
     ./scripts/prod.sh artisan migrate --force"
    fi
}
