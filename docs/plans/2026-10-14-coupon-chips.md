# Applied Coupon Chips Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-14
**Goal:** Show each applied coupon as a `✓ CODE · −$X ×` chip under the Coupon form field, and drop `[Remove]` from the Order Summary Breakdown's coupon rows.
**Spec:** [docs/specs/2026-10-14-coupon-chips-design.md](../specs/2026-10-14-coupon-chips-design.md)
**Architecture:** The server renders the chip list. `fragments()` refreshes it on every `update_order_review`, so the chips follow every cart change without new JS. The × reuses WooCommerce's `.woocommerce-remove-coupon` handler. The obvious alternative, building chips in JS from the Apply response, was rejected because it re-implements price formatting and misses cart changes made elsewhere.
**Touches:** `includes/Renderer/CheckoutFragments.php`, `packages/blocks/src/coupon-form/render.php`, `packages/blocks/src/coupon-form/style.css`, `assets/js/checkout-coupon-form.js`, `docs/architecture.md`
**Tests:** none. CLAUDE.md's test rule doesn't cover this change, so each task ends in a manual check.

## Global constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types, `mixed`/`static` return types, or a trailing comma after the last parameter of a declaration.
- Free code only; never reference `YayCheckout\Pro`.
- Edit `render.php` in `packages/blocks/src/coupon-form/`, then run `pnpm build:blocks`. Never edit `assets/dist/`.
- WooCommerce strings keep the `woocommerce` text domain with `// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Woo's own string, so its translation still applies.`
- Escape every output. Amount HTML goes through `wp_kses_post()`.
- `composer lint -- <changed PHP files>` passes.
- Branch `alan/feat/coupon-chips`. Commit only when the user asks.

## Review focus

- **A layout whose only fragment block is the Coupon form** (no Items, Breakdown, Total or Savings note): WooCommerce's review-order table must still render and refresh. The Coupon form stays out of `BLOCK_FRAGMENTS`, so `skip_review_order_table()` and the `unset( $fragments[ REVIEW_ORDER_TABLE ] )` in `fragments()` never count it. Verified in Task 1 Step 4.
- **Remove by keyboard:** Tab to the ×, then Enter. WooCommerce's `keydown` handler removes the coupon. Verified in Task 2 Step 3.
- **Free-shipping coupon with zero amount:** the chip reads `Free shipping coupon` and the Breakdown row shows the same text. Verified in Task 1 Step 4.

---

### Task 1: Chips and coupon rows on the server

**Files:**
- Modify: `includes/Renderer/CheckoutFragments.php` (class constants, `fragments()`, coupon rows in `order_breakdown_html()`)
- Modify: `packages/blocks/src/coupon-form/render.php` (after `.yaycheckout-coupon-form__row`)
- Modify: `packages/blocks/src/coupon-form/style.css`

**Interfaces:**
- Produces:
  - `CheckoutFragments::coupon_amount_html( \WC_Coupon $coupon ): string`
  - `CheckoutFragments::coupon_chips_html(): string`
  - `CheckoutFragments::COUPON_CHIPS_CLASS` = `'yaycheckout-coupon-form__chips'`

- [ ] **Step 1: Add `public static function coupon_amount_html( \WC_Coupon $coupon ): string` to `CheckoutFragments`**

It returns the amount half of WooCommerce's `wc_cart_totals_coupon_html()`, without the link:
- The amount is `'-' . wc_price( WC()->cart->get_coupon_discount_amount( $coupon->get_code(), WC()->cart->display_cart_ex_tax ) )`.
- When `$coupon->get_free_shipping()` and the amount is empty, it is `__( 'Free shipping coupon', 'woocommerce' )` instead.
- Return it through `apply_filters( 'woocommerce_coupon_discount_amount_html', $html, $coupon )`. Add a phpcs ignore for the non-prefixed hook name, as the file already does.

