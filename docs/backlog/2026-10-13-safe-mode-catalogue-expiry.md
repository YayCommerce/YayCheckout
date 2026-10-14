# A remote conflict stops applying on the storefront when the catalogue transient expires

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** /yay-review of the safe mode fallback (`docs/plans/2026-10-13-safe-mode-fallback.md`), 2026-10-13, branch `alan/feat/safe-mode`, PR #114. Kept out of that PR to keep it to the fallback itself.

## Today

- `CheckoutHealth::store_problem()` reads the catalogue through `CompatibilityCatalogueRepository::cached()`, which never fetches: it returns the `yaycheckout_compatibility_catalogue` transient, or `CompatibilityCatalogue::bundled()` when the transient is gone.
- The bundled catalogue has no `conflict` entries. The remote one is cached for `TTL_REMOTE` (12 hours) and is fetched again only when an admin opens the Compatibility page.

## Why it matters

Once the transient expires and no admin has opened Compatibility, a plugin the remote catalogue marks as conflicting no longer sends shoppers to WooCommerce's checkout. If it expires between a shopper's page render and their submit, the two requests resolve different layouts.

## Suggested fix

Keep the last remote catalogue in a non-expiring option, written by `CompatibilityCatalogueRepository::get()` on each successful fetch, and have `cached()` fall back to it before the bundled one. Refresh it in the background (a daily cron) rather than on a storefront request.

## Done when

- [ ] With the transient deleted, `cached()` returns the last remote catalogue when one was ever fetched.
- [ ] No storefront request calls `wp_remote_get()` for the catalogue.
