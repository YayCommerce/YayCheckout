# Phase 1: Layout and styling

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** nothing
**Delivers:** Items printed as divs, with a header-row toggle and editable words, a thumbnail toggle, and core's Color, Typography, Dimensions (padding, margin, block spacing) and Border panels
**Exit criterion:** Task 3 Step 4 passes

### Task 1: Move Steps' block gap into a shared helper

**Files:**
- Create: `includes/Renderer/BlockGap.php`, `packages/blocks/src/shared/block-gap.ts`
- Modify: `packages/blocks/src/steps/render.php:28-83`, `packages/blocks/src/steps/edit.tsx:1-59,71-83`

**Interfaces:**
- Produces: `BlockGap::add_rules()` and `block-gap.ts` (see the README contract). `add_rules()` queues `gap` rules for `$selector` in core's `block-supports` stylesheet (`wp_style_engine_get_stylesheet_from_css_rules()`, `context` `block-supports`, `prettify` false). The value is the block's own `$style['spacing']['blockGap']`, else `wp_get_global_styles( [ 'spacing', 'blockGap' ], [ 'block_name' => $block_name ] )`. Each viewport's `$style[ $viewport ]['spacing']['blockGap']` is added in its media query when `WP_Theme_JSON::get_viewport_media_queries` exists. Every value goes through `wp_sanitize_block_gap_value()`, and `var:preset|spacing|<slug>` becomes `var(--wp--preset--spacing--<slug>)`. It returns false when it queues nothing. `blockGapCSS()` returns one stylesheet (the base rule, then each viewport rule in its media query), or `''`.

- [ ] **Step 1: Create `includes/Renderer/BlockGap.php`**

A `final` class with the one static method and no constructor or singleton. Move the logic out of `steps/render.php:28-83`, keeping its comment about classic themes and core's `layout.php`. The caller builds the selector, so Steps keeps its two-class specificity.

- [ ] **Step 2: Create `packages/blocks/src/shared/block-gap.ts`**

Move `viewportMediaQueries()` and `gapCSS()` from `steps/edit.tsx` unchanged, with their comments, and build `blockGapCSS()` around them from the `css` array in `steps/edit.tsx:77-83`.

- [ ] **Step 3: Switch Steps to the helpers**

In `steps/render.php`, keep the `wp_unique_id( 'yaycheckout-steps-gap-' )` class. Call `\YayCheckout\Renderer\BlockGap::add_rules( $attributes['style'] ?? [], 'yaycheckout/steps', '.yaycheckout-steps.' . $class )`, and add the class to the wrapper only when it returns true.
In `steps/edit.tsx`, use `const css = blockGapCSS( \`#block-${ clientId }\`, style, viewport );`. Delete the moved functions and interfaces, and keep the `<style>` placement and its comment.

- [ ] **Step 4: Verify Steps is unchanged**

Run: `pnpm build:blocks`, then `composer lint -- includes/Renderer/BlockGap.php packages/blocks/src/steps/render.php`
Expected: the build succeeds, and lint reports no errors.
Open: the active layout in the layout editor. On its Steps block, set Block spacing to `40px`, and on the Mobile viewport set `8px` (WP 7.1+ only; skip the viewport check on older WordPress). Save.
Expected: the canvas shows a 40px gap between steps. On the storefront checkout, DevTools shows `.yaycheckout-steps.yaycheckout-steps-gap-N { gap: 40px }` winning, and `8px` at a width ≤ 480px.
Action: in the code editor, set the Steps block's `style.spacing.blockGap` to `1px;}body{display:none`, and save.
Expected: the storefront page renders, and no `gap` rule for that class is printed.
Action: clear Block spacing.
Expected: the gap falls back to the `.yaycheckout-steps` stylesheet default.

- [ ] **Step 5: Commit**

Stage: `includes/Renderer/BlockGap.php`, `packages/blocks/src/shared/block-gap.ts`, `packages/blocks/src/steps/render.php`, `packages/blocks/src/steps/edit.tsx`
Message: `refactor(blocks): share Steps' block gap printing`

### Task 2: Div layout with header and thumbnail toggles

**Files:**
- Create: `packages/blocks/src/order-summary-items/style.css`
- Modify: `includes/Renderer/CheckoutFragments.php` (`fragments()`, `summary_block_fragments()`, `print_summary_block()`, `order_items_html()`, `order_totals_html()`, new `order_items_options()`), `packages/blocks/src/order-summary-items/{block.json,edit.tsx,index.ts}`, `assets/css/checkout-base.css:398-418`, `docs/architecture.md:68`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: the four Phase 1 attributes, `order_items_options()` with `show_labels`, `product_label`, `subtotal_label` and `show_thumbnails`, the new `order_items_html()`, `order_totals_html()` and `summary_block_fragments()` signatures, and the base markup (see the README contract).