- [ ] **Step 2: Add `public static function coupon_chips_html(): string` and refresh it in `fragments()`**

- **Markup:** `<ul class="yaycheckout-coupon-form__chips">`, then for each `WC()->cart->get_coupons()` as `$code => $coupon`:
  ```html
  <li class="yaycheckout-coupon-form__chip">
    <svg class="yaycheckout-coupon-form__chip-check" width="12" height="12" viewBox="0 0 24 24" … aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>
    <strong>esc_html( wc_strtoupper( $code ) )</strong>
    <span aria-hidden="true">·</span>
    wp_kses_post( self::coupon_amount_html( $coupon ) )
    <a role="button" class="woocommerce-remove-coupon yaycheckout-coupon-form__chip-remove" …>×</a>
  </li>
  ```
- **The × link's attributes:**
  - `href`: `esc_url( add_query_arg( 'remove_coupon', rawurlencode( $code ), wc_get_checkout_url() ) )`
  - `data-coupon`: `esc_attr( $code )`
  - `aria-label`: `esc_attr( sprintf( __( 'Remove %s coupon', 'woocommerce' ), $code ) )`
- **No cart:** when `WC()->cart` is not a `\WC_Cart`, return the empty `<ul>`.
- **Refresh:** add `public const COUPON_CHIPS_CLASS = 'yaycheckout-coupon-form__chips';`, and in `fragments()` after the summary loop always set `$html[ '.' . self::COUPON_CHIPS_CLASS ] = self::coupon_chips_html();`. Keep the Coupon form out of `BLOCK_FRAGMENTS`, so `skip_review_order_table()` and the `unset( $fragments[ self::REVIEW_ORDER_TABLE ] )` stay unchanged and never count it.

- [ ] **Step 3: Drop `[Remove]` from Breakdown rows; print the chips; style them**

- **Breakdown rows:** in `order_breakdown_html()`, change the coupon row's `td` to `<?php echo wp_kses_post( self::coupon_amount_html( $coupon ) ); ?>`. Keep the `th` as `wc_cart_totals_coupon_label( $coupon )`.
- **Coupon form:** in `packages/blocks/src/coupon-form/render.php`, echo `\YayCheckout\Renderer\CheckoutFragments::coupon_chips_html()` between `</div>` of `__row` and `__notices`. Use the file's existing phpcs-ignore comment style ("Built and escaped by CheckoutFragments").
- **Styles:** in `packages/blocks/src/coupon-form/style.css`, add:
  - `.yaycheckout-coupon-form__chips`: `display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0 0; padding: 0; list-style: none;`
  - `.yaycheckout-coupon-form__chips:empty`: `display: none;`
  - `.yaycheckout-coupon-form__chip`: `display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 4px; background: rgba(0, 0, 0, 0.06); font-size: 0.875em;`
  - `.yaycheckout-coupon-form__chip-remove`: `color: inherit; text-decoration: none; opacity: 0.6;`, with `opacity: 1` on `:hover` and `:focus-visible`.

- [ ] **Step 4: Build, lint and verify on the storefront**

- **Build:** run `pnpm build:blocks`. Expected: completes with no errors.
- **Lint:** run `composer lint -- includes/Renderer/CheckoutFragments.php packages/blocks/src/coupon-form/render.php`. Expected: no errors.
- **Setup:** open `https://yaycheckout.test/checkout/` with the Classic two-column layout active, one product in the cart, and an applied coupon worth $10. Apply it through the field; a leftover success notice is fine until Task 2.
- **Expected:**
  - Under the field, `ul.yaycheckout-coupon-form__chips` holds one chip reading `✓ CODE · −$10.00 ×`.
  - The Breakdown row reads `Coupon: code  −$10.00` with no `[Remove]`.
  - Applying a free-shipping coupon (zero amount) shows `Free shipping coupon` in both places.
