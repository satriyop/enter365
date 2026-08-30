#!/usr/bin/env bash
# pg_dump + copy off-box to ./storage/backups/aidev/
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

ensure_ssh_alias
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
remote="/var/backups/enter365/enter365-${stamp}.sql.gz"
local_dir="${PROD_ROOT}/storage/backups/aidev"
mkdir -p "${local_dir}"

info "dumping ${DB_NAME} on aidev"
ssh_aidev "mkdir -p /var/backups/enter365 && sudo -u postgres pg_dump --no-owner --format=plain ${DB_NAME} | gzip -c > ${remote} && chmod 600 ${remote} && ls -lh ${remote}"

info "fetching dump"
scp_aidev "${AIDEV_HOST}:${remote}" "${local_dir}/"
green "saved ${local_dir}/$(basename "${remote}")"
echo "Keep this off the droplet. Restore is manual (never migrate:fresh)."
