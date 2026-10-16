# Phase 2: Show as Inline, Stepper or Hidden, plus a badge toggle

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Branch:** `trace/feat/order-items-quantity-display`, from `dev/edd` after Phase 1 merges
**Depends on:** Phase 1 Task 1 (`.yaycheckout-order-summary-items__controls`)
**Delivers:** Settings → Quantity offers Show as Inline | Stepper | Hidden and a separate Badge on thumbnail toggle; the badge is extra and hides nothing.
**Exit criterion:** Task 2 Step 3 passes.

### Task 1: Replace `showStepper` and the `badge` choice with `quantityDisplay` and `showBadge`

**Files:**
- Modify: `packages/blocks/src/order-summary-items/block.json`
- Modify: `packages/blocks/src/order-summary-items/edit.tsx`
- Modify: `includes/Renderer/CheckoutFragments.php` (`print_summary_block()`, `order_items_options()` and its `@return` shape, `order_items_html()`)

**Interfaces:**
- Produces: attributes `quantityDisplay` (`inline | stepper | hidden`) and `showBadge`, option keys `quantity_display` and `show_badge`; `showStepper` / `show_stepper` removed (README contract).

- [ ] **Step 1: Change the attributes in `block.json`**

`quantityDisplay`: enum `[ "inline", "stepper", "hidden" ]`, default `"inline"`. Add `showBadge`: boolean, default `false`. Delete `showStepper`.

- [ ] **Step 2: Change the PHP**

`order_items_options()`: `'quantity_display' => $choice( 'quantityDisplay', [ 'inline', 'stepper', 'hidden' ] )`, add `'show_badge' => self::bool_attr( $attributes, 'showBadge', false )`, drop `show_stepper`; update the `@return` array shape. In `order_items_html()`:
- `$badge = $options['show_badge'] && $options['show_thumbnails']`.
- `$stepper_mode = 'stepper' === $options['quantity_display']`; the per-item `$stepper` is `$stepper_mode` plus the existing sold-individually and min/max checks.
- Inline `× N` prints when `'hidden' !== $options['quantity_display'] && ! $stepper` (no longer depends on the badge). So Inline prints it, and a stepper-ineligible item in Stepper mode falls back to it. Update the comment above it: the stepper's input shows the quantity, so the text would repeat it.
- The controls row (Phase 1) prints when `$stepper_mode`.
In `print_summary_block()`, the nonce and notices condition reads `'stepper' === ...['quantity_display']`. Rename local variables only where they now mislead.

- [ ] **Step 3: Change `edit.tsx`**

Attributes interface: `quantityDisplay: 'inline' | 'stepper' | 'hidden'`, `showBadge: boolean`, no `showStepper`. Quantity panel: SelectControl **Show as** with Inline / Stepper / Hidden (help on Stepper: "Lets shoppers change or remove items."; on Hidden without a badge showing: "Shoppers won't see item quantities."); ToggleControl **Badge on thumbnail**, rendered only while `showThumbnails` is on. `badge = showBadge && showThumbnails`. Canvas: `× 1` prints when Show as is Inline, the controls row when Stepper, the badge when `badge`. The Styles → Quantity badge panel shows while `badge`, as today.

- [ ] **Step 4: Verify**

Run: `pnpm build:blocks`, then `composer lint -- includes/Renderer/CheckoutFragments.php`
Expected: build succeeds; lint prints no errors; `git grep -n "showStepper\|show_stepper" -- packages includes` prints nothing.

Editor (fresh layout from Upsell & cross-sell; Task 2 has not changed its markup yet, so start by setting Show as to Stepper):
- Inline, badge off: `Sample product × 1`, no badge, no stepper.
- Inline, badge on: `× 1` and the badge on the thumbnail.
- Stepper, badge on: badge and the controls row, no `× 1`.
- Hidden, badge on: only the badge; no `× 1`, no stepper, no remove ×.
- Hidden, badge off: no quantity anywhere, and the Show as help reads "Shoppers won't see item quantities."
- Thumbnails off: the Badge on thumbnail toggle disappears and no badge shows.

Storefront (preview token as in Phase 1 Task 1 Step 3), Stepper + badge on, cart holding an ordinary product and a product with Sold individually ticked (Inventory tab):
Expected: the ordinary row shows badge and stepper; the sold-individually row shows `× 1` inline, its badge and its remove ×, no stepper. Click + on the ordinary row; after the refresh both rows look the same as before with the new quantity in the badge and input.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/order-summary-items/block.json`, `packages/blocks/src/order-summary-items/edit.tsx`, `includes/Renderer/CheckoutFragments.php`
Message: `feat(blocks): choose Items quantity as inline or stepper, badge as its own toggle`

### Task 2: Move the templates and docs to the new attributes

**Files:**
- Modify: `templates/layouts/classic-two-column/markup.php`, `templates/layouts/split-express/markup.php`, `templates/layouts/pro/accordion-one-page/markup.php`, `templates/layouts/pro/multi-step-wizard/markup.php`: `"quantityDisplay":"badge"` → `"quantityDisplay":"hidden","showBadge":true`
- Modify: `templates/layouts/editorial-fashion/markup.php`, `templates/layouts/upsell-cross-sell/markup.php`: `"showStepper":true` → `"quantityDisplay":"stepper"`, and drop Editorial's `"quantityDisplay":"inline"`
- Modify: `docs/architecture.md` (the **Quantity stepper** bullet: "`showStepper` option ... whichever way its `quantityDisplay` shows the quantity" becomes "Show as Stepper (`quantityDisplay: stepper`)")

**Interfaces:**
- Consumes: Task 1's attributes.

- [ ] **Step 1: Edit the six markup files and the architecture bullet**

Leave `live-receipt` and `pro/minimal-steps` (their `"quantityDisplay":"inline"` is still valid) and `pro/soft-steps` unchanged.

- [ ] **Step 2: Check no old values remain**

Run: `git grep -n '"quantityDisplay":"badge"\|showStepper' -- templates docs/architecture.md`
Expected: no output.

- [ ] **Step 3: Verify every template**

Run: `composer test -- --filter LayoutTemplateCatalogueTest`
Expected: OK (every template still publishes).

Editor: create a new layout from each of the eight templates that place the Items block (Classic two-column, Split express, Accordion one-page, Multi-step wizard, Editorial fashion, Upsell & cross-sell, Live receipt, Minimal steps).
Expected: no block-validation warning; the four badge templates show only the badge, as before this phase; Editorial and Upsell show the stepper row; Live receipt and Minimal steps show `× 1` and no thumbnail. Delete the throwaway layouts afterwards, with the user's OK.

- [ ] **Step 4: Commit**

Stage: the six `markup.php` files above, `docs/architecture.md`
Message: `refactor(templates): use the Items quantityDisplay and showBadge attributes`
