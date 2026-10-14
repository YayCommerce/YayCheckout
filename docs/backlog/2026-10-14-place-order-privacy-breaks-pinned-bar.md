# Place Order’s WooCommerce privacy text breaks the accordion pinned bar

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** fidelity pass on Accordion one-page against the HTML mock, 2026-10-14, branch `onyx/feat/template-04-accordion-one-page`.

## Today

- `packages/blocks/src/place-order/render.php` wraps the submit control in `.form-row.place-order` and, when a cart exists, always loads WooCommerce’s `checkout/terms.php` before the button (then `woocommerce_review_order_before_submit` / `woocommerce_order_button_html` / `woocommerce_review_order_after_submit` and the checkout nonce). That template prints the long “Your personal data will be used…” privacy sentence when WooCommerce’s checkout privacy setting is on.
- Accordion one-page puts Place Order beside the total in the pinned Summary Header: `templates/layouts/accordion-one-page/markup.php` makes the total group and the Continue / Place Order group direct children of `yaycheckout/order-summary-header`, which uses flex `space-between` (`packages/blocks/src/order-summary-header/block.json`).
- With the privacy paragraph inside the same Place Order wrapper, the header’s right-hand flex item grows and wraps, so Place Order no longer sits on the right like the mock.

## Why it matters

On Accordion one-page (and any layout that puts Place Order in a tight flex row with the total), shoppers see a left-stacked privacy wall and a misplaced Place Order whenever WooCommerce shows privacy text on checkout. The mock’s pinned bar has no such paragraph next to the button.

## Suggested fix

Keep the classic-checkout contract (terms, hooks, nonce) but stop the privacy/terms markup from sharing the flex item that must stay button-sized in a Summary Header row—for example print terms outside that row, or style/structure `.form-row.place-order` so the privacy block cannot stretch or wrap the header’s `space-between`. A template-only hide is not enough: the same Place Order block is used elsewhere.

## Done when

- [ ] With WooCommerce’s checkout privacy text enabled, Accordion one-page’s pinned Summary Header still shows the total on the left and Place Order on the right (no privacy paragraph between them in that row).
- [ ] `packages/blocks/src/place-order/render.php` still runs WooCommerce’s `checkout/terms.php` (or an equivalent that keeps the privacy/terms content available on checkout) and the review-order before/after-submit hooks plus the process-checkout nonce.
