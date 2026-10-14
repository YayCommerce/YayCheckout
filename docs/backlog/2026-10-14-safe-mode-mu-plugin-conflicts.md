# A must-use plugin the catalogue marks as conflicting is shown but not acted on

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** whole-feature review of the safe mode fallback, 2026-10-14, branch `alan/feat/safe-mode`, PR #114. Kept out of that PR as low risk.

## Today

- `CheckoutHookScanHelper` scans must-use plugins, so the Compatibility page shows a catalogue `conflict` on one.
- `CheckoutHealth::conflicting_plugin()` reads only `active_plugins` (and `active_sitewide_plugins` on multisite), so the storefront ignores it.

## Why it matters

The Compatibility page and the storefront disagree: the merchant sees a Conflict that safe mode never falls back for.

## Suggested fix

Add `get_mu_plugins()` to the slugs `conflicting_plugin()` checks, keyed the way `CheckoutHookScanHelper::owner()` keys must-use plugins.

## Done when

- [ ] A must-use plugin marked `conflict` in the catalogue makes `CheckoutHealth::store_problem()` non-empty.
