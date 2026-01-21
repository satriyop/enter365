#!/bin/bash
#
# CI Check: Pattern Compliance
#
# Checks for common architectural anti-patterns that should be avoided.
# Run this before commits to prevent architectural drift.
#
# Checks performed:
# 1. auth()->id() in Services (should use $this->getUserId())
# 2. new ...Strategy() in Strategies (should inject via constructor)
# 3. ??= app() in constructors (hidden dependencies)
# 4. DB::transaction in AbstractApplicationService children (should use executeInTransaction)
#
# Usage: ./scripts/check-pattern-compliance.sh
#

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0

echo -e "${CYAN}=== Pattern Compliance Check ===${NC}"
echo ""

# -----------------------------------------------------------------------------
# Check 1: auth()->id() in Services
# -----------------------------------------------------------------------------
echo -e "${CYAN}[1/4] Checking for auth()->id() in Services...${NC}"

AUTH_VIOLATIONS=$(grep -rn "auth()->id()" app/Services/ 2>/dev/null | grep -v "AbstractApplicationService.php" || true)

if [ -n "$AUTH_VIOLATIONS" ]; then
    echo -e "${RED}ERROR: Found auth()->id() calls${NC}"
    echo "$AUTH_VIOLATIONS" | while read -r line; do
        echo -e "  ${YELLOW}$line${NC}"
    done
    echo -e "${RED}Fix: Use \$this->getUserId() instead${NC}"
    echo ""
    ERRORS=$((ERRORS + $(echo "$AUTH_VIOLATIONS" | wc -l | tr -d ' ')))
else
    echo -e "${GREEN}OK${NC}"
fi
echo ""

# -----------------------------------------------------------------------------
# Check 2: new ...Strategy() in Strategy classes
# -----------------------------------------------------------------------------
echo -e "${CYAN}[2/4] Checking for 'new ...Strategy()' in Strategies...${NC}"

STRATEGY_VIOLATIONS=$(grep -rn "new [A-Za-z]*Strategy(" app/Services/Accounting/Strategies/ 2>/dev/null || true)

if [ -n "$STRATEGY_VIOLATIONS" ]; then
    echo -e "${RED}ERROR: Found strategies creating other strategies with 'new'${NC}"
    echo "$STRATEGY_VIOLATIONS" | while read -r line; do
        echo -e "  ${YELLOW}$line${NC}"
    done
    echo -e "${RED}Fix: Inject the strategy via constructor instead${NC}"
    echo ""
    ERRORS=$((ERRORS + $(echo "$STRATEGY_VIOLATIONS" | wc -l | tr -d ' ')))
else
    echo -e "${GREEN}OK${NC}"
fi
echo ""

# -----------------------------------------------------------------------------
# Check 3: ??= app() fallback in constructors
# -----------------------------------------------------------------------------
echo -e "${CYAN}[3/4] Checking for '??= app()' fallback pattern...${NC}"

APP_FALLBACK=$(grep -rn '??=.*app(' app/Services/ 2>/dev/null || true)

if [ -n "$APP_FALLBACK" ]; then
    echo -e "${RED}ERROR: Found service locator fallback pattern${NC}"
    echo "$APP_FALLBACK" | while read -r line; do
        echo -e "  ${YELLOW}$line${NC}"
    done
    echo -e "${RED}Fix: Use explicit constructor injection, no fallback${NC}"
    echo ""
    ERRORS=$((ERRORS + $(echo "$APP_FALLBACK" | wc -l | tr -d ' ')))
else
    echo -e "${GREEN}OK${NC}"
fi
echo ""

# -----------------------------------------------------------------------------
# Check 4: Raw DB::transaction in AbstractApplicationService children
# -----------------------------------------------------------------------------
echo -e "${CYAN}[4/4] Checking for raw DB::transaction() in Pattern A services...${NC}"

# Find files that extend AbstractApplicationService and use raw DB::transaction
FOUND_RAW_TX=0
for file in $(grep -l "extends AbstractApplicationService" app/Services/**/*.php app/Services/**/**/*.php 2>/dev/null || true); do
    if grep -q "DB::transaction" "$file" 2>/dev/null; then
        if [ $FOUND_RAW_TX -eq 0 ]; then
            echo -e "${YELLOW}WARNING: Found raw DB::transaction() usage${NC}"
        fi
        echo -e "  ${YELLOW}$file${NC}"
        FOUND_RAW_TX=$((FOUND_RAW_TX + 1))
        WARNINGS=$((WARNINGS + 1))
    fi
done

if [ $FOUND_RAW_TX -eq 0 ]; then
    echo -e "${GREEN}OK${NC}"
else
    echo -e "${YELLOW}Recommendation: Use executeInTransaction() for automatic logging${NC}"
fi
echo ""

# -----------------------------------------------------------------------------
# Summary
# -----------------------------------------------------------------------------
echo -e "${CYAN}=== Summary ===${NC}"
echo ""

if [ $ERRORS -gt 0 ]; then
    echo -e "${RED}Errors: $ERRORS${NC}"
fi

if [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}Warnings: $WARNINGS${NC}"
fi

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}All checks passed!${NC}"
fi

echo ""

# Exit with error if there are errors (warnings are advisory)
if [ $ERRORS -gt 0 ]; then
    echo -e "${RED}Pattern compliance check failed.${NC}"
    exit 1
fi

echo -e "${GREEN}Pattern compliance check passed.${NC}"
exit 0
