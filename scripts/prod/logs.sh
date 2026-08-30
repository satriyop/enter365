#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

target="${1:-laravel}"
lines="${2:-80}"
ensure_ssh_alias

case "$target" in
    laravel) ssh_aidev "tail -n ${lines} ${APP_DIR}/storage/logs/laravel.log" ;;
    queue) ssh_aidev "tail -n ${lines} /var/log/enter365/queue.log" ;;
    scheduler) ssh_aidev "tail -n ${lines} /var/log/enter365/scheduler.log" ;;
    php) ssh_aidev "tail -n ${lines} /var/log/enter365/php-fpm.log" ;;
    caddy) ssh_aidev "journalctl -u caddy -n ${lines} --no-pager" ;;
    follow) ssh_aidev "tail -f ${APP_DIR}/storage/logs/laravel.log" ;;
    *) die "usage: $0 [laravel|queue|scheduler|php|caddy|follow] [lines]" ;;
esac
