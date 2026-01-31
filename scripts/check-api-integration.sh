#!/bin/bash

# API Integration Check Script
# Automates the full integration check flow in a single command
#
# Usage:
#   ./scripts/check-api-integration.sh              # Full check
#   ./scripts/check-api-integration.sh --no-tests   # Skip tests
#   ./scripts/check-api-integration.sh --no-phpstan # Skip PHPStan

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Flags
SKIP_TESTS=false
SKIP_PHPSTAN=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --no-tests)
            SKIP_TESTS=true
            shift
            ;;
        --no-phpstan)
            SKIP_PHPSTAN=true
            shift
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Usage: $0 [--no-tests] [--no-phpstan]"
            exit 1
            ;;
    esac
done

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  API Integration Check${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

# Step 1: Generate OpenAPI Schema
echo -e "${YELLOW}[1/7]${NC} Generating OpenAPI schema..."
if php artisan scramble:export --path=api.json > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} OpenAPI schema generated"
else
    echo -e "${RED}✗${NC} Failed to generate OpenAPI schema"
    exit 1
fi

# Step 2: Check for mismatches
echo -e "${YELLOW}[2/7]${NC} Checking for API contract mismatches..."
MISMATCH_OUTPUT=$(php check-api-mismatches.php 2>&1)
MISMATCH_EXIT=$?

if [ $MISMATCH_EXIT -eq 0 ]; then
    echo -e "${GREEN}✓${NC} No mismatches found"
else
    echo -e "${RED}✗${NC} API contract mismatches detected:"
    echo "$MISMATCH_OUTPUT"
    exit 1
fi

# Step 3: Verify api.json exists and is valid JSON
echo -e "${YELLOW}[3/7]${NC} Validating api.json..."
if [ ! -f "api.json" ]; then
    echo -e "${RED}✗${NC} api.json not found"
    exit 1
fi

if ! php -r "json_decode(file_get_contents('api.json'), true);" 2>/dev/null; then
    echo -e "${RED}✗${NC} api.json is not valid JSON"
    exit 1
fi

echo -e "${GREEN}✓${NC} api.json is valid"

# Step 4: Run PHPStan (if not skipped)
if [ "$SKIP_PHPSTAN" = false ]; then
    echo -e "${YELLOW}[4/7]${NC} Running PHPStan type check..."
    if ./scripts/phpstan-check.sh app/Http/Resources/Api/V1/ > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} PHPStan passed"
    else
        echo -e "${RED}✗${NC} PHPStan found errors"
        echo "Run './scripts/phpstan-check.sh app/Http/Resources/Api/V1/' for details"
        exit 1
    fi
else
    echo -e "${YELLOW}[4/7]${NC} Skipping PHPStan (--no-phpstan flag)"
fi

# Step 5: Run API tests (if not skipped)
if [ "$SKIP_TESTS" = false ]; then
    echo -e "${YELLOW}[5/8]${NC} Running API tests..."
    if php artisan test --filter=ApiTest > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} All API tests passed"
    else
        echo -e "${RED}✗${NC} Some API tests failed"
        echo "Run 'php artisan test --filter=ApiTest' for details"
        exit 1
    fi
else
    echo -e "${YELLOW}[5/8]${NC} Skipping tests (--no-tests flag)"
fi

# Step 6: Run contract tests (if not skipped)
if [ "$SKIP_TESTS" = false ]; then
    echo -e "${YELLOW}[6/8]${NC} Running API contract tests..."
    if php artisan test --filter=ApiContractTest > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} All contract tests passed"
    else
        echo -e "${RED}✗${NC} Some contract tests failed"
        echo "Run 'php artisan test --filter=ApiContractTest' for details"
        exit 1
    fi
else
    echo -e "${YELLOW}[6/8]${NC} Skipping contract tests (--no-tests flag)"
fi

# Step 7: Check if frontend directory exists and types need regeneration
echo -e "${YELLOW}[7/8]${NC} Checking frontend integration..."
FRONTEND_DIR="../front-end-enter365"
if [ -d "$FRONTEND_DIR" ]; then
    if [ -f "$FRONTEND_DIR/package.json" ]; then
        echo -e "${GREEN}✓${NC} Frontend directory found"
        echo -e "${YELLOW}   Note:${NC} Run 'npm run types:generate' in frontend directory to update TypeScript types"
    else
        echo -e "${YELLOW}⚠${NC}  Frontend directory found but package.json not found"
    fi
else
    echo -e "${YELLOW}⚠${NC}  Frontend directory not found (expected at: $FRONTEND_DIR)"
fi

# Step 8: Final summary
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ API Integration Check Complete${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${GREEN}All checks passed!${NC}"
echo ""
echo "Next steps:"
echo "  1. If you modified API Resources, update frontend types:"
echo "     cd ../front-end-enter365 && npm run types:generate"
echo "  2. Enable response validation (optional):"
echo "     Set API_RESPONSE_VALIDATION_ENABLED=true in .env"
echo "  3. Commit changes:"
echo "     git add api.json app/Http/Resources/Api/V1/ tests/Feature/Api/ tests/Contract/"
echo ""

exit 0
