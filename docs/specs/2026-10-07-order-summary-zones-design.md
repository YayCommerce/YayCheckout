# Order Summary Zones Design

**Status:** accepted 2026-10-07
**Related:** None

## Goal

A merchant can add blocks to two places in the Order Summary block: above the items (below the heading) and between the items and the totals. Both places start empty; the merchant decides whether to use them.

## Context

- `packages/blocks/src/order-summary/` is a leaf block (`label` attribute). `render.php` prints the heading and `woocommerce_order_review()`, i.e. WooCommerce's `checkout/review-order.php`: one `<table class="shop_table woocommerce-checkout-review-order-table">` with items in `<tbody>` and subtotal, coupons, shipping, fees, tax and total in `<tfoot>`.
- On every `update_order_review`, WooCommerce's `checkout.js` replaces every `.woocommerce-checkout-review-order-table` with the fragment of the same key. Anything inside that table is wiped and re-rendered.
- `includes/Renderer/CheckoutFragments.php` already swaps per-layout fragments (`payment`, `shipping_methods`) through `woocommerce_update_order_review_fragments`, keyed by the layout slots from `LayoutMarkupReader::slots()` (`SECTION_SLOTS`).
- Containers with inner blocks: Billing, Shipping, Step (`useInnerBlocksProps`, `useBlocksAllowedExcept` in `packages/blocks/src/shared/allowed-blocks.ts`, `saveNestedBlocks` in `shared/register-dynamic-block.tsx`).
- `templates/default-layout.php:75` places `<!-- wp:yaycheckout/order-summary /-->` with Coupon Form after it.

## Scope

**In:**
- Order Summary becomes a container with two locked children, Items and Totals; the merchant inserts blocks before or between them.
- Items and Totals render their own tables and refresh through their own AJAX fragments.
- Fallback for stored layouts whose Order Summary has no children.
- Default layout uses the nested markup.

**Out:**
- The new visual design in the mock (thumbnails, variation line, quantity stepper).
- A Frequently bought together block.
- A zone inside the totals (between rows).
- Any Pro gating.
- Migrating stored layout markup.
- Changes to the Order Review block.

## Acceptance criteria

1. Given a stored layout with `<!-- wp:yaycheckout/order-summary /-->`, the storefront prints the heading and WooCommerce's `review-order.php` table, the same HTML as before this change.
2. Given that layout opened in the editor, Order Summary gets Order Summary Items and Order Summary Totals as children, and neither can be moved or removed.
3. Given a Paragraph inserted between Items and Totals and published, the storefront prints, in order: heading, items table, the Paragraph, totals table.
4. Given a Coupon Form in a zone with text typed in its input, when the shopper changes shipping method or billing address (triggering `update_order_review`), the items and totals tables update and the Paragraph and the typed coupon text remain.
5. The inserter inside Order Summary offers core blocks and Coupon Form, and does not offer Billing, Shipping, Payment, Steps, Order Review, Place Order, Order Summary Items or Order Summary Totals.
6. A new layout (from `LayoutRepository::default_markup()`) contains Order Summary with both children and empty zones.
7. End-to-end verification: run `pnpm build:blocks`; create a new layout, add a Paragraph above Items and one between Items and Totals, publish; on the storefront with a cart, change the shipping address so shipping cost changes. Both Paragraphs still show, and Total shows the new amount.

## Constraints

- Product PHP runs on PHP 7.4 (CLAUDE.md hard rule 4).
- Free and Pro both ship it; no `Plan::is_pro()` check and no reference to `YayCheckout\Pro`.
- Edit block sources in `packages/blocks/src/`, never `assets/dist/blocks/`.
- The split tables must not carry the class `woocommerce-checkout-review-order-table`, or WooCommerce's fragment replaces each with the full table.
- The split tables fire WooCommerce's hooks at the same positions as `review-order.php`: `woocommerce_review_order_before_cart_contents`, `woocommerce_review_order_after_cart_contents`, `woocommerce_review_order_before_shipping`, `woocommerce_review_order_after_shipping`, `woocommerce_review_order_before_order_total`, `woocommerce_review_order_after_order_total`.

## Approach

**Chosen:** container with two locked children (the WooCommerce Blocks order-summary pattern).

**Architecture:** `yaycheckout/order-summary` keeps its heading (from `label`) and renders its inner blocks. Its template inserts `yaycheckout/order-summary-items` and `yaycheckout/order-summary-totals`, locked against move and remove; `templateLock: false` lets the merchant insert blocks before, between and after them. Each child prints one table whose wrapper class is also its fragment selector, so `update_order_review` replaces only those two tables and the merchant's blocks stay untouched.

