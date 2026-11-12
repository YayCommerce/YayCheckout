---
id: BS-1
title: Rules v2: cart value conditions and layout priority
status: decided
from: R-1
decision: A, plus billing/shipping country conditions
created: 2026-11-12
---
# BS-1 Rules v2: cart value conditions and layout priority

## Problem
[R-1 Rules v2](../roadmap/sprint-1.md) is the next version of Pro's layout assignment rules. Its acceptance: *"A merchant assigns a layout with the new rule conditions and the storefront serves it, including when two rules match the same cart."* That asks for two things:

1. **New conditions.** Today a rule tests customer role, login status, device, and which products or categories are in the cart (`includes/Enums/ConditionType.php:16-25`). A merchant can't test how big the cart is, so "a short checkout for orders under $50" or "a wholesale layout for 20+ items" isn't possible. These are the most common asks for checkout rules in WooCommerce plugins.
2. **Two rules matching the same cart.** Today the most recently activated layout wins, and nothing tells the merchant. `LayoutAssignment` says so itself: *"The candidates' order stands in for a priority the merchant can't set, and overlapping rules aren't checked, so the first match wins"* (`includes/Services/LayoutAssignment.php:10-13`). The order comes from `LayoutRepository::active_layouts()`, which reverses the stored active ids (`includes/Repositories/LayoutRepository.php:78-80`), and `set_active()` moves an id to the end on every activation (`:343-357`). So the merchant can change which layout wins only by deactivating and reactivating layouts in the right order.

## Constraints
- **Pro only.** Free serves its one active layout and ignores rules (`LayoutRepository::active()`, `:53-57`; `active_ids()` keeps only the last id on Free, `:653-659`). Nothing here may name `YayCheckout\Pro` outside `includes/Pro/` (hard rule 1); new routes live in `includes/Pro/Controllers/`, and the SPA gates UI behind `isPro &&` (hard rule 2).
- **PHP 7.4:** constants, not enums, as `ConditionType` already does.
- **Storage through `LayoutRepository`** (hard rule 4): the active set is `yaycheckout_active_layout`, the rules are `_yaycheckout_rules` meta, and both are written only there.
- **Cart facts are pinned.** Cart conditions are read only on the checkout page load and the pick is pinned for that form's AJAX and submit (`includes/Renderer/LayoutPin.php`, `ConditionType::CART_TYPES`). Cart subtotal and quantity change mid-form too, so they must join `CART_TYPES` and go through the same pin, or the layout would swap under the shopper.
- **The theme is chosen at `setup_theme`, before the cart loads.** `LayoutAssignment::outlook()` reasons three-valued about missing cart facts (`:47-63`); new cart conditions must return "unknown" without the cart, like product conditions do (`:151-152`).
- **Rules shape stays.** Groups ORed, conditions ANDed, `{type, operator, value}` (`Layout::sanitize_rules()`, `includes/Models/Layout.php:134-208`). Export and import carry rules through `sanitize_rules()`, so new types travel for free.
- **No shipped users** yet: no migration of stored rules or of the active order is needed.
- **PHP and TS mirrors:** `ConditionType` is mirrored by `apps/admin/src/pages/layouts/assignment/condition-types.ts` (its own comment says so), and nothing catches drift (hard rule 7).

## Options

### A. Merchant-ordered priority list, plus cart subtotal and quantity conditions
- **How:**
  - *Priority:* the active set's stored order already *is* the priority. Add `LayoutRepository::set_priority( int[] $ids_highest_first )`, which accepts only a reordering of the current active ids and stores them through `store_ids()` (highest last, the order `active_layouts()` already reverses). A Pro route `PUT /layouts/priority` calls it. In the admin, a "Priority" sheet on the Layouts page lists the active layouts top to bottom with move up and move down buttons. Activating a layout still puts it on top, as today.
  - *Conditions:* `cart_subtotal` (value: an amount, operators "at least" and "less than") and `cart_quantity` (value: a whole number, same operators). `CartFacts::context()` adds the subtotal the shopper sees and the item count; both join `CART_TYPES`, so the existing pin and outlook logic handle them unchanged. `Layout::sanitize_rule_value()` gets a `VALUE_NUMBER` kind; the drawer gets a number input.
