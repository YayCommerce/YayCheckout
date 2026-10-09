# Phase 3: Quantity stepper

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** Phase 2 Task 2 (`quantityDisplay` and `quantity_display`), and Phase 1 Task 2 (the fragment refreshing through `update_order_review`)
**Delivers:** in `stepper` mode, a shopper changes an item's quantity or removes it on the checkout, and the whole checkout refreshes
**Exit criterion:** `composer test -- --filter CartItemQuantityTest` passes, and acceptance criterion 9 in the README passes on the storefront

### Task 1: Cart write endpoint and stepper markup

**Files:**
- Create: `includes/Renderer/CartItemQuantity.php`
- Test: `tests/Integration/Renderer/CartItemQuantityTest.php`
- Modify: `includes/YayCheckout.php` (boot it beside `CheckoutFragments`), `includes/Renderer/CheckoutFragments.php` (`order_items_options()`, `order_items_html()`, `print_summary_block()`), `packages/blocks/src/order-summary-items/{block.json,edit.tsx,style.css}`

**Interfaces:**
- Consumes: `quantity_display` (Phase 2 Task 2).
- Produces: `CartItemQuantity` with its constants, `apply()`, the request and the response, the stepper and remove markup, the wrapper's `data-yaycheckout-cart-nonce` and `data-error-text`, and `.yaycheckout-order-summary-items__notices` (see the README contract).

Decisions that pin behaviour:
- **`CartItemQuantity`:** a singleton (`SingletonTrait`) whose constructor adds `wc_ajax_` . `AJAX_ACTION` → `handle(): void`.
- **`handle()`:**
  1. `check_ajax_referer( self::NONCE_ACTION, 'security' )`.
  2. It answers `wp_send_json_error` with a generic message when `CheckoutPage::get_instance()->layout()` is null or `WC()->cart` isn't a `WC_Cart`.
  3. It reads `cart_item_key` with `wc_clean()` and `quantity` with `wc_stock_amount()`, then calls `apply()`.
  4. On success it sends `cart_empty` and `redirect`. On refusal it sends `message`.
- **`apply()`**, in this order:
  1. An unknown key is refused with `__( 'This item is no longer in your cart.', 'yaycheckout' )`.
  2. A quantity below 0 is refused.
  3. A quantity of 0 calls `remove_cart_item()`.
  4. A quantity equal to the current one is a no-op.
  5. A sold-individually product above 1 is refused with WooCommerce's `'You can only have 1 %s in your cart.'`.
  6. A quantity above a positive `get_max_purchase_quantity()` is refused with `/* translators: 1: maximum quantity, 2: product name */ __( 'Only %1$s of %2$s can be bought.', 'yaycheckout' )`.
  7. When `apply_filters( 'woocommerce_update_cart_validation', true, $key, $values, $quantity )` is false, the change is refused. The message is the text of the error notices the filter added (read, then cleared, with `wc_get_notices( 'error' )` and `wc_clear_notices()`), or `__( 'The quantity could not be updated.', 'yaycheckout' )` when there are none.
  8. Otherwise it calls `set_quantity( $key, $quantity, true )`.
  9. It always ends with `$cart->calculate_totals()` after a change, since `remove_cart_item()` alone doesn't persist.
- **Stepper markup** (`quantity_display` `stepper`):
  - **Stepper:** when `get_min_purchase_quantity() !== get_max_purchase_quantity()` and the product isn't sold individually, print `.yaycheckout-qty`, passed through `apply_filters( 'woocommerce_cart_item_quantity', $html, $cart_item_key, $cart_item )`. Otherwise print `inline`.
  - **Input:** `min="1"`, plus `max` when the maximum is positive, and no `name`. The + button is `disabled` at the maximum, and the − button at 1.
  - **Remove link:** always printed, through `apply_filters( 'woocommerce_cart_item_remove_link', $link, $cart_item_key )`, with `href="wc_get_cart_remove_url( $cart_item_key )"` so it still works without JavaScript.
  - **ARIA labels:** "Decrease quantity of %s", "Increase quantity of %s", "Quantity of %s" and "Remove %s from cart", each filled with the product name stripped of tags.
- **`print_summary_block()`:** in `stepper` mode only, the wrapper gets `data-yaycheckout-cart-nonce="wp_create_nonce( CartItemQuantity::NONCE_ACTION )"` and `data-error-text` (`__( 'The quantity could not be updated. Please try again.', 'yaycheckout' )`). The empty notices `div` goes after the fragment, inside the wrapper.
- **`block.json`:** add `stepper` to `quantityDisplay`'s enum.
- **`edit.tsx`:** add the Stepper option. The canvas shows the stepper and remove link with `disabled` controls.
- **`style.css`:** a 32px-tall stepper with a 32px-square border, using `currentColor` and `--yaycheckout-field-radius`.

