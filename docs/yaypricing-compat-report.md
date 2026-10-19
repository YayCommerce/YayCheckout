# YayCheckout × YayPricing Pro 3.6.1.1: compatibility report

Tested 2026-10-19 on yaycheckout.test (Pro build). Active alongside: WooCommerce 11.2.1, Stripe 11.1.1 and **YayCurrency Pro 3.3.5**, which could not be isolated (see "Limits").
Method: WP-CLI (cart and render), curl (`add_to_cart`, `update_order_review`, `remove_coupon`) and Playwright (stepper, remove link, coupon ×, payment switch, a real COD order).

## 1. What YayPricing does at checkout

Rules live in the options `yaydp_product_pricing_rules`, `yaydp_cart_discount_rules` and `yaydp_checkout_fee_rules`.

| Rule type | Mechanism | Main hooks |
|---|---|---|
| Product pricing (simple, bulk, tiered, bundle, BOGO, buy-X-get-Y, product fee) | changes cart item prices while totals are calculated; BXGY and BOGO add **gift items** (`is_extra`, price 0) | `woocommerce_before_calculate_totals` (110), `woocommerce_cart_item_price/subtotal/name/quantity/remove_link` |
| Cart discount | creates a **virtual coupon** named after the rule | `woocommerce_get_shop_coupon_data`, `woocommerce_cart_totals_coupon_label/html` |
| Checkout fee (custom_fee, shipping_fee, custom_shipping_fee) | adds a positive or negative fee, or changes a shipping rate | `woocommerce_cart_calculate_fees`, `woocommerce_shipping_packages`, `woocommerce_cart_totals_fee_html` |
| Payment, shipping and billing-email conditions | `payment.js`, `shipping.js` and `billing-email.js` call `update_checkout`; PHP reads `payment_method` and `post_data` | `woocommerce_checkout_update_order_review` |

YayCheckout's compatibility scan lists YayPricing as `unverified` and sees it touch only the `submission` group.

## 2. Test data

Script: `docs/yaypricing-compat-seed.php`. Run `wp eval-file docs/yaypricing-compat-seed.php run`, or `clean` to remove everything. It replaces all YayPricing rules on the site, so use a test site. Each run deletes and recreates the data, so **product IDs change**.

- `YPTEST …` products: Simple $100, Bulk $50, Buy X $80, Gift A $30, Gift B $40, BOGO $25, Plain $60.
- Rules: (1) simple −20% · (2) bulk 2–4 −10%, 5+ −20% · (3) buy X, get Gift A or B free · (4) BOGO buy 2 get 1 free · (5) cart discount −10% when subtotal ≥ 100 · (6) custom fee +5 · (7) cart discount −7 when paying by COD · (8) shipping fee −50% · (9) custom shipping fee +2 when paying by BACS.
- The rules are enabled, so they apply to the whole site until removed with `clean`.

## 3. Findings (YayCheckout side)

**Status** (each fix re-tested in a browser, on YayPricing Pro and Lite, with YayCheckout Pro and with the Pro folder removed to simulate Free): YC-1, YC-2, YC-3 and YC-5 are fixed. YC-6 is fixed too. YC-4 is YayPricing's own behaviour, so it was not changed.

### YC-1 · Low · The coupon chip showed a × that did nothing (fixed)
- **Symptom:** a cart-discount rule's virtual coupon showed as a chip `YPTEST CART_DISCOUNT 5 · -16 $ ×`. Clicking × reported "Coupon has been removed." but the chip and the discount stayed, because YayPricing re-applies its coupon on the next totals calculation.
- **Cause:** `CheckoutFragments::coupon_chips_html()` always printed the ×. YayPricing deliberately drops Woo's [Remove] link from its coupons in `woocommerce_cart_totals_coupon_html` (`class-yaydp-cart-discount-manager.php`).
- **Fix:** the × prints only when `wc_cart_totals_coupon_html()` contains `woocommerce-remove-coupon`, so it works for any extension that applies coupons itself.
- **Verified:** the original code kept both chips and the total after clicking ×; the fixed code shows no × on YayPricing chips, and a normal coupon still shows × and can be removed.
- **Not changed:** the chip uppercases the coupon code while the breakdown shows the rule name, and YayPricing's rule tooltip does not appear on the chip.

