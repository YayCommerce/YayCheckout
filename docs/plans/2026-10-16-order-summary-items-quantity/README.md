# Order Summary Items: quantity, links and stepper style Implementation Plan

> **For agentic workers:** execute phases in order. Read this file, then the current phase file. Do not start a phase until the previous phase's exit criterion has been verified and `/yayplancheck` has passed on it. Each phase is its own branch and PR into `dev/edd`.

**Status:** done 2026-10-16
**Goal:** Order Summary Items gets a clean quantity model (Show as Inline, Stepper or Hidden, plus a separate badge), a stepper that always sits on its own row, optional product links, and a stepper look merchants can change.
**Spec:** none, planned from the request
**Architecture:** Every change stays inside the Items block: its attributes (`block.json`), its editor mirror (`edit.tsx`), its storefront markup (`CheckoutFragments::order_items_html()` and helpers) and its CSS (`style.css`). Look options follow the badge's existing pattern: settings become `--yaycheckout-*` custom properties on the block wrapper (kept across AJAX refreshes) plus a modifier class on the root (rebuilt on each refresh from the same attributes), so no template needs custom CSS. There are no shipped users, so attributes are renamed outright with no migration.
**Touches:** `packages/blocks/src/order-summary-items/` (`block.json`, `edit.tsx`, `style.css`), `includes/Renderer/CheckoutFragments.php` (Items methods only), `templates/layouts/*/markup.php` and `templates/layouts/pro/*/markup.php` that place the Items block, `docs/architecture.md` (Quantity stepper paragraph). `assets/js/checkout-order-summary-items.js` (shared by every layout with a stepper) is not changed, and its selectors (`.yaycheckout-qty`, `.yaycheckout-qty__button`, `.yaycheckout-qty__input`, `.yaycheckout-order-summary-items__remove`, `.yaycheckout-order-summary-items__notices`) must keep working.
**Tests:** none. CLAUDE.md's test rule covers publish rules, layout assignment, required fields and import review; these are display options and CSS, which PHPUnit can't reproduce cheaply. Every task ends in an exact manual check.

## Requirements

- **Goal:** In the Items block, the merchant picks how the quantity shows (Inline text, Stepper or Hidden), separately adds a badge on the thumbnail, can make the image and name link to the product, and can restyle the stepper; the stepper never sits on the product-name line.
- **Out of scope:** the cart AJAX endpoint (`Renderer\CartItemQuantity`) and the stepper script's behaviour; the remove link's look; the Order Summary Items header row; `preview.html` files and the HTML mock.
- **Acceptance criteria:**
  1. With the stepper on and SKU, unit price and short description all off, the stepper and remove link sit on their own row below the product name, in the editor and on the storefront, exactly as when any of those details is on.
  2. Settings → Quantity has **Show as: Inline | Stepper | Hidden** (attribute `quantityDisplay`, default `inline`) and a **Badge on thumbnail** toggle (attribute `showBadge`, default `false`). `showStepper` no longer exists. Hidden prints no `× N`, no stepper and no remove link. The badge is extra: it never hides the inline `× N` text or the stepper, and it works with any Show as. With thumbnails off, the badge toggle is hidden and no badge prints.
  3. Settings → Product has **Link image** and **Link name** toggles (`linkImage`, `linkName`, default `false`). Each links to the product page in a new tab (`target="_blank" rel="noopener"`); a product with no public permalink prints unlinked.
  4. Styles → **Quantity stepper** (shown while Show as is Stepper) has Shape (Boxed | Round), Border colour and Size (20–40px). Boxed with nothing set looks as today; Round matches the Editorial fashion mock (separate 24px circular buttons, plain number between).
  5. End to end: Editorial fashion and Upsell & cross-sell, created as new layouts, show their stepper (Round and Boxed) on its own row in the editor and on the storefront at 1280px and 375px; a + click updates the quantity and totals, and after the refresh the row keeps its shape, colours, links and badge.
- **Accepted differences:** none. Classic two-column, Split express, Accordion one-page and Multi-step wizard set Show as Hidden plus `showBadge`, so they look as today.
- **Decided with the user:**
  - Badge combines as extra: Inline + badge prints both; Stepper + badge prints both; Hidden + badge prints only the badge.
  - Show as has a third choice, Hidden, so a layout can show the quantity only on the badge, or not at all.
  - Product links open in a new tab, with no setting for it.
  - Stepper look: a Shape preset (Boxed, Round) plus a small Styles panel with Border colour and Size, like the badge panel.
  - Split into one branch per phase.

## Global constraints

- Branch from `dev/edd` as `trace/<type>/<topic>`; one PR per phase into `dev/edd`, squash merge. Commit only when the user asks.
- Start Phase 1 only after `trace/refactor/templates-without-custom-css` has merged into `dev/edd`: it edits `CheckoutFragments.php` and `templates/layouts/editorial-fashion/markup.php`, which every phase here touches.
- Never hand-edit `assets/dist/` or any `preview.html`. Edit `packages/blocks/src/order-summary-items/`, then run `pnpm build:blocks`.
- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union/`mixed`/`static` types, `catch` without a variable, or trailing comma after the last parameter of a declaration.
- `CheckoutFragments::order_items_options()` defaults must equal `block.json` defaults: during an AJAX refresh an untouched attribute is missing from the parsed layout, so a mismatch flips the look after the first update.
- `edit.tsx` mirrors `order_items_html()`; every markup or class change lands in both, in the same commit.
- Commit messages are Conventional Commits, `type(scope): summary`, ending with the `Co-Authored-By:` and `Claude-Session:` trailers CLAUDE.md and the session require.
- Lint changed PHP with `composer lint -- includes/Renderer/CheckoutFragments.php`; expected: no errors.

