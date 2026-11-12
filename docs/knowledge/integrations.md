---
area: integrations
updated: 2026-11-12
updated_ref: 44fdc3b
covers: [includes/Integrations/, includes/Helpers/IntegrationsHelper.php, includes/Helpers/PluginBlockCatalogue.php, includes/Helpers/GatewayCompatibilityHelper.php, includes/Helpers/StoreEnvironmentHelper.php, includes/Support/CompatibilityCatalogue.php, includes/Repositories/CompatibilityCatalogueRepository.php, includes/Repositories/FontRepository.php, includes/Renderer/ExpressPay.php]
---
# Integrations

> Update when: compatibility code for a WooCommerce surface, a gateway or another plugin is added or changes.

## WooCommerce

| Surface | How | Where |
|---|---|---|
| Classic checkout | taken over: the layout prints WooCommerce's own form, fields and fragments | [storefront.md](storefront.md#how-it-stays-a-woocommerce-checkout) |
| Block checkout | not taken over. The Compatibility page reports the store type, via `CartCheckoutUtils::is_checkout_block_default()` | `Helpers\StoreEnvironmentHelper::store_type()` |
| HPOS | declared compatible; core doesn't read or write order meta | `Engine\ActDeact` |
| Order-received | Pro's thank-you page, gated by WooCommerce's own rules and firing `woocommerce_thankyou` | `includes/Pro/` |
| Order-pay | never taken over | `Renderer\CheckoutPage::on_checkout_page()` |

## Gateways

- **The compatibility catalogue.** `Support\CompatibilityCatalogue` gives each gateway or plugin a status: verified, community, unverified or block-only.
  - The remote list is `YAYCHECKOUT_COMPATIBILITY_CATALOGUE_URL`, fetched with a 5 s timeout and cached (see [architecture.md](architecture.md#storage)).
  - The bundled fallback covers `cod`, `bacs`, `cheque`, `stripe`, `ppcp-gateway` and `klarna_payments`.
- **Block-only gateways.** `Helpers\GatewayCompatibilityHelper` lists the enabled classic gateways, then the Checkout-block payment methods that have no classic counterpart, as `block-only`. Those can't run in a layout.
- **Stripe Express.** Its params get `has_block=false` when the layout has an Express Pay block (`Renderer\ExpressPay`).
- **Wallet orders.** A gateway's own wallet endpoint posts a marker key; `ExpressPay::is_wallet_order()` reads it and the layout's own required rules stand down for that order. A gateway with another key is added through `yaycheckout_express_wallet_order_markers`. See [storefront.md](storefront.md#how-it-stays-a-woocommerce-checkout).

## Other plugins

- **YayBoost** (`Integrations\YayBoost`, active when `YAYBOOST_VERSION` is defined):
  - reads YayBoost's feature registry lazily, because YayBoost boots at `plugins_loaded` 20;
  - registers the `yayboost_*` shortcodes only when YayBoost has none of its own;
  - unhooks YayBoost's own output for the features the layout has a spot for, so each prints once;
  - reprints spots on `update_order_review`.

  Every call into YayBoost is guarded with `class_exists` or `method_exists`. The views are `templates/yayboost-fbt.php` and `templates/yayboost-free-shipping-bar.php`.
- **Germanized** (`Integrations\Germanized`, active when `WC_germanized` exists):
  - drops its item thumbnail while the Items block prints;
  - renders its checkboxes on `yaycheckout_checkout_terms`;
  - removes its submit button on takeover;
  - adds CSS at priority 21, after checkout-base at 20.
- **FunnelKit Stripe** (`Integrations\FunnelKitStripe`): its "inline" checkout position prints the wallet buttons on `woocommerce_checkout_billing`, which no block fires, so the adapter registers `FKWCS\Gateway\Stripe\SmartButtons::payment_request_button` with `ExpressPay::adopt()`. Inert without FunnelKit. Other gateways: BL-5.
- **Plugin spots.** `Helpers\PluginBlockCatalogue` lists the placeholder spots, extensible with `yaycheckout_plugin_blocks`. `Helpers\IntegrationsHelper` tells the SPA which companion plugins are loaded (`YAYBOOST_VERSION`, `YAYLOYALTY_VERSION`). Icons are bundled, because wordpress.org forbids remote images.

## WordPress

- **Font Library.** `Repositories\FontRepository` installs picked Google fonts as `wp_font_family` and `wp_font_face` posts when a design is saved. It's guarded with `class_exists( 'WP_Font_Library' )`.
- **Icon block.** `Blocks\IconRegistry` adds a `yaycheckout` icon collection when `wp_register_icon` exists.
- **Viewport gaps.** `Renderer\BlockGap` prints viewport gap values only on WordPress 7.1+, guarded with `method_exists`.
