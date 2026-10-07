# Order Summary Zones Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** active
**Goal:** Turn Order Summary into a container with locked Items and Totals children, so merchants can add blocks above the items and between items and totals.
**Spec:** [docs/specs/2026-10-07-order-summary-zones-design.md](../specs/2026-10-07-order-summary-zones-design.md)
**Architecture:** Two new child blocks each print one table that `CheckoutFragments` refreshes as its own fragment; the merchant's blocks sit between them, outside any fragment, so `update_order_review` never replaces them. Rendering zone blocks inside WooCommerce's table was rejected because every AJAX update wipes them.
**Touches:** `includes/Renderer/CheckoutFragments.php`, `includes/Services/LayoutMarkupReader.php` (shared: `slots()` is read by `CheckoutFragments::fragments()` and every other slot consumer; adding two map entries changes no existing slot), `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/shared/block-icons.tsx` (shared: every block's icon), `packages/blocks/src/order-summary/`, new `packages/blocks/src/order-summary-items/`, new `packages/blocks/src/order-summary-totals/`, `templates/default-layout.php`, `docs/architecture.md`
**Tests:** none (CLAUDE.md's test rule does not cover this area; every task has a manual check)

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free and Pro both ship it; no `Plan::is_pro()` check and no reference to `YayCheckout\Pro` in `includes/`.
- Never hand-edit `assets/dist/`; edit `packages/blocks/src/<block>/` and run `pnpm build:blocks`.
- The split tables must not carry the class `woocommerce-checkout-review-order-table`.
- The split tables fire, at the same positions as WooCommerce's `templates/checkout/review-order.php`: `woocommerce_review_order_before_cart_contents`, `woocommerce_review_order_after_cart_contents`, `woocommerce_review_order_before_shipping`, `woocommerce_review_order_after_shipping`, `woocommerce_review_order_before_order_total`, `woocommerce_review_order_after_order_total`. Fire them on AJAX too: unlike `woocommerce_review_order_before_payment`, WooCommerce fires these inside the fragment it re-renders.
- Commit messages: `type(scope): summary` ending with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`. Commit only when the user asks.

## Review focus

- Old stored layout (`<!-- wp:yaycheckout/order-summary /-->`): storefront HTML must be identical to before. Verified in Task 2 Step 4.
- Coupon code typed in a Coupon Form placed in a zone must survive `update_order_review`. Verified in Task 3 Step 3.
- WooCommerce's checkout.js puts its loading overlay (`.block()`, checkout.js:705) only on `.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table`; the split tables get no overlay while totals refresh. The spec does not cover this; accepted as-is, noted in Task 3 Step 3 so the reviewer sees it rather than reports it as a regression.
- Theme override of `checkout/review-order.php` applies only to the fallback (spec trade-off). Nothing to verify.

---

### Task 1: Items and Totals blocks with their fragments

**Files:**
- Modify: `includes/Renderer/CheckoutFragments.php:17-24` (constants and `FRAGMENTS`), append methods
- Modify: `includes/Services/LayoutMarkupReader.php:28-38` (`SECTION_SLOTS`)
- Modify: `includes/Blocks/BlockRegistry.php:50` (`ORDER`)
- Modify: `packages/blocks/src/shared/block-icons.tsx:121` (add two entries after `yaycheckout/order-summary`)
- Create: `packages/blocks/src/order-summary-items/{block.json,index.ts,edit.tsx,render.php}`
- Create: `packages/blocks/src/order-summary-totals/{block.json,index.ts,edit.tsx,render.php}`

**Interfaces:**
- Produces:
  - `CheckoutFragments::ORDER_ITEMS_CLASS = 'yaycheckout-order-summary-items'`, `CheckoutFragments::ORDER_TOTALS_CLASS = 'yaycheckout-order-summary-totals'`
  - `CheckoutFragments::order_items_html(): string`, `CheckoutFragments::order_totals_html(): string`, `CheckoutFragments::print_order_items(): void`, `CheckoutFragments::print_order_totals(): void`
  - Block names `yaycheckout/order-summary-items`, `yaycheckout/order-summary-totals`
  - Layout slots `order_summary_items`, `order_summary_totals`

- [ ] **Step 1: Add the HTML builders to `CheckoutFragments`**

Constants `ORDER_ITEMS_CLASS`, `ORDER_TOTALS_CLASS` beside `SHIPPING_RATES_CLASS`. `FRAGMENTS` gains `'order_summary_items' => [ '.' . self::ORDER_ITEMS_CLASS, 'order_items_html' ]` and `'order_summary_totals' => [ '.' . self::ORDER_TOTALS_CLASS, 'order_totals_html' ]`.
`order_items_html()`: `''` without a `\WC_Cart` (same guard as `shipping_rates_html()`); otherwise `<table class="shop_table yaycheckout-order-summary-items">` holding the `<thead>` and `<tbody>` of WooCommerce's `templates/checkout/review-order.php` (lines 21–61), filters and hooks as there.
`order_totals_html()`: same guard; `<table class="shop_table yaycheckout-order-summary-totals"><tbody>` holding the rows of that template's `<tfoot>` (lines 64–118), filters and hooks as there.
`print_order_items()` / `print_order_totals()` echo them, like `print_shipping_rates()`.

- [ ] **Step 2: Map the blocks to slots and register them**

`SECTION_SLOTS` adds `'yaycheckout/order-summary-items' => 'order_summary_items'` and `'yaycheckout/order-summary-totals' => 'order_summary_totals'`. `BlockRegistry::ORDER` adds `'order-summary-items'`, `'order-summary-totals'` right after `'order-summary'`.

- [ ] **Step 3: Create the two blocks**

`block.json` as `order-summary/block.json`, minus `attributes`, `ancestor` and `keywords`, with: name `yaycheckout/order-summary-items` / `yaycheckout/order-summary-totals`, title `Order Summary Items` / `Order Summary Totals`, `"parent": [ "yaycheckout/order-summary" ]`, `"supports": { "html": false, "multiple": false, "reusable": false, "inserter": false }`, `editorScript` and `render` only (they reuse the parent's editor CSS).
`index.ts`: `registerDynamicBlock( metadata, Edit )`.
`edit.tsx`: `useBlockProps()` wrapping one `yaycheckout-order-summary__table` table. Items: the header row (Product / Subtotal) and the row "Sample product × 1 / $20.00". Totals: rows Subtotal $20.00 and Total $20.00 (`--total` cell class). Cell classes as in today's `order-summary/edit.tsx`.
`render.php`: `<div get_block_wrapper_attributes()>` holding `CheckoutFragments::print_order_items()` / `print_order_totals()`, docblock and phpcs comments in the style of `shipping-methods/render.php`.
`block-icons.tsx`: one icon each, keyed by the block name.

- [ ] **Step 4: Verify**

Run: `pnpm build:blocks && composer lint -- includes/Renderer/CheckoutFragments.php includes/Services/LayoutMarkupReader.php includes/Blocks/BlockRegistry.php packages/blocks/src/order-summary-items/render.php packages/blocks/src/order-summary-totals/render.php`
Expected: build succeeds, `assets/dist/blocks/order-summary-items/` and `order-summary-totals/` exist; lint reports no errors.
Run: `wp eval 'var_dump( WP_Block_Type_Registry::get_instance()->is_registered( "yaycheckout/order-summary-items" ), WP_Block_Type_Registry::get_instance()->is_registered( "yaycheckout/order-summary-totals" ) );'`
Expected: `bool(true) bool(true)`

- [ ] **Step 5: Commit**

Stage: the files listed above.
Message: `feat(blocks): add Order Summary Items and Totals blocks`

### Task 2: Order Summary as a container

**Files:**
- Modify: `packages/blocks/src/order-summary/edit.tsx`
- Modify: `packages/blocks/src/order-summary/index.ts`
- Modify: `packages/blocks/src/order-summary/render.php`
- Modify: `packages/blocks/src/order-summary/block.json` (`description` only)

**Interfaces:**
- Consumes: block names from Task 1; `useBlocksAllowedExcept`, `isCheckoutSection` (`shared/allowed-blocks.ts`); `saveNestedBlocks` (`shared/register-dynamic-block.tsx`)

- [ ] **Step 1: Editor**

`edit.tsx`: keep the Label `InspectorControls` and the heading; replace the table with `useInnerBlocksProps` on a wrapper after the heading, with
`template: [ [ itemsMeta.name, { lock: { move: true, remove: true } } ], [ totalsMeta.name, { lock: { move: true, remove: true } } ] ]`, `templateLock: false`,
`allowedBlocks: useBlocksAllowedExcept( isCheckoutSection )`, as `shared/address-section-edit.tsx` does; Coupon Form has no `ancestor` rule, so it stays allowed. Import names from each `block.json`.
`index.ts`: `registerDynamicBlock( metadata, Edit, saveNestedBlocks )`.
`block.json` description: `Cart line items and totals for classic checkout, with room for your own blocks above and between them.`

- [ ] **Step 2: Render**

`render.php`: heading as today; then, when `$block->inner_blocks` is empty, today's `#order_review` + `woocommerce_order_review()` branch unchanged; otherwise `echo $content` (phpcs comment as in `billing/render.php:22`). Update the docblock: `$content` is the inner blocks.

- [ ] **Step 3: Build**

Run: `pnpm build:blocks && composer lint -- packages/blocks/src/order-summary/render.php && pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`
Expected: build succeeds; lint no errors; tsc no new errors versus `dev/edd`.

- [ ] **Step 4: Verify old layouts and the editor**

Before opening the editor, load the storefront checkout of a layout whose markup still has `<!-- wp:yaycheckout/order-summary /-->`.
Expected: heading, then `<div id="order_review">` with `table.woocommerce-checkout-review-order-table`, same as on `dev/edd`.
Open that layout in the layout editor.
Expected: Order Summary shows Items and Totals; neither offers Move or Remove in its toolbar/options. The inserter inside Order Summary lists Paragraph and Coupon Form, and does not list Billing, Shipping, Payment, Steps, Order Review, Place Order, Order Summary Items or Order Summary Totals.

- [ ] **Step 5: Commit**

Stage: the four files above.
Message: `feat(blocks): let merchants add blocks inside Order Summary`

### Task 3: Default layout and docs, end-to-end check

**Files:**
- Modify: `templates/default-layout.php:75`
- Modify: `docs/architecture.md:46`

- [ ] **Step 1: Default layout**

Replace `<!-- wp:yaycheckout/order-summary /-->` with the container markup holding `<!-- wp:yaycheckout/order-summary-items {"lock":{"move":true,"remove":true}} /-->` and `<!-- wp:yaycheckout/order-summary-totals {"lock":{"move":true,"remove":true}} /-->`, no other children. Copy the exact serialisation the editor saves in Task 2 Step 4 (Code editor view), so a new layout opens without a block-validation diff.

- [ ] **Step 2: Docs**

`docs/architecture.md:46`: say Order Summary holds locked Items and Totals blocks, with room for the merchant's blocks above and between them. Add one sentence to the `CheckoutFragments` description where architecture.md lists the refreshed fragments, if it lists them (`git grep -n CheckoutFragments docs/architecture.md`).

- [ ] **Step 3: End-to-end verification**

Run: `pnpm build:blocks`. In wp-admin create a new layout; confirm Order Summary has Items and Totals with nothing between. Add a Paragraph "ABOVE" above Items, a Paragraph "BETWEEN" and a Coupon Form between Items and Totals; publish and make it the active layout.
On the storefront with a cart: order is heading, items table, "BETWEEN" Paragraph and Coupon Form, totals table, with "ABOVE" between heading and items. Type `TEST` in the Coupon Form input (don't apply), then change the shipping country so shipping cost changes.
Expected: Total shows the new amount; both Paragraphs remain; the Coupon Form input still holds `TEST`; the browser's network tab shows `update_order_review` fragments with keys `.yaycheckout-order-summary-items` and `.yaycheckout-order-summary-totals`. No loading overlay on the summary tables while it refreshes (known, see Review focus).

- [ ] **Step 4: Commit**

Stage: `templates/default-layout.php`, `docs/architecture.md`, `docs/specs/2026-10-07-order-summary-zones-design.md`, `docs/plans/2026-10-07-order-summary-zones.md` (Status → `done YYYY-MM-DD`).
Message: `feat(layouts): ship Order Summary zones in the default layout`
