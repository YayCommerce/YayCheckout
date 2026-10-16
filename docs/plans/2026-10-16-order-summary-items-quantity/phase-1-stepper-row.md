# Phase 1: Stepper on its own row

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Branch:** `trace/fix/order-items-stepper-row`, from `dev/edd`
**Depends on:** nothing in this plan; `trace/refactor/templates-without-custom-css` merged into `dev/edd`
**Delivers:** with the stepper on and no SKU, unit price or short description, the stepper and remove link sit on a row below the product name instead of beside it.
**Exit criterion:** Task 1 Step 3 passes.

Cause: `.yaycheckout-qty` is `display: inline-flex` and the remove link `inline-block`, both loose inside `.product-name` after the name text. A detail line (`div.product-sku` and friends) is a block, so it pushes them down; with no detail line they flow onto the name's line.

### Task 1: Wrap the stepper and remove link in a controls row

**Files:**
- Modify: `includes/Renderer/CheckoutFragments.php` (`order_items_html()`, the `$stepper` / `show_stepper` echoes)
- Modify: `packages/blocks/src/order-summary-items/edit.tsx` (the `showStepper && (...)` fragment)
- Modify: `packages/blocks/src/order-summary-items/style.css` (stepper and remove rules)

**Interfaces:**
- Produces: `.yaycheckout-order-summary-items__controls` (README contract).

- [ ] **Step 1: Print the controls row in `order_items_html()` and `edit.tsx`**

When `$options['show_stepper']` is true, print `<div class="yaycheckout-order-summary-items__controls">` (class built from `self::ORDER_ITEMS_CLASS . '__controls'`) after `print_item_details()`, containing the existing filtered stepper (when `$stepper`) and the existing filtered remove link, in that order. Print nothing when `show_stepper` is false. In `edit.tsx`, replace the fragment around `.yaycheckout-qty` and the remove `<span>` with the same `div`.

- [ ] **Step 2: Lay the row out in `style.css`**

`.yaycheckout-order-summary-items__controls`: `display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-block-start: 6px;`. Remove `margin-block-start` and `vertical-align` from `.yaycheckout-order-summary-items .yaycheckout-qty`, and `display`, `margin-block-start`, `margin-inline-start` and `vertical-align` from `.yaycheckout-order-summary-items__remove`, since the row now places them. Keep the qty height, border and button rules unchanged.

- [ ] **Step 3: Verify in the editor and on the storefront**

Run: `pnpm build:blocks`, then `composer lint -- includes/Renderer/CheckoutFragments.php`
Expected: build succeeds; lint prints no errors.

Editor: in wp-admin → YayCheckout, create a new layout from **Upsell & cross-sell** (a fresh layout, not an existing draft: autosave writes edits into drafts). Select Order Summary Items.
Expected: with Show SKU, Show unit price and Show short description all off, the − 1 + box and × sit on a row below "Sample product", left-aligned with the name. Turning Show SKU on puts the SKU line between name and row; the row does not move sideways.

Storefront: publish that layout, put a product with a stock-unlimited quantity in the cart (`http://yaydevs.local/?add-to-cart=29`), open `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>` (token from `\YayCheckout\Helpers\PreviewToken::mint( <layout id>, 60 )` via wp-cli). In the console run:
`[...document.querySelectorAll('.yaycheckout-order-summary-items__item')].map(r => r.querySelector('.yaycheckout-order-summary-items__controls').getBoundingClientRect().top > r.querySelector('.product-name').getBoundingClientRect().top + 10)`
Expected: every entry `true`. Click +; after the totals refresh, run it again: still all `true`, and the quantity reads 2.

Locked quantity: add `add_filter( 'woocommerce_cart_item_quantity', function () { return '<span class="locked">2</span>'; } );` in a temporary file `wp-content/mu-plugins/yc-locked-qty.php`, reload.
Expected: "2" and × sit on the row below the name. Delete the temporary file afterwards.

At 375px wide (DevTools device toolbar): the row stays below the name and the line total stays in the right column.

- [ ] **Step 4: Commit**

Stage: `includes/Renderer/CheckoutFragments.php`, `packages/blocks/src/order-summary-items/edit.tsx`, `packages/blocks/src/order-summary-items/style.css`
Message: `fix(blocks): put the Items quantity stepper on its own row`