### YC-2 · Low · The Savings note ignored free gifts (fixed)
- **Symptom:** a cart with only a gift (Gift A, worth $30) hid the note entirely. With a $40 sale saving and a $24 coupon added, it read "You're saving 64 $" instead of 94 $.
- **Cause:** YayPricing sets `regular_price = 0` on gift items when "discount based on regular price" is on, so `CheckoutSavingsNote::sale_savings()` computed 0 − 0 for them.
- **Fix:** for items flagged `is_extra` (YayPricing's gifts), `sale_savings()` reads the regular price from the saved product (`wc_get_product()`). A line that is priced 0 on purpose, such as a bundle child, still counts as 0.
- **Verified:** gift only shows 30 $; gift + sale + coupon shows 94 $; a plain product still gives 0.

### YC-3 · Low (cosmetic) · The unit price read "2 × 100 80 each" (fixed)
- **Cause:** with `showUnitPrice` on, YayPricing's price HTML already starts with `N ×` (`.yaydp-cart-item-quantity`) and the Items block appended " each".
- **Fix:** `Integrations/YayPricing.php` hides that prefix inside the Items block, on both YayPricing editions (it waits for `yaydp-frontend-index`, which Pro and Lite both enqueue).
- **Verified:** the unit price now reads "100 $ 80 $ each".

### YC-4 · Info · The gift cannot be changed at checkout (not changed)
- YayPricing's Change/Manage gift button only appears on the cart page (`Gift_Row_Renderer` checks `is_cart()`). With several candidate gifts, a YayCheckout shopper keeps the default (Gift A) unless they go back to the cart. This is YayPricing's behaviour.

### YC-5 · Low · The encouraged notice went stale (fixed)
- **Symptom:** with the checkout-fee encouraged notice on, clicking + on an item raised the subtotal from 240 to 320 but the notice still said "add 161 $ more" (it should say 81). Confirmed in a browser before the fix.
- **Cause:** `encouraged-notice.js` refreshes only on cart-page events (`wc_update_cart`, `added_to_cart`, `updated_wc_div`, `updated_cart_totals`, `removed_from_cart`, cart form events). The stepper, remove link and Coupon form fire `update_checkout`, `wc_fragment_refresh` and `applied_coupon_in_checkout` instead.
- **Fix:** `Integrations/YayPricing.php` fires `updated_cart_totals` after `updated_checkout`, but only when the cart changed (subtotal or coupon state, or an Items stepper quantity), so page load and address, shipping or payment changes cause no extra request. It runs only while YayPricing's notice script is enqueued and a layout renders the checkout.
- **Verified:** the notice follows the subtotal (81 $ at 320 $) and disappears once the 400 $ threshold is met. Page load and three payment switches sent 0 notice requests; each stepper click sent exactly 1.
- **Note:** after a refresh YayPricing nests its wrapper inside the existing `.checkout-fee-encouraged-notice`. Only one block is visible; this is YayPricing's own markup.

### YC-6 · Info · YayPricing was not in the compatibility catalogue (fixed)
- The scan reported `unverified` ("Hooks into checkout submission") and Settings → Integrations had no YayPricing card.
- **Fix:** `yaypricing-pro` and `yaypricing` (Lite's folder name) are now `verified` in `CompatibilityCatalogue::bundled()`, and `IntegrationsHelper` lists YayPricing (detected through `YAYDP_VERSION`). The remote catalogue, when it loads, still overrides the bundled list.
- **Verified:** Compatibility shows "Verified" for YayPricing Pro, and Integrations lists it under "Detected plugins" with its version.

## 4. Checked and working

- First render and the `update_order_review` fragments for Items, Breakdown, Total, Shipping, Payment and the coupon chips agree. The total in the UI (217 $) equals the order total.
- Price cuts from simple, bulk and BOGO rules show the old price struck through next to the new one, in both the unit price and the line total.
- Gifts show "Free" with a read-only quantity (no stepper, no remove link). Calling `CartItemQuantity::apply()` on a gift key with quantity 5 or 0 changes nothing, because YayPricing recalculates, so the gift cannot be bypassed. Removing the bought product removes its gift, and changing its quantity updates totals and discounts correctly.
- Payment-method conditions (COD −7, BACS +2 shipping) update the total when the payment radio changes, through `update_order_review` and in the browser.
- Positive and negative fees and the shipping fee appear in the breakdown. A real COD order carried two coupons, the fee, the gift, the `_yaydp_rule_discounts` meta and a row in `wp_yaydp_order_rule_stats`.
- With `group_gift_items` on, the "Free gift" badge and gift icon show in the Items block.
- YayCheckout's preview: when its sample item is stripped, the gift that came with it is stripped too.
- No JS or PHP errors from YayPricing in the console. The 500 from `apay-us.amazon.com` is the Stripe/Amazon sandbox.

## 5. Editions

All four fixes were re-run in a browser on each combination below. YayCurrency was active in every run.

| YayCheckout | YayPricing | YC-1 chip | YC-2 savings | YC-3 unit price | YC-5 notice |
|---|---|---|---|---|---|
| Pro | Pro 3.6.1.1 | no × | 94 $ → 122 $ → 150 $ | "100 $ 80 $ each" | follows the subtotal |
| Pro | Lite 3.6.1 | no × | 94 $ → 122 $ | "100 $ 80 $ each" | not applicable: Lite has no notice |
| Free (Pro folder removed) | Pro 3.6.1.1 | no × | 94 $ → 122 $ → 150 $ | "100 $ 80 $ each" | follows the subtotal |
| Free (Pro folder removed) | Lite 3.6.1 | no × | 94 $ → 122 $ | "100 $ 80 $ each" | not applicable |

Lite has no countdown, encouraged notice, free-gift notice or gift picker, and only the `shipping_fee` checkout-fee type. Rules of a type Lite lacks (`custom_fee`, `custom_shipping_fee`) are ignored without an error. The "Free" rows simulate the edition and were not run on the Free zip.

## 6. Outside YayCheckout (YayPricing, found through malformed seed data)

- A BXGY or BOGO rule whose filters lack `quantity` throws `DivisionByZeroError` at `class-yaydp-buy-x-get-y.php:99` and makes **add-to-cart return 500 site-wide**.
- `pricing.maximum_value` set to an empty string instead of `null` throws `TypeError: float - string` at `class-yaydp-simple-adjustment.php:100`, also a 500.
- A `yaydp_core_settings` option missing the `cart_discount` key prints a PHP warning into AJAX responses and breaks the JSON of `update_order_review`.
- All three need malformed stored data and there is no validation on read. Worth reporting to the YayPricing team if they want protection against a broken rule.

## 7. Limits

- YayCurrency was active, so prices render as "80 $" and Stripe shows VND. Its effect could not be separated from YayPricing's.
- Not tested: WooCommerce's block checkout (YayCheckout does not use it), logged-in customer specifics, variable products, bundle and tiered rules, shipping-method and billing-email-order-count conditions, and tax.
