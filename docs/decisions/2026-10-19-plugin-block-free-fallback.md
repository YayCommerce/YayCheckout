# A plugin block placeholder prints the plugin's free edition through a shortcode

**Purpose:** why the placeholder that holds a spot for another Yay plugin's block now prints on the storefront, which the record it supersedes turned down, and how YayCheckout reaches YayBoost to do it.

**Update when:** never edit it. To change the decision, write a record that supersedes it.

## Status

Proposed, 2026-10-19. Supersedes [Templates promote other Yay plugins' blocks through one placeholder block](2026-10-19-plugin-block-placeholders.md).

## Context

- **What the earlier record decided:** templates hold `yaycheckout/plugin-block` placeholders that print nothing on the storefront, and the card installs YayBoost from wordpress.org and swaps in its real block. It rejected rendering through the placeholder because that "couples the two plugins at runtime".
- **What an end-to-end test found:**
  - YayBoost's order bump and free shipping bar blocks exist only in YayBoost Pro, and Pro boots nothing without a license. The wordpress.org install the card offers could never fill a spot.
  - Free YayBoost prints the same features on WooCommerce checkout hooks, at positions the merchant picks. On a YayCheckout checkout they landed away from the template's spots.
  - With licensed Pro and the block added, the order bump printed twice: once from the block, once from its hook.
- **What Free YayBoost 1.3.0 offers:**
  - no shortcode for either feature;
  - a public action, `yayboost_register_features`, that hands over its `FeatureRegistry`;
  - public render methods on the features.
- **Options turned down:**
  - linking only to YayBoost Pro: it drops the merchants who run Free, the promotion's widest audience;
  - showing where Free's hook output lands, or bridging it with a WooCommerce Hook block: the result depends on each bump's position setting, a Hook block prints every plugin's output on that hook, and no hook sits at the sidebar spot;
  - waiting for YayBoost to ship shortcodes: Free merchants get nothing until then;
  - calling YayBoost's render methods straight from the placeholder: it ties YayCheckout to YayBoost internals for good, with no contract for YayBoost to take over.

## Decision

- **The placeholder prints a shortcode.** It prints its catalogue entry's `shortcode` on the storefront when that shortcode exists. Free YayBoost fills the spot that way; licensed Pro still swaps in the real block from the card.
- **YayCheckout stands in for YayBoost's shortcodes.**
  - `Integrations\YayBoost` keeps YayBoost's registry from `yayboost_register_features`.
  - It registers `[yayboost_order_bump]` and `[yayboost_free_shipping_bar]` only while YayBoost registers no shortcode of that name.
  - Every call into YayBoost is guarded, and anything thrown leaves the spot empty.
- **YayBoost's own output stops where the layout has a spot.**
  - When the served layout has a spot for a feature (a placeholder, or the registered real block: `PluginBlockCatalogue::spots_in()`), `Integrations\YayBoost` removes YayBoost's hook output for that feature.
  - It also answers the filter `yayboost_render_default_output`, which YayBoost is asked to honour so the removal can go.
  - Layouts without a spot keep YayBoost's positions.
- **The card states come from the server.** The catalogue resolves `unlicensed`, `feature_off`, `shortcode` and `ready` from YayBoost's adapter and registry. After an install or activate, the card reads them back from the editor page it already fetches.

## Consequences

- **Free merchants get the promotion's result.** A merchant on Free YayBoost sees the feature at the template's spot, and the card offers Pro for the block.
- **The shim depends on YayBoost internals** until [the backlog item](../backlog/2026-10-19-yayboost-allows-layout-editor.md) is done: the render methods, and how the order bump is hooked (closures bound to `OrderBumpFeature` in Free 1.3.0, the renderer's `render()` in Pro 1.3.2). If YayBoost changes them, the spot prints nothing, or the bump prints twice; it never fatals.
- **A real YayBoost block that is unregistered counts as no spot,** so YayBoost keeps printing on its hooks after Pro is deactivated. The editor shows the catalogue card in its place.
