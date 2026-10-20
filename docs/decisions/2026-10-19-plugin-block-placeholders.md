# Templates promote other Yay plugins' blocks through one placeholder block

**Purpose:** why a template holds a placeholder for another Yay plugin's block, not the block itself or a stub under its name, and why the placeholder installs and activates that plugin in place.

**Update when:** never edit it. To change the decision, write a record that supersedes it.

## Status

Superseded by [2026-10-19-plugin-block-free-fallback](2026-10-19-plugin-block-free-fallback.md).

## Context

- Some template mocks draw parts that another Yay plugin's blocks provide. In Up cross-sell, YayBoost provides the order bump and the free-shipping bar, and FBT is still to come. A template's markup is copied into the layout once, when the layout is created, so it can't depend on which plugins are active.
- A block from a plugin that isn't active shows as core's "missing block" in the editor and prints nothing on the storefront. That tells the merchant neither what it was nor how to get it.
- YayBoost registers a block only while that block's feature is on, and its own `allowed_block_types_all` filter hides Order Bump outside checkout templates and the checkout page, the YayCheckout layout editor included.
- Options turned down:
  - writing the real block names into templates, with stubs registered under those names while the plugin is missing, as Free does for Pro blocks: a stub can't give way to the real block without a reload, and our markup would carry YayBoost's attributes;
  - one hard-coded Order Bump placeholder: the next promoted block would need a refactor;
  - keeping the placeholder and rendering the real block through it on the storefront: it couples the two plugins at runtime;
  - swapping placeholders by themselves when the editor loads: it changes markup the merchant didn't touch;
  - always linking out to the Plugins screen, or always saving and reloading after activation: either takes the merchant out of the editor when core can do both in place.

## Decision

- `Helpers\PluginBlockCatalogue` lists the promoted blocks (target block, plugin, wordpress.org slug, title, pitch, settings and docs links); other plugins can add to it through the `yaycheckout_plugin_blocks` filter. In the layout editor, `BlockRegistry` prints the catalogue as `window.yayCheckoutPluginBlocks`, with each entry's state on this site: `not_installed`, `inactive`, `feature_off`, `unavailable` or `ready`.
- A template holds `yaycheckout/plugin-block {"target":"<block>"}`, a Free block that prints nothing on the storefront. It is a card in the editor, and the inserter offers one variation per catalogue entry under From Yay plugins.
- The card installs and activates the plugin through core's `/wp/v2/plugins` routes when the user has `install_plugins` or `activate_plugins`, and links to the Plugins screen otherwise. YayCheckout adds no route of its own.
- After activation, `shared/load-editor-assets.ts` fetches the editor page again, as core's Block Directory does. It hands core the server-side block definitions that page prints (`wp.blocks.unstable__bootstrapServerSideBlockDefinitions`), since a block.json block's editor script registers with its name alone and relies on them. Then it injects the scripts and styles this page lacks, with the styles also going into the canvas iframe, so the plugin's blocks register and look right without a reload. When the block still isn't registered (its feature is off, or injection fell short), the card offers Save and reload.
- A template prints each spot through `PluginBlockCatalogue::block( $target )`, which prints the block when this site has registered it and the placeholder otherwise. A merchant who already runs the plugin starts with the real block, and an existing layout is never changed by itself.
- **Add block** replaces the placeholder with the real block. The placeholder's margins carry over.
- `BlockRegistry::allowed_blocks()` lists every catalogue target in the layout editor, registered or not. That overrides a plugin that hides its block there, and it lets a block activated from the card be inserted, since the editor fixes its allowed list when it loads.

## Consequences

- A new promoted block is one catalogue entry, plus placeholders in the templates that want it.
- The placeholder has no ancestor rule: the blocks it stands for have none, and an ancestor naming Checkout would make Order Summary Details and the address sections refuse it.
- The definitions come from the editor page, not from core's `/wp/v2/block-types`: YayBoost puts PHP objects in its blocks' `provides_context`, which makes that route fatal.
- Only a merchant with the core plugin capabilities installs or activates anything, and nothing reaches wordpress.org until they click.
- Overriding YayBoost's filter is temporary: [the backlog item](../backlog/2026-10-19-yayboost-shortcode-shim.md) tracks YayBoost allowing the layout editor itself.
