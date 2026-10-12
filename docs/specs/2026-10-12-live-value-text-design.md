# Live Value Text Design

**Status:** accepted 2026-10-12
**Related:** None. Builds on the Field Value block design (`docs/specs/2026-10-08-field-value-block-design.md`).

## Goal

Merchants can drop live values into their own text in the layout editor, e.g. "Your cart is **$30.00** to pay", where `$30.00` is the cart's order total on the storefront and follows the cart as the shopper changes it. A value can be a cart total, a cart count, or a shopper's field.

## Context

- The checkout is WooCommerce's classic form. Totals refresh through `update_order_review` (`Renderer\CheckoutFragments::fragments()` on `woocommerce_update_order_review_fragments`), then `checkout.js` fires `updated_checkout` with the response. The quantity stepper, the coupon form, shipping and country changes all go through `update_checkout`.
- Custom inline formats already exist: the Login block's `yaycheckout/login-trigger` (`packages/blocks/src/login/trigger-format.tsx`) is a `registerFormatType()` span.
- Field Value (`packages/blocks/src/field-value/`) already decides which layout fields can be shown (`render.php`: Billing and Shipping Field blocks, `billing_email`, and Extra fields, excluding `checkbox`, `password` and `hidden` types and the single-checkbox Extra field). It prints their values on the server (country and state by name, choices by label, `CheckoutFieldDefaults::hidden_for_country()`) and keeps them live in `view.ts` through `shared/form-text.ts` (`controlsText()`).
- `Renderer\HideWhenEmpty` is the precedent for filtering `render_block_{name}` on core blocks.
- `BlockRegistry` prints `window.yayCheckoutBlocks` before the Checkout block's editor script (`BlockRegistry::inline_data()`); `editor_design_data()` adds keys to it in the block editor.
- `CheckoutPage::takes_over()` is true on a checkout (or preview link) that renders a layout.
- `packages/blocks` builds with the default `wp-scripts build`, which only picks up `block.json` entries.
- WordPress floor 6.5: core's Block Bindings API binds whole attributes only, so it cannot place a value inside a sentence.

## Scope

**In:**
- A RichText format `yaycheckout/live-value`, available in the layout editor only, in blocks named by an allow-list. The v1 list is `core/paragraph` and `core/heading`.
- Values: `cart_total`, `cart_subtotal`, `cart_shipping_total`, `cart_discount_total`, `cart_tax_total`, `cart_item_count`, `cart_line_count`, and `field:<key>` for each layout field Field Value can show.
- Each inserted value has an optional fallback text, shown when the value is empty.
- Server-rendered first paint, and live updates: cart values after each `update_order_review`, field values as the shopper types.
- Free and Pro.

