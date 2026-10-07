# Address fields never refresh totals and never show the customer's saved address

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`packages/blocks/src/field/render.php` calls `woocommerce_form_field()` with no `class` argument and no value, and `packages/blocks/src/email/render.php` passes no value either, so the value falls back to `''`.

- **No value.** WooCommerce's `../woocommerce/templates/checkout/form-billing.php` passes `$checkout->get_value( $key )`. `WC_Checkout::get_value()` reads the posted value, the `woocommerce_checkout_get_value` filter, the logged-in customer's saved address, then the `default_checkout_{key}` filter. The blocks have never passed it, since their first version in `6bff9db`.
- **No refresh classes.** `wc_checkout_form.init()` in `../woocommerce/assets/js/frontend/checkout.js` binds its refresh handlers to controls inside `.address-field` and `.update_totals_on_change`, plus `#ship-to-different-address input` and the shipping-method inputs. `d617a00` stopped passing `address-field` and `update_totals_on_change`. The comment it deleted said that without them "a country or postcode change never updates shipping and totals".

Found by reading the code; not reproduced.

## Why it matters

**Severity:** high: it breaks a shopper's checkout or loses their data.

- A logged-in customer sees empty fields and retypes their address on every order.
- The country select loads empty, so the page-load refresh posts `country=''` and the order review is priced for no address (inferred).
- Typing or changing the address never refreshes rates, taxes or payment methods.
- At submit, `WC_Checkout::update_session()` recalculates from the posted address. The shopper can be charged shipping or tax the page never showed, or get "No shipping method has been selected."

## Suggested fix

Render each Field from WooCommerce's own definition: take the key's entry from `WC()->checkout()->get_checkout_fields()`, lay the layout's label, placeholder and required flag over it, and call `woocommerce_form_field( $key, $args, WC()->checkout()->get_value( $key ) )`. WooCommerce then owns the classes, the value and the country locale; the layout owns order, labels and the required override. The smaller fix restores the classes `d617a00` removed and passes `get_value()`, but leaves every later WooCommerce change to be copied by hand. Fix it together with `docs/backlog/2026-10-05-the-shipping-block-discards-the-shipping-address.md`.

## Done when

- [ ] `packages/blocks/src/field/render.php` passes `WC()->checkout()->get_value()` for its Key as the third argument of `woocommerce_form_field()`.
- [ ] `packages/blocks/src/email/render.php` passes `WC()->checkout()->get_value( 'billing_email' )` as the third argument of `woocommerce_form_field()`.
- [ ] A `billing_*` or `shipping_*` Field renders with `address-field` among its `.form-row` classes.
- [ ] A country Field renders with `update_totals_on_change` among its `.form-row` classes.
- [ ] A logged-in customer with a saved billing address opens checkout on the default layout and sees that address filled in.
- [ ] As a guest on the default layout, the first `update_order_review` request in the browser's network tab posts a non-empty `country`.
- [ ] As a guest on the default layout, changing the billing postcode to one in another shipping zone updates the rates in Order review without a page reload.
