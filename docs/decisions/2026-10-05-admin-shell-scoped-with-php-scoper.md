# Bundle admin-shell, scoped with php-scoper

**Purpose:** why YayCheckout draws the shared YayCommerce admin through the admin-shell package, and why it ships a scoped copy of it.

**Update when:** never edit it. Supersede it with a new record if the plugin stops using admin-shell or stops scoping it.

## Status

Accepted, 2026-10-05, by Trace Bui, who confirmed the reasons. Backfilled: the decision was made on 2026-08-28, in `9b620fe`, the plugin's initial backbone.

## Context

- YayCommerce plugins share one admin menu and its common pages. The private Composer package yaycommerce/admin-shell provides them, so no plugin builds its own, and every YayCommerce plugin uses it the same way.
- WordPress plugins can't share a Composer dependency: each ships its own copy. PHP declares a class name only once, so when two active plugins bundle the same unscoped classes, the first copy loaded wins, and the other plugin runs against a copy it wasn't built with.
- The package is built to be scoped: each plugin runs php-scoper with its own prefix, and when several copies are loaded, the newest one draws the shared menu.

## Decision

- YayCheckout requires admin-shell through Composer, and ships only a copy scoped under the `YayCheckoutScoped` prefix (`scoper.inc.php`). The copy is generated into `vendor-prefixed/` and loads through Composer's classmap.
- `YayCheckoutPluginAdapter.php` stays global and unscoped. It tells the shell about this plugin, and it sees the real WordPress classes.
- The package's own scripts do the scoping, run by `./scripts/run.sh dev` and `release` and by `composer shell-update`. [architecture.md](../architecture.md#admin-shell-sync-and-scoping) has the steps.

## Consequences

- YayCheckout runs beside any other YayCommerce plugin, whatever admin-shell version each one bundles.
- `vendor-prefixed/` is generated and gitignored, so a fresh clone can't install until the sync has run once: see [known-issues.md](../known-issues.md).
- The sync updates dependencies and may rewrite `composer.json` and `scoper.inc.php`, and a lasting change to `scoper.inc.php` belongs in admin-shell's template.
- The tooling needs a newer PHP than the product: php-scoper needs PHP 8.2 or later, while the plugin must still run on 7.4.
- Every global name the shell uses, such as a WordPress function, class or constant, has to be excluded in `scoper.inc.php`. A name that isn't excluded gets the prefix and no longer reaches WordPress.
