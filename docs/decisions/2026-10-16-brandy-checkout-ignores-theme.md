# On the Brandy theme, a layout checkout loads none of the theme's assets and uses Twenty Twenty-Five's theme.json with Brandy's fonts

**Purpose:** why a layout checkout on Brandy strips the theme's stylesheets, scripts and inline CSS, swaps its theme.json for a bundled one, and how a site keeps Brandy's.

**Update when:** never edit it. To change the decision, write a record that supersedes it.

## Status

proposed, 2026-10-16.

## Context

- The checkout template is YayCheckout's own (`templates/checkout-page.php`), so Brandy's header and footer never print there. But the theme's assets still load through `wp_head()` and `wp_footer()`:
  - its Vite sheets (`brandy_main`, `brandy_wc`, `brandy_wc-components`, `brandy_wc-checkout`), which style every input, button, notice and the WooCommerce checkout block;
  - the `brandy-frontend` script;
  - the `#brandy-dynamic-css` and `#brandy-wc-css` inline blocks;
  - `brandy-global-style-variables` and an iOS input font-size fix;
  - `PerformanceService`, which moves jQuery to the footer and makes some sheets load async;
  - theme.json global styles (the Brandy palette, the Outfit font, heading and button element styles).
- These beat the layout's design wherever their selectors are more specific than the base styles, and [the base-styles record](2026-10-11-checkout-base-styles-and-heading-font.md) rules out an arms race of overrides. The ask was a Brandy checkout that looks like the layout in the editor.
- Brandy names its handles after hashed Vite files and prints part of its CSS as raw `<style>` blocks outside the style queue, so a list of handles to dequeue would miss some and go stale.
- Options turned down:
  - ignoring the theme on every theme: not asked for. Other themes keep the base-styles behaviour, where empty tokens follow the theme;
  - dequeuing a fixed list of handles: it misses the inline blocks and the hashed names;
  - also stripping Brandy Blocks' assets (wishlist, compare, frosted glass): they belong to a plugin and were kept on purpose;
  - an empty theme.json: core's defaults alone leave out what blocks and the layout expect from a theme: spacing, font-size and color presets, content and wide sizes, block gap and root padding, and element styles. The checkout lost them.
- Twenty Twenty-Five is core's current default theme, and the editor's look follows it on most sites. Its theme.json is GPL.
- The font is part of each Brandy niche's look. A niche's niche.json, merged into the theme data by Brandy's `wp_theme_json_data_theme` filter, sets a body font, a heading font or both, as a font family preset. Brandy's own default is the system font stack.

## Decision

- `Integrations\Brandy` acts only when Brandy is the active theme (`BRANDY_VERSION` is defined) and a layout takes over the checkout.
- It removes every callback that a file under the theme's directory, or a child theme's, adds to the asset hooks: `wp_enqueue_scripts`, `enqueue_block_assets`, `wp_head`, the `wp_print_*` hooks, `wp_footer` and the loader-tag filters. A file is matched by reflection (`CheckoutHookScanHelper::callback_file()`), so closures are caught too.
- As a backstop, it dequeues any style or script whose `src` lies under the theme, plus the theme's inline-only handles, at the end of `wp_enqueue_scripts` and before footer scripts print.
- The theme's theme.json data is replaced with `assets/theme-json/checkout.json`, Twenty Twenty-Five 1.5's `theme.json` without its template parts and custom templates. The Site Editor's data for Brandy is replaced with an empty document. Core layout CSS (`is-layout-*`) stays.
- The bundled file has no font files. Twenty Twenty-Five's Manrope is replaced with a `system-fonts` preset, the system font stack, as the body font. Fira Code, its monospace face, is kept as a family name only.
- Brandy's body font (`styles.typography.fontFamily`) and heading font (`styles.elements.heading.typography.fontFamily`) are kept. They are read from the theme data after every other filter has run, so the current niche's fonts are included. The font family preset each one names is copied with its faces. Their `file:./` sources still resolve against Brandy's folder, and Brandy Sites rewrites its own to its plugin.
- The `yaycheckout_brandy_isolation` filter, default `true`, turns all of this off for a site that wants Brandy's styling on the checkout.

## Consequences

- On Brandy, an empty design token falls back to Twenty Twenty-Five's value rather than to Brandy's, except the body and heading fonts, which follow the niche. A layout that picks the niche's font prints no second `@font-face`, since core already prints it.
- Fonts set only in the Site Editor's global styles are not kept: the Site Editor's data is dropped whole.
- `checkout.json` is a copy. A Twenty Twenty-Five update doesn't reach it until someone copies it again.
- Anything Brandy adds to the checkout through those hooks is gone with it, including fixes Brandy ships for other Yay plugins (its YayCurrency and YayExtra integration CSS). Such a fix, if the checkout needs it, belongs in a YayCheckout integration.
- Scripts Brandy moved to the footer load where WordPress puts them, which is what payment gateways expect.
- A future theme that needs the same treatment would repeat this pattern in its own class. A second one is the time to factor out a shared helper.
