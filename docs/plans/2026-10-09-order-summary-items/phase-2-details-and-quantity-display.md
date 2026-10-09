# Phase 2: Details and quantity display

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** Phase 1 Task 2 (`order_items_options()`, the div markup, `.product-thumbnail` with `position: relative`) and Phase 1 Task 3 (the style supports that the details inherit)
**Delivers:** the item-data layout, the SKU, unit price and short description toggles, and quantity shown inline or as a badge on the thumbnail
**Exit criterion:** Task 3's check passes

### Task 1: Product detail options

**Files:**
- Modify: `includes/Renderer/CheckoutFragments.php` (`order_items_options()`, `order_items_html()`), `packages/blocks/src/order-summary-items/{block.json,edit.tsx,style.css}`

**Interfaces:**
- Consumes: `order_items_options()` and the Phase 1 markup (see the README contract).
- Produces: the attributes `itemData`, `showSku`, `showUnitPrice` and `showShortDescription`, the option keys `item_data`, `show_sku`, `show_unit_price` and `show_short_description`, the `.product-sku`, `.product-unit-price` and `.product-short-description` elements, and the `yaycheckout-order-summary-items--item-data-inline` root class.

Decisions that pin behaviour:
- **Order:** under the name, in this order: item data, SKU, unit price, short description.
- **Item data:**
  - `list` prints `wc_get_formatted_cart_item_data( $cart_item )` as today.
  - `inline` prints the same markup and adds the `--item-data-inline` class to the fragment root. In `style.css`, the root's `dl.variation` then lays its `dt` and `dd` on one wrapping line, with `dd p { margin: 0; display: inline }` and a `", "` separator after every `dd` but the last. This keeps WooCommerce's escaping and every item-data filter.
  - `hidden` doesn't call `wc_get_formatted_cart_item_data()`.
- **SKU:** prints only when `$product->get_sku()` is non-empty, as `esc_html__( 'SKU:', 'woocommerce' ) . ' ' . esc_html( $sku )`.
- **Unit price:** `apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $product ), $cart_item, $cart_item_key )` inside `sprintf( /* translators: %s: product price */ __( '%s each', 'yaycheckout' ), … )`. It prints for every quantity.
- **Short description:**
  - The text is `$product->get_short_description()`. When that's empty and `$product->get_parent_id()` is greater than 0, use the parent's: `wc_get_product( $parent_id )`.
  - It prints as `esc_html( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $text ) ), 20 ) )`, and only when the result is non-empty.
- **Editor:**
  - **Panel:** a `SelectControl` "Product details" (`itemData`: List, Inline, Hidden), then `ToggleControl`s "Show SKU", "Show unit price" and "Show short description".
  - **Sample row:** `<dl class="variation"><dt>Color:</dt><dd><p>Blue</p></dd></dl>`, `SKU: SAMPLE-01`, `$20.00 each`, and "A short product description.".

Steps: expand after Phase 1 lands. The verification must cover:
- each `itemData` value on the variation;
- a simple product without a SKU, which gets no `.product-sku`;
- a short description of `<script>alert(1)</script>[gallery] Hello <b>world</b>`, which prints as the text `Hello world` (the script tag's contents are stripped with it);
- the state after a billing-country change.

### Task 2: Quantity as a badge

**Files:**
- Modify: `includes/Renderer/CheckoutFragments.php` (`order_items_options()`, `order_items_html()`), `packages/blocks/src/order-summary-items/{block.json,edit.tsx,style.css}`

**Interfaces:**
- Consumes: `order_items_options()` and `.product-thumbnail` (Phase 1 Task 2).
- Produces: `quantityDisplay` with the enum `inline`, `badge` (default `inline`), the option key `quantity_display`, and `.yaycheckout-order-summary-items__badge`. Phase 3 Task 1 adds `stepper` to this enum.

Decisions that pin behaviour:
- **`inline`:** prints today's quantity HTML through `woocommerce_checkout_cart_item_quantity`.
- **`badge`, thumbnails on:** prints `<span class="yaycheckout-order-summary-items__badge">` with `esc_html( $cart_item['quantity'] )` inside `.product-thumbnail`, for every quantity including 1. It doesn't go through `woocommerce_checkout_cart_item_quantity`, whose callers expect the `× N` shape. The span gets a screen-reader label: `<span class="screen-reader-text">` with `/* translators: %s: quantity */ __( 'Quantity: %s', 'yaycheckout' )`, and the visible number is `aria-hidden`.
- **`badge`, thumbnails off:** `order_items_html()` prints `inline`. `order_items_options()` keeps returning `badge`.
- **Badge style:**
  - Shape: a 20px minimum circle at the thumbnail's top-end corner (`inset-block-start: -6px; inset-inline-end: -6px`), `font-size: 12px`, `line-height: 20px`.
  - Colours: the background is `color-mix( in srgb, currentColor 70%, transparent )`, and the number is white.
- **Editor:**
  - A `SelectControl` "Quantity" (`quantityDisplay`: Inline, Badge).
  - While `badge` is chosen and thumbnails are off, help text reads "Shown inline while thumbnails are off."

Steps: expand after Phase 1 lands. The verification must cover:
- a quantity of 1 and of 12;
- the badge with thumbnails off, which shows `× N`;
- the screen-reader text, through the accessibility tree in DevTools;
- the state after a billing-country change.

### Task 3: Verify the phase exit criterion

**Files:** none

Steps: expand after Phase 1 lands. The check:
1. In the editor, set `itemData` to `inline`, turn on SKU, unit price and short description, and set Quantity to `badge`.
2. On the storefront, with a variation (quantity 2) and a simple product with a SKU in the cart, the canvas and the storefront show the same details in the order the README gives.
3. A billing-country change leaves them unchanged.
4. The order places.
