#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

ensure_ssh_alias
[[ $# -gt 0 ]] || die "usage: $0 <artisan args>"
refuse_destructive_artisan "$@"

# Pass args safely through SSH
printf -v quoted '%q ' "$@"
ssh_aidev "cd ${APP_DIR} && sudo -u ${APP_USER} -H php artisan ${quoted}"
