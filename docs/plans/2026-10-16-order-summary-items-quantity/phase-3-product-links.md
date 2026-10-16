# Phase 3: Link the product image and name

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Branch:** `trace/feat/order-items-product-links`, from `dev/edd` after Phase 2 merges
**Depends on:** Phase 2 Task 1 (`order_items_options()` shape)
**Delivers:** Settings → Product has Link image and Link name; each opens the product page in a new tab.
**Exit criterion:** Task 1 Step 3 passes.

### Task 1: Add `linkImage` and `linkName`

**Files:**
- Modify: `packages/blocks/src/order-summary-items/block.json`
- Modify: `packages/blocks/src/order-summary-items/edit.tsx`
- Modify: `packages/blocks/src/order-summary-items/style.css`
- Modify: `includes/Renderer/CheckoutFragments.php` (`order_items_options()`, `order_items_html()`, new `product_permalink()`)

**Interfaces:**
- Produces: attributes `linkImage`, `linkName`; option keys `link_image`, `link_name`; `product_permalink()`; class `.yaycheckout-order-summary-items__link` (README contract).

- [ ] **Step 1: Attributes and PHP**

`block.json`: `linkImage` and `linkName`, boolean, default `false`. `order_items_options()`: `link_image` and `link_name` through `bool_attr()`, added to the `@return` shape.

Add `private static function product_permalink( \WC_Product $product, array $cart_item, string $cart_item_key ): string` as the README contract gives it. In `order_items_html()`, compute it once per item when either option is on. Build the anchor as `<a class="yaycheckout-order-summary-items__link" href="{esc_url}" target="_blank" rel="noopener">`.
- Name: when `link_name` and the permalink is non-empty, wrap `$product->get_name()` in the anchor before `woocommerce_cart_item_name`, as WooCommerce's `cart/cart.php` does; the filter and `wp_kses_post()` stay.
- Image: when `link_image` and the permalink is non-empty, wrap the filtered `woocommerce_cart_item_thumbnail` HTML in the anchor; the badge stays a sibling after the anchor. When the name is linked too, the image anchor gets `tabindex="-1" aria-hidden="true"`, so keyboard and screen-reader users meet one link per product.

- [ ] **Step 2: Editor and CSS**

`edit.tsx`: two ToggleControls in the Product panel after Show thumbnails, **Link image** (rendered only while thumbnails are on) and **Link name**, help on Link name: "Opens the product page in a new tab." Canvas: wrap the placeholder and the name in `<a className="yaycheckout-order-summary-items__link" href="#" onClick={ e => e.preventDefault() }>` when on, so the canvas shows the link styling without navigating.

`style.css`: `.yaycheckout-order-summary-items__link { color: inherit; text-decoration: none; }` and `:hover` / `:focus-visible` `{ text-decoration: underline; }`; the image anchor `display: block` so the 48px thumbnail and badge position are unchanged.

- [ ] **Step 3: Verify**

Run: `pnpm build:blocks`, then `composer lint -- includes/Renderer/CheckoutFragments.php`
Expected: build succeeds; lint prints no errors.

Storefront (preview token as in Phase 1 Task 1 Step 3, Classic two-column layout with Link image and Link name on):
- Clicking the name opens the product page in a new tab; the checkout tab keeps its filled fields.
- Clicking the image does the same; the badge still sits on the image's top-right corner.
- Tab through the summary: focus lands once per product, on the name.
- In WooCommerce → Products, set one cart product's Catalog visibility to Hidden: its image and name print unlinked (no `<a>` in that row), the other rows stay linked. Restore the visibility.
- Set the same layout's Show as to Stepper, publish, and click + on a row: after the refresh both links are still there.
- Both toggles off: no `.yaycheckout-order-summary-items__link` in the page.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/order-summary-items/block.json`, `packages/blocks/src/order-summary-items/edit.tsx`, `packages/blocks/src/order-summary-items/style.css`, `includes/Renderer/CheckoutFragments.php`
Message: `feat(blocks): link the Items product image and name to the product`