## Review focus

- **AJAX refresh keeps the look** (every phase): after a stepper click, `update_checkout` replaces the root; a Round stepper, the badge, the links and the item-data class must survive. Owned by each phase's storefront check, which clicks + and re-checks after the refresh.
- **Locked quantity** (Phase 1 Task 1): an extension that filters `woocommerce_cart_item_quantity` to plain text puts that text in the controls row; it must sit on its own row, not beside the name. Checked in Phase 1 Task 1 Step 3 with a temporary `add_filter` in a mu-plugin.
- **Stepper-ineligible item** (Phase 2 Task 1): a sold-individually product in Stepper mode gets no stepper and prints `× 1` inline, plus its remove link. Checked in Phase 2 Task 1 Step 4.
- **Hidden product** (Phase 3 Task 1): a product with catalogue visibility Hidden has no public permalink, so neither its image nor its name links. Checked in Phase 3 Task 1 Step 3.
- **Name filters that already link** (Phase 3 Task 1): the name is wrapped in the link before `woocommerce_cart_item_name` runs, as WooCommerce's `cart/cart.php` does, so an extension gets the same input it gets on the cart page. No step; review the diff for the order.
- **Contrast on dark panels** (Phase 4 Task 1): a stepper on a coloured block with no Border colour set uses `currentColor` at 25%, so it follows the block's text colour (Editorial's white). Checked in Phase 4 Task 2 Step 3.

## Phases

| Phase | Branch | Delivers (user-visible) | Exit criterion (exact check) | Status |
|---|---|---|---|---|
| 1 | `trace/fix/order-items-stepper-row` | The stepper and remove link always sit on their own row | Phase 1 Task 1 Step 3 passes on the Upsell & cross-sell layout | done |
| 2 | `trace/feat/order-items-quantity-display` | Show as Inline / Stepper / Hidden, plus a separate badge toggle | Phase 2 Task 2 Step 3 passes on all eight templates | done |
| 3 | `trace/feat/order-items-product-links` | Link image and Link name toggles | Phase 3 Task 1 Step 3 passes | done |
| 4 | `trace/feat/order-items-stepper-style` | Stepper Shape, Border colour and Size; Editorial uses Round | Acceptance criterion 5, run as Phase 4 Task 2 Step 3 | done |

## Interface contract

Attributes of `yaycheckout/order-summary-items` (`block.json`), with their PHP option keys in `CheckoutFragments::order_items_options()`:

| Attribute | Type and default | Option key | Phase |
|---|---|---|---|
| `quantityDisplay` | `"inline" \| "stepper" \| "hidden"`, default `"inline"` | `quantity_display` | 2 (enum changes from `inline \| badge`) |
| `showBadge` | boolean, default `false` | `show_badge` | 2 |
| `showStepper` | removed | `show_stepper` removed | 2 |
| `linkImage` | boolean, default `false` | `link_image` | 3 |
| `linkName` | boolean, default `false` | `link_name` | 3 |
| `stepperStyle` | `"boxed" \| "round"`, default `"boxed"` | `stepper_style` | 4 |
| `stepperBorderColor` | string, default `""` | read by `order_items_style_vars()` | 4 |
| `stepperSize` | number, no default; clamped to 20–40 | read by `order_items_style_vars()` | 4 |

Markup and CSS names:

- `.yaycheckout-order-summary-items__controls`: the row holding the stepper (or the HTML `woocommerce_cart_item_quantity` returns) and the remove link, inside `.product-name`, after the item details. Produced by Phase 1; Phase 4 styles the stepper inside it.
- `.yaycheckout-order-summary-items__link`: the `<a>` around the thumbnail image and around the name. Produced by Phase 3.
- `.yaycheckout-order-summary-items--stepper-round`: modifier on the root `.yaycheckout-order-summary-items` when `stepper_style` is `round`. Produced by Phase 4.
- Wrapper custom properties: `--yaycheckout-badge-bg`, `--yaycheckout-badge-text`, `--yaycheckout-badge-size` (existing), `--yaycheckout-stepper-border`, `--yaycheckout-stepper-size` (Phase 4).

PHP and TypeScript:

- `CheckoutFragments::order_items_style_vars( array $attributes ): string` (private static): the badge and stepper custom properties joined with `;`, empty when none are set. Phase 4 renames `order_items_badge_style()` to it; `print_summary_block()` is its only caller.
- `itemsStyleVars( attributes: OrderSummaryItemsAttributes )` in `edit.tsx`: the same properties as a React style object. Phase 4 renames `badgeStyle()` to it.
- `CheckoutFragments::product_permalink( \WC_Product $product, array $cart_item, string $cart_item_key ): string` (private static): `apply_filters( 'woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key )`. Produced by Phase 3.