- **Coupon-form-only layout:** in a scratch layout that has a Coupon form, no Items, Breakdown, Total or Savings note, and a `woocommerce_checkout_order_review` slot, change the shipping country. Expected: the `update_order_review` response still carries the `.woocommerce-checkout-review-order-table` fragment with rows (DevTools › Network).

- [ ] **Step 5: Commit (only when the user asks)**

Stage: `includes/Renderer/CheckoutFragments.php`, `packages/blocks/src/coupon-form/render.php`, `packages/blocks/src/coupon-form/style.css`
Message: `feat(coupon-form): show applied coupons as removable chips`

### Task 2: Apply without Woo's success notice; keep extension hooks; docs

**Files:**
- Modify: `assets/js/checkout-coupon-form.js:58-61` (`.done` success branch)
- Modify: `docs/architecture.md:68` (Storefront › Coupon form)

**Interfaces:**
- Consumes: the `.yaycheckout-coupon-form__chips` fragment from Task 1.

- [ ] **Step 1: Drop only Woo's success notice; keep extension hooks**

- `CheckoutFragments::drop_applied_message( $msg, $msg_code )` on `woocommerce_coupon_message`, priority `PHP_INT_MAX`: return `''` when `(int) $msg_code === WC_Coupon::WC_COUPON_SUCCESS`, `doing_action( 'wc_ajax_apply_coupon' )`, the request posts `CheckoutFragments::COUPON_FORM_FIELD` (`'yaycheckout_coupon_form'`), and a layout resolves; else `$msg`. The cart page's `cart.js` posts to the same endpoint without the field, so it keeps Woo's notice.
- `assets/js/checkout-coupon-form.js`: post `yaycheckout_coupon_form: 1` with `apply_coupon`; empty `__notices` before each apply; in the success branch print the response into `__notices` and clear the input.
- `order_breakdown_html()` coupon `td`: `wp_kses_post( apply_filters( 'woocommerce_cart_totals_coupon_html', $amount_html, $coupon, $amount_html ) )`.

- [ ] **Step 2: Note the chips in `docs/architecture.md`, Coupon form bullet**

Add one sentence covering:
- Applied coupons print as chips from `CheckoutFragments::coupon_chips_html()`, which `fragments()` refreshes on every `update_order_review` without touching WooCommerce's review-order table.
- The chip's × is WooCommerce's `.woocommerce-remove-coupon` link, handled by `checkout.js`.
- Breakdown coupon rows print `coupon_amount_html()` without `[Remove]` when the layout has a Coupon form, and keep Woo's otherwise; applied coupons keep the form shown (`Disclosure::mode()` value).
- Woo's success notice is dropped server-side; extension notices and `woocommerce_cart_totals_coupon_html` still apply.

- [ ] **Step 3: End-to-end verification (spec criterion 11; the JS is served unbuilt, so hard-reload)**

- **Setup:** on `https://yaycheckout.test/checkout/` (Pro build), first with the Classic two-column layout active and then with Multi-step wizard, add a product.
- **Valid code:** apply a valid coupon, then expect:
  - The input clears, and Woo's success notice does not appear under the field.
  - After the totals refresh, the chip `✓ CODE · −$X ×` appears under the field.
  - The Breakdown shows `Coupon: code  −$X` with no `[Remove]`.
- **Invalid code:** apply one and expect the red error under the field, as before, with no chip.
- **Remove:** click × and expect the chip and the Breakdown row to disappear, the total to rise by $X, and WooCommerce's "Coupon has been removed." notice above the form.
- **Keyboard:** re-apply, Tab to ×, press Enter, and expect the same removal.
- **Two coupons:** apply two and expect two chips that wrap at narrow width (DevTools at 375px).

- [ ] **Step 4: Commit (only when the user asks)**

Stage: `assets/js/checkout-coupon-form.js`, `docs/architecture.md`
Message: `feat(coupon-form): let the chip replace Woo's apply success notice`
