# Live receipt's mock footer (PAYMENT, barcode, details filled) has no block yet

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** fidelity Out list while building template 07 Live receipt, 2026-10-15, branch `onyx/feat/template-07-live-receipt`.

## Today

- `templates/layouts/live-receipt/markup.php` prints a monospace receipt with Field Value lines for contact and address, then Order Summary Items, Breakdown and Total. Below the card: Coupon Form, the terms sentence, Terms & Privacy and Place Order.
- `yaycheckout/field-value` only shows layout fields (Billing/Shipping Field blocks, `billing_email`, Extra fields) via `Renderer\ShowableFields`. It cannot read a payment method title or card last four digits; the card form is drawn by the gateway inside `yaycheckout/payment`.
- `Renderer\LiveValues::cart_values()` lists cart money and counts only (`cart_total`, `cart_subtotal`, `cart_shipping_total`, …). There is no live value for the selected payment method, nor a count of how many checkout details the shopper has filled.
- The mock's barcode and the line `0 OF 6 DETAILS FILLED` are decorative / progress UI with no matching block or token.

## Why it matters

A merchant who picks Live receipt from the library gets a receipt that stops after the totals. Next to the HTML mock, the footer looks unfinished: no PAYMENT line, no barcode, no fill progress. Shoppers lose the "check it before you pay" cue the mock was designed around.

## Suggested fix

Design and plan before coding (template branch stays free of new blocks):
1. A live value (or small block) for the selected payment method's label — not the card number, which stays with the gateway.
2. A progress indicator for how many of a fixed set of details are non-empty (the mock's "N of 6"), or an accepted decision to drop that line.
3. Optional decorative barcode markup in the receipt card if the receipt look still needs it after (1)–(2).

## Done when

- [ ] `templates/layouts/live-receipt/markup.php` prints a PAYMENT section whose text updates with the selected payment method's title (no card number from the gateway).
- [ ] The live-receipt markup either prints an "N of M details filled" (or equivalent) indicator that updates as the shopper fills fields, or `docs/architecture.md`'s Live receipt sentence records that the mock's progress line is omitted on purpose.
- [ ] The live-receipt markup either includes a decorative barcode under PAYMENT, or the same architecture sentence records that the barcode is omitted on purpose.
