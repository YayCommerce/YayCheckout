# The Shipping block discards the shipping address the shopper types

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`packages/blocks/src/shipping/render.php` prints a `div.yaycheckout-shipping`, an optional heading and its inner Field blocks. It prints no `ship_to_different_address` input, no `div.shipping_address` and no `woocommerce-shipping-fields` class, and the block has no view script that adds them.

- In `../woocommerce/includes/class-wc-checkout.php`, `WC_Checkout::get_posted_data()` reads `ship_to_different_address` as false. `WC_Checkout::maybe_skip_fieldset()` then skips the shipping fieldset, every `shipping_*` value is copied from its `billing_*` twin, and `WC_Checkout::validate_posted_data()` skips the shipping fields.
- `update_checkout_action()` in `../woocommerce/assets/js/frontend/checkout.js` sends the billing address as the `s_*` address unless `#ship-to-different-address input` is checked, so shipping rates are computed for the billing address.
- Without the `woocommerce-shipping-fields` wrapper, `../woocommerce/assets/js/frontend/country-select.js` falls back to the country field's own row. `#shipping_state` never repopulates, and `../woocommerce/assets/js/frontend/address-i18n.js` never relabels the shipping fields.
- The section also renders for a virtual-only cart and when the store ships to the billing address only.

The file's docblock still describes the checkbox and the early return. The `shipToDifferent` attribute in `packages/blocks/src/shipping/block.json`, and its toggle in `packages/blocks/src/shared/address-section-edit.tsx`, are read by nothing. Introduced by `d617a00`, which deleted what `5a73efe` had built; it is on `main`, and as of 2026-10-05 no branch restores it. Found by reading the code; not reproduced.

## Why it matters

**Severity:** high: it breaks a shopper's checkout or loses their data.

The default layout from `LayoutRepository::default_markup()` has a Shipping block. A shopper who types a different delivery address is quoted and charged rates for the billing address, and the order ships to the billing address. The typed address is dropped without an error, so neither the shopper nor the merchant knows until the parcel goes to the wrong place.

## Suggested fix

Restore what `d617a00` removed from `packages/blocks/src/shipping/render.php`: the early return when `WC()->cart->needs_shipping_address()` is false or `wc_ship_to_billing_address_only()` is true, the `#ship-to-different-address` checkbox copied from `../woocommerce/templates/checkout/form-shipping.php`, the `div.shipping_address` around the inner blocks, and the `woocommerce-shipping-fields` class on the wrapper. Keep the plain render where there is no cart, so the editor preview still works. Then decide what `shipToDifferent` means, for example whether the box starts ticked, and read it; or delete the attribute and its toggle. Fix it together with `docs/backlog/2026-10-05-address-fields-never-refresh-totals-or-prefill.md`, which has the same cause.

## Done when

- [x] `packages/blocks/src/shipping/render.php` prints an input named `ship_to_different_address` inside `#ship-to-different-address`. — shipping/render.php, `AddressRenderTest`
- [x] `packages/blocks/src/shipping/render.php` wraps the inner blocks in `div.shipping_address`. — shipping/render.php, `AddressRenderTest`
- [x] The Shipping block's wrapper carries the `woocommerce-shipping-fields` class. — shipping/render.php, `AddressRenderTest`
- [x] `packages/blocks/src/shipping/render.php` prints nothing when `WC()->cart->needs_shipping_address()` is false or `wc_ship_to_billing_address_only()` is true. — `AddressRenderTest` virtual-cart and billing-only tests
- [x] `packages/blocks/src/shipping/render.php` reads `shipToDifferent`, or the attribute and its toggle in `packages/blocks/src/shared/address-section-edit.tsx` are gone. — attribute and toggle deleted
- [x] On the default layout, a guest who ticks "Ship to a different address?" and enters a shipping address in another shipping zone sees that zone's rates in Order review. — US shipping address quoted `flat_rate:3` ($50) with AE billing
- [x] An order placed that way shows, in wp-admin, the shipping address the guest typed. — order 716: shipping 500 Market St, San Francisco CA 94105 US
- [x] On the default layout, changing the shipping country to the United States fills `#shipping_state` with US states. — `#shipping_state` had 55 options
