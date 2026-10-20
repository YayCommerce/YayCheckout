# A plugin spot prints the plugin's shortcode in every edition, and never becomes the plugin's block

**Purpose:** why the placeholder that holds a spot for another Yay plugin's feature fills it through a shortcode in YayBoost Free and Pro alike, why it no longer swaps in YayBoost Pro's real block, and how YayCheckout reaches YayBoost to do it.

**Update when:** never edit it. To change the decision, write a record that supersedes it.

## Status

Proposed, 2026-10-20. It supersedes [the free-edition fallback record](2026-10-19-plugin-block-free-fallback.md) whole, restating its decisions that still hold and dropping the swap to YayBoost Pro's block.

## Context

- **What the superseded record decided:**
  - The placeholder prints a shortcode, and YayCheckout registers YayBoost's shortcodes while YayBoost has none.
  - YayBoost's own hook output stops where the layout has a spot.
  - The card states come from the server.
  - Licensed YayBoost Pro still swapped in its real block from the card. A registered real block counted as a spot, and templates printed the real block when it was registered.
- **What two editions on two paths cost:**
  - The same layout behaved differently in Free and Pro: a card or a stand-in in one, YayBoost's block in the other. Each path needed its own checks.
  - Keeping the swap working took machinery that served only it:
    - `ready` state;
    - **Add block** and the missing-block card;
    - re-adding Order Bump to the layout editor, which YayBoost hides there;
    - bootstrapping block definitions and injecting scripts after an activation, with Save and reload as the fallback.
  - A test of the swap found the order bump printing twice when the real block and the hook output both ran.
- **What YayBoost offers:** still no shortcode of its own. It has a public action, `yayboost_register_features`, that hands over its `FeatureRegistry`, and public render methods on the features, in both editions. Pro prints its bumps from the renderer's `render()`, which the order bump shortcode already calls when `render_all_bumps_for_block()` is missing.
- **Options turned down:**
  - keeping the swap for Pro only: it keeps two paths and the machinery above;
  - hiding YayBoost's real blocks in the layout editor: where YayBoost's blocks may go stays YayBoost's call;
  - waiting for YayBoost to ship shortcodes: merchants get nothing until then;
  - calling YayBoost's render methods straight from the placeholder: it ties YayCheckout to YayBoost internals for good, with no contract for YayBoost to take over.

## Decision

- **The placeholder prints a shortcode, in every edition.**
  - On the storefront, `yaycheckout/plugin-block` prints its catalogue entry's `shortcode` when that shortcode exists, whether YayBoost Free or licensed Pro runs.
  - It never swaps itself for the plugin's block.
  - Templates print the placeholder, whatever the site has registered.
- **YayCheckout stands in for YayBoost's shortcodes.**
  - `Integrations\YayBoost` keeps YayBoost's registry from `yayboost_register_features`.
  - It registers `[yayboost_order_bump]`, `[yayboost_free_shipping_bar]` and `[yayboost_frequently_bought_together]` only while YayBoost registers no shortcode of that name.
  - Every call into YayBoost is guarded, and anything thrown leaves the spot empty.
- **Only a placeholder is a spot.**
  - When the served layout holds a placeholder for a feature (`PluginBlockCatalogue::spots_in()`), `Integrations\YayBoost` removes YayBoost's hook output for that feature.
  - It also answers the filter `yayboost_render_default_output`, which YayBoost is asked to honour so the removal can go.
  - YayBoost's own block in a layout is no spot, so the hook output stays beside it.
  - Layouts without a placeholder keep YayBoost's positions.
- **YayBoost's own blocks are neither promoted nor re-added.** The layout editor offers them only as far as YayBoost itself does.
- **The card states come from the server.**
  - The catalogue resolves `not_installed`, `inactive`, `unlicensed`, `feature_off` and `shortcode` from `get_plugins()`, YayBoost's adapter and its registry.
  - After an install or activate, the card reads them back from the editor page it fetches, and takes that page's new stylesheets into the canvas.
- **The editor shows a stand-in for what the shortcode prints.** A spot in state `shortcode` shows a preview of the feature, built from YayBoost's markup with sample data, together with the plugin's settings link.

## Consequences

- **One path to check.**
  - A layout looks and prints the same with YayBoost Free and Pro.
  - The swap machinery is gone: the `ready` state, **Add block**, the missing-block card, the re-added Order Bump, and the script and block-definition injection.
- **Pro merchants lose YayBoost's block in the editor.**
  - Each spot shows YayCheckout's preview instead, which can differ from the merchant's YayBoost design. The free shipping bar preview, for example, shows the minimal-text design in the layout's Primary.
- **A merchant who inserts YayBoost's own block gets the feature twice.** The block prints it, and so does YayBoost's hook. The placeholder is the way to place a feature.
- **The shim depends on YayBoost internals** until [the backlog item](../backlog/2026-10-19-yayboost-shortcode-shim.md) is done:
  - the render methods;
  - how the order bump is hooked: closures bound to `OrderBumpFeature` in Free, the renderer's `render()` in Pro.

  If YayBoost changes them, the spot prints nothing, or the bump prints twice. A crash inside YayBoost's own rendering is caught at the spot, but not on YayBoost's hooks.
