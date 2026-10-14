# Applied Coupon Chips Design

**Status:** accepted 2026-10-14
**Related:** Design file `WooCommerce Checkout Templates_v1.1.html`, templates 1–3 (applied-coupon chip with remove ×).

## Goal

On the storefront, each coupon in the cart shows as a chip under the Coupon form's field (`✓ CODE · −$12.00 ×`), and the shopper removes it with the ×. The Order Summary Breakdown keeps one row per coupon but loses WooCommerce's `[Remove]` link.

## Context

- `packages/blocks/src/coupon-form/render.php` prints the wrapper, the disclosure (`Services\Disclosure`), `.yaycheckout-coupon-form__row` (input and the `coupon-button` child) and `.yaycheckout-coupon-form__notices`. It prints no `<form>` (docs/architecture.md, Storefront › Coupon form).
- `assets/js/checkout-coupon-form.js` posts to WooCommerce's `apply_coupon` wc-ajax endpoint. On success it prints the response (WooCommerce's success notice) into `__notices` and clears the input. On error it marks the input and prints `.yaycheckout-coupon-form__error`. Either way it then fires `applied_coupon_in_checkout` and `update_checkout`. On `removed_coupon_in_checkout` it empties every form's notices.
- `includes/Renderer/CheckoutFragments.php`:
  - `BLOCK_FRAGMENTS` maps a block name to the selector `update_order_review` replaces and the method that builds its HTML. `fragments()` adds those entries for the blocks in the layout (`summary_block_fragments()`).
  - `skip_review_order_table()` swaps WooCommerce's review-order template for an empty one whenever `summary_block_fragments()` is non-empty.
  - `order_breakdown_html()` prints one row per coupon: `wc_cart_totals_coupon_label()` in the `th` and `wc_cart_totals_coupon_html()` in the `td`. The `td` holds the amount and the `[Remove]` link.
- WooCommerce's `wc_cart_totals_coupon_html()` builds its amount as `'-' . wc_price( WC()->cart->get_coupon_discount_amount( $code, WC()->cart->display_cart_ex_tax ) )`. A free-shipping coupon with a zero amount reads `__( 'Free shipping coupon', 'woocommerce' )`. The result passes through `woocommerce_coupon_discount_amount_html`. The function then appends the `[Remove]` link and runs `woocommerce_cart_totals_coupon_html` over the whole string.
- WooCommerce's `checkout.js` binds `click` and `keydown` on `.woocommerce-remove-coupon` on `document.body`. It reads `data-coupon`, posts `remove_coupon`, prints WooCommerce's notice before `form.woocommerce-checkout`, and fires `removed_coupon_in_checkout` and `update_checkout`.
- Both shipped templates, `templates/layouts/classic-two-column/markup.php` and `templates/layouts/multi-step-wizard/markup.php`, contain a Coupon form and an Order Summary Breakdown.

## Scope

**In:**
- A chip list in the Coupon form's storefront output, refreshed on every `update_order_review`.
- Chip × removes the coupon through WooCommerce's own handler.
- No WooCommerce "Coupon code applied successfully." notice under the field after a successful Apply; notices other extensions add on apply still print there.
- Breakdown coupon rows show the amount without `[Remove]` when the layout has a Coupon form; without one they keep `wc_cart_totals_coupon_html()`.
- Chip CSS matching screenshot 2.

**Out:**
- Editor preview. The Coupon form preview shows no chip, and the Breakdown preview is unchanged.
- New block attributes or Styles-tab controls for the chip.
- Silencing WooCommerce's "Coupon has been removed." notice, which `checkout.js` prints above the checkout form on removal (as today's `[Remove]` does).
- Template markup changes.
- The cart page and WooCommerce's classic `form.checkout_coupon`.

## Acceptance criteria

