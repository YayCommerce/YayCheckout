# The checkout form names the layout that rendered it, and its AJAX and submit follow it

**Purpose:** why a layout's checkout form carries the layout's id, and why the requests that carry the form follow that id instead of choosing a layout afresh.

**Update when:** never edit it. To change the decision, write a record that supersedes it.

## Status

Proposed, 2026-10-14.

## Context

- One checkout form takes several requests: the page render, `update_order_review` AJAX calls while the shopper fills it in, and the order submit (`wc-ajax=checkout`). Each request picked the layout afresh, and nothing in the POST said which layout had rendered the form. That was safe while the answer could only change when the merchant changed something, as every `Enums\ConditionType` reads a fact that holds for the life of a form.
- Safe mode made the answer change on its own. `Renderer\CheckoutHealth` reads the circuit breaker, which another shopper's failed render can trip, which expires after an hour, and store facts such as the compatibility catalogue, plugins and gateways. So a form's later requests could reach a different answer than its page did:
  - a shopper filling in the layout's form, while another shopper trips the breaker: the submit was checked by WooCommerce's field rules against the layout's form, so fields the layout hides or makes optional were refused and its extra fields were not saved;
  - a shopper filling in WooCommerce's form while the breaker was on, when it expires: the submit was checked by the layout's rules, so the layout's required extra fields, absent from that form, blocked the order, and its AJAX swapped the layout's fragments into WooCommerce's page.
- The breaker had to block every shopper for any render failure, so that the requests of one form agreed, even when the failure came from third-party code (totals, gateways, hooks) for one cart only.
- Options turned down:
  - pinning the layout in the WooCommerce session: a session can hold several tabs and outlives the form, and the block checkout's Store API has its own;
  - checking the breaker and store facts only on the page render: a form rendered by WooCommerce would then be submitted against the layout;
  - letting the posted id choose any active layout: a shopper could pick the layout whose rules suit them.

## Decision

- The Checkout block prints a hidden `CheckoutPage::FORM_FIELD` (`yaycheckout_layout`) in the form, holding the id of the layout that rendered it.
- A request that carries the form, the order submit (it posts `woocommerce-process-checkout-nonce`) or `update_order_review` (its `post_data`), follows the form: it gets the layout only when that layout is the one the shopper's facts pick and its id is the one posted, and WooCommerce's checkout otherwise, as WooCommerce's own form has no such field. Safe mode, `CheckoutHealth` and the breaker are judged on the page render only.
- Every other request, the page render and YayCheckout's own AJAX included, picks the layout as before.
- Because a form keeps the renderer it started with, a render failure on the page blocks only that request; it is reported, not recorded as blocking. A fatal error mid-render, which can't fall back in its own request, and a failing `update_order_review` fragment, whose reload must land on WooCommerce's form, still block the layout for later requests.

## Consequences

- The page, AJAX and submit of one form always use the same rules, whatever the breaker, the catalogue or safe mode do meanwhile, and whichever renderer the form started with, including the block checkout, whose Store API requests carry no id.
- One shopper's failed render no longer sends every shopper to WooCommerce's checkout for an hour.
- A form rendered before a merchant change still submits against the layout it was rendered with, as long as that layout is still the one picked; one picked no longer, or deactivated, sends the submit to WooCommerce's rules, as before.
- A shopper who removes the field gets WooCommerce's rules, which are stricter, not looser. One who adds it to WooCommerce's form gets the rules of the layout they would have been served.
- A form cached or rendered before the field existed submits against WooCommerce's rules.
