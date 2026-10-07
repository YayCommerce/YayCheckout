# A layout that cannot take an order still publishes

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`LayoutMarkupReader::publish_error()` checks only that a Checkout block exists and that extra-field Keys are set, well formed and unique. `packages/blocks/src/checkout/publish-guard.ts` copies those rules, and `"multiple": false` in a block's block.json only stops that block repeating. These layouts publish:

- **Order review and Payment, without Place order.** Order review prints `../woocommerce/templates/checkout/payment.php`: gateways, Place order and the checkout nonce. When the layout has a `payment` slot, `CheckoutFragments::fragments()` replaces every `.woocommerce-checkout-payment` with the gateway-only HTML of `CheckoutFragments::payment_methods_html()` on the first refresh, and Place order and the nonce are gone.
- **A Slot named `payment`.** `LayoutMarkupReader::slots()` merges each Slot's `sanitize_key()`'d name with the section slots, so the default layout plus a Slot named `payment` triggers the same swap. `packages/blocks/src/slot/edit.tsx` accepts any name. The swap on slot names has been there since `873ae15` (PR #22); `63bf81b` and `38e7eba` moved it into `LayoutMarkupReader::slots()` and `CheckoutFragments::FRAGMENTS`.
- **An extra-field Key WooCommerce reads from the POST**, such as `payment_method`, `shipping_method`, `createaccount` or `order_comments`, or any `billing_`, `shipping_` or `account_` name (`WC_Checkout::get_posted_data()`). A `payment_method` field placed after the payment methods replaces the posted gateway, and the order fails with "Invalid payment method." (inferred from PHP keeping the last value posted under a name). `packages/blocks/src/shared/extra-field-keys.ts` reserves no names either.
- **A `billing_email` Field beside the Email address block.** The Field picker no longer offers `billing_email` (`billingAddressFields()` in `packages/blocks/src/shared/address-fields.ts`), but a layout saved before that change, imported, or edited in the Code editor can still hold such a Field, and `packages/blocks/src/field/edit.tsx` keeps a saved key selected. Both inputs post as `billing_email` and PHP keeps the last, so if the second is left empty the order fails with "Billing Email address is a required field."
- **No submit button**: a layout with neither Place order nor Order review, such as one with Payment alone.
- **No country field.** `RequiredFieldOverlay::apply()` makes the missing `billing_country` optional, and for a cart that needs shipping, `WC_Checkout::validate_checkout()` fails with "Please enter an address to continue."

Found by reading the code; not reproduced.

## Why it matters

**Severity:** high: it breaks a shopper's checkout or loses their data.

Each of these layouts looks complete in the editor. Once active, every shopper's checkout fails or has no button, and nothing tells the merchant until orders stop.

## Suggested fix

Grow `publish_error()` into the one composition validator, with one message per rule above, and have the editor ask it instead of keeping a copy in `packages/blocks/src/checkout/publish-guard.ts`. If virtual-only stores need a layout without an address, make the country rule a warning and rewrite its box. `LayoutImportReview` applies only the first rule, so imports meet the new ones at publish. `publish_error()` is one of the risk areas CLAUDE.md's Tests rule names, so an integration test is warranted there; ask before adding it.

## Done when

- [ ] `LayoutMarkupReader::publish_error()` refuses a layout with a Payment block and no Place order block.
- [ ] `LayoutMarkupReader::publish_error()` refuses a layout with neither a Place order block nor an Order review block.
- [ ] `LayoutMarkupReader::publish_error()` refuses a Slot whose name, after `sanitize_key()`, is a section slot name such as `payment`.
- [ ] `LayoutMarkupReader::publish_error()` refuses an extra-field Key that `WC_Checkout::get_posted_data()` reads, such as `payment_method`, and any Key starting `billing_`, `shipping_` or `account_`.
- [ ] `LayoutMarkupReader::publish_error()` refuses a `billing_email` Field in a layout that has an Email address block.
- [ ] `LayoutMarkupReader::publish_error()` refuses a layout with no `billing_country` Field.
- [ ] `packages/blocks/src/checkout/publish-guard.ts` takes its message from `publish_error()` and holds no copy of the rules.
