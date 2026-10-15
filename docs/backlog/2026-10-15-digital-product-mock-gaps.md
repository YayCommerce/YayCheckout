# Digital product's mock still has regions no block can print

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** fidelity Out list while building template 12 Digital product, 2026-10-15, branch `onyx/feat/template-12-digital-product` (commit `18aa013`).

## Today

`templates/layouts/digital-product/markup.php` is a centred card: Return to cart, Order Summary (items, coupon, breakdown, total) on the left; Express Pay, Email, Payment, billing country and postcode, a VAT Extra Field, Place Order and the terms sentence on the right. The file's docblock already leaves out the license picker, the product hero and the drawn card form.

- There is no block that selects a product variation and updates the cart. `yaycheckout/extra-field` with `type` `radio` only stores order meta (`Renderer\CheckoutExtraFields`).
- `yaycheckout/order-summary-items` prints the cart line (thumbnail, name, optional short description, price). `quantityDisplay` is only `inline` or `badge`, so the quantity still prints. It has no hero panel, download label, file size or version.
- `yaycheckout/order-summary-breakdown` has `subtotalLabel` as a static string. Its render (`CheckoutFragments::print_summary_block`) follows WooCommerce's totals, including a shipping row when the cart has one, and the tax label is WooCommerce's, not a rate taken from the billing country.
- `yaycheckout/return-to-cart` always links to `wc_get_cart_url()` (`return-to-cart/render.php`). The template's label is "Back to Storefront".
- `yaycheckout/express-pay` prints the buttons gateways add on `ExpressPay::HOOK`. `yaycheckout/payment` prints the gateway list (`CheckoutFragments::print_payment_methods`), not card number, expiry, CVC or brand marks.
- Billing is two separate Field blocks (`billing_country`, `billing_postcode`). The VAT control is `yaycheckout/extra-field` `vat_number` with a disclosure link; it does not change the tax WooCommerce calculates.
- The template ships `yaycheckout/terms`. With a Terms page set, that block prints WooCommerce's required checkbox (`docs/architecture.md`, Terms and privacy).

## Why it matters

A merchant who picks Digital product gets a working checkout, but next to the HTML mock the left column is a normal cart line instead of a download hero and a license that reprices the order. The back link, the tax label and the quantity marker also disagree with the mock. Shoppers cannot buy the Personal / Professional / Team choice the mock is built around.

## Suggested fix

Design and plan each missing block before adding it. Keep the template branch free of new block code.

1. A license control that selects a variation (or equivalent) and updates the cart line and the subtotal label.
2. A product hero on the summary for downloadable goods (label, file meta, version), or an accepted note in `docs/architecture.md` that the cart line stands in for it.
3. Let Order Summary Items hide the quantity, and let Breakdown omit the shipping row, then use both in this template.
4. Point "Back to Storefront" at the shop, not the cart.
5. Leave the drawn card form and the fixed Apple Pay / PayPal pair with the gateway. Record that in the Digital product sentence in `docs/architecture.md` if it is not already clear. The VAT field stays a stored number unless a later design makes it affect tax.

## Done when

- [ ] `templates/layouts/digital-product/markup.php` includes a control whose selected option changes the cart line price (not only `yaycheckout/extra-field` order meta).
- [ ] The same markup prints a download hero (label, file size or version) above the product name, or the Digital product sentence in `docs/architecture.md` says the cart line replaces that hero.
- [ ] The order summary subtotal label in that markup follows the selected license, or the same sentence says the label stays WooCommerce's subtotal.
- [ ] `yaycheckout/order-summary-items` can hide the quantity, and the digital-product markup does so.
- [ ] `yaycheckout/order-summary-breakdown` can omit its shipping row, and the digital-product markup does so.
- [ ] The digital-product Return to cart control links to the shop page rather than `wc_get_cart_url()`.
- [ ] The Digital product sentence in `docs/architecture.md` says the card number, expiry, CVC and the fixed wallet buttons stay with the payment gateway.
