---
id: BL-1
title: Let the PHPUnit suite run from a git worktree
kind: deferred
status: open
from: 
affects: []
default: 
decision:
created: 2026-11-12
---
# BL-1 Let the PHPUnit suite run from a git worktree

`/yay:implement` and `/yay:fix` build in `.claude/worktrees/<slug>/`, but the suite can't run there:
- `tests/wp-tests-config.php` defaults `ABSPATH` to `dirname( __DIR__, 4 )`, which is the plugin root from a worktree;
- `tests/bootstrap.php` requires `dirname( __DIR__, 2 ) . '/woocommerce/woocommerce.php'` with no override.

Fix: read the WooCommerce path from an env var (e.g. `WP_TESTS_WC_DIR`), and when the default paths don't exist, find the site by walking up to the first `wp-load.php`. Then `composer test` runs the same from the main checkout and from any worktree. Found while installing the yay harness ([known-issues.md](../knowledge/known-issues.md#tests-dont-run-from-a-worktree)).