**Components:**
- `packages/blocks/src/order-summary/render.php`: heading, then `$content`; when `$block->inner_blocks` is empty, heading plus `woocommerce_order_review()` as today.
- `packages/blocks/src/order-summary/edit.tsx`: Label control; heading plus `useInnerBlocksProps` with `template: [ [ 'yaycheckout/order-summary-items', { lock: { move: true, remove: true } } ], [ 'yaycheckout/order-summary-totals', { lock: { move: true, remove: true } } ] ]`, `templateLock: false`, `allowedBlocks: useBlocksAllowedExcept( isCheckoutSection )` (Coupon Form has no `ancestor` rule, so it is not a checkout section and stays allowed).
- `packages/blocks/src/order-summary/index.ts`: register with `saveNestedBlocks`.
- `packages/blocks/src/order-summary-items/` (`block.json`, `index.ts`, `edit.tsx`, `render.php`): `parent: [ "yaycheckout/order-summary" ]`, `supports: { html: false, multiple: false, reusable: false, inserter: false }`; editor shows the mock row "Sample product × 1 / $20.00"; render calls `CheckoutFragments::print_order_items()`.
- `packages/blocks/src/order-summary-totals/` (same files): same `parent` and `supports`; editor shows Subtotal and Total mock rows; render calls `CheckoutFragments::print_order_totals()`.
- `includes/Renderer/CheckoutFragments.php`: constants `ORDER_ITEMS_CLASS = 'yaycheckout-order-summary-items'`, `ORDER_TOTALS_CLASS = 'yaycheckout-order-summary-totals'`; `FRAGMENTS` entries `'order_summary_items' => [ '.' . self::ORDER_ITEMS_CLASS, 'order_items_html' ]` and `'order_summary_totals' => [ '.' . self::ORDER_TOTALS_CLASS, 'order_totals_html' ]`; `order_items_html()` / `order_totals_html()` return `<table class="shop_table …">` mirroring `review-order.php`'s `<thead>`+`<tbody>` and `<tfoot>` rows (as `<tbody>`), or `''` without a `WC_Cart`; `print_order_items()` / `print_order_totals()` echo them.
- `includes/Services/LayoutMarkupReader.php`: `SECTION_SLOTS` adds `'yaycheckout/order-summary-items' => 'order_summary_items'` and `'yaycheckout/order-summary-totals' => 'order_summary_totals'`.
- `includes/Blocks/BlockRegistry.php`: `ORDER` adds `order-summary-items`, `order-summary-totals` after `order-summary`.
- `templates/default-layout.php`: Order Summary as a container holding both children.

**Data and storage:** None. Layout markup changes only when a merchant saves it.

**Integration points:** `woocommerce_update_order_review_fragments` (existing filter in `CheckoutFragments`); the six WooCommerce hooks listed in Constraints; WooCommerce's own `.woocommerce-checkout-review-order-table` fragment is left alone and matches nothing in a split summary.

**UI:** Block editor only; no admin SPA change.

**Edge cases and failure modes:**
- No `WC_Cart` (admin, REST, CLI): Items and Totals print nothing; zone blocks still render.
- Empty cart in a preview link: `PreviewRequest` already disables `woocommerce_checkout_update_order_review_expired`; tables render empty.
- Layout with both Order Review and a split Order Summary: Order Review keeps WooCommerce's table and fragment; the new fragments match only the split tables.
- A theme override of `checkout/review-order.php` applies to the fallback only, not to the split tables.
- Theme CSS or plugin JS targeting `.woocommerce-checkout-review-order-table` does not reach the split tables.

**Decision record:** None.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Two fixed zone child blocks, parent renders items and totals around them | More blocks and manual interleaving of `$block->inner_blocks` for the same result. |
| Keep the leaf block and render zone blocks inside the table through `woocommerce_review_order_*` hooks | Every `update_order_review` wipes and re-renders them in AJAX; a Coupon Form loses typed input. |
| Split WooCommerce's rendered table HTML at `</tbody>` | Depends on template markup; breaks on any theme override. |
| Migrate stored layout markup | Unneeded: render fallback plus the editor template cover old layouts. |

## Rollout

No migration and no setting. Stored layouts keep rendering WooCommerce's table until the merchant opens and saves them in the editor; then they use the split summary.

## Open questions

None.
