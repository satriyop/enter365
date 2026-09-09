#!/usr/bin/env bash
# Kopitiam catalog ONLY — ignores FEATURE_PRESET. Prefer ./scripts/prod.sh seed-demo
# so data matches the mockup preset (full → all + till). Idempotent-ish
# (PosKopitiamDemoSeeder uses updateOrCreate). Rotate hashes only with:
#   PosKopitiamDemoSeeder::rotatePasswords()
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

ensure_ssh_alias
yellow "This seeds Chart of Accounts + Kopitiam 57 catalog."
yellow "Demo logins: admin@example.com, siti@kopitiam57.test,"
yellow "  rina@kopitiam57.test, dewi@kopitiam57.test — see PosKopitiamDemoSeeder::DEMO_PASSWORD"
confirm "Seed POS catalog on production ${DOMAIN}?" || die "aborted"

ssh_aidev "APP_DIR='${APP_DIR}' APP_USER='${APP_USER}' bash -s" <<'REMOTE'
set -euo pipefail
cd "${APP_DIR}"
sudo -u "${APP_USER}" -H php artisan db:seed --force --class=Database\\Seeders\\FiscalPeriodSeeder
sudo -u "${APP_USER}" -H php artisan db:seed --force --class=Database\\Seeders\\ChartOfAccountsSeeder
sudo -u "${APP_USER}" -H php artisan db:seed --force --class=Database\\Seeders\\RolesAndPermissionsSeeder
sudo -u "${APP_USER}" -H php artisan db:seed --force --class=Database\\Seeders\\Demo\\PosKopitiamDemoSeeder
sudo -u "${APP_USER}" -H php artisan optimize
echo "SEED_POS_OK — demo password is PosKopitiamDemoSeeder::DEMO_PASSWORD (shared; change before a real till)"
REMOTE