1. Given a cart with coupon `save10` worth $10.00, the Coupon form prints `ul.yaycheckout-coupon-form__chips` between `.yaycheckout-coupon-form__row` and `.yaycheckout-coupon-form__notices`. The list holds one `li.yaycheckout-coupon-form__chip` that reads `✓ SAVE10 · −$10.00 ×`.
2. Given an empty cart coupon list, `ul.yaycheckout-coupon-form__chips` prints with no children.
3. Given a free-shipping coupon with a zero amount, its chip reads `✓ CODE · Free shipping coupon`.
4. Given two coupons, two chips print in cart order and wrap onto a new line when the row is too narrow.
5. When the shopper applies a valid code: the input clears, WooCommerce's success notice does not print under the field (a notice another extension adds on `woocommerce_applied_coupon` does), and after `updated_checkout` the new chip shows without a page reload.
6. When the shopper applies an invalid code: the error shows under the field exactly as today, and no chip is added.
7. When the shopper clicks the × or presses Enter on it: the coupon is removed, and after `updated_checkout` its chip and its Breakdown row are gone and the total rises by its amount.
8. The × is an `<a role="button">` with `class="woocommerce-remove-coupon"`, `data-coupon="<code>"`, `aria-label` = WooCommerce's `Remove %s coupon` string, and `href` = the checkout URL with `?remove_coupon=<code>`.
9. The Breakdown's coupon row `th` is unchanged (`wc_cart_totals_coupon_label()`). On a layout with a Coupon form, its `td` holds only the amount from `coupon_amount_html()`, with no `.woocommerce-remove-coupon` link; on a layout without one, it keeps `wc_cart_totals_coupon_html()` and its `[Remove]`.
10. A layout with a Coupon form and no Items, Breakdown, Total or Savings note still gets WooCommerce's review-order table during `update_order_review`: `skip_review_order_table()` returns the original template.
11. End-to-end verification: on yaycheckout.test (Pro build, Classic two-column layout), add a product, apply a valid coupon in the Coupon form, then the Multi-step wizard layout the same way. In both layouts the chip appears under the field with the right amount and no success notice, and the Breakdown row shows `Coupon: CODE  −$X` with no `[Remove]`. Click × and both disappear while the total updates.

## Constraints

- Product PHP runs on PHP 7.4 (CLAUDE.md hard rule 4).
- No Pro code. The chips are Free (hard rule 2).
- `render.php` is edited in `packages/blocks/src/coupon-form/`, then rebuilt with `pnpm build:blocks`. Never edit `assets/dist/` (hard rule 1).
- WooCommerce strings keep the `woocommerce` text domain with the existing `phpcs:ignore WordPress.WP.I18n.TextDomainMismatch` pattern, so their translations apply.
- Every output is escaped. Amount HTML goes through `wp_kses_post()`.
- `composer lint -- <changed PHP files>` passes.

## Approach

**Chosen:** A server-rendered chip list refreshed as an `update_order_review` fragment.

**Architecture:** One builder renders the chip list, and both `render.php` (first paint) and `fragments()` (every refresh) call it, so the chips stay correct after any cart change (apply, remove, stepper, expired coupon). Removal reuses WooCommerce's `.woocommerce-remove-coupon` handler, so the chips need no new JS. One amount helper feeds both the chip and the Breakdown row.

