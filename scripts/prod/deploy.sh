#!/usr/bin/env bash
# Ship this laptop's trees to aidev. Does not push git.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

SKIP_FRONTEND=0
SKIP_BUILD=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-frontend) SKIP_FRONTEND=1; shift ;;
        --skip-build) SKIP_BUILD=1; shift ;;
        -h|--help)
            echo "Usage: $0 [--skip-frontend] [--skip-build]"
            exit 0
            ;;
        *) die "unknown arg: $1" ;;
    esac
done

require_cmd rsync
require_cmd ssh
ensure_ssh_alias

[[ -f "${PROD_ROOT}/artisan" ]] || die "not the enter365 backend root: ${PROD_ROOT}"
ssh_aidev "test -d ${APP_DIR} && test -f ${APP_DIR}/.env" \
    || die "${APP_DIR}/.env missing on aidev — run ./scripts/prod.sh provision && ./scripts/prod.sh env-init"

info "rsync backend → ${AIDEV_HOST}:${APP_DIR}"
rsync -az --delete --human-readable \
    --exclude-from="${PROD_SCRIPTS}/rsync-exclude.txt" \
    -e "ssh -o ControlMaster=auto -o ControlPath=${CTRL_PATH} -o ControlPersist=10m -o BatchMode=yes" \
    "${PROD_ROOT}/" "${AIDEV_HOST}:${APP_DIR}/"

ssh_aidev "chown -R ${APP_USER}:${APP_USER} ${APP_DIR} && chmod 640 ${APP_DIR}/.env"

if [[ "${SKIP_FRONTEND}" -eq 0 ]]; then
    [[ -n "${FRONTEND_DIR}" && -f "${FRONTEND_DIR}/package.json" ]] \
        || die "frontend not found at ${FRONTEND_DIR:-unset}. Set FRONTEND_DIR=..."
    if [[ "${SKIP_BUILD}" -eq 0 ]]; then
        info "building Vue SPA (vite; vue-tsc is not a deploy gate)"
        (cd "${FRONTEND_DIR}" && npx vite build)
    fi
    [[ -f "${FRONTEND_DIR}/dist/index.html" ]] || die "missing ${FRONTEND_DIR}/dist/index.html"
    info "rsync SPA → ${AIDEV_HOST}:${SPA_DIR}"
    rsync -az --delete --human-readable \
        -e "ssh -o ControlMaster=auto -o ControlPath=${CTRL_PATH} -o ControlPersist=10m -o BatchMode=yes" \
        "${FRONTEND_DIR}/dist/" "${AIDEV_HOST}:${SPA_DIR}/"
    ssh_aidev "chown -R ${APP_USER}:${APP_USER} ${SPA_DIR}"
fi

info "remote release (composer, migrate --force, optimize)"
scp_aidev "${PROD_SCRIPTS}/remote-release.sh" "${AIDEV_HOST}:/tmp/enter365-remote-release.sh"
ssh_aidev "APP_DIR='${APP_DIR}' APP_USER='${APP_USER}' bash /tmp/enter365-remote-release.sh"

green "Deployed to https://${DOMAIN}"
echo "Check: ./scripts/prod.sh health"
