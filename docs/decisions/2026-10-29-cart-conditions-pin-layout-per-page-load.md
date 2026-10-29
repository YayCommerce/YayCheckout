# The cart may be an assignment condition, and the layout a checkout page load picks from it is pinned

**Purpose:** why Pro's assignment rules may test the cart (products, product categories), why the layout a checkout page load picks from the cart is pinned for that form's AJAX calls and its submit, and how the themeless theme is chosen before the cart is known.

**Update when:** never edit it. Supersede it with a new record if cart conditions go, if the pin moves out of the WooCommerce session or applies to every active set, or if the theme choice stops being a guess corrected by one reload.

## Status

Proposed, 2026-10-29.

## Context

- Every request picks the layout afresh, the wc-ajax calls and the order submit included, and checkout validates the order against the layout that request picks. So far, every condition read a fact that holds for the life of a form (role, login status, device), and `docs/architecture.md` ruled the cart out for that reason.
- Merchants want a layout per cart, for example a short one when the cart is only digital products. The cart changes mid-form: Order Summary Items' stepper changes or removes items, and another tab can add some. If the cart is re-read on every request, a stepper click can swap the layout and its required fields between render and submit.
- `ThemelessCheckout` decides at `setup_theme` whether to load the bundled theme, because the theme is included right after. WooCommerce starts the session and cart at `init` and loads the cart from the session at `wp_loaded`, and `product_cat` is registered at `init`. At `setup_theme`, only cookies are readable.
- If the theme is guessed wrong, the page breaks: the themeless theme with no layout prints WooCommerce's checkout without any theme styles. In the other direction, the layout renders under the site theme's CSS.
- Options turned down:
  - **Re-picking the cart on every request:** the layout swaps mid-form, as above.
  - **Pinning every active set:** sites without cart rules gain a session write and a cookie for nothing, and lose the current behaviour where a merchant's change takes effect on the next request.
  - **Reading WooCommerce's session table and term relationships with raw SQL at `setup_theme`:** it bypasses a custom `woocommerce_session_handler`, the persistent-cart merge, `?add-to-cart` and item validation. It would still guess wrong at times, so the correction would still be needed.
  - **Starting the WooCommerce session early:** `wc_load_cart()` refuses to run before `before_woocommerce_init`, and a handler swapped in at `init` would start a second session.
  - **Binding the theme hint to the `woocommerce_cart_hash` cookie:** the hash includes the totals, so it changes with every shipping update.
  - **"Contains all" meaning every listed value is in the cart:** the use case is "the cart consists only of these". Every-listed-value would also read differently for products and for categories.

## Decision

- Two Pro condition types read the cart: `ConditionType::PRODUCT` (a variation counts as its parent product) and `ConditionType::PRODUCT_CATEGORY` (a product's categories and all their ancestors). Their value is a list of ids.
- Their operators are `contains_any` (some item matches), `contains_all`, shown as "contains only" (the cart has items and every one matches), and `contains_none` (no item matches). An empty cart contains none of anything and is never "only" anything.
- While the active set has a cart condition (`LayoutRepository::depends_on_cart()`), a checkout page load picks from the shopper and the cart and pins the pick in the WooCommerce session (`LayoutPin::SESSION_KEY`, 0 for none). A page load is a GET that is not wc-ajax, on the checkout page, after `wp`.
- Every other request uses the pin while the pinned layout is in the active set; a pin of 0 stays "none". If the pinned layout has left the active set, or the request has no pin, it picks afresh and pins nothing. The next page load picks again.
- Without a cart condition in the active set, nothing changes: no pin, no cookie, and every request picks afresh. Free ignores rules, so it never pins.
- A signed, httponly, session-length cookie (`LayoutPin::COOKIE`) mirrors the pin for `setup_theme`. It only chooses the theme; the layout comes from the session.
- At `setup_theme`, `ThemelessCheckout` decides the theme like this:
  - The rules are evaluated three-valued (`LayoutAssignment::outlook()`), with the cart unknown.
  - On a page load, a certain yes or no decides; a maybe follows a valid hint.
  - On any other request, a valid hint decides.
  - Otherwise a maybe guesses themeless.
- When a page load's real pick disagrees with the theme that loaded, `CheckoutPage::maybe_repick()` redirects once, to the same URL with `LayoutPin::REPICK_ARG`. The pin is written by then, so the reload's guess is exact. If the guess is still wrong on the marked reload (cookies blocked), it logs a warning and renders as it is.

## Consequences

- Merchants can serve layouts by cart content, and a shopper's form keeps its layout, with that layout's required fields, through stepper changes up to the submit.
- A cart change takes effect on the next checkout page load. A shopper who empties the cart of the digital item mid-form keeps the digital layout until they reload.
- A wrong theme guess costs one extra round trip. That happens only on a page load whose cart changed the match since the last one, or on a first visit with no hint where no layout ends up picked.
- Two tabs share one session and one cart. The later page load's pin wins for both tabs' AJAX calls and submits.
- Assignment adds a writer on the storefront (the session key and the cookie). The Renderer layer's "only reads" rule lists it as an exception.
- Rules may name product or category ids that another store lacks, for example after an import. Such an id never matches, so `contains_none` with a missing id widens a layout, as `not_in` with a missing role does today. The drawer flags missing ids.
