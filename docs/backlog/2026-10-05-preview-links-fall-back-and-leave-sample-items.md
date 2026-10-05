# Preview links fall back to the live layout and leave sample items behind

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`includes/Renderer/PreviewRequest.php` turns `?yaycheckout_preview=TOKEN` into a preview, but its edges leak:

- `PreviewRequest::read_token()` runs on `init` and sets the layout id for any URL with a valid token. `CheckoutPage::resolve()` uses it without asking `PreviewRequest::active()`, so the field and fragment filters use the preview layout on any request carrying the token.
- On a wc-ajax call with an expired or invalid token, the layout id is null. `PreviewRequest::guard_and_attach()` answers 410 only on the checkout page, so `CheckoutPage::resolve()` falls through to the live layout, and `CheckoutFragments::fragments()` refreshes the preview page with the live layout's fragments. Ordering stays disabled.
- `read_token()` hooks `PreviewRequest::refuse_order()` on `woocommerce_checkout_process`, which only the classic checkout fires, so a Store API checkout carrying the token isn't refused. An array-valued argument isn't read at all.
- `PreviewRequest::strip_sample_items()` returns early whenever the argument is present, valid or not, so an expired token keeps the sample items.
- `PreviewRequest::prefill_cart()` adds the sample products to the viewer's WooCommerce session and, for a logged-in viewer, to the `_woocommerce_persistent_cart_{blog_id}` user meta through `WC_Cart_Session::persistent_cart_update()` (`../woocommerce/includes/class-wc-cart-session.php`).
- `CheckoutPage::on_checkout_page()` excludes the order-received and order-pay endpoints; `PreviewRequest::on_checkout_page()` includes them.

Introduced by `ab7d0ed`. Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

A preview is the merchant, or someone they sent the link to, looking; the classic path refuses orders. But an expired link shows a mix of the previewed and live layouts instead of the 410. Sample products land in a real cart, and a hand-made Store API request with the token could order them (inferred). The two `on_checkout_page()` rules are nearly inert today, but a reader can't tell which one is right.

## Suggested fix

Return the 410 on wc-ajax too when the token doesn't resolve. Have `CheckoutPage::resolve()` use the preview layout only when `PreviewRequest::active()` is true. Refuse any order whose cart holds an item flagged `PreviewRequest::CART_ITEM_FLAG`, on the classic path and the Store API alike (for example through `woocommerce_store_api_cart_errors`), and strip sample items whenever the token doesn't resolve. Return false from `woocommerce_persistent_cart_enabled` while a preview fills the cart. Keep one `on_checkout_page()` rule.

## Done when

- [ ] `PreviewRequest::guard_and_attach()` refuses a wc-ajax request whose token doesn't resolve, as it does on the checkout page.
- [ ] `CheckoutPage::resolve()` returns the preview layout only when `PreviewRequest::active()` is true.
- [ ] A Store API checkout whose cart holds an item flagged `PreviewRequest::CART_ITEM_FLAG` is refused.
- [ ] `PreviewRequest::strip_sample_items()` removes sample items on a request whose token doesn't resolve.
- [ ] Opening a preview as a logged-in viewer with an empty cart leaves their `_woocommerce_persistent_cart_{blog_id}` user meta unchanged.
- [ ] `CheckoutPage` and `PreviewRequest` decide whether a request is the checkout page with one shared method.
