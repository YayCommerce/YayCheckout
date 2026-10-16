#!/bin/bash
#
# Pro boundary guard: no file outside includes/Pro/ may name a YayCheckout\Pro\ class,
# except the one guarded bootstrap in includes/YayCheckout.php. A Free block's render.php
# (packages/blocks/src/ outside pro/) counts too; a Pro block's, under pro/, may name Pro.
#
# Without it the boundary erodes until includes/Pro/ can no longer be removed to build
# Free, a failure a dev tree, which always contains everything, never shows.

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
matches=$(grep -rn 'YayCheckout\\Pro' includes packages/blocks/src --include='*.php' \
    | grep -v '^includes/Pro/' \
    | grep -v '^packages/blocks/src/pro/' \
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

# The Pro blocks are named in two hand-kept lists, BlockRegistry::PRO_BLOCKS (PHP) and
# PRO_BLOCK_METAS (packages/blocks/src/shared/pro-blocks.ts), which must match the folders
# under packages/blocks/src/pro/: a block missing from either list has no Free stub, or a stub
# Free's inserter never offers.
pro_dirs=$(ls -1 packages/blocks/src/pro | sort)
pro_php=$(sed -n '/const PRO_BLOCKS = \[/,/\];/p' includes/Blocks/BlockRegistry.php \
    | grep -oE "'yaycheckout/[a-z-]+'" | sed -E "s#'yaycheckout/([a-z-]+)'#\1#" | sort)
pro_ts=$(grep -oE "'\.\./pro/[a-z-]+/block\.json'" packages/blocks/src/shared/pro-blocks.ts \
    | sed -E "s#'\.\./pro/([a-z-]+)/block\.json'#\1#" | sort)

if [ "$pro_dirs" != "$pro_php" ] || [ "$pro_dirs" != "$pro_ts" ]; then
    echo -e "${RED}✗ Pro block lists out of step${NC}"
    echo "  packages/blocks/src/pro/: $(echo $pro_dirs)"
    echo "  BlockRegistry::PRO_BLOCKS: $(echo $pro_php)"
    echo "  shared/pro-blocks.ts:      $(echo $pro_ts)"
    exit 1
fi

echo -e "${GREEN}✓ Pro boundary intact${NC} (1 guarded reference in $BOOTSTRAP; $(echo "$pro_dirs" | grep -c .) Pro blocks listed alike)"
