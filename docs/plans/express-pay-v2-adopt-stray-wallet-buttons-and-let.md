---
id: P-2
title: Express pay v2: adopt stray wallet buttons and let wallet orders through
status: in-progress
from: BS-2
roadmap: R-6
design:
trello:
type: feat
branch: feat/express-pay-v2-adopt-stray-wallet-buttons-and-let
pr:
editions: [free, pro]
created: 2026-11-12
---
# P-2 Express pay v2: adopt stray wallet buttons and let wallet orders through

## Goal
R-6's acceptance: *Express pay buttons show and complete payment from the checkout layout in Free and Pro, including when a gateway offers no express method.* After this plan, a gateway whose wallet buttons sit on a classic hook no block fires (FunnelKit Stripe's "inline" position, `woocommerce_checkout_billing`) shows them in the Express Pay block, and the editor stops warning that no gateway can fill it. A wallet payment (Apple Pay, Google Pay, Link) completes into an order even when the layout has required extra fields or makes extra address fields required, because the layout's own required rules stand down for that request and WooCommerce's and the gateway's rules decide. With no express-capable gateway, the block and its "OR" divider still print nothing. Done means the three Tests below pass and the manual checks in Verify pass on Free and Pro builds.

## Non-goals
- Block styling options (heading, button height and theme, per-gateway toggles): BL-4.
- Adapters for gateways other than FunnelKit Stripe: BL-5. WooCommerce Stripe and WooPayments already print on `ExpressPay::HOOK`; PayPal Payments' smart buttons are payment-method buttons in the Payment block (`woocommerce-paypal-payments/modules/ppcp-button/src/Assets/SmartButton.php:1129-1135`, `woocommerce_review_order_after_payment`), not express buttons.
- YayCheckout's own wallet integration (BS-2 option C).
- Block checkout: never taken over.
- Asking the wallet for required custom fields before paying: wallets can't.

## Findings
1. **FunnelKit Stripe's inline buttons never print in a layout.** Its checkout position is `woocommerce_checkout_before_customer_details` for "above checkout", otherwise `woocommerce_checkout_billing` (`funnelkit-stripe-woo-payment-gateway/gateways/smart-buttons.php:74-78`), attached as `[SmartButtons::get_instance(), 'payment_request_button']` at priority 5 (filterable, line 84). No YayCheckout block fires `woocommerce_checkout_billing`: `git grep` finds it only in `includes/Renderer/WcHooks.php:51,202,250`. The Billing block prints its fields itself. → T3 adds adoption to `ExpressPay`, T4 registers FunnelKit's callback.
2. **The adopted callback has to move after FunnelKit has hooked.** FunnelKit hooks in its singleton constructor, and YayCheckout can't resolve the served layout before `wp` (`CheckoutPage::layout()` needs the cart, `includes/Renderer/CheckoutPage.php:47-60`). So the move happens in `CheckoutPage::maybe_take_over()` (on `wp`), next to the existing Stripe filter (`CheckoutPage.php:87-89`), by scanning `$wp_filter` for an object callback of the registered class and method, as `WcHooks::kept_in_place()` scans (`WcHooks.php:511-533`). Calling `SmartButtons::get_instance()` ourselves would construct FunnelKit early, so the registration takes class and method names, not a callable.
3. **A Hook block for the original hook wins.** `WcHooks::in_place()` fires a hook only where no Hook block places it (`WcHooks.php:385-405`). A merchant who placed a "Billing" Hook block already gets FunnelKit's inline buttons there, so the move is skipped when `WcHooks::is_placed()` is true for the original hook.
4. **The editor's "no gateway" notice is wrong for FunnelKit inline.** `ExpressPay::has_buttons()` counts only callbacks on `HOOK` and gateways that support `pay_button` (`includes/Renderer/ExpressPay.php:24-37`). → T3 counts adopted callbacks attached to their original hook.
5. **Layout validation runs on wallet orders.** FunnelKit's wallet endpoint calls `WC()->checkout()->process_checkout()` with only the wallet's fields and a `payment_request_type` POST key (`funnelkit-stripe-woo-payment-gateway/includes/traits/wc-smart-button-functions.php:817-856`; also read at `gateways/creditcard.php:1108`). `CheckoutExtraFields::validate()` then adds "X is a required field." for every required extra field (`includes/Renderer/CheckoutExtraFields.php:88-92`, message from `includes/Services/ExtraFieldSubmission.php:33-35`), and `CheckoutRequiredFields::filter_fields()` at 9999, not page-gated (`includes/Renderer/CheckoutRequiredFields.php:35-55`), re-applies the layout's required address flags; FunnelKit un-requires only WooCommerce's default billing list at `PHP_INT_MAX` (`wc-smart-button-functions.php:877-901`). → T1 detects the wallet order, T2 stands both down.
6. **`persist()` already copes.** With errors, `CheckoutExtraFields::persist()` returns before writing meta (`CheckoutExtraFields.php:104-109`). A wallet order posts no extra-field values, so nothing is lost by it returning early. T2 changes only `validate()`.
7. **The empty case already works but is untested.** `render.php:27-30` returns nothing when the hooks print nothing, and the templates hide the "OR" divider with a "Block is empty" rule on the Express Pay block (`templates/layouts/split-express/markup.php:181`). → a regression test in T3.
8. **Other gateways' markers are not in this tree.** Only FunnelKit Stripe and PayPal Payments are installed. WooCommerce Stripe's Express Checkout Element and WooPayments are believed to post `express_checkout_type` / `express_payment_type` alongside or instead of `payment_request_type`; that is unverified here. → T1 makes the marker list a filter with all three keys, and Verify has a manual check for WooCommerce Stripe; BL-5 tracks the rest.
- differs: `docs/knowledge/storefront.md:43` and `docs/knowledge/integrations.md:27` describe Express Pay as Stripe's `has_block` fix only → T5 updates both, and `docs/knowledge/helpers.md` gains the two new `ExpressPay` helpers.

