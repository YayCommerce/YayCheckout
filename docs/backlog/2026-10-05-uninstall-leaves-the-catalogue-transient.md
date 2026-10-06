# Uninstall leaves the compatibility catalogue's transient behind

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

When the merchant has opted in, `uninstall.php` deletes the options whose names match `LIKE 'yaycheckout\_%'`. `CompatibilityCatalogueRepository` caches the catalogue with `set_transient()` under `Support\CompatibilityCatalogue::TRANSIENT`, `yaycheckout_compatibility_catalogue`. Without a persistent object cache, WordPress stores it as two options, `_transient_yaycheckout_compatibility_catalogue` and `_transient_timeout_yaycheckout_compatibility_catalogue`, and the pattern misses both. The header of `uninstall.php` lists the literals it mirrors, and the transient isn't among them.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

It cleans itself up. The transient lives at most `CompatibilityCatalogue::TTL_REMOTE`, 12 hours, and then WordPress core's daily `wp-includes/option.php` `delete_expired_transients()` removes it. Until then, a merchant who asked for all data to go can still find a `yaycheckout` row.

## Suggested fix

Call `delete_transient( 'yaycheckout_compatibility_catalogue' )` in `uninstall.php`, and add `CompatibilityCatalogue::TRANSIENT` to the literals its header mirrors. A cache isn't the merchant's data, so it can go before the opt-in check.

## Done when

- [ ] `uninstall.php` deletes the `yaycheckout_compatibility_catalogue` transient.
- [ ] The `uninstall.php` header names `CompatibilityCatalogue::TRANSIENT` among the literals it mirrors.
- [ ] After the plugin is deleted from wp-admin, `SELECT option_name FROM wp_options WHERE option_name LIKE '%yaycheckout_compatibility_catalogue%'` returns no rows.
