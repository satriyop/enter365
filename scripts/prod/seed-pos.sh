#!/usr/bin/env bash
# First-time Kopitiam catalog on a fresh production DB. Idempotent-ish
# (PosKopitiamDemoSeeder uses updateOrCreate). Resets demo passwords to
# "password" — change them immediately after.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

ensure_ssh_alias
yellow "This seeds Chart of Accounts + Kopitiam 57 catalog."
yellow "Demo logins (CHANGE after): admin@example.com, siti@kopitiam57.test,"
yellow "  rina@kopitiam57.test, dewi@kopitiam57.test / password"
confirm "Seed POS catalog on production ${DOMAIN}?" || die "aborted"

ssh_aidev "APP_DIR='${APP_DIR}' APP_USER='${APP_USER}' bash -s" <<'REMOTE'
set -euo pipefail
cd "${APP_DIR}"
sudo -u "${APP_USER}" -H php artisan db:seed --force --class=Database\\Seeders\\FiscalPeriodSeeder
sudo -u "${APP_USER}" -H php artisan db:seed --force --class=Database\\Seeders\\ChartOfAccountsSeeder
sudo -u "${APP_USER}" -H php artisan db:seed --force --class=Database\\Seeders\\RolesAndPermissionsSeeder
sudo -u "${APP_USER}" -H php artisan db:seed --force --class=Database\\Seeders\\Demo\\PosKopitiamDemoSeeder
sudo -u "${APP_USER}" -H php artisan optimize
echo "SEED_POS_OK — change every demo password before handing the till to Siti"
REMOTE
