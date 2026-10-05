# Required address fields ignore WooCommerce's rules for the shopper's country

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`CheckoutRequiredFields::filter_fields()` runs on `woocommerce_checkout_fields` at priority 9999, after `WC_Countries::get_address_fields()` has merged `WC_Countries::get_country_locale()`. It calls `RequiredFieldOverlay::apply()`, which sets `required` on every billing and shipping field to `RequiredFieldOverlay::is_required()`: true for email, otherwise the layout's flag. The locale's own `required => false` is overwritten.

- **Hidden fields become required.** In `../woocommerce/includes/class-wc-countries.php` the UAE postcode and the Singapore state are `required => false, hidden => true`. The default layout seed, `LayoutRepository::address_field_blocks()`, marks state and postcode required. In the browser, `../woocommerce/assets/js/frontend/address-i18n.js` hides and clears the field; `assets/js/checkout-field-requirements.js` runs after it, re-adds `validate-required` and the asterisk, and leaves the field hidden. On submit, `WC_Checkout::validate_posted_data()` reports the empty field.
- **Omitted fields become optional.** A billing field the layout leaves out is made optional, as `RequiredFieldOverlay::apply()`'s docblock says, even when the merchant made it required in the Customizer's WooCommerce checkout options (for example Phone). Nothing tells the merchant.

Introduced by `40b734a`, which reached `dev/edd` in the squash merge `ff15893` (PR #26) and was split into the overlay in `d2dce1c`; not on `main`. Found by reading the code; not reproduced.

## Why it matters

**Severity:** high: it breaks a shopper's checkout or loses their data.

With the default layout, a shopper who picks the United Arab Emirates gets "Billing Postcode / ZIP is a required field." for a field they can't see, and can't order. Singapore fails the same way on State. The merchant sees no error, only fewer orders from those countries. Separately, a store that requires a phone number starts taking orders without one as soon as a layout leaves Phone out.

## Suggested fix

In `RequiredFieldOverlay::apply()`, never require a field that WooCommerce's locale hides for the fieldset's country, and apply the layout's flag only to fields the locale shows. The field definitions don't carry the flag (`wc_array_overlay()` drops locale keys the default fields lack, and `hidden` is one), so pass the country's entry from `WC_Countries::get_country_locale()` into `apply()`, which stays pure. Make `assets/js/checkout-field-requirements.js` skip a field that WooCommerce's address-i18n.js has hidden. For omitted fields, keep them optional (the shopper can't fill them), but name the WooCommerce-required billing fields the layout leaves out in the layout editor, so the merchant chooses. `RequiredFieldOverlay::apply()` is one of the risk areas CLAUDE.md's Tests rule names, so an integration test is warranted there; ask before adding it.

## Done when

- [ ] `RequiredFieldOverlay::apply()` leaves `required` false on a field that `WC_Countries::get_country_locale()` marks `hidden` for the fieldset's country.
- [ ] `assets/js/checkout-field-requirements.js` adds no `validate-required` class to a field that WooCommerce's address-i18n.js has hidden.
- [ ] On the default layout, a guest who picks United Arab Emirates as billing country places an order without filling a postcode.
- [ ] On the default layout, a guest who picks Singapore as billing country places an order without filling a state.
- [ ] With Phone set to required in the Customizer's WooCommerce checkout options, the layout editor names Phone when the layout has no `billing_phone` Field.
