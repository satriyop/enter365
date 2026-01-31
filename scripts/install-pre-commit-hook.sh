#!/bin/bash

# Install pre-commit hook for API contract checking
#
# Usage:
#   ./scripts/install-pre-commit-hook.sh

set -e

HOOK_SOURCE=".git/hooks/pre-commit-api-check"
HOOK_TARGET=".git/hooks/pre-commit"

if [ ! -f "$HOOK_SOURCE" ]; then
    echo "❌ Pre-commit hook source not found: $HOOK_SOURCE"
    exit 1
fi

# Check if pre-commit hook already exists
if [ -f "$HOOK_TARGET" ]; then
    echo "⚠️  Pre-commit hook already exists at $HOOK_TARGET"
    echo ""
    echo "Options:"
    echo "  1. Backup existing hook and install API check hook"
    echo "  2. Append API check to existing hook"
    echo "  3. Cancel"
    echo ""
    read -p "Choose option (1/2/3): " choice
    
    case $choice in
        1)
            BACKUP="$HOOK_TARGET.backup.$(date +%Y%m%d_%H%M%S)"
            cp "$HOOK_TARGET" "$BACKUP"
            echo "✓ Backed up existing hook to: $BACKUP"
            cp "$HOOK_SOURCE" "$HOOK_TARGET"
            chmod +x "$HOOK_TARGET"
            echo "✓ Installed API check hook"
            ;;
        2)
            echo "" >> "$HOOK_TARGET"
            echo "# API Contract Check Hook" >> "$HOOK_TARGET"
            cat "$HOOK_SOURCE" >> "$HOOK_TARGET"
            chmod +x "$HOOK_TARGET"
            echo "✓ Appended API check to existing hook"
            ;;
        3)
            echo "Cancelled"
            exit 0
            ;;
        *)
            echo "Invalid option"
            exit 1
            ;;
    esac
else
    cp "$HOOK_SOURCE" "$HOOK_TARGET"
    chmod +x "$HOOK_TARGET"
    echo "✓ Pre-commit hook installed successfully"
fi

echo ""
echo "The hook will now run automatically before each commit."
echo "To skip the hook (not recommended): git commit --no-verify"
