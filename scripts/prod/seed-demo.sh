#!/usr/bin/env bash
# Seed demo data that matches aidev FEATURE_PRESET (`php artisan seed:demo`).
# Never migrate:fresh. Existing rows are updateOrCreated.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

ensure_ssh_alias
yellow "Seeds FEATURE_PRESET-mapped demo on ${DOMAIN} (full → all + Kopitiam till)."
yellow "Never migrate:fresh."
confirm "Seed demo from FEATURE_PRESET on ${DOMAIN}?" || die "aborted"

ssh_aidev "APP_DIR='${APP_DIR}' APP_USER='${APP_USER}' bash -s" <<'REMOTE'
set -euo pipefail
cd "${APP_DIR}"
sudo -u "${APP_USER}" -H php artisan seed:demo --no-interaction
sudo -u "${APP_USER}" -H php artisan optimize
echo "SEED_DEMO_OK"
REMOTE
