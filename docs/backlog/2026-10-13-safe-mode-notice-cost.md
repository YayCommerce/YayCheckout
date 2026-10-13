# The safe mode admin notice runs every checkout health rule on every wp-admin screen

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** /yay-review of the safe mode fallback (`docs/plans/2026-10-13-safe-mode-fallback.md`), 2026-10-13, branch `alan/feat/safe-mode`, PR #114. Kept out of that PR to keep it to the fallback itself.

## Today

- `Engine\Admin\SafeModeNotice::print_notice()` runs on `admin_notices` for every user with `LayoutPostType::CAPABILITY`, on every admin screen.
- It calls `CheckoutHealth::reported_problems()`: the active layouts query, `publish_error()` on each layout's markup, the active-plugin scan and `WC()->payment_gateways()->payment_gateways()`.

## Why it matters

Every admin page load, including Posts, Media and Users, pays for a check whose answer changes only when a layout, a plugin, a gateway or the catalogue changes.

## Suggested fix

Show the notice only on WooCommerce screens, the dashboard and the plugins screen (`get_current_screen()`), or cache `reported_problems()` in a short transient that layout saves, activation changes, `activated_plugin`/`deactivated_plugin`, gateway settings saves and breaker writes clear.

## Done when

- [ ] Loading `edit.php` (Posts) does not call `CheckoutHealth::reported_problems()`, or reads it from a cache.
