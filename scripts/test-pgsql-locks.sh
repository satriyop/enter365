#!/usr/bin/env bash
set -euo pipefail

# Run the F-14 PostgreSQL lock suite against enter365_test.
# SQLite phpunit.xml is unchanged — this is a second lane.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

HOST="${DB_HOST:-127.0.0.1}"
PORT="${DB_PORT:-5432}"
USER="${DB_USERNAME:-$(whoami)}"
DB="${PGSQL_TEST_DATABASE:-enter365_test}"

if ! command -v pg_isready >/dev/null 2>&1; then
    echo "pg_isready not found. Install PostgreSQL client tools."
    exit 1
fi

if ! pg_isready -h "$HOST" -p "$PORT" >/dev/null 2>&1; then
    echo "PostgreSQL is not accepting connections on ${HOST}:${PORT}."
    echo "Start Postgres 16 and re-run, or skip this lane locally."
    exit 1
fi

if command -v createdb >/dev/null 2>&1; then
    createdb -h "$HOST" -p "$PORT" -U "$USER" "$DB" 2>/dev/null || true
fi

export DB_CONNECTION=pgsql
export DB_HOST="$HOST"
export DB_PORT="$PORT"
export DB_DATABASE="$DB"
export DB_USERNAME="$USER"

php artisan config:clear --ansi >/dev/null
php artisan test -c phpunit.pgsql.xml "$@"
