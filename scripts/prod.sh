#!/usr/bin/env bash
# Enter365 production CLI — run from this laptop against aidev.
#
#   ./scripts/prod.sh help
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BIN="${ROOT}/scripts/prod"

cmd="${1:-help}"
shift || true

case "${cmd}" in
    help|-h|--help)
        cat <<'EOF'
Enter365 → aidev (enter365.pamungkas.org)

First time
  ./scripts/prod.sh ssh-config   add Host aidev to ~/.ssh/config
  ./scripts/prod.sh inspect      what is already on the droplet
  ./scripts/prod.sh provision    PHP pool, Postgres DB, Caddy site (additive)
  ./scripts/prod.sh env-init     APP_KEY
  ./scripts/prod.sh deploy       rsync this laptop + composer + migrate --force
  ./scripts/prod.sh seed-pos     Kopitiam catalog (once). Then change passwords.
  ./scripts/prod.sh health

Every release
  ./scripts/prod.sh deploy
  ./scripts/prod.sh health

Day-2
  ./scripts/prod.sh ssh
  ./scripts/prod.sh logs [laravel|queue|scheduler|php|caddy|follow]
  ./scripts/prod.sh artisan migrate --force
  ./scripts/prod.sh artisan queue:restart
  ./scripts/prod.sh backup          pg_dump to storage/backups/aidev/
  ./scripts/prod.sh status          alias for health

Never
  migrate:fresh / db:wipe / DatabaseSeeder on production.

Env
  AIDEV_HOST=root@aidev DOMAIN=enter365.pamungkas.org
  FRONTEND_DIR=../front-end-enter365  PROD_YES=1
EOF
        ;;
    ssh-config)
        # shellcheck source=prod/lib.sh
        source "${BIN}/lib.sh"
        ensure_ssh_alias
        echo "Host aidev ready (${AIDEV_IP})"
        ;;
    inspect) exec bash "${BIN}/inspect.sh" "$@" ;;
    provision) exec bash "${BIN}/provision.sh" "$@" ;;
    env-init) exec bash "${BIN}/env-init.sh" "$@" ;;
    deploy) exec bash "${BIN}/deploy.sh" "$@" ;;
    seed-pos) exec bash "${BIN}/seed-pos.sh" "$@" ;;
    health|status) exec bash "${BIN}/health.sh" "$@" ;;
    logs) exec bash "${BIN}/logs.sh" "$@" ;;
    backup) exec bash "${BIN}/backup.sh" "$@" ;;
    artisan) exec bash "${BIN}/artisan.sh" "$@" ;;
    ssh)
        # shellcheck source=prod/lib.sh
        source "${BIN}/lib.sh"
        ensure_ssh_alias
        exec ssh -o ControlMaster=auto -o ControlPath="${CTRL_PATH}" -o ControlPersist=10m "${AIDEV_HOST}" "$@"
        ;;
    *)
        echo "unknown command: ${cmd}" >&2
        echo "try: $0 help" >&2
        exit 1
        ;;
esac