## Approach
- **Wallet-order detection** (`Renderer\ExpressPay::is_wallet_order()`): true when the POST holds a non-empty value for one of the marker keys `payment_request_type`, `express_checkout_type`, `express_payment_type`, filtered by `yaycheckout_express_wallet_order_markers` (array of keys). Read-only, no nonce of its own: it runs inside WooCommerce's checkout processing, after the gateway has checked its nonce.
- **Stand down on wallet orders:** `CheckoutRequiredFields::filter_fields()` returns WooCommerce's fields untouched, and `CheckoutExtraFields::validate()` adds no errors. WooCommerce's own required set and the gateway's own relaxations then apply, as on stock WooCommerce. BL-3 is decided this way (the user chose it over hiding the block).
- **Adoption** (`ExpressPay::adopt( string $hook, string $class, string $method )`, `ExpressPay::adopt_into_block()`): integrations register "this class's method on this classic hook". `CheckoutPage::maybe_take_over()`, when the layout has an Express Pay block, calls `adopt_into_block()`, which for each registration not placed by a Hook block finds the attached object callback in `$wp_filter[ $hook ]`, removes it there and adds it on `ExpressPay::HOOK` at the same priority. `has_buttons()` also counts a registration whose callback is attached to its hook. No new option, meta or REST.
- **FunnelKit adapter** (`Integrations\FunnelKitStripe`, new): constructed in `YayCheckout`'s constructor beside Germanized (`includes/YayCheckout.php:42`); registers `woocommerce_checkout_billing` / `FKWCS\Gateway\Stripe\SmartButtons` / `payment_request_button`. Adoption checks `class_exists()` at move time, so the adapter is inert without FunnelKit.
- **Editions:** all Free code (`includes/Renderer/`, `includes/Integrations/`), so Free and Pro both get it; nothing names `YayCheckout\Pro` (hard rule 1). No SPA or block JS changes, so no PHP/TS mirror drifts (`editor-data.ts` keeps mirroring `has_buttons()`'s meaning).
- **PHP 7.4:** no `match`, enums, named args or promoted constructors (hard rule 3).
- **UI:** none. The editor notice text is unchanged; it just stops showing falsely.

## Tasks
- [x] T1 Add wallet-order detection: `ExpressPay::is_wallet_order()` and the `yaycheckout_express_wallet_order_markers` filter, with a docblock naming which gateway posts which key — `includes/Renderer/ExpressPay.php`
  - Reuse: none, new is_wallet_order() because no helper reads gateway wallet markers today (`yayh code find` and `docs/knowledge/helpers.md` have none); sanitising follows `CheckoutExtraFields::posted()` in `includes/Renderer/CheckoutExtraFields.php` (`wp_unslash` + `sanitize_text_field`)
- [x] T2 Stand the layout's required rules down on wallet orders: early return in `CheckoutRequiredFields::filter_fields()` and `CheckoutExtraFields::validate()`; add `tests/Integration/Renderer/ExpressWalletOrderTest.php` (new) — `includes/Renderer/CheckoutRequiredFields.php`, `includes/Renderer/CheckoutExtraFields.php`, `tests/Integration/Renderer/ExpressWalletOrderTest.php` (new) [risk: 3]
  - Reuse: ExpressPay::is_wallet_order() from T1; LayoutFactory::serve() in `tests/Support/LayoutFactory.php`; the WC_Checkout fields pattern of `tests/Integration/Renderer/CheckoutRequiredFieldsHiddenTest.php`
- [ ] T3 Add adoption to ExpressPay: `adopt()`, `adopt_into_block()` (skipped for a hook a Hook block places), `has_buttons()` counting attached registrations, and the call from `CheckoutPage::maybe_take_over()` beside the Stripe filter; add `tests/Integration/Blocks/ExpressPayRenderTest.php` (new) — `includes/Renderer/ExpressPay.php`, `includes/Renderer/CheckoutPage.php`, `tests/Integration/Blocks/ExpressPayRenderTest.php` (new)
  - Reuse: WcHooks::is_placed() and the `$wp_filter` scan of WcHooks::kept_in_place() in `includes/Renderer/WcHooks.php`; WcHooks::callbacks_besides() for the existing count; the WcHooks::anchor() registry shape for adopt()
- [ ] T4 Add the FunnelKit Stripe adapter and boot it — `includes/Integrations/FunnelKitStripe.php` (new), `includes/YayCheckout.php`
  - Reuse: ExpressPay::adopt() from T3; the singleton and guard shape of `includes/Integrations/Germanized.php`
- [ ] T5 Update the knowledge docs for the new Express Pay behaviour: adoption and wallet orders in storefront.md (Stripe line) and integrations.md (Gateways, Other plugins: FunnelKit Stripe), the two helpers in helpers.md — `docs/knowledge/storefront.md`, `docs/knowledge/integrations.md`, `docs/knowledge/helpers.md` [risk: 1] [tier: veryfast]
  - Reuse: n/a

**Tests:** [integration] test_wallet_order_skips_layout_required_extra_field_and_address_flags (tests/Integration/Renderer/ExpressWalletOrderTest.php) — with `payment_request_type` posted, a layout-required extra field adds no checkout error and a layout-required `billing_company` stays WooCommerce's (not required), and without the marker both still apply, so a normal checkout keeps the layout's rules; [integration] test_adopted_callback_moves_to_express_hook_unless_a_hook_block_places_it (tests/Integration/Blocks/ExpressPayRenderTest.php) — a fake object callback registered with adopt() on `woocommerce_checkout_billing` moves to `ExpressPay::HOOK` and `has_buttons()` is true, but stays put when the served layout has a "Billing" Hook block; [integration] test_block_prints_nothing_without_express_buttons (tests/Integration/Blocks/ExpressPayRenderTest.php) — with nothing on the express hooks the block renders '' (the "no express method" half of the acceptance)

**E2E:** none

## Verify
- `php -l includes/Renderer/ExpressPay.php` → exit 0
- `php -l includes/Integrations/FunnelKitStripe.php` → exit 0
- `composer lint` → exit 0
- `vendor/bin/phpunit --filter ExpressWalletOrderTest` → OK (integration; needs the MySQL test DB, from the main checkout, see known-issues)
- `vendor/bin/phpunit --filter ExpressPayRenderTest` → OK (integration)
- `git grep -n "YayCheckout.Pro" -- includes/Integrations/FunnelKitStripe.php includes/Renderer/ExpressPay.php` → exit 1
- Manual, Free build and Pro build, local site with FunnelKit Stripe in test mode, express checkout on, checkout position "inline": serve the Split express template; on `?page_id=9` with a product in the cart, Google Pay / Link buttons show in the Express Pay block above the "OR" divider, and nothing prints in the billing section.
- Manual: in that layout add a required extra field "Delivery note"; pay with the Google Pay test card from the Express Pay block → the order is created (WooCommerce → Orders) with no "Delivery note is a required field." error; a normal Place order without the field still shows that error.
- Manual: open the layout in the editor with FunnelKit inline on → the Express Pay block shows no "Shoppers will not see this block" notice; turn express checkout off in FunnelKit → the notice shows.
- Manual: with only Cash on delivery enabled, the checkout shows no Express Pay box and no "OR" divider (integration covers the block; this checks the divider).
- Manual: add a WooCommerce Hook block set to "Billing" to the layout → FunnelKit's inline buttons print in that Hook block, not in the Express Pay block.
- Manual (when WooCommerce Stripe is available on a test site): its Express Checkout buttons show in the block and a wallet order completes with a required extra field in the layout; if the order still fails on the required field, add its marker key through T1's filter list and update BL-5.

## Risks
- **Wallet orders without required custom fields (merchant data).** Paid wallet orders can lack a required extra field's value or a layout-required address field. Rollback: revert T2's two early returns. Decided (user, autopilot sheet): BL-3 - let wallet orders through without the layout's own required extra fields and address flags; WooCommerce's and the gateway's rules still decide.
- **Forged marker.** A shopper can post `payment_request_type` on a normal checkout to skip the layout's own required flags for their own order. WooCommerce's and the gateway's rules still apply, and FunnelKit Checkout accepts the same tradeoff. Accepted; tightening it (also matching the wc-ajax action) is a one-line change in `is_wallet_order()`.
- **Gateway renames its callback.** Adoption finds nothing and FunnelKit's inline buttons are missing again, as today; nothing else breaks. Caught by the manual FunnelKit check before each release.
- **Double print.** If FunnelKit ever hooks both hooks, the move could print its container twice; `adopt_into_block()` moves only when the callback is not already on `ExpressPay::HOOK`.
- Rollback for the whole plan: revert the branch; there is no stored data, option or migration.
