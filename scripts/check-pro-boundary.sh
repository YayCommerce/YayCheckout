#!/bin/bash
#
# Pro boundary guard.
#
# Rule: no file outside includes/Pro/ may name a YayCheckout\Pro\ class.
# Exactly one reference is permitted — the guarded bootstrap in YayCheckout.php
# that loads the provider when the Pro directory is present.
#
# Without this check the boundary erodes: a `use` here, a `new` there, and the
# directory can no longer be removed to build the Free artifact. That failure is
# invisible in a dev tree, which always contains everything.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
BOOTSTRAP="includes/YayCheckout.php"

cd "$PLUGIN_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

# Comment lines are excluded: docblocks legitimately describe the rule, and a
# guard that reported prose as a violation would train people to ignore it.
matches=$(grep -rn 'YayCheckout\\Pro' includes --include='*.php' \
    | grep -v '^includes/Pro/' \
    | grep -vE ':[0-9]+:[[:space:]]*(\*|//|/\*|#)' \
    || true)
count=$(printf '%s' "$matches" | grep -c . || true)

if [ "$count" -eq 0 ]; then
    echo -e "${RED}✗ Pro boundary: bootstrap missing${NC}"
    echo "  Expected exactly one reference in $BOOTSTRAP, found none."
    echo "  Without it the Pro build never boots its pro code."
    exit 1
fi

if [ "$count" -ne 1 ]; then
    echo -e "${RED}✗ Pro boundary violated: $count references outside includes/Pro/${NC}"
    echo "$matches" | sed 's/^/  /'
    echo ""
    echo "  Only $BOOTSTRAP may name a YayCheckout\\Pro class."
    echo "  Pro code must attach to core through hooks, so that removing"
    echo "  includes/Pro/ still leaves a working Free build."
    exit 1
fi

if ! printf '%s' "$matches" | grep -q "^$BOOTSTRAP:"; then
    echo -e "${RED}✗ Pro boundary: the single reference is in the wrong file${NC}"
    echo "$matches" | sed 's/^/  /'
    echo "  Expected it in $BOOTSTRAP."
    exit 1
fi

echo -e "${GREEN}✓ Pro boundary intact${NC} (1 guarded reference in $BOOTSTRAP)"
