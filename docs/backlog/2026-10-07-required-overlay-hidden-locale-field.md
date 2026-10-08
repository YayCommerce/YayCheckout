# The required overlay keeps a field required when WooCommerce hides it for the shopper's country

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** found while browser-testing Remember value on the Extra field block, 2026-10-07, branch `onyx/feat/extra-field-remember-value`.

## Today

- `Renderer\CheckoutRequiredFields::filter_fields()` runs `Services\RequiredFieldOverlay::apply()` on `woocommerce_checkout_fields` at 9999. `RequiredFieldOverlay::is_required()` sets `required` from the layout block alone and ignores WooCommerce's own definition, including its `hidden` flag.
- WooCommerce builds each address field from `WC_Countries::get_address_fields()` merged with `WC_Countries::get_country_locale()` for the shopper's country. For some countries the locale sets `'hidden' => true`; for VN it does so for `state`. `address-i18n.js` then turns the State field into an empty `<input type="hidden">` and hides its row.
- `assets/js/checkout-field-requirements.js` (`applyRequired()`) puts `validate-required` and the required mark back on every field the layout marks required, hidden or not.

## Why it matters

A shopper who picks a country whose locale hides a field the layout marks required, such as Vietnam with a required State block, can't check out. WooCommerce rejects the order with "Billing State / County is a required field." for a field the shopper can't see or fill in. Reproduced on layout 61 with billing country VN.

## Suggested fix

In `RequiredFieldOverlay::apply()`, leave `required` false when WooCommerce's field for that country has `hidden` set. `WC_Checkout::get_checkout_fields()` builds the fields for the posted or session country, so the flag is already in the `$fields` the overlay receives. On the client, `applyRequired()` should skip a field whose row WooCommerce hid. The overlay was built to beat WooCommerce's country rules on `required`, and that is still right for visible fields; only a field the locale hides can't be filled in.

## Done when

- [ ] `RequiredFieldOverlay::apply()` returns `required => false` for a field whose WooCommerce definition has `hidden => true`, even when the layout marks it required.
- [ ] `assets/js/checkout-field-requirements.js` `applyRequired()` adds no `validate-required` class to a field row that `address-i18n.js` hid.
- [ ] On a layout with a required State block, checking out with billing country VN creates the order without a State error.