**Out:**
- Rich-text blocks other than Paragraph and Heading (List, Button, YayCheckout blocks' labels). The allow-list is the scaffold for them; adding one is a later change.
- Free-shipping-remaining and other computed values (amount to threshold, savings).
- A per-value incl./excl. tax choice. Money follows WooCommerce's `tax_display_cart`.
- Number formatting options (decimals, currency symbol on or off).
- Live values anywhere other than a taken-over checkout (pages, emails, order-received).
- Auto-removing chips whose field left the layout.
- Changes to Field Value's behaviour (only its field logic is moved into shared helpers).

## Acceptance criteria

1. In the layout editor, with a Paragraph or Heading selected, the rich-text toolbar has a "Live value" button. With any other block selected, such as List, Button or YayCheckout blocks, it does not. In a non-layout editor (a page), the format isn't registered.
2. The button opens a popover with a value select grouped Cart totals (Order total, Subtotal, Shipping, Discount, Tax), Counts (Item count, Product count) and Fields (the layout's showable fields, each `Label (Billing|Shipping|Extra)`). It also has a "Fallback when empty" text input. Inserting puts a non-editable chip showing the value's label at the cursor. Clicking a chip reopens the popover to change its value or fallback, or to remove it.
3. A chip whose `field:<key>` is no longer a showable field of the layout shows "Missing field" in a warning style in the editor and stays in the text.
4. The saved markup is `<span class="yaycheckout-live-value" data-yaycheckout-value="<key>" data-fallback="<fallback>"><label></span>`, inside the block's normal markup.
5. Given a Paragraph "Your cart is [Order total] to pay" and a cart of one $30.00 product, no tax and no shipping, the storefront prints "Your cart is $30.00 to pay". The `$30.00` is plain text formatted by `wc_price()` (store currency, decimals and separators) with no "(ex. tax)" suffix.
6. Changing the quantity to 2 with the Order Summary Items stepper changes it to "$60.00" without reloading. "[Item count]" in the same layout changes from `1` to `2`. Applying a 10% coupon changes "[Discount]" from its fallback (or `$0.00`) to `$6.00`.
7. With `tax_display_cart` = `incl`, Subtotal, Shipping and Discount include their tax. With `excl`, they do not. Both cases match the Order Summary Totals block's rows.
8. A cart value of 0 with fallback `Free` prints `Free`, unmuted. With an empty fallback it prints `$0.00` (money) or `0` (counts).
9. Given "Hi [First name (Billing)]," with fallback `there`, with an empty session the storefront prints "Hi there," with `there` muted (`opacity: .5`). Typing `Ana` into First name changes it to "Hi Ana," unmuted, on each keystroke. Clearing it brings back the muted `there`. Reloading after typing prints `Ana` from the server, with no fallback flash.
10. `field:billing_country` shows the country's name, e.g. `United States (US)`. A state field hidden for the chosen country (e.g. VN) counts as empty.
11. A span with an unknown key, a `field:` key the served layout cannot show, or nested tags prints its fallback, or nothing, never its label.
12. A Paragraph without live values renders byte-for-byte as before, and the live-value script and style aren't enqueued.
13. End-to-end verification: on yaycheckout.test, in the active layout add a Paragraph "Your cart is [Order total] to pay ([Item count] items)" and a Heading "Hi [First name (Billing)]" with fallback `there`, then publish. Open the checkout with one product in the cart: it shows the cart's total and `1`, and "Hi there" muted. Type a first name: the heading follows. Increase quantity with the stepper: total and count update without reload. Apply a coupon: the total drops. Reload: all values are printed by the server.

## Constraints

- Product PHP runs on PHP 7.4 (no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types).
- Plugin floor WordPress 6.5, WooCommerce 10.0.0.
- Free and Pro; no code in `includes/Pro/`, no `Plan::is_pro()` check.
- Edit `packages/blocks/src/`, never `assets/dist/blocks/`; rebuild with `pnpm build:blocks`.
- Every value and fallback is escaped on output (`esc_html`, `esc_attr`). Values are plain text, never HTML.
- The PHP and TS allow-lists (`LiveValues::BLOCKS`, `LIVE_VALUE_BLOCKS`) must name the same blocks, each with a `Mirrors` comment pointing at the other (`docs/conventions.md`).
- No new tests: display only, so it doesn't meet the CLAUDE.md test bar. Verified by criterion 13.

## Approach

**Chosen:** A: a RichText format, filled by the server, kept live by a fragment and form listeners.

**Architecture:** A `registerFormatType()` span marks a value in Paragraph or Heading text. Only its data attributes matter; its text is the editor label. On a taken-over checkout, `Renderer\LiveValues` filters each allow-listed block's rendered HTML and replaces each span's text with the current value or fallback. Cart values reach the browser as a JSON element that `update_order_review` swaps as a fragment; field values are read from the form as the shopper types, by the same reader Field Value uses. The block allow-list is the only place that decides which blocks hold live values, so more rich-text blocks can be added later with a one-line change in each language.

**Components:**
- `includes/Renderer/LiveValues.php`. Singleton like the other renderers, constructed where they are. It owns:
  - `BLOCKS` = `[ 'core/paragraph', 'core/heading' ]`;
  - `cart_values(): array` (key => translated label, for the editor);
  - `values(): array` (key => `[ 'text' => string, 'empty' => bool ]` for the current cart);
  - the `render_block_{name}` filters;
  - the footer JSON element, the fragment, and enqueuing.
- `includes/Blocks/BlockRegistry.php`. In the layout editor only, enqueue the `live-value/index` editor script after the Checkout editor script and add `window.yayCheckoutBlocks.liveValues` = `LiveValues::cart_values()`. Register the `live-value/view` script and style handles.
- A shared PHP helper for "the layout's showable fields and their server values". It is extracted from `packages/blocks/src/field-value/render.php` (showable-field lookup and `$yaycheckout_value_of`) into a new `includes/Renderer/ShowableFields.php` (`ShowableFields::labels( string $layout_content ): array` key => label, and `ShowableFields::value( string $key, string $layout_content ): string`). `field-value/render.php` then calls it, with its output unchanged.
- The matching TS helper extracted from `packages/blocks/src/field-value/edit.tsx` (the layout's pickable fields and labels) into `packages/blocks/src/shared/showable-fields.ts`, used by both Field Value and the format's picker.
- `packages/blocks/src/live-value/format.tsx`: `registerFormatType( 'yaycheckout/live-value', { title: 'Live value', tagName: 'span', className: 'yaycheckout-live-value', contentEditable: false, attributes: { value: 'data-yaycheckout-value', fallback: 'data-fallback' }, edit } )`. The toolbar button shows only when the selected block's name is in `LIVE_VALUE_BLOCKS`.
- `packages/blocks/src/live-value/popover.tsx`: value select and fallback input; insert, update and remove.
- `packages/blocks/src/live-value/constants.ts`: `LIVE_VALUE_BLOCKS`, `LIVE_VALUE_CLASS`, the `field:` prefix.
- `packages/blocks/src/live-value/index.ts`: the editor entry (imports the format and the editor CSS).
- `packages/blocks/src/live-value/editor.css`: chip style, and the "Missing field" warning style.
- `packages/blocks/src/live-value/view.ts`: the storefront script.
- `packages/blocks/src/live-value/style.css`: `.yaycheckout-live-value.is-empty[data-yaycheckout-value^="field:"] { opacity: .5; }`.
- `packages/blocks/webpack.config.js`: extends `@wordpress/scripts/config/webpack.config` and adds the entries `live-value/index` and `live-value/view` next to the default `block.json` entries. `package.json` scripts stay the same (wp-scripts picks up `webpack.config.js`).

**Data and storage:** None. The value key and fallback live in the layout's post content, as span attributes. Values come from `WC()->cart`, the session/customer (`CheckoutFieldDefaults::value()`) and the live form. Nothing to migrate or uninstall.

**Integration points:**
- Values (money is display-adjusted when `WC()->cart->display_prices_including_tax()`):
  - `cart_total` = `get_total( 'edit' )`;
  - `cart_subtotal` = `get_subtotal()` (+ `get_subtotal_tax()`);
  - `cart_shipping_total` = `get_shipping_total()` (+ `get_shipping_tax()`);
  - `cart_discount_total` = `get_discount_total()` (+ `get_discount_tax()`);
  - `cart_tax_total` = `get_total_tax()`.
  Money text is `html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' )`. `cart_item_count` = `get_cart_contents_count()` and `cart_line_count` = `count( get_cart() )`, through `number_format_i18n()`. A money or count value is empty when it is 0; a field is empty when its text is `''`.
- Server: `add_filter( "render_block_{$name}", … )` for each of `LiveValues::BLOCKS`. It acts only when `CheckoutPage::get_instance()->takes_over()` and the HTML contains `yaycheckout-live-value`. It matches `<span …class="…yaycheckout-live-value…"…>text without tags</span>` and reads `data-yaycheckout-value` and `data-fallback` with `WP_HTML_Tag_Processor`. It prints the value text, or the fallback when the value is empty and the fallback isn't `''`, and adds `is-empty` whenever the value is empty.
- Footer: on `wp_footer`, when at least one span rendered, print `<script type="application/json" id="yaycheckout-live-values">` holding `wp_json_encode( LiveValues::values() )`, and enqueue the view script and style.
- Fragment: `woocommerce_update_order_review_fragments` adds `#yaycheckout-live-values` => the same element, built for the recalculated cart. It is added only when the served layout's markup contains `yaycheckout-live-value`.
- Storefront script:
  - On `updated_checkout`, parse `#yaycheckout-live-values` and update each `[data-yaycheckout-value]` span without the `field:` prefix.
  - On `input change country_to_state_changed updated_checkout` (jQuery, `document.body`), update each `field:` span from `form.checkout [name="<key>"]` via `controlsText()`. A control hidden for the country, or no control at all, counts as empty.
  - Each update sets the text to the value or fallback and toggles `is-empty`.

**UI:** A toolbar button and popover in the layout editor's rich-text toolbar, built with `@wordpress/components` (`Popover`, `SelectControl` with optgroups, `TextControl`, `Button`), matching the Login trigger format. Storefront output is inline text inside the merchant's Paragraph or Heading.

**Edge cases and failure modes:**
- Unknown key, a `field:` key the layout can't show, or a span holding tags: print the fallback when the value is empty or unknown, otherwise `''`, never the label. The script leaves such spans as the server printed them.
- Fields with Remember value off (Extra fields): the server prints empty on load, and the script fills them, as Field Value does.
- A span outside a taken-over checkout (markup copied to a page): left untouched, and shows its label.
- The fragment arrives but the JSON fails to parse: the spans keep their last text.
- Multi-currency plugins: follow `wc_price()` and the cart totals they filter, as the Totals block does.
- Several spans with the same key: all are updated.
- A Paragraph inside a Group hidden by Hide when empty: unaffected. Live-value spans are not watchable.

**Decision record:** None. It extends the existing inline-format, `render_block` and fragment patterns.

## Alternatives considered

| Option | Why rejected |
|---|---|
| B: Core Block Bindings API | Binds a whole attribute, not a phrase inside text. Its editor UI arrives in WP 6.7, above the 6.5 floor. |
| C: Dedicated "Live Text" block with a `{token}` template | Merchants lose their own Paragraph and Heading and core typography, and it can't extend to other rich-text blocks. |
| D: Typed `{{key}}` text tokens | No picker, silent typos, raw braces in the editor. |
| Sample value in the editor chip (e.g. `$30.00`) | The editor has no shopper cart, so the number would be invented. A labelled chip reads as dynamic. |
| Per-value incl./excl. tax choice | More options and edge cases. Following WooCommerce keeps the values in step with the Totals block. |
| Hand-written `assets/js/` storefront script | Would duplicate `controlsText()` from `shared/form-text.ts`. A webpack entry reuses it. |

## Rollout

None. A new editor feature; existing layouts render unchanged (criterion 12). Release note: "Insert live cart totals, counts and shopper fields into Paragraph and Heading text."

## Open questions

None.
