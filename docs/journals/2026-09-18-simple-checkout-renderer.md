# 2026-09-18 — Simple checkout renderer

Branch `dev/edd`, uncommitted. Plan: `plans/260918-1346-simple-checkout-renderer`.

## What changed

- `Renderer/CheckoutPage` hooks `template_include` @ `PHP_INT_MAX` to render the active layout as the whole WooCommerce checkout page (theme header/footer around the layout — `get_header()`/`get_footer()` on classic themes, `block_header_area()`/`block_footer_area()` in our own shell on block themes; started as a blank canvas, changed the same day); falls back to WooCommerce default when safe mode on, no layout, or layout lacks `yaycheckout/order-review`.
- `templates/checkout-page.php` runs `do_blocks()` on the layout; the root `checkout` block renders WooCommerce's `<form name="checkout">` and its logged-in guard (the shortcode / `form-checkout.php` path was dropped mid-session so hook blocks can own the rest later). The `woocommerce_checkout_fields` filter, the order-review stand-down and the order-received/order-pay exclusion were then removed by the user: validation stays WooCommerce's and the canvas owns every checkout-page request until hook/account/fields blocks exist.
- New `yaycheckout/order-review` block: wraps `#order_review` div; guards on cart presence (REST/editor/CLI safe); fires `woocommerce_checkout_before_order_review` / `_after_order_review` for gateway/coupon plugins.
- `LayoutRepository::create()` now seeds contact, billing (name, country, address 1, city, state, postcode), shipping (same, `shipping_` prefix), order-review blocks.
- Shipping block emits WooCommerce's `ship_to_different_address` checkbox (markup unchanged).
- Block renderers (billing, field, shipping) pass `woocommerce-*-fields` wrapper classes and `address-field` / `update_totals_on_change` to `woocommerce_form_field()` so checkout.js binds event handlers.
- `phpcs.xml` scans `templates/`; `scripts/run.sh release` copies `templates/` to dist.

## Decisions

- Filter removes WooCommerce keys absent from layout; never touches `required` or other WC attributes (locale-driven `required` wins).
- Stand-down rule: active layout in safe mode/missing/no order-review → WooCommerce default (silent, not a validation error on activate).
- Shipping `ship_to_different_address` always rendered by block (not read server-side); form posts like WooCommerce.
- Seeded `required: true` on state/postcode (known backlog: block asterisk vs WC locale; `required` omit if block supports fallback to WC marker).

## Gotchas worth remembering

- **WP-CLI has a cart.** Conditional on "CLI has no cart" was wrong; `class-woocommerce.php:974` boots `wc_load_cart()` on any frontend request, including wp-cli. Verified the true no-cart branch by nulling `WC()->cart` directly.
- **Visual pass ≠ correct code.** "DE hides state" manual check passed on first run because country-select.js silently failed to find `#billing_state` (billing wrapper lacked `woocommerce-billing-fields`). Code review spot-checked WC's source and caught it. WC 11 actually ships German states as an optional select. Lesson: reusing WC's JS against custom markup requires verifying against *its* selectors, not just visible outcome.
- **checkout.js only binds to specific classes.** `checkout.js:180-195` only triggers `update_checkout` on `.address-field select|input` and `.update_totals_on_change`. Without those classes on the field block output, country/state/postcode changes never refresh shipping or totals — even if the field renders correctly. Fixed via `woocommerce_form_field()` parameters in field/billing/shipping render functions.
- **form.submit() loses the button.** Calling `form.submit()` programmatically omits the submit button's name and value; `WC_Form_Handler::checkout_action()` @ `wp_loaded`@20 reads `woocommerce_checkout_place_order` / `_update_totals` and never runs. Non-JS fallback must call `requestSubmit(button)` after detaching checkout.js handlers to exercise the real WC path.
- **Release script has a fixed file list.** New top-level dirs (e.g., `templates/`) must be explicitly added to the copy list in `scripts/run.sh release`, or they ship silently missing from the distributed zip.
- **`wp_phpunit` reads `WP_PHPUNIT__TESTS_CONFIG`.** Not `WP_TESTS_CONFIG_FILE_PATH` (core's name in WP 7+). Vendor hook in composer.json must set the env var before phpunit runs.

## Unresolved

- Admin-ajax validation path (`wp_doing_ajax()` + `$_POST['action']`) not covered (finding 5, backlog).
- Seed `required` on state/postcode surfaces the block asterisk vs WC locale marker mismatch on every new layout (finding 9, backlog; defer to block support work).
- § Storefront docs section not written (finding 3, Medium; blocker for acceptance).
