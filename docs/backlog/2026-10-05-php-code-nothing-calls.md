# Some PHP code has no caller, and two settings describe features that don't exist

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`git grep` across `includes`, `apps`, `packages` and `tests` shows each of these unused, or doing nothing:

- `LayoutMarkupReader::order_modes()` has no caller and returns `[]`; its docblock says no block declares an order mode yet.
- `Layout::META_SELF_TEST` (`includes/Models/Layout.php`) is never read or written. The Settings copy still mentions a self-test: `apps/admin/src/pages/settings/components/AdvancedTab.tsx` ("Records render errors and self-test runs.", "Only turn this on if the self-test reports missing scripts") and `apps/admin/src/pages/settings/components/GeneralTab.tsx` ("…and self-test results"). No self-test exists.
- `LayoutQuery::any()` has no caller.
- `BaseRestController::error_unauthorized()` and `BaseRestController::error_forbidden()` have no caller.
- `ActDeact::deactivate()` and `ActDeact::single_activate()` are empty, yet `yaycheckout.php` registers `activate()` and `deactivate()` as hooks. On a network, `ActDeact::activate()` loops over every site with `switch_to_blog()` to call `single_activate()`, which does nothing.
- `general.share_usage_data` is in `Models\Settings::schema()` and saved from the General tab, but no PHP reads it. The toggle says it shares "Which blocks you use, WooCommerce and theme versions, and self-test results", with a "What is sent" link. Nothing is sent.

`BaseRestController::exec_write()` is unused on purpose, and the public filters are extension points, so neither is listed.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

Unused code reads as a contract: a reader keeps `order_modes()` in step, or looks for the self-test the copy promises. The usage-data toggle is a privacy statement in the merchant's settings: a merchant who turns it on is told data is shared when nothing is.

## Suggested fix

Delete the unused members and the `switch_to_blog()` loop, and the hooks if nothing is left for them to do. Drop the self-test from the Settings copy until one exists. Remove the usage-data toggle and `share_usage_data` until data is sent; `Settings::from_option()` walks the schema, so the stored key stops being read. If the team builds the self-test or the sending instead, change those boxes.

## Done when

- [ ] `git grep -n 'order_modes' -- includes apps packages tests` prints nothing.
- [ ] `git grep -n 'META_SELF_TEST' -- includes apps packages tests` prints nothing.
- [ ] `git grep -n -i 'self-test' -- apps/admin/src` prints nothing.
- [ ] `includes/Models/LayoutQuery.php` has no `any()`.
- [ ] `includes/Controllers/BaseRestController.php` has no `error_unauthorized()`.
- [ ] `includes/Controllers/BaseRestController.php` has no `error_forbidden()`.
- [ ] `includes/Engine/ActDeact.php` calls no `switch_to_blog()`.
- [ ] `yaycheckout.php` binds no activation or deactivation hook to an empty method.
- [ ] `git grep -n 'share_usage_data\|shareUsageData' -- includes apps packages tests` prints nothing.
