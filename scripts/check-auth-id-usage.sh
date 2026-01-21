#!/bin/bash
#
# CI Check: Detect auth()->id() usage in Services
#
# This script fails if any service file uses auth()->id() directly.
# Services should use $this->getUserId() from AbstractApplicationService instead.
#
# Usage: ./scripts/check-auth-id-usage.sh
#

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "Checking for auth()->id() usage in app/Services/..."
echo ""

# Find all occurrences (exclude AbstractApplicationService which has the legitimate fallback)
VIOLATIONS=$(grep -rn "auth()->id()" app/Services/ 2>/dev/null | grep -v "AbstractApplicationService.php" || true)

if [ -n "$VIOLATIONS" ]; then
    echo -e "${RED}ERROR: Found direct auth()->id() calls in Services${NC}"
    echo ""
    echo "The following files use auth()->id() directly:"
    echo ""
    echo "$VIOLATIONS" | while read -r line; do
        echo -e "  ${YELLOW}$line${NC}"
    done
    echo ""
    echo -e "${RED}Fix: Replace auth()->id() with \$this->getUserId()${NC}"
    echo ""
    echo "Services should extend AbstractApplicationService and use getUserId()"
    echo "which supports OperationContext for proper testability."
    echo ""

    # Count violations
    COUNT=$(echo "$VIOLATIONS" | wc -l | tr -d ' ')
    echo -e "Total violations: ${RED}$COUNT${NC}"

    exit 1
else
    echo -e "${GREEN}OK: No auth()->id() calls found in app/Services/${NC}"
    exit 0
fi
