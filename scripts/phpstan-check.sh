#!/bin/bash

# PHPStan Check Script
# Usage: ./scripts/phpstan-check.sh [path]
# If no path provided, runs on configured paths from phpstan.neon

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_ROOT"

# PHPStan is configured to run in single-process mode (maximumNumberOfProcesses: 0)
# This avoids TCP server permission issues on macOS

# Default to configured paths if no argument provided
# Use higher memory limit for full codebase analysis (single-process mode uses more memory)
if [ -z "$1" ]; then
    echo "Running PHPStan on configured paths (app/, config/, database/, routes/)..."
    vendor/bin/phpstan analyse --level=5 --memory-limit=1G
else
    echo "Running PHPStan on: $1"
    vendor/bin/phpstan analyse "$1" --level=5
fi