- [ ] **Step 1: Add the attributes and stylesheet to `block.json`**

Add `showLabels`, `productLabel`, `subtotalLabel` and `showThumbnails`, as the README table gives them, and `"style": "file:./style-index.css"`. Add `import './style.css';` to `index.ts`, as `payment/index.ts` does.

- [ ] **Step 2: Add `order_items_options()` and rewrite `order_items_html()`**

`order_items_options( array $attributes ): array` follows the README contract. `order_items_html()` prints the README markup, minus the Phase 2 and 3 parts.
- **Thumbnail:** `apply_filters( 'woocommerce_cart_item_thumbnail', $product->get_image(), $cart_item, $cart_item_key )`, as WooCommerce's `cart/cart.php` does.
- **Item classes:** `yaycheckout-order-summary-items__item` always comes after the output of `woocommerce_cart_item_class`.
- **Hooks:** each is buffered with `ob_start()` and printed in its wrapper only when `'' !== trim( $output )`.
- **Unchanged:** the existing filters, phpcs comments and escaping for the name, quantity, item data and subtotal.
- **Docblock:** update it to say the table is gone and why the hooks are wrapped.

- [ ] **Step 3: Pass the attributes through both render paths**

- `summary_block_fragments()` returns each matching flattened block's `attrs` as element 2.
- `fragments()` calls `self::$build( $attrs )` for summary entries and keeps calling the slot builders with no argument.
- `print_summary_block()` calls `self::$build( $block->attributes )`.
- `order_totals_html()` gains the `$attributes = []` parameter, with a one-line note that Totals has no attributes.

- [ ] **Step 4: Styles for the new layout**

