---
id: BS-2
title: Express pay v2: gateway buttons the layout never prints, and wallet orders the layout blocks
status: open
from: R-6
decision:
created: 2026-11-12
---
# BS-2 Express pay v2: gateway buttons the layout never prints, and wallet orders the layout blocks

## Problem
R-6 (sprint 1, target 1.1.0, Free and Pro) asks for the next version of the Express Pay block. Its acceptance: *express pay buttons show and complete payment from the checkout layout in Free and Pro, including when a gateway offers no express method.*

v1 (#171, `b8e9714`) made the block fire WooCommerce's classic `woocommerce_checkout_before_customer_details` / `..._after_customer_details` (`includes/Renderer/ExpressPay.php:14-18`, `packages/blocks/src/express-pay/render.php:19-22`), cleared WooCommerce Stripe's `has_block` (`includes/Renderer/CheckoutPage.php:87-89`), and hides the block and its "OR" divider until a wallet button shows (`view.ts`, `style.css`). Reading the code and the gateways installed on the dev site, two gaps stop the acceptance from holding:

1. **Buttons a layout never prints.** A gateway that puts its wallet buttons on a classic hook other than "before customer details" vanishes from a layout. FunnelKit Stripe's "inline" checkout position hooks `woocommerce_checkout_billing` (`funnelkit-stripe-woo-payment-gateway/gateways/smart-buttons.php:74-78`), and no YayCheckout block fires that hook: only a WooCommerce Hook block set to "Billing" would (`git grep woocommerce_checkout_billing` finds it only in `includes/Renderer/WcHooks.php`). The Billing block prints fields itself. The editor's "no gateway" notice then also says the block is empty when a gateway is ready (`ExpressPay::has_buttons()` counts only `HOOK`, `ExpressPay.php:24-37`).
2. **Wallet orders the layout blocks.** A wallet order is a `WC()->checkout()->process_checkout()` call from the gateway's own wc-ajax endpoint with only what the wallet knows: name, email, phone, address (FunnelKit: `includes/traits/wc-smart-button-functions.php:817-856`, marked by `payment_request_type`). YayCheckout's layout rules still run on it:
   - `CheckoutExtraFields::validate()` adds an error for each required extra field (`includes/Renderer/CheckoutExtraFields.php:88-92`), which the wallet never sends, so the payment sheet fails.
   - `CheckoutRequiredFields::filter_fields()` (priority 9999, not page-gated, `includes/Renderer/CheckoutRequiredFields.php:35-55`) makes layout-required address fields required. FunnelKit un-requires only WooCommerce's default list at `PHP_INT_MAX` (`wc-smart-button-functions.php:877-901`), so a field the layout made required (company, a shipping field) still fails the order.

"When a gateway offers no express method" already prints nothing (`render.php:27-30`) and hides the divider through the "Block is empty" rule (`templates/layouts/split-express/markup.php:181`), but nothing tests it.

## Constraints
- **Editions:** the block and `Renderer\ExpressPay` are Free code; both editions get the change. Nothing may name `YayCheckout\Pro` (hard rule 1).
- **PHP 7.4** (hard rule 3).
- **The form stays WooCommerce's** (hard rule 5): wallet buttons and orders stay the gateway's; YayCheckout only places markup and stands its own rules down.
- **Hook blocks win:** once a layout places a WooCommerce Hook block for a hook, only that block fires it (`WcHooks::in_place()`, `WcHooks.php:385-405`). A merchant who put a "Billing" Hook block keeps FunnelKit's inline buttons there.
- **Classic checkout only.** Block-only gateways can't run in a layout (`docs/knowledge/integrations.md:26`).
- No shipped users yet: no migration or back-compat (project memory).
- Gateway plugins are third-party; only FunnelKit Stripe and PayPal Payments are installed locally. WooCommerce Stripe and WooPayments behaviour is from their public code and must be checked by hand.

## Options

### A. Harden the classic path: adopt stray wallet buttons, stand down for wallet orders
- **How:**
  - `ExpressPay` gains a small adoption list: an integration registers "this gateway's button callback, on this classic hook" (class + method, resolved against `$wp_filter` like `WcHooks::kept_in_place()` does). When a layout with an Express Pay block takes over (`CheckoutPage::maybe_take_over()`, next to the Stripe filter), each adopted callback moves onto `ExpressPay::HOOK` at its priority, unless a Hook block places its original hook. `has_buttons()` counts adopted callbacks too.
  - `Integrations\FunnelKitStripe` (new, like `Integrations\Germanized`) registers FunnelKit's `SmartButtons::payment_request_button` on `woocommerce_checkout_billing`.
  - `ExpressPay::is_wallet_order()` reads the wallet-order POST markers the gateways send (`payment_request_type`; filterable). On such a request `CheckoutRequiredFields` leaves WooCommerce's field set alone and `CheckoutExtraFields` skips its required errors, so the gateway's own rules decide.
- **Pros:** meets both halves of the acceptance with small, local changes; no new UI; reuses `WcHooks`' `$wp_filter` handling; each gateway is one registration line.
- **Cons:** one adapter per gateway that uses a stray hook; wallet orders carry no required custom field values (a merchant-visible tradeoff); the POST marker can be forged by a shopper to skip the layout's own required flags (WooCommerce's and the gateway's rules still apply).
- **Risks:** a gateway update renames its callback, and the adoption silently does nothing (buttons gone, as today). Mitigated by the manual check and a log line when an adopted class exists but its callback isn't found.
- **Edition split:** all Free code; Pro unchanged.
- **Size:** M.

### B. Express Pay block settings: a merchant-facing v2 of the block
- **How:** add block attributes for a heading ("Express checkout"), button height and theme, and per-gateway on/off, then push them into each gateway through its params filter (`wc_stripe_express_checkout_params` button height/theme, FunnelKit's `fkwcs_localized_data`), with editor controls and a richer preview.
- **Pros:** visible "v2" for merchants; matches how Shopify/FunnelKit Checkout style their express row.
- **Cons:** doesn't fix either gap above, so the acceptance still fails for FunnelKit inline and for layouts with required custom fields; each gateway's params differ and change between versions; new UI needs a design.
- **Edition split:** Free (or styling in Pro, a pricing call).
- **Size:** M–L.

### C. YayCheckout's own wallet integration
- **How:** render Apple Pay / Google Pay ourselves through Stripe's Payment Request / Express Checkout Element and create orders through our own endpoint.
- **Pros:** full control of look and the order data, including asking for required custom fields first.
- **Cons:** we'd own payments for one gateway only, duplicate what gateways already do, and break hard rule 5's spirit (gateways stay WooCommerce's); PCI and support load.
- **Size:** L.

## Recommendation
**A.** The roadmap's acceptance is about buttons showing and payments completing, and both failures are in code YayCheckout owns: a hook no block fires, and layout validation that runs on wallet orders. A fixes them where they start, in Free, without new UI, and keeps the gateways in charge of their buttons and orders. B is the natural follow-up once the buttons reliably show, so it becomes a deferred note rather than part of this item. C is out of proportion.

What would change my mind: if product wants required custom fields to block wallet orders (then A hides the buttons instead of standing down; see open questions), or if WooCommerce Stripe / WooPayments turn out to post no shared marker, in which case A needs a per-gateway wc-ajax action list instead of POST keys.

## Open questions
- **Required custom fields on wallet orders.** A lets a wallet order through without the layout's required extra fields (the wallet can't ask for them). The alternative is hiding the Express Pay block while the layout has a required extra field. A product call; A's default is to let the order through, like FunnelKit Checkout does for its own phone rule (`compatibilities/plugins/class-fk-checkout.php:69-77`).
- **Which gateways get adapters beyond FunnelKit Stripe.** WooCommerce Stripe and WooPayments print on "before customer details" already; PayPal Payments' smart buttons are payment-method buttons in the Payment block, not express buttons. Confirm with support which other gateways merchants use.
- **Block styling options (option B):** Free or Pro, and when.
