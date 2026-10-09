# Order Summary Items Options Implementation Plan

> **For agentic workers:** execute phases in order. Read this file, then the current phase file. Do not start a phase until the previous phase's exit criterion has been verified and `/yayplancheck` has passed on it.

**Status:** done 2026-10-09
**Goal:** Give the Order Summary Items block merchant controls: a header-row toggle, style supports with spacing between items, thumbnails, product-detail options, and a quantity shown inline, as a badge, or as a stepper the shopper can change.
**Spec:** none, planned from the request
**Architecture:**
- **One builder, attributes in:** `CheckoutFragments::order_items_html()` stays the single builder for the first paint and the `update_order_review` fragment. It takes the block's attributes, normalised once by `order_items_options()`, so every option survives an AJAX refresh. It prints divs instead of a table, because a thumbnail, a badge and a stepper don't fit a two-column table.
- **Styles on the wrapper:** the style supports land on the block wrapper, which sits outside the fragment and is never replaced. Block spacing becomes the gap between rows. Core doesn't print that gap without layout support, so Steps' existing gap code moves into one shared helper that both blocks call, instead of being copied.
- **Stepper writes through our own wc-ajax action:** WooCommerce's checkout has no endpoint that sets a cart item's quantity. A new `wc_ajax_yaycheckout_cart_item_quantity` action applies the change with WooCommerce's own cart rules, and the view script then fires `update_checkout`, so every fragment (items, totals, shipping, payment) refreshes through the existing path.
- **Why three phases:** each one ships on its own and is visible to a merchant. Phase 1 (layout and styling) is the base the other two print into, and Phase 3 is the only one that writes to the cart, so it is isolated for review.

**Touches:**
- PHP: `includes/Renderer/CheckoutFragments.php`, new `includes/Renderer/BlockGap.php`, new `includes/Renderer/CartItemQuantity.php`, `includes/Blocks/BlockRegistry.php`, `includes/YayCheckout.php`
- Blocks: `packages/blocks/src/order-summary-items/{block.json,edit.tsx,index.ts,render.php}`, new `packages/blocks/src/order-summary-items/{style.css,editor.css}`, `packages/blocks/src/steps/{render.php,edit.tsx}`, new `packages/blocks/src/shared/block-gap.ts`
- Storefront assets: `assets/css/checkout-base.css`, new `assets/js/checkout-order-summary-items.js`
- Tests: new `tests/Integration/Renderer/CartItemQuantityTest.php`
- Docs: `docs/architecture.md`, `.claude/rules/php-standards.md`
- Shared code:
  - **`packages/blocks/src/shared/block-gap.ts` (shared):** consumed by `steps/edit.tsx` and `order-summary-items/edit.tsx`. `packages/blocks/src/shared/` is also the SPA's `@blocks-shared`, but the SPA imports nothing from this new file.
  - **`includes/Renderer/BlockGap.php` (shared):** consumed by `steps/render.php` and `CheckoutFragments::print_summary_block()`.
  - **`CheckoutFragments::fragments()` and `summary_block_fragments()` (shared):** both Items and Totals go through them. Totals' builder gains an unused `$attributes` parameter and is otherwise unchanged.

**Tests:** `tests/Integration/Renderer/CartItemQuantityTest.php`, suite `Integration` (`composer test`). It guards `CartItemQuantity::apply()`, the stepper's cart write: an unknown key is refused, 0 removes the item, a quantity above the product's maximum is refused, a sold-individually item can't go above 1, and a `woocommerce_update_cart_validation` veto leaves the quantity unchanged. Everything else is display and gets an exact manual check.

## Requirements

- **Goal:** In the layout editor, a merchant can hide the Items header row, style the block and the spacing between items, show thumbnails, choose which product details to show, and choose whether the quantity is shown inline, as a badge on the thumbnail, or as a stepper. On the storefront, a shopper using the stepper changes or removes items in the cart without leaving checkout.
- **Out of scope:**
  - Order Summary Totals, which stays a table, and Order Summary's legacy fallback (`woocommerce_order_review()` for a layout without inner blocks).
  - The Order Summary heading ("Your order"), which has its own `label` attribute.
  - The Design screen and its tokens.
  - Per-element style controls (separate colours for the header, name, price, badge or stepper).
  - Quantity changes from WooCommerce's Cart and Checkout *blocks* (Store API). This plan covers the classic checkout YayCheckout renders.
