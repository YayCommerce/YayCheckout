---
area: storefront
updated: 2026-11-12
updated_ref: c7ee247
covers: [includes/Renderer/, includes/Blocks/, packages/blocks/, templates/, assets/js/, assets/css/]
---
# Storefront, blocks and design

> Update when: how a layout is picked or printed, a block's build or registration, a WooCommerce fragment or hook mapping, the design tokens, or the template catalogue changes.

## Request flow

1. **Themeless (`setup_theme` priority 1).** `Renderer\ThemelessCheckout` swaps in `templates/themeless/yaycheckout-themeless/` for the checkout, its wc-ajax calls, the layout editor and an order-received page a layout renders (Pro). The site theme's `functions.php`, styles and `theme.json` never load.
   - This runs before the main query and the cart exist, so it guesses from the URL.
   - With pinned rules (cart or country), `LayoutPin`'s signed cookie is the hint. If the guess was wrong, `CheckoutPage::maybe_repick` reloads **once** with `?yaycheckout_repick=1`.
2. **Takeover (`wp`).** `Renderer\CheckoutPage::maybe_take_over` takes the checkout page (not order-received, not order-pay) when a layout resolves. It adds `template_include` at `PHP_INT_MAX` (to beat block themes' `wp-includes/template-canvas.php`).
   - It stands down entirely when safe mode is on or no layout resolves.
   - A preview token renders its layout even in safe mode.
3. **Which layout.** A preview token wins. Otherwise, without pinned rules, the layout is `LayoutRepository::active( shopper_context() )` on every request.
   - Pro tries the active layouts in the merchant's priority (`LayoutRepository::priority()`, the Priority sheet on the Layouts page), and the first whose rules match is served.
   - Rules on the cart (products, categories, subtotal, quantity) or the billing/shipping country are pinned (`ConditionType::PINNED_TYPES`): the checkout GET picks one from `CartFacts::context()` and `CustomerFacts::context()` and pins it in the WC session (`LayoutPin::SESSION_KEY`), and every later AJAX or submit uses the pin. A country changed inside the form doesn't swap the layout; the next page load re-picks (BL-6).
   - Both facts read only after `woocommerce_cart_loaded_from_session`; before it a pinned condition is unknown (`LayoutAssignment::outlook()` `maybe`). The country falls back from shipping to billing to `wc_get_customer_default_location()`.
   - `CheckoutPage::get_instance()->layout()` is the one answer to "which layout is served". It's meaningful from `wp` on, and `null` means stand down.
4. **Print.** `templates/checkout-page.php` is a whole HTML document with no theme header or footer.
   - It calls `WC()->cart->calculate_totals()` first, because shipping rows only exist after a calculation.
   - It renders the blocks into a buffer **before** `wp_head()`, so the script modules they enqueue land in the import map.
   - It then runs `LayoutShortcodes::render()` and `BlockVisibility::finalize()`.
5. **Assets.** `CheckoutPage::enqueue_scripts` forces `wc-checkout`, `wc-country-select` and `wc-address-i18n`. It dequeues WooCommerce's general, layout and blocks styles, because their `.form-row` rules would beat `assets/css/checkout-base.css`.

## How it stays a WooCommerce checkout

- **The form is WooCommerce's.** The root block `packages/blocks/src/checkout/` prints WooCommerce's `<form name="checkout">` (classes `yaycheckout-checkout checkout woocommerce-checkout`). So WooCommerce's `woocommerce/assets/js/frontend/checkout.js`, `update_order_review` and wc-ajax keep working. Fields go through `woocommerce_form_field()`.
- **Fragments.** `Renderer\CheckoutFragments` (on `woocommerce_update_order_review_fragments`, priority 20) replaces WooCommerce's selectors with the layout's blocks.
  - `FRAGMENTS` covers the payment and shipping slots, and `BLOCK_FRAGMENTS` covers the Order Summary Items, Breakdown, Total and Savings Note.
  - **A new block that must refresh on `update_checkout` needs a unique class and an entry here.**
  - `skip_review_order_table` swaps `checkout/review-order.php` for the empty `templates/review-order-skipped.php`, so the review-order hooks fire once.
- **WooCommerce Hook block.** `Renderer\WcHooks::HOOKS` catalogues the classic checkout actions. Once a layout places a Hook block for a hook, only that block fires it, and callbacks are detached elsewhere. `refresh` hooks are reprinted as fragments.
- **Coupons.** `assets/js/checkout-coupon-form.js` posts `apply_coupon` and `remove_coupon` itself, with the marker field `yaycheckout_coupon_form`.
  - PHP blanks WooCommerce's notice only for that marker. It prints chips at `woocommerce_applied_coupon` priority 30, after the cart recalculates at 20.
  - The cart page posts without the marker and keeps WooCommerce's notice.
  - **Why not WooCommerce's own handler:** `woocommerce/assets/js/frontend/checkout.js` applies a coupon only on a `form.checkout_coupon` submit. Forms can't nest, so the Coupon form block prints no `<form>` and can sit inside or beside the checkout form (`CheckoutScope::classes()` gives it the checkout styles beside it).
    - Its chips' × (`.yaycheckout-coupon-form__chip-remove`) posts `remove_coupon` itself, with no `.woocommerce-remove-coupon` class, so WooCommerce's own remove handler doesn't fire.
    - Its field has no `input-text` or `form-row` class, so WooCommerce's inline validation skips it, and the script catches Enter, which would submit the checkout.
- **wc-ajax endpoints:** `yaycheckout_cart_item_quantity` (`CartItemQuantity`, with a 400 ms debounced stepper), `yaycheckout_login` and `yaycheckout_login_state` (`CheckoutLogin`). The login cookie is only seen by the next request.
- **Stripe:** with an Express Pay block, `wc_stripe_express_checkout_params` gets `has_block=false` (`Renderer\ExpressPay`).

## Blocks

- **Every block is dynamic.** Each has `block.json` + `render.php` + an editor script under `packages/blocks/src/<name>/`. `wp-scripts build` writes them to `assets/dist/blocks/`, with extra entries in `packages/blocks/webpack.config.js` (`live-value`, `visibility`).
- **Registration.** `Blocks\BlockRegistry::register_blocks()` globs `assets/dist/blocks/*/block.json`, so **with no build, nothing registers**. `ORDER` sets the inserter order. View scripts register in `register_view_scripts()` and load only when their block renders.
- **Pro blocks** live under `packages/blocks/src/pro/` and build to `assets/dist/blocks/pro/`, which only `ProServiceProvider` adds (through `yaycheckout_built_blocks`).
  - In Free they're locked editor stubs (`packages/blocks/src/checkout/locked-pro-blocks.tsx`).
  - `LayoutMarkupReader::publish_error()` refuses to make a layout live while it holds one, and `publish-guard.ts` mirrors that.
  - Three lists must match, and `scripts/check-pro-boundary.sh` checks them: `BlockRegistry::PRO_BLOCKS`, `PRO_BLOCK_METAS` in `packages/blocks/src/shared/pro-blocks.ts`, and the `src/pro/` folders.
  - Don't gate a block with `Plan::is_pro()` in `render.php`: Pro blocks aren't registered in Free at all.
- **Plugin placeholders.** `yaycheckout/plugin-block` prints a `PluginBlockCatalogue` entry's shortcode only when `shortcode_exists()` (YayBoost order bump, free shipping bar, FBT).
- **Visibility** is a block attribute (`yaycheckoutVisibility`), not meta.
  - The server decides it with step 1 open. A footer JSON (`#yaycheckout-visibility-state`) refreshes as a fragment.
  - "Block is empty" rules are decided again over the whole HTML in `finalize()`.
  - `checkout`, `steps` and `step` are excluded.
- **Live values.** Only `LiveValues::BLOCKS` may hold a `yaycheckout-live-value` span. Pro's thank-you supplies its values through `yaycheckout_live_values_source`.

Recipe for adding a block: [conventions.md § Add a block](conventions.md#add-a-block).

## Design

`Renderer\CheckoutBaseStyles::enqueue` loads, in order:
1. `assets/css/design-presets.css`
2. `assets/css/checkout-base.css`, which styles WooCommerce's classic markup (block CSS lives in each block's `style.css`)
3. the `@font-face` rules from `DesignFonts::css()`
4. the `--yaycheckout-*` tokens and custom CSS from `DesignStyles::css()`

The storefront and the editor canvas share it, so they match.

- **An empty token prints nothing.** The CSS falls back to the theme, for example `var(--yaycheckout-color-primary, var(--wp--preset--color--primary, #3858e9))`.
- **Token values print unescaped.** `LayoutDesign::from_meta()` is the sanitiser, and custom CSS is checked again at print time.
- An empty base size prints 13px (`DesignStyles::DEFAULT_BASE_SIZE`).
- **Fonts install into the Font Library when the design is saved** (`Engine\DesignFontInstall`), never on a page view. Shoppers never load from Google, and only the editor preview uses Google URLs.
- Our blocks have no `layout` support, so `Renderer\BlockGap` prints the gaps itself. `BlockRegistry::block_settings` turns on the spacing and border controls.

## Templates

- **The catalogue** is `Controllers\LayoutTemplateCatalogue` (default `classic-two-column`).
  - Free templates live in `templates/layouts/<slug>/`, Pro ones in `templates/layouts/pro/<slug>/`.
  - Each has `markup.php`, `design.json`, `preview.html` and `thank-you-preview.html`.
  - The shared views are `templates/layouts/logged-out.php` and `templates/layouts/pro/thank-you.php`.
- **`markup.php` is the markup exactly as the editor saves it.** Build the template in the editor and copy it from the Code editor. A hand edit that differs by one class makes the block invalid.
  - The structure of `classic-two-column` must match `defaultTemplate()` in `packages/blocks/src/checkout/edit.tsx`.
- **Previews.** `preview.html` is static: the Free zip keeps only that file from `templates/layouts/pro/`, so Free shows Pro templates locked.
  - Rebuild previews with `./scripts/run.sh previews` or `./scripts/run.sh thank-you-previews`.
