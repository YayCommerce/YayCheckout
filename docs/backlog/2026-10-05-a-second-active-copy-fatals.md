# A second active copy of the plugin fatals instead of deactivating itself

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 code audit for the harness renewal (`docs/plans/2026-10-05-harness-renewal.md`), on `trace/docs/harness-renewal`.

## Today

When `YayCheckout\plugin_init` already exists, `yaycheckout.php` loads `includes/Fallback.php` and schedules its own deactivation on `admin_init`, but doesn't return. The rest of the file still runs, and it requires its own `vendor/autoload.php` and `YayCheckoutPluginAdapter.php`, which declare global classes with no `class_exists` guard. With Free and Pro both active, the second copy declares them again. The comment in `cmd_release` (`scripts/run.sh`) says this case is handled. Found by reading the code; not yet reproduced.

## Why it matters

Pro installs into its own folder, so a merchant who upgrades from Free has both copies installed, and activating both is a likely first step. A fatal error there takes the whole site down, not just the plugin.

## Suggested fix

Return from `yaycheckout.php` right after scheduling the deactivation, so nothing else in the second copy runs. Check that the notice from `includes/Fallback.php` still shows.

## Done when

- [ ] With `yaycheckout/` and `yaycheckout-pro/` both active, wp-admin loads without a fatal error and shows the duplicate notice.
- [ ] After the next admin page load, only one copy is active.