- **Acceptance criteria:**
  1. **No table:** the Items fragment root is `<div class="yaycheckout-order-summary-items">`. It holds no `<table>` except the extension-hook wrappers from criterion 7.
  2. **Header row ("label"):** `showLabels` (boolean, default `true`) shows a header row above the items. `productLabel` and `subtotalLabel` (strings, no default) set its two words. A blank one falls back to WooCommerce's translated "Product" or "Subtotal".
  3. **Styling:** the block supports colour (text, background), typography (the same set as `field-value`), border (colour, radius, style, width) and spacing (margin, padding, blockGap), all on the block wrapper. Block spacing is the gap between the header, the rows and the hook wrappers. When it is unset, the gap is `12px`. Global Styles and the Tablet/Mobile viewport values (WP 7.1+) apply as they do on Steps, and Steps prints exactly what it printed before.
  4. **Thumbnails:** `showThumbnails` (boolean, default `true`) shows a 48px square image, filtered by `woocommerce_cart_item_thumbnail`, at the start of each row. When it is off, the row has no image element.
  5. **Product details:** `itemData` (`list` default, `inline`, `hidden`) shows WooCommerce's item data (variation attributes, add-on data) as today's stacked list, on one line separated by commas, or not at all. `showSku`, `showUnitPrice` and `showShortDescription` (booleans, default `false`) add, under the name and in this order after the item data: the SKU (`SKU: X`, only when the product has one), the unit price (`$10.00 each`, through `woocommerce_cart_item_price`), and the short description (tags stripped, trimmed to 20 words; a variation without one uses its parent's).
  6. **Quantity:** `quantityDisplay` (`inline` default, `badge`, `stepper`).
     - `inline` is today's `× N` after the name, through `woocommerce_checkout_cart_item_quantity`.
     - `badge` prints N in a circle on the thumbnail's top-end corner, for every quantity including 1. With thumbnails off, it prints `inline` instead.
     - `stepper` prints − / input / + plus a remove control, through `woocommerce_cart_item_quantity` and `woocommerce_cart_item_remove_link`, as WooCommerce's `cart/cart.php` does. A change applies WooCommerce's cart rules (maximum purchase quantity, sold individually, `woocommerce_update_cart_validation`), shows a refused change as a message under the items, and refreshes the whole checkout. Removing the last item sends the shopper to the cart page. A sold-individually item shows `inline` plus the remove control.
  7. **Extension hooks:** `woocommerce_review_order_before_cart_contents` and `woocommerce_review_order_after_cart_contents` still fire, before and after the rows. Each is buffered. When its output is non-empty after `trim()`, it prints inside `<table class="shop_table yaycheckout-order-summary-items__hook"><tbody>…</tbody></table>`. Empty output prints nothing. Every WooCommerce filter the table version applied still applies.
  8. **AJAX:** after an `update_order_review` refresh, every option above keeps the merchant's setting, and the wrapper keeps its styles and gap.
  9. **End to end:** after `pnpm build:blocks`, in the active layout, set Items to header off, thumbnails on, quantity `stepper`, item data `inline`, SKU on, a background colour, 16px padding, a 1px border and a 24px block spacing. On the storefront, as a logged-out shopper with two products in the cart (one a variation with stock 3), the Items area shows exactly that. Pressing + on the variation raises its quantity and the subtotal, total and shipping refresh. Pressing + at 3 is not possible. Removing the other product leaves one row, and the order places with the new quantity.
- **Decided with the user:**
  - **Earlier plan:** replace `docs/plans/2026-10-09-order-summary-items-layout.md` (untracked, never started) with this plan. Its decisions on the header row, the hook wrappers and the thumbnails carry over.
  - **"Adjustable" quantity:** display modes plus a shopper-facing stepper that changes the cart, with a remove control.
  - **Product details:** an item-data layout (list, inline, hidden) and toggles for SKU, unit price and short description.
  - **Tests:** only `CartItemQuantityTest` (see `**Tests:**`).
  - **Assumed, not asked:**
    - "Label" means the "Product / Subtotal" header row, as the earlier plan's user decision said.
    - `showThumbnails` defaults to on, so existing layouts gain thumbnails on update. Flip it to `false` if existing checkouts should keep today's look.
    - Everything ships in Free and Pro alike.
    - The badge and the stepper take their colours from the text colour and the checkout's tokens, with no controls of their own.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types, and none of the items in `.claude/rules/php-standards.md` ("PHP 7.4, beyond hard rule 4").
- Free and Pro both ship it: no `Plan::is_pro()` check, and no reference to `YayCheckout\Pro` in `includes/`.
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/<block>/` and run `pnpm build:blocks`.
- Blocks are dynamic. Items' `save` stays null (`registerDynamicBlock`), so every new attribute needs a default on the PHP side (`order_items_options()`), not a block deprecation.
- The Items element must not carry the class `woocommerce-checkout-review-order-table`.
- A storefront hook stands down when `CheckoutPage::get_instance()->layout()` is null.
- Data printed for scripts keeps camelCase keys; JSON on the wire is snake_case (`docs/conventions.md`, Wire casing).
- Comments follow `docs/conventions.md` (Comments): no doc pointers, and no plan or feature IDs.
- Run from the repo root on Node 22 (`nvm use`), with pnpm only.
- `composer lint -- <files>` must be clean for every changed PHP file. Nothing type-checks the blocks' TypeScript, so `pnpm build:blocks` is the check there.
- **Commits:** Conventional Commits, ending with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`. Stage files by name, and leave the unrelated `composer.json` change unstaged. Branch from `dev/edd` as `<name>/feat/order-summary-items`. Ask the user for the name before the first commit. Commit only when the user asks.

## Review focus

- **Attributes during AJAX:** `print_summary_block()` gets `$block->attributes` with the block.json defaults filled in, while the AJAX fragment gets the raw parsed `attrs`, where an untouched option is missing. `order_items_options()` must give a missing key the same default block.json gives it, or the header and thumbnails disappear on the first refresh. Phase 1 Task 2 Step 6 checks this.
- **Hook markup:** an extension that echoes `<tr><td>…</td></tr>` into the cart-contents hooks must render inside its wrapper table, not as loose text. With nothing hooked, there's no wrapper and no empty gap. Phase 1 Task 2 Step 6 checks this.
- **Gap selector across AJAX:** the gap class sits on the wrapper, and the rule targets the fragment root as its child. A rule written on the fragment root itself would be lost when `checkout.js` replaces it. Phase 1 Task 3 Step 4 checks this.
- **Unsafe gap value:** a `blockGap` such as `1px;}body{display:none` is dropped, as `wp_sanitize_block_gap_value()` and `gapCSS()` drop it for Steps today. Phase 1 Task 1 Step 4 checks this.
- **Steps regression:** Steps' gap code moves. Its stylesheet default (`.yaycheckout-steps`) must still lose to the printed rule. Phase 1 Task 1 Step 4 checks this.
- **Badge without a thumbnail:** `badge` with `showThumbnails` off prints `inline`, so the quantity never vanishes. Phase 2 Task 2 checks this.
- **Short description markup:** a short description containing HTML, shortcodes or a script tag prints as plain, escaped text. Phase 2 Task 1 checks this.
- **Stepper inside the checkout form:** pressing Enter in the quantity input must not place the order. The input has no `name`, so it's never posted with the checkout, and every button is `type="button"`. Phase 3 Task 2 checks this.
- **Bundles and composites:** a plugin that filters `woocommerce_cart_item_quantity` or `woocommerce_cart_item_remove_link` to lock a child item gets its filtered output, and the script ignores rows without our markup. Phase 3 Task 1 checks this.
- **Last item removed:** the shopper lands on the cart page, not WooCommerce's "session has expired" notice from `update_order_review` on an empty cart. Phase 3 Task 2 checks this.
- **Guest shoppers and preview links:** the nonce verifies for a logged-out shopper. In a preview, the request carries the preview token through `wc_checkout_params.wc_ajax_url`, so the sample items aren't stripped. Phase 3 Task 2 checks this.
- **Double clicks:** a click while a request is pending is ignored, and the request sends the target quantity, not a delta, so a retry can't apply a change twice. Phase 3 Task 2 checks this.

## Phases

| Phase | Delivers (user-visible) | Exit criterion (exact check) | Status |
|---|---|---|---|
| 1 | [Layout and styling](phase-1-layout-and-styling.md): Items as divs, header-row toggle and words, thumbnails, colour, typography, border, padding, margin and block spacing | Phase 1 Task 3 Step 4 passes: the styled Items area with the header off and thumbnails on matches on the canvas and the storefront, before and after a billing-country change, and the order places | done |
| 2 | [Details and quantity display](phase-2-details-and-quantity-display.md): item-data layout, SKU, unit price, short description, and quantity inline or as a badge | Phase 2 Task 3's check passes: every detail option and both quantity modes match on the canvas and the storefront, before and after a billing-country change | done |
| 3 | [Quantity stepper](phase-3-quantity-stepper.md): shoppers change or remove items on the checkout | `composer test -- --filter CartItemQuantityTest` passes, and acceptance criterion 9 passes on the storefront | done |

## Interface contract

**Block attributes on `yaycheckout/order-summary-items`** (`block.json`). Every one is optional in stored markup:

| Attribute | Type | Default | Added in |
|---|---|---|---|
| `showLabels` | boolean | `true` | Phase 1 Task 2 |
| `productLabel` | string | none | Phase 1 Task 2 |
| `subtotalLabel` | string | none | Phase 1 Task 2 |
| `showThumbnails` | boolean | `true` | Phase 1 Task 2 |
| `itemData` | string, enum `list`, `inline`, `hidden` | `list` | Phase 2 Task 1 |
| `showSku` | boolean | `false` | Phase 2 Task 1 |
| `showUnitPrice` | boolean | `false` | Phase 2 Task 1 |
| `showShortDescription` | boolean | `false` | Phase 2 Task 1 |
| `quantityDisplay` | string, enum `inline`, `badge` (Phase 2 Task 2), plus `stepper` (Phase 3 Task 1) | `inline` | Phase 2 Task 2 |

**PHP:**

- `CheckoutFragments::order_items_options( array $attributes ): array`. Public static. Produced by Phase 1 Task 2 with the first four keys, and each later task adds its keys. It returns the snake_case keys `show_labels`, `product_label`, `subtotal_label`, `show_thumbnails`, `item_data`, `show_sku`, `show_unit_price`, `show_short_description` and `quantity_display`. A missing or wrong-typed attribute gets the default from the table above. An enum value outside its list gets its default, and the labels go through `LayoutMarkupReader::plain_text()` with WooCommerce's translated "Product" and "Subtotal" as fallbacks.
- `CheckoutFragments::order_items_html( array $attributes = [] ): string`: produced by Phase 1 Task 2. It reads only `order_items_options( $attributes )`.
- `CheckoutFragments::order_totals_html( array $attributes = [] ): string`: produced by Phase 1 Task 2. The parameter is unused.
- `CheckoutFragments::summary_block_fragments()` returns `array<int, array{0: string, 1: string, 2: array}>`: selector, builder, and the block's parsed `attrs` (`[]` when missing). Produced by Phase 1 Task 2.
- `YayCheckout\Renderer\BlockGap::add_rules( array $style, string $block_name, string $selector ): bool`: produced by Phase 1 Task 1, consumed by Phase 1 Task 3.
- `YayCheckout\Renderer\CartItemQuantity` (Phase 3 Task 1):
  - `public const AJAX_ACTION = 'yaycheckout_cart_item_quantity'`, the wc-ajax endpoint `?wc-ajax=yaycheckout_cart_item_quantity`.
  - `public const NONCE_ACTION = 'woocommerce-yaycheckout-cart-item-quantity'`. The `woocommerce` prefix makes WooCommerce tie a guest's nonce to their session (decided in review).
  - `public static function apply( \WC_Cart $cart, string $cart_item_key, int $quantity ): ?string`: null when applied, else a translated message for the shopper.
  - Request: POST `security`, `cart_item_key`, `quantity`. Response: `wp_send_json_success( [ 'cart_empty' => bool, 'redirect' => string ] )`, where `redirect` is `wc_get_cart_url()` when the cart is empty and `''` otherwise, or `wp_send_json_error( [ 'message' => string ] )`.
- `BlockRegistry::ORDER_SUMMARY_ITEMS_SCRIPT = 'yaycheckout-order-summary-items'`: a handle for `assets/js/checkout-order-summary-items.js`, with dependencies `jquery` and `wc-checkout`. Produced by Phase 3 Task 2.

**TypeScript** (`packages/blocks/src/shared/block-gap.ts`, Phase 1 Task 1):

- `export interface BlockGapStyle { spacing?: { blockGap?: unknown } }`
- `export type BlockGapStyles = BlockGapStyle & Record< string, BlockGapStyle | undefined >`
- `export interface ViewportSettings { mobile?: string; tablet?: string }`
- `export function blockGapCSS( selector: string, style: BlockGapStyles | undefined, viewport: ViewportSettings | undefined ): string`

**Markup:** the fragment root and every class the CSS, the editor mirror and the view script rely on. Phase 1 produces the base, Phase 2 adds the `product-*` details and the badge, and Phase 3 adds the stepper and the notices:

```html
<!-- Block wrapper, from get_block_wrapper_attributes(); never replaced by AJAX. -->
<div class="wp-block-yaycheckout-order-summary-items [yaycheckout-order-summary-items-gap-N]"
     [data-yaycheckout-cart-nonce="…" data-error-text="…"]>                <!-- stepper only -->
  <div class="yaycheckout-order-summary-items [yaycheckout-order-summary-items--item-data-inline]">  <!-- fragment root -->
    <div class="yaycheckout-order-summary-items__header"><span class="product-name">Product</span><span class="product-total">Subtotal</span></div>
    <table class="shop_table yaycheckout-order-summary-items__hook"><tbody><!-- before_cart_contents --></tbody></table>
    <div class="cart_item yaycheckout-order-summary-items__item">          <!-- woocommerce_cart_item_class, then our class -->
      <div class="product-thumbnail"><img …><span class="yaycheckout-order-summary-items__badge">2</span></div>
      <div class="product-name">
        Name&nbsp;<strong class="product-quantity">×&nbsp;2</strong>        <!-- inline mode -->
        <dl class="variation">…</dl>                                       <!-- itemData list|inline -->
        <div class="product-sku">SKU: ABC</div>
        <div class="product-unit-price">$10.00 each</div>
        <div class="product-short-description">…</div>
        <div class="yaycheckout-qty">                                      <!-- stepper mode -->
          <button type="button" class="yaycheckout-qty__button" data-step="-1" aria-label="…">−</button>
          <input type="number" class="yaycheckout-qty__input" value="2" min="1" max="3" step="1" inputmode="numeric" data-cart-item-key="KEY" aria-label="…">
          <button type="button" class="yaycheckout-qty__button" data-step="1" aria-label="…">+</button>
        </div>
        <a href="…remove url…" class="remove yaycheckout-order-summary-items__remove" data-cart-item-key="KEY" aria-label="…">×</a>
      </div>
      <div class="product-total">$20.00</div>
    </div>
    <table class="shop_table yaycheckout-order-summary-items__hook"><tbody><!-- after_cart_contents --></tbody></table>
  </div>
  <div class="yaycheckout-order-summary-items__notices" aria-live="polite"></div>  <!-- stepper only -->
</div>
```
