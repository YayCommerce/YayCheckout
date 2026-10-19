# YayBoost hides Order Bump from the layout editor, and YayCheckout stands in for YayBoost's missing shortcodes

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** building plugin block placeholders and their Free fallback, 2026-10-19, branch `trace/feat/plugin-block-placeholders`.

## Today

- YayBoost's `OrderBumpBlock::restrict_block_to_checkout()` (`allowed_block_types_all`, priority 10) removes `yayboost/order-bump` from every editor except checkout `wp_template`/`wp_template_part` and the WooCommerce checkout page. YayCheckout puts it back in the layout editor through `BlockRegistry::allowed_blocks()` (priority 999), which lists every `PluginBlockCatalogue` target.
- YayBoost's checkout blocks exist only in licensed Pro, and Free has no shortcode for the order bump or the free shipping bar. `Integrations\YayBoost` therefore:
  - registers `[yayboost_order_bump]` and `[yayboost_free_shipping_bar]` itself, through YayBoost internals (`OrderBumpFeature::get_renderer()`'s `render_all_bumps_for_block()` in Free or `render()` in Pro, `FreeShippingBarFeature::render_bar()`);
  - removes YayBoost's hook output for a feature the served layout has a spot for, finding the order bump's callbacks by their object: closures bound to `OrderBumpFeature` (Free 1.3.0) or the renderer's `render()` (Pro 1.3.2).
- YayBoost ticks a bump only when it prints it, and `order-bump.js` reads the tick back from the page, so a bump stays ticked after its product leaves the cart until something reprints it. WooCommerce reprints it with the payment area; a layout's spot is outside every fragment, so `Integrations\YayBoost::spot_fragments()` reprints the spot on `update_order_review`.
- `Integrations\YayBoost::order_bump_hooks()` tells the editor where a layout without a spot gets the bump: Pro's `woocommerce_review_order_before_submit`, or in Free each active bump's `position` from `BumpRepository::get_active()`, mapped by `OrderBumpRenderer::get_hook_for_position()`. A change to either drops or misplaces the editor's stand-in card.
- YayBoost's Frequently bought together has no shortcode, block or checkout output, and recommends for one product only. `Integrations\YayBoost::fbt_shortcode()` builds `FBTRenderer` itself (with `FrequentlyBoughtTogetherFeature::get_cache_manager()` and `get_settings()`), merges `get_prepared_products()` across the cart items, and prints its own markup; `assets/js/checkout-yayboost-fbt.js` adds through YayBoost's `yayboost_fbt_add_to_cart` AJAX action and nonce name.
- YayBoost's order bump has no style setting, CSS variables or filters, so `assets/css/yayboost.css` gives it the templates' card look by restyling the classes `OrderBumpRenderer::render_single_bump()` prints, inside any layout.
- YayBoost's own `OrderBumpBlock` links its settings to `admin.php?page=yayboost`, which WordPress refuses; the menu slug is `yayboost-settings`.
- YayBoost registers its blocks with PHP objects in `provides_context` (`OrderBumpBlock::register_block()` passes the feature and renderer), which makes core's `GET /wp/v2/block-types` fatal ("Object of class …FreeShippingBarFeature could not be converted to string"). YayCheckout reads block definitions from the editor page instead.

## Why it matters

The shim breaks quietly when YayBoost renames those methods or changes how it hooks its bumps: the spot prints nothing, or the bump prints twice. A renamed bump class drops the card look. A merchant following YayBoost's own block link lands on a page they can't open.

## Suggested fix

1. YayBoost adds `yaycheckout_layout` to `OrderBumpBlock::is_checkout_editor_context()`.
2. YayBoost registers the two shortcodes; YayCheckout's shim already steps aside when they exist.
3. YayBoost checks `apply_filters( 'yayboost_render_default_output', true, $feature_id )` before printing on its hooks; YayCheckout already returns false for a feature with a spot.
4. `order-bump.js` takes each bump's tick from the `yayboost_cart_state` snapshot it already receives on every refresh, not from the page.
5. YayBoost ships a cart-based Frequently bought together render (shortcode or public method), so the shim stops building its renderer and calling its AJAX action.
6. YayBoost reads its bump's border, background and accent colours from CSS variables, so `assets/css/yayboost.css` sets those instead of restyling its classes.

## Done when

- [ ] YayBoost allows the `yaycheckout_layout` post type, and `BlockRegistry::allowed_blocks()`'s re-add of catalogue targets is kept or dropped on purpose.
- [x] An unlicensed YayBoost Pro shows a card that leads to its license screen, not to a page WordPress refuses.
- [x] Both settings links open the right YayBoost screen.
- [x] Free Shipping Bar renders and updates on a YayCheckout checkout with a cart below the threshold.
- [ ] `GET /wp/v2/block-types?namespace=yayboost` answers 200 with YayBoost active.
- [ ] YayBoost registers `[yayboost_order_bump]` and `[yayboost_free_shipping_bar]` itself.
- [ ] YayBoost honours `yayboost_render_default_output( bool $render, string $feature_id )` before printing on its hooks, and `Integrations\YayBoost::unhook_order_bumps()` is dropped.
- [ ] `OrderBumpBlock`'s `settingsPageUrl` uses `admin.php?page=yayboost-settings`.
- [ ] `order-bump.js` ticks bumps from the cart state, and `Integrations\YayBoost::spot_fragments()` stops reprinting the order bump spot.
- [ ] YayBoost renders Frequently bought together for a cart itself, and `Integrations\YayBoost::fbt_shortcode()` stops constructing `FBTRenderer` and posting to `yayboost_fbt_add_to_cart`.
- [ ] YayBoost's bump takes its look from CSS variables, and `assets/css/yayboost.css` sets them instead of overriding `render_single_bump()`'s classes.
