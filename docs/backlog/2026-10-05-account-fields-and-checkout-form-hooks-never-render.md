# WooCommerce's checkout form hooks never fire, so account fields, login, coupon and order notes are missing

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`templates/checkout-page.php` renders the layout with `do_blocks()`, and `packages/blocks/src/checkout/render.php` prints the form. The only WooCommerce form hooks fired are `woocommerce_checkout_before_order_review`, `woocommerce_checkout_order_review` and `woocommerce_checkout_after_order_review` (Order review block), `woocommerce_review_order_before_submit` and `woocommerce_review_order_after_submit` (Place order block), and `woocommerce_review_order_before_payment` and `woocommerce_review_order_after_payment` (`CheckoutFragments::payment_methods_html()`). These hooks from `../woocommerce/templates/checkout/form-checkout.php` and `WC_Shortcode_Checkout::checkout()` (`../woocommerce/includes/shortcodes/class-wc-shortcode-checkout.php`) never fire:

- `woocommerce_before_checkout_form`, which carries `woocommerce_checkout_login_form`, `woocommerce_checkout_coupon_form` and `woocommerce_output_all_notices` (`../woocommerce/includes/wc-template-hooks.php`);
- `woocommerce_before_checkout_form_cart_notices` and `woocommerce_check_cart_items`;
- `woocommerce_checkout_before_customer_details` and `woocommerce_checkout_after_customer_details`;
- `woocommerce_checkout_billing` and `woocommerce_checkout_shipping`, which `WC_Checkout::instance()` wires to `../woocommerce/templates/checkout/form-billing.php` (the "Create an account?" box and account fields) and `../woocommerce/templates/checkout/form-shipping.php` (order notes);
- `woocommerce_after_checkout_form`.

With guest checkout off, registration at checkout on and "generate password" off, `WC_Checkout::initialize_checkout_fields()` adds a required `account_password`, and `WC_Checkout::maybe_skip_fieldset()` validates it for every guest. With guest checkout and registration both off, the Checkout block prints "You must be logged in to checkout." WooCommerce's own login form still does not render; when the checkout login reminder is on, `checkout/render.php` prints the layout's `yaycheckout/login` block, or a default one, above that sentence. `CheckoutHookScanHelper::HOOKS` lists four of the unfired hooks, so the compatibility scan reports plugins on them as touching this checkout. No version fired them: not the renderer's first (`5a73efe`), nor the blocks' first (`6bff9db`).

As of 2026-10-05, `onyx/feat/checkout-hook-blocks` adds before/after-checkout-form blocks and a Hook block the merchant places, all opt-in per layout, and `alan/Blocks-Coupon-Form` adds a coupon-form block. Neither renders the account fields or order notes. Found by reading the code; not reproduced.

As of 2026-10-06 the default layout (`LayoutRepository::default_markup()`) is a header, a two-column Checkout (Express Pay, Contact, Billing, Shipping, Payment, Place Order; Order Summary and Coupon Form) and a footer. It still has no account fields, order notes, login form or before/after-form hooks. It no longer uses Order Review, so `woocommerce_checkout_before_order_review`, `woocommerce_checkout_order_review` and `woocommerce_checkout_after_order_review` don't fire on it either.

## Why it matters

**Severity:** high: it breaks a shopper's checkout or loses their data.

A store that requires an account with a password the shopper chooses rejects every guest order with "Create account password is a required field.", and there is no field to fill. A store with optional registration loses its checkout sign-ups. Shoppers can't log in, apply a coupon or leave order notes at checkout. Plugins that print through these hooks show nothing, while the compatibility scan suggests they work.

## Suggested fix

Decide which hooks a layout always fires and which the merchant places. Account fields can't be opt-in when the store's settings require them: render the account section of WooCommerce's form-billing.php whenever registration is enabled and the shopper is a guest. Fire `woocommerce_before_checkout_form` and `woocommerce_after_checkout_form` around the layout in `templates/checkout-page.php`, or put the hook blocks from `onyx/feat/checkout-hook-blocks` in the default layout. Render `order_comments` when order notes are enabled, as a block or by default. Then make `CheckoutHookScanHelper::HOOKS` list only hooks the renderer fires.

## Done when

- [ ] With guest checkout off, registration at checkout on and "generate password" off, a guest on the default layout can place an order after filling a "Create account password" field.
- [ ] With guest checkout on and registration at checkout on, a guest on the default layout sees the "Create an account?" box.
- [x] With guest checkout and registration at checkout both off and the checkout login reminder on, a logged-out visitor sees a login reminder on the checkout page.
- [ ] `woocommerce_before_checkout_form` fires once on a checkout page that uses the default layout.
- [ ] `woocommerce_after_checkout_form` fires once on a checkout page that uses the default layout.
- [x] With coupons enabled, a shopper can apply a coupon on a checkout page that uses the default layout.
- [ ] With order notes enabled, an order note a shopper enters on the default layout appears on the order in wp-admin.
- [ ] Every hook in `CheckoutHookScanHelper::HOOKS` runs on a checkout page view or submit that uses the default layout.