Test (accepted). `CartItemQuantityTest extends WP_UnitTestCase`, in namespace `YayCheckout\Tests\Integration\Renderer`. `set_up()` calls `wc_load_cart()` and `WC()->cart->empty_cart()`. Each test adds one simple product (`new \WC_Product_Simple()`, `set_regular_price( '10' )`, `save()`) at quantity 1 with `WC()->cart->add_to_cart()`, keeps the returned key, and asserts:
- `test_unknown_key_is_refused`: `apply( $cart, 'nope', 2 )` returns a non-null string, and the cart is unchanged.
- `test_zero_removes_the_item`: `apply( $cart, $key, 0 )` returns null, and `$cart->get_cart_item( $key )` is `[]`.
- `test_sets_the_quantity`: `apply( $cart, $key, 3 )` returns null, and the item's `quantity` is `3`.
- `test_above_max_purchase_quantity_is_refused`: with `set_manage_stock( true )`, `set_stock_quantity( 3 )` and backorders `no`, `apply( $cart, $key, 4 )` returns a non-null string, and the quantity stays `1`.
- `test_sold_individually_stays_at_one`: with `set_sold_individually( true )`, `apply( $cart, $key, 2 )` returns a non-null string, and the quantity stays `1`.
- `test_validation_filter_veto_keeps_quantity`: with `add_filter( 'woocommerce_update_cart_validation', '__return_false' )`, `apply( $cart, $key, 2 )` returns `'The quantity could not be updated.'`, and the quantity stays `1`.

Steps: expand after Phase 2 lands. The task ends with `composer test -- --filter CartItemQuantityTest` passing and `composer lint -- includes/Renderer/CartItemQuantity.php includes/Renderer/CheckoutFragments.php includes/YayCheckout.php tests/Integration/Renderer/CartItemQuantityTest.php` clean. A temporary mu-plugin that filters `woocommerce_cart_item_quantity` to return `'LOCKED'` must show `LOCKED` in place of the stepper.

### Task 2: Storefront script

**Files:**
- Create: `assets/js/checkout-order-summary-items.js`
- Modify: `includes/Blocks/BlockRegistry.php` (`ORDER_SUMMARY_ITEMS_SCRIPT`, `register_view_scripts()`), `packages/blocks/src/order-summary-items/block.json` (`"viewScript": [ "yaycheckout-order-summary-items" ]`)

**Interfaces:**
- Consumes: `CartItemQuantity`'s request and response, and the stepper markup and wrapper data attributes (Task 1, and the README contract).
- Produces: nothing later tasks use.

Decisions that pin behaviour:
- **Shape:** a jQuery IIFE in the style of `checkout-coupon-form.js`. It delegates from `.wp-block-yaycheckout-order-summary-items`, because the fragment inside it is replaced, and ignores any wrapper without `data-yaycheckout-cart-nonce`.
- **Triggers:**
  - A `.yaycheckout-qty__button` click sends `current + data-step`.
  - The input's `change` event sends its value. A value below 1, or one that isn't an integer, restores the current value and sends nothing.
  - Enter in the input calls `preventDefault()` and commits, so the checkout isn't submitted.
  - A `.yaycheckout-order-summary-items__remove` click calls `preventDefault()` and sends 0.
- **Request:** POST to `wc_checkout_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'yaycheckout_cart_item_quantity' )` with `security`, `cart_item_key` and `quantity`. The preview token rides along in that URL.
- **Pending:** one request per wrapper at a time. While it runs, the wrapper has `aria-busy="true"`, and the item's controls are disabled. Clicks during a request are ignored.
- **Success:** empty the notices.
  - If `cart_empty`, go to `window.location = redirect`.
  - Otherwise, fire `update_checkout` on `document.body`, then `wc_fragment_refresh`, so a mini cart updates.
- **Refusal or failure:** print `message` (or `data-error-text`) as `<p role="alert">` in the notices, then fire `update_checkout`, so the row shows the real quantity again.
- **`removed_coupon_in_checkout`:** not needed here.

Steps: expand after Phase 2 lands. The verification is the phase exit criterion (README acceptance criterion 9), plus:
- as a logged-out shopper in a private window, + works, so the nonce verifies;
- Enter in the quantity input doesn't place the order;
- a fast double click on + raises the quantity by exactly 1 per completed request;
- a `woocommerce_update_cart_validation` veto from a temporary mu-plugin shows its message under the items, and the quantity stays put;
- removing the last item lands on the cart page with WooCommerce's empty-cart message;
- in a layout preview link with sample products, + changes a sample item, and the sample items remain afterwards.

### Task 3: Docs

**Files:**
- Modify: `docs/architecture.md` (the Writers line at :39, and a Storefront entry beside "Coupon form" at :67), `.claude/rules/php-standards.md:38` (the Storefront exceptions)

**Interfaces:** none

- **Writers line:** add "`Renderer\CartItemQuantity` changes cart item quantities and removes items from the shopper's cart through its wc-ajax action".
- **Storefront entry:**
  - Why our own wc-ajax action: WooCommerce's checkout has no endpoint that sets a quantity.
  - Which WooCommerce rules and filters `apply()` keeps.
  - Why the script fires `update_checkout` instead of building fragments itself.
  - That an emptied cart redirects to the cart page.
  - That the script is registered as `BlockRegistry::ORDER_SUMMARY_ITEMS_SCRIPT` for the same reason as the coupon form's.
- **php-standards.md:** add `CartItemQuantity` as the third exception to "`Renderer/` classes only read".

Steps: expand after Phase 2 lands. Commit the docs with Task 2, or on their own as `docs(order-summary): describe the Items quantity stepper`.