In `order-summary-items/style.css`, at single-class specificity (0,1,0), so Task 3's gap rule beats it:
- `.yaycheckout-order-summary-items`: a flex column with `gap: 12px`.
- `__header` and `__item`: one grid row. The name column takes the remaining width and wraps, and the subtotal column is end-aligned and doesn't wrap. With a thumbnail, the item row has a fixed 48px first column, and the header's "Product" spans it.
- `.product-thumbnail`: `position: relative` (Phase 2's badge anchors to it). Its `img` is a 48px square with `object-fit: cover` and `border-radius: var(--yaycheckout-field-radius)`.
- `__hook`: `width: 100%`, `border-collapse: collapse`, no cell border.

In `checkout-base.css:398-418`, drop `.yaycheckout-order-summary-items` from the four `table:is(…)` selectors, leaving Totals' rules, and rewrite the comment above them, since Items is no longer a table.

- [ ] **Step 5: Mirror the markup and add the controls in `edit.tsx`**

- **Canvas:** render the same markup with one sample row: "Sample product", `× 1`, `$20.00`, and a 48px grey placeholder `<div className="product-thumbnail">` while `showThumbnails` is on. Keep the `Mirrors CheckoutFragments::order_items_html()` comment.
- **Panel:** in `InspectorControls`, add a `PanelBody` titled "Order summary items" with:
  - `ToggleControl` "Show labels" (`showLabels`).
  - Two `TextControl`s, "Product label" and "Subtotal label", shown only while `showLabels` is on. Their placeholders are `__( 'Product', 'yaycheckout' )` and `__( 'Subtotal', 'yaycheckout' )`.
  - `ToggleControl` "Show thumbnails" (`showThumbnails`).
- **Blank labels:** a blank label shows its placeholder word on the canvas.

- [ ] **Step 6: Verify on the storefront and in the editor**

Run: `pnpm build:blocks`, then `composer lint -- includes/Renderer/CheckoutFragments.php`
Expected: the build succeeds, and lint reports no errors.
Open: the storefront checkout with the default layout active and two products in the cart, one a variation.
Expected:
- `.yaycheckout-order-summary-items` is a `div`, and the header reads "Product / Subtotal".
- Each row shows the thumbnail, the name with `× qty` and the variation data, and the subtotal end-aligned.
- The page has no `.yaycheckout-order-summary-items__hook` element.
Action: in the editor, turn off Show labels and Show thumbnails, save, reload the checkout, then change the billing country to trigger `update_order_review`.
Expected: no header and no `.product-thumbnail`, both before and after the refresh. In the Network tab, the `.yaycheckout-order-summary-items` fragment in the `update_order_review` response has neither.
Action: turn both back on, set Product label to `Item`, save, reload, and change the country again.
Expected: the header reads "Item / Subtotal" before and after the refresh.
Action: add a temporary `wp-content/mu-plugins/yaycheckout-hook-check.php` that hooks `woocommerce_review_order_before_cart_contents` to echo `<tr><td>HOOK BEFORE</td></tr>`, then reload and change the country.
Expected: "HOOK BEFORE" renders as a table cell inside `table.yaycheckout-order-summary-items__hook`, above the first row, before and after the refresh.
Action: delete the mu-plugin.
Expected: the wrapper table is gone.
Open: the layout in the editor.
Expected: the Items canvas matches the storefront layout, and the four controls work live.

- [ ] **Step 7: Update `docs/architecture.md:68`**

In the "Extra fields and fragments" paragraph, say three things about Items: it prints divs, not a table; it wraps any output of the cart-contents hooks in its own table; and its builder receives the block's attributes, normalised by `order_items_options()`, so its options survive the fragment.

- [ ] **Step 8: Commit**

Stage: `includes/Renderer/CheckoutFragments.php`, `packages/blocks/src/order-summary-items/block.json`, `packages/blocks/src/order-summary-items/edit.tsx`, `packages/blocks/src/order-summary-items/index.ts`, `packages/blocks/src/order-summary-items/style.css`, `assets/css/checkout-base.css`, `docs/architecture.md`
Message: `feat(order-summary): lay out Items without a table, with label and thumbnail toggles`

### Task 3: Style supports and block spacing on Items

**Files:**
- Modify: `packages/blocks/src/order-summary-items/{block.json,edit.tsx,render.php}`, `includes/Renderer/CheckoutFragments.php` (`print_summary_block()`)

**Interfaces:**
- Consumes: `BlockGap::add_rules()` and `blockGapCSS()` (Task 1), and the `.yaycheckout-order-summary-items` fragment root (Task 2).
- Produces: the `yaycheckout-order-summary-items-gap-N` wrapper class (see the README contract).

- [ ] **Step 1: Add the supports to `block.json`**

Copy `color`, `typography` and `__experimentalBorder` from `packages/blocks/src/field-value/block.json` exactly. Add `"spacing": { "margin": true, "padding": true, "blockGap": true }`. Keep `html`, `multiple` and `reusable` false.

- [ ] **Step 2: Print the gap on the storefront**

In `print_summary_block()`, for `yaycheckout/order-summary-items` only:
1. Create the class: `$class = wp_unique_id( 'yaycheckout-order-summary-items-gap-' )`.
2. Call `BlockGap::add_rules( $block->attributes['style'] ?? [], $block->name, '.' . $class . ' > .' . self::ORDER_ITEMS_CLASS )`.
3. When it returns true, pass `[ 'class' => $class ]` to `get_block_wrapper_attributes()`.

The class goes on the wrapper because `checkout.js` replaces the child on every refresh. Update `render.php`'s `@var array $attributes` line, which still says "(none)".

- [ ] **Step 3: Print the gap in the editor**

In `edit.tsx`, read `style` from the attributes and `viewport` from `useSettings( 'viewport' )`. Render `<style>{ blockGapCSS( \`#block-${ clientId } > .yaycheckout-order-summary-items\`, style, viewport ) }</style>` inside the wrapper, only when the result is non-empty.

- [ ] **Step 4: Verify the phase exit criterion**

Run: `pnpm build:blocks`, then `composer lint -- includes/Renderer/CheckoutFragments.php packages/blocks/src/order-summary-items/render.php`
Expected: the build succeeds, and lint reports no errors.
Open: the active layout in the editor, and select Order Summary Items.
Expected: the Styles tab shows Color (Text, Background), Typography, Dimensions (Padding, Margin, Block spacing) and Border.
Action: set the header off, thumbnails on, a background colour, padding `16px`, a `1px solid` border and Block spacing `24px`. Save.
Expected: the canvas shows all of them, with 24px between rows.
Open: the storefront checkout with two products in the cart, one a variation.
Expected: the wrapper `div.wp-block-yaycheckout-order-summary-items` carries the background, padding and border. DevTools shows `.yaycheckout-order-summary-items-gap-N > .yaycheckout-order-summary-items { gap: 24px }` winning over the `12px` default.
Action: change the billing country.
Expected: after the refresh, the styles, the 24px gap, the hidden header and the visible thumbnails are all unchanged.
Action: place the order.
Expected: the order completes.
Action: clear Block spacing and save.
Expected: the rows fall back to the `12px` gap.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/order-summary-items/block.json`, `packages/blocks/src/order-summary-items/edit.tsx`, `packages/blocks/src/order-summary-items/render.php`, `includes/Renderer/CheckoutFragments.php`
Message: `feat(order-summary): add style supports and block spacing to Items`