**Components:**
- `CheckoutFragments::coupon_amount_html( \WC_Coupon $coupon ): string`: the amount half of `wc_cart_totals_coupon_html()`. It returns `'-' . wc_price( amount )`, or the `Free shipping coupon` string, passed through `woocommerce_coupon_discount_amount_html`.
- `CheckoutFragments::COUPON_CHIPS_CLASS` = `'yaycheckout-coupon-form__chips'`: the chip list's class and fragment selector.
- `CheckoutFragments::coupon_chips_html(): string`: returns `<ul class="yaycheckout-coupon-form__chips">`, one `<li class="yaycheckout-coupon-form__chip">` per `WC()->cart->get_coupons()`. Each chip has a check icon, `<strong>` holding `wc_strtoupper( $code )`, `·`, `coupon_amount_html()`, and the × link from criterion 8. With no cart it returns an empty list.
- `CheckoutFragments::fragments()`: always add `'.' . COUPON_CHIPS_CLASS => coupon_chips_html()`; on a layout without a Coupon form the selector matches nothing. The Coupon form stays out of `BLOCK_FRAGMENTS`, so `skip_review_order_table()` and the review-table `unset` are unchanged and never count it.
- `CheckoutFragments::order_breakdown_html()`: the coupon row `td` prints `apply_filters( 'woocommerce_cart_totals_coupon_html', $amount_html, $coupon, $amount_html )`, where `$amount_html` is `coupon_amount_html( $coupon )`, instead of `wc_cart_totals_coupon_html( $coupon )`.
- `CheckoutFragments::drop_applied_message( $msg, $msg_code )` on `woocommerce_coupon_message` (priority `PHP_INT_MAX`): returns `''` for `WC_Coupon::WC_COUPON_SUCCESS` during `wc_ajax_apply_coupon` when the request posts `CheckoutFragments::COUPON_FORM_FIELD` (`yaycheckout_coupon_form`) and a layout resolves, so Woo adds no success notice; otherwise returns `$msg` unchanged. The field keeps the cart page's `cart.js`, which posts to the same endpoint, on Woo's notice.
- `packages/blocks/src/coupon-form/render.php`: echo `CheckoutFragments::coupon_chips_html()` after `.yaycheckout-coupon-form__row`, inside the disclosure.
- `assets/js/checkout-coupon-form.js`: posts `yaycheckout_coupon_form: 1` with `apply_coupon`, empties `__notices` before each apply, and on success prints the response into `__notices` (now only other extensions' notices) and clears the input.
- `packages/blocks/src/coupon-form/style.css`: style `__chips` and `__chip` after screenshot 2.
  - `__chips`: flex, wrap, gap 8px, margin-top 8px, no list style.
  - `__chip`: inline-flex, gap 6px, padding 4px 10px, light-grey background, radius 4px, 0.875em.
  - The code is bold, and the × link has no underline.
- `docs/architecture.md`, Storefront › Coupon form: add one sentence on the chips fragment and the reused remove handler.

**Data and storage:** None.

**Integration points:**
- Filters still applied: `woocommerce_coupon_discount_amount_html` (chip and row), `woocommerce_cart_totals_coupon_label` (row label) and `woocommerce_cart_totals_coupon_html` (row amount; it receives the amount without `[Remove]`, so an extension that rewrites that link has nothing to rewrite).
- `woocommerce_coupon_message` is filtered to drop only Woo's success message, only for the Coupon form's own `apply_coupon` request (marked by `yaycheckout_coupon_form`).
- Events: WooCommerce's `removed_coupon_in_checkout`, `update_checkout` and `updated_checkout`.

**UI:** Storefront only, inside the Coupon form block, using plain block CSS.

**Edge cases and failure modes:**
- A coupon added by another plugin (auto-applied): its chip shows with × anyway, and WooCommerce decides whether removal is allowed.
- Coupons already applied (reload, or applied on the cart page): `render.php` passes `WC()->cart->get_applied_coupons()` to `Disclosure::mode()` as the field's value, so the form prints shown with no toggle and the chips are visible. With no coupon, the merchant's disclosure applies.
- Several Coupon forms: none, since `block.json` sets `multiple: false`.
- A layout with no Coupon form: no chips; the Breakdown keeps Woo's `[Remove]`. A Coupon form hidden by a Visibility rule or on another wizard step still drops `[Remove]`. Accepted.

**Decision record:** None.

## Alternatives considered

| Option | Why rejected |
|---|---|
| JS builds chips from the Apply response | It would have to re-implement price, tax and free-shipping formatting, and it misses cart changes made elsewhere (stepper, removed item, expired coupon). |
| A dedicated endpoint returning the chips | An extra request per change that duplicates `update_order_review`. |
| One summed "Discount" row in Breakdown | It drops `woocommerce_cart_totals_coupon_label` per coupon. The user chose per-coupon rows. |
| An own silent remove handler | ~30 lines of new JS for the notice alone. WooCommerce's handler already works. |

## Rollout

None. No build has shipped, so no migration is needed.

## Open questions

None.