- **Pros:** no new storage: reuses the option and `store_ids()`. "Top of the list wins" is the model merchants know from shipping zones and tax rates. `pick()`, `outlook()`, `LayoutPin` and `ThemelessCheckout` need no change, because they already walk candidates in order. Free is untouched: it keeps the last id, which is the top of the list.
- **Cons:** one more admin surface (the sheet). Priority isn't part of a layout's export, so an imported layout lands where activation puts it (top). Up/down buttons rather than drag (the SPA has no drag-and-drop library).
- **Risks:** a stale reorder request overwriting a concurrent activation, handled by refusing a list that isn't exactly the current active set.
- **Edition split:** all Pro: the route under `includes/Pro/Controllers/`, the sheet behind `isPro &&`.
- **Size:** M

### B. Per-layout priority number in meta, plus the same conditions
- **How:** a new `_yaycheckout_priority` meta (integer, default 10, like WordPress hook priorities), written by `LayoutRepository::set_priority( $id, $n )` and edited as a number field in the Assignment drawer. `active_layouts()` sorts by priority, then by activation recency. Conditions as in A.
- **Pros:** the priority sits next to the rules in the drawer the merchant already uses. Travels with export/import and duplicate.
- **Cons:** a new meta key in `hydrate_meta()`, `insert_draft()`, export, import and uninstall. Ties are the default case (every layout starts at 10), so recency still decides silently unless the merchant numbers every layout. Numbers are harder to reason about than a list ("is 5 higher or lower than 10?"). Each `active()` call sorts on meta.
- **Edition split:** Pro; the meta is ignored on Free.
- **Size:** M

### C. Most specific rule wins automatically, plus the same conditions
- **How:** when several layouts match, `LayoutAssignment::pick()` scores each by the number of conditions in its matching group and serves the highest; ties fall back to activation recency. No new UI or storage. Conditions as in A.
- **Pros:** smallest change; no new screen.
- **Cons:** opaque: a merchant can't predict or override the winner, and "more conditions" isn't the same as "more specific" (`device is desktop AND role in [customer]` beats `product in [Gift card]`, which is probably the opposite of intent). `outlook()` then has to score unknown groups too, which turns the three-valued walk into a search. Support load: "why is this layout showing?" has no answer on screen.
- **Edition split:** Pro.
- **Size:** S to M

Rejected without a full option: *refusing overlapping rules on save.* Deciding whether two arbitrary AND/OR rule sets can match the same shopper is a satisfiability check over role lists, choices, id sets and now number ranges, and it would forbid the most useful setup (a specific layout plus a catch-all without rules).

## Recommendation
**A. Merchant-ordered priority list, plus cart subtotal and quantity conditions.** The repo already stores an ordered active set and already serves the first match in that order; the only missing piece is letting the merchant set the order and see it. That makes "two rules match the same cart" predictable and visible without new storage, and without touching the pin, outlook or theme logic that took the most care to get right. B adds a meta key across five code paths and still decides ties silently; C is cheaper but leaves the merchant unable to control the outcome, which is the complaint the acceptance line is about.

Subtotal and quantity are the smallest pair of conditions that unlocks the common "small order / big order" layouts, and both are cart facts the existing `CART_TYPES` pin already handles. Coupon and billing or shipping country conditions are deliberately left out: coupons need a new search route, and country is edited inside the form itself, so a pinned layout would ignore the shopper's change.

**Decided (R-1 autopilot sheet):** A, and billing/shipping country ships in v2 too (the user chose it over this recommendation). Condition: country is read from the customer's stored billing/shipping country (falling back to the store's default or geolocated location) when the checkout page loads, is unknown before the customer loads, and the picked layout stays pinned for the form; a mid-form country change does not swap the layout (BL-6). Coupon stays parked under BL-2.

What would change my mind: if merchants need priority to travel in export files between stores (then B), or if product wants country rules in v2 (then country needs its own design, because it changes mid-form).

## Open questions
- Which conditions does "the new rule conditions" mean to product? This brainstorm assumes cart subtotal and cart quantity; coupon and country stay for a later version.
- Should the subtotal compare with or without tax? The recommendation compares the subtotal as the shopper sees it (with tax when the store displays prices with tax).
- Multi-currency stores: the threshold is in the store's base currency; a currency-switcher plugin that converts the cart may need a filter later.
