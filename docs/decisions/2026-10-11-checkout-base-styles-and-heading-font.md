# YayCheckout ships its own base styles for the checkout, Design tokens are values only, and a picked font reaches the headings

**Purpose:** why the checkout loads YayCheckout's `checkout-base.css` in place of WooCommerce's general stylesheets, how styles are split between the base, a block's own CSS and the Design tokens, why a section left at theme default prints nothing, and why the design's font also sets the checkout's headings.

**Update when:** never edit it. Supersede it with a new record if the checkout loads WooCommerce's general stylesheets again, if tokens start choosing selectors beyond the page and heading rules, or if the base / block / token split changes.

## Status

Proposed, 2026-10-11, by Trace Bui. It supersedes [the base styles record](2026-10-08-checkout-owns-base-styles.md) whole, restating its decisions and adding the heading rule.

## Context

- The storefront renders the active layout in its own template, and the layout's blocks print WooCommerce's classic markup: `woocommerce_form_field()` rows, the gateway list and payment boxes, the shipping rates, select2 for country and state, notices and blockUI.
- WooCommerce's `woocommerce-general`, `woocommerce-layout` (floats and widths) and, on block themes, `woocommerce-blocktheme` styled that markup with selectors such as `.woocommerce-page form .form-row input.input-text` (0,3,2). Those rules assume WooCommerce's own checkout page. They beat anything the layout's design set short of `!important`, and they don't style the markup our blocks add.
- The layout editor once drew its own previews with hard-coded values, so the canvas and the storefront looked different.
- Themes style `h1`–`h6` directly, often with a heading font of their own (Twenty Twenty-Four sets Cardo, for example). A heading doesn't inherit the page rule's font, so with a picked font the section titles that Billing, Shipping, Order summary, Payment, Shipping methods and Login print kept the theme's heading font, in the editor and on the storefront. A merchant who picks a font expects the whole checkout to use it.
- Options turned down:
  - The reference design's full look as the base (fixed blue, grey borders, system font): it ignores the theme, and "Theme default" would no longer be true.
  - A structure-only base with no look for inputs or buttons: themes that don't style inputs, which is most block themes, would show browser defaults.
  - Styling payment and shipping rows in the base: one block prints each list, so its CSS should load only where that block renders.
  - Base selectors wrapped in `:where()` (0,1,0): they lose to a theme's `input[type="text"]` (0,1,1).
  - `@layer` for the base: unlayered theme CSS beats every layer.
  - Keeping WooCommerce's stylesheets and overriding them: an arms race against 0,3,2 selectors on every input.
  - DesignStyles printing selectors per token (for example a class per input style): the CSS would then live in two places.
  - Headings reading `--yaycheckout-font-family` in the base: an empty token would then need a fallback for the theme's heading font, which no custom property names.
  - A heading rule that sets the design font itself rather than `inherit`: it would beat the font a block sets on its own wrapper.

## Decision

- On the checkout page, `CheckoutPage` dequeues `woocommerce-general`, `woocommerce-blocktheme`, `woocommerce-layout` and `woocommerce-smallscreen`, and `Renderer\CheckoutBaseStyles` loads `assets/css/checkout-base.css` followed by the design. `LayoutEditorCanvas` loads the same into the editor canvas, without the merchant's custom CSS.
- Placement rule:
  - If two or more blocks print the markup, or WooCommerce prints it outside any block, its CSS goes in `checkout-base.css`.
  - If one block owns the markup, its CSS goes in that block's `style.css`, even when another block's looks alike: Payment and Shipping methods each draw their own choice rows, so either can change without the other.
  - Tokens supply values only, never selectors.
- Every token is read with a neutral fallback, resolved once at the top of `checkout-base.css` (`--yaycheckout-primary-color`, `--yaycheckout-border-color`, `--yaycheckout-field-radius`) or as `var(--yaycheckout-<name>, <fallback>)`. The fallback is neutral and follows the theme: inherited font and colour, a border mixed from the text colour, a transparent background, `#3858e9` behind the theme's primary preset. A section left at theme default prints nothing.
- `DesignStyles` prints two selectors, and only these:
  - The page rule (`body.yaycheckout-page, .editor-styles-wrapper`) carries the page background, text colour, font and base size when the design sets them. An unset property there would reset the theme's own value: background isn't inherited, and an unset inherited property on `body` takes `html`'s value.
  - The heading rule, printed only when the design sets a font, gives the checkout's `h1`–`h6` `font-family: inherit`. A heading with a `has-*-font-family` class of its own is left out, and `inherit` lets a block's own font reach the headings inside it.
- Specificity budget:
  - Base selectors are `.yaycheckout-checkout` plus at least one class or element, and anything appended outside the form is scoped to `body.yaycheckout-page`.
  - Block CSS starts with `.yaycheckout-checkout .<block-class>`, because block styles print before the base on the storefront.
  - No `!important`, except against WooCommerce's inline styles.

## Consequences

- The checkout looks finished on any theme without WooCommerce's general CSS, and a token changes the storefront and the editor canvas alike.
- A picked font covers the whole checkout, headings included. With Font at theme default, headings keep the theme's heading font.
- A theme with input selectors stronger than 0,2,0 can still outrank the base, and a theme heading selector stronger than (0,2,2) can still outrank the heading rule. The merchant's custom CSS, which prints last, is the escape hatch.
- WooCommerce markup that changes shape (a new notice template, select2 markup) needs the base updated, because WooCommerce's own CSS for it is no longer loaded.
- New WooCommerce-printed markup inside a single block gets block CSS. New markup several blocks share goes in the base, so it reaches the editor canvas too.
- `shared/design-css.ts` copies both selectors, so the editor canvas reprints them as the merchant edits; the two change together.
