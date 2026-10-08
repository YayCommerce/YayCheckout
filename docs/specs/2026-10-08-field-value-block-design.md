# Field Value Block Design

**Status:** accepted 2026-10-08
**Related:** None. Making Order Summary accept inner blocks is a separate branch, merged later.

## Goal

Merchants can add blocks that echo what the shopper has typed or picked at checkout (email, phone, name, address, country, …), one field per block, with a muted placeholder until filled. Stacked under headings from other blocks (e.g. core Heading or Paragraph) they give a "CONTACT / SHIP TO" recap; later they will sit inside Order Summary.

## Context

- Blocks are dynamic: `block.json` + `render.php` per block, leaf `save()` returns null via `registerDynamicBlock()` (`packages/blocks/src/shared/register-dynamic-block.tsx`). `Blocks\BlockRegistry::ORDER` sets the inserter order.
- `yaycheckout/order-summary` is a leaf today (`$content` unused). Another branch turns it into a container; this design does not touch it.
- Field keys the picker offers come from `packages/blocks/src/shared/address-fields.ts` (`billingAddressFields()`, `shippingAddressFields()`), plus `billing_email` from the Email block.
- `Renderer\CheckoutFieldDefaults::value( $key )` returns the value classic checkout prints (`WC()->checkout()->get_value()`, session or account), null outside a storefront request. Field and Email `render.php` already use it.
- `isCheckoutSection()` (`packages/blocks/src/shared/allowed-blocks.ts`) keeps every block whose ancestor rule names Checkout out of Billing and Shipping.
- Steps switch in the browser (`packages/blocks/src/steps/view.ts`), so every step's inputs stay in the DOM.
- The Shipping block prints no `ship_to_different_address` checkbox, so WooCommerce copies billing into shipping (`docs/architecture.md`, Fields).
- WooCommerce's select2 country/state selects and `country-select.js` fire `change` through jQuery only; native listeners do not see them.

## Scope

**In:**
- New block `yaycheckout/field-value`, title "Field Value", Free and Pro.
- One field per block: `billing_email`, every `billingAddressFields()` key, every `shippingAddressFields()` key.
- Attributes `fieldKey`, `placeholder`, `showLabel`.
- Server-rendered initial value; live update while the shopper types or picks.
- Country and state shown by name, not code.
- `shipping_*` falls back to `billing_*` when the layout has no input for the shipping key.

**Out:**
- Making Order Summary accept inner blocks, and where its children render (other branch).
- Extra-field keys, shipping method, payment method, account fields, checkbox fields.
- Grouped/formatted address presets (several fields in one block).
- Typed label or heading text on the block (use core Heading or Paragraph); `showLabel` only prints the field's own name.
- Hiding the block when empty.
- Fixing the missing `ship_to_different_address` checkbox.

## Acceptance criteria

1. In the layout editor, "Field Value" is insertable anywhere inside Checkout except directly inside Billing and Shipping, and more than once.
2. The inspector shows a field select grouped Billing / Shipping (Email address is first under Billing). The placeholder is edited on the canvas with `RichText` (`.claude/rules/blocks.md`), muted, showing the field's label as its hint while empty. Changing the field clears the placeholder. A "Show label" toggle (`showLabel`, default off) prints the field's name above the value; section headings come from other blocks.
3. Given `fieldKey` `billing_email`, `placeholder` `email@____`, on the storefront with an empty session: the block prints a muted `email@____`. Typing `a@b.co` into Email address changes the value to `a@b.co`, unmuted, on each keystroke. Clearing it brings back the muted `email@____`.
4. Given `fieldKey` `billing_country`, picking United States (US) shows `United States (US)`. With `fieldKey` `billing_state` on the same page, picking California shows `California`; changing country to one with no states shows the typed state text or the placeholder.
5. Reloading the checkout after typing keeps the values: the server prints them, with no placeholder flash.
6. Given a layout with no Shipping block and a block on `shipping_city`, typing `Austin` into billing Town / City shows `Austin`.
7. A block whose `fieldKey` does not match `/^(billing|shipping)_[a-z0-9_]+$/` prints nothing.
8. A field shown on step 1 is still displayed, with its value, on step 2.
9. End-to-end verification: on yaycheckout.test, add to the active layout's Order Summary column two Field Value blocks (`billing_email`; `shipping_address_1` with placeholder `STREET ADDRESS`) and one on `billing_country`, publish, open the checkout with an item in the cart: muted placeholders show; filling the email, street address and country updates all three live; a reload keeps them.

## Constraints

- Product PHP runs on PHP 7.4 (no enums, `match`, `readonly`, promoted properties, `?->`, named arguments, union types).
- Plugin floor WordPress 6.5.
- Free and Pro; no code in `includes/Pro/`.
- Edit `packages/blocks/src/field-value/`, never `assets/dist/blocks/`; rebuild with `pnpm build:blocks`.
- Every attribute is escaped on output (`esc_html`, `esc_attr`); the placeholder is read with `LayoutMarkupReader::plain_text()`.
- No new tests (display only; does not meet CLAUDE.md's test bar).

## Approach

**Chosen:** A: server-rendered initial value + view script live update.

**Architecture:** `render.php` prints a value element tagged with the field key, filled from `CheckoutFieldDefaults::value()` so the first paint is correct. `view.ts` binds one delegated jQuery `input change` listener and, on every event and once on load, re-syncs every `[data-field-key]` element on the page from its form input. Re-syncing all (a handful of elements) keeps state in step with country changes without tracking dependencies.

**Components:**
- `packages/blocks/src/field-value/block.json`: `name` `yaycheckout/field-value`, `ancestor` `["yaycheckout/checkout"]`, attributes `fieldKey` (string, default `billing_email`), `placeholder` (string, default `""`), `showLabel` (boolean, default `false`); supports `html: false`, `reusable: false`, color text/background, typography (fontSize, lineHeight, fontFamily, fontStyle, fontWeight, letterSpacing, textDecoration, textTransform), border (color, radius, style, width), spacing margin/padding, as the Field block; `editorScript`, `viewScript`, `style`, `render`.
- `packages/blocks/src/field-value/edit.tsx`: inspector SelectControl with optgroups built from `address-fields.ts` plus `billing_email`; label and placeholder as canvas `RichText`.
- `packages/blocks/src/field-value/index.ts`: `registerDynamicBlock( metadata, Edit )`.
- `packages/blocks/src/field-value/render.php`: key check, initial value, country/state name lookup, markup.
- `packages/blocks/src/field-value/view.ts`: live sync.
- `packages/blocks/src/field-value/style.css`: `.yaycheckout-field-value__value.is-empty { opacity: .5; }`.
- `includes/Blocks/BlockRegistry.php`: add `'field-value'` to `ORDER` right after `'order-summary'`.

**Data and storage:** None. Values come from the WooCommerce session/customer and the live form.

**Integration points:**
- `CheckoutFieldDefaults::value()`; new `CheckoutFieldDefaults::label( string $key ): string`, sharing `classes()`' field lookup.
- `WC()->countries->get_countries()` and `WC()->countries->get_states( $country )`, where `$country` is the value of `<prefix>_country`.
- Markup: wrapper `div.yaycheckout-field-value` via `get_block_wrapper_attributes()`; `div.yaycheckout-field-value__label` holding `CheckoutFieldDefaults::label( $key )` when `showLabel` is on and the label is not empty; `div.yaycheckout-field-value__value` with `data-field-key="<key>"`, `data-placeholder="<placeholder or field label>"`, and class `is-empty` when the value is empty.
- Storefront: reads `form.checkout [name="<key>"]`; select values use the selected option's text. Listener on `jQuery( document.body )`.
- Order Summary (a container since #60) admits `yaycheckout/field-value` as the one exception to `isCheckoutSection()` in `packages/blocks/src/order-summary/edit.tsx`; Billing and Shipping still keep it out.

**UI:** Gutenberg block in the layout editor; storefront output from `render.php`, updated by `view.ts`.

**Edge cases and failure modes:**
- Empty value, or whitespace only: placeholder, muted (`is-empty`).
- Empty `placeholder` attribute: the field's label. The editor reads it from `address-fields.ts`; `render.php` calls `CheckoutFieldDefaults::label( $key )` (empty string when missing), so no PHP copy of the label list is added.
- Field not in the layout and no fallback: placeholder.
- `shipping_X` input absent: read `billing_X` (server and client).
- Country without states, state shown as text input: show its typed value.
- Country whose state list is empty (WooCommerce hides the state field, e.g. VN): placeholder, even when a value is left over (a saved state, or one the login fill wrote). Server: `get_states()` returns `[]`; client: the control is `input[type=hidden]`.
- Invalid key: nothing printed.
- Outside a storefront request (editor REST render, admin): `value()` returns null, placeholder printed.
- Known risk, not fixed here: with no `ship_to_different_address` checkbox, a shopper-typed `shipping_*` value shown by this block may differ from the billing address WooCommerce stores as shipping.

**Decision record:** None.

## Revision after review (2026-10-08)

Review of PR #62 changed the field source and the label. Where this section and the rest of the doc disagree, this section wins.

- **Fields come from the layout, not a static list.** The editor's Field select lists the layout's own Email address and Field blocks, with the label each prints, mirroring `LayoutMarkupReader::fields()`. It starts at "Select a field".
- **No field, no output.** `fieldKey` defaults to `""`. `render.php` prints nothing unless the key is in the served layout's `LayoutMarkupReader::fields()`. In the editor, a Field Value whose field leaves the layout resets to `""` (not an undo step), and its canvas asks to select a field.
- **The label is the form's label.** The server prints the layout field's label (an empty one falls back as `email/render.php` and `field/render.php` do). `view.js` then copies the label the form shows now, without its required or optional mark, on load (`updated_checkout`), on `input`/`change` and on `country_to_state_changed`, so WooCommerce's locale relabels (State → County) match. The default placeholder follows the same label; `data-placeholder` carries only a typed placeholder.
- **No shipping → billing fallback.** A pickable field is always in the layout, so the shipping input always exists; the fallback is gone from both `render.php` and `view.ts`.
- **Hidden-for-country fields** read as empty through `CheckoutFieldDefaults::hidden_for_country()` (from #64) instead of the `get_states() === []` check. `CheckoutFieldDefaults::label()` is dropped.
- **Not every field type.** Field blocks of type `checkbox` (its value is always 1), `password` (never echoed) and `hidden` (nothing typed) are not offered, and `render.php` prints nothing for them.
- Labels are tags-stripped then entity-decoded in the editor too, as `LayoutMarkupReader::plain_text()` does on the server.
- Extra fields stay out of scope.

## Alternatives considered

| Option | Why rejected |
|---|---|
| B: JS only (empty span filled on load) | Placeholder flash on every load; country/state names need a client-side lookup. |
| C: `update_order_review` fragment | Not live while typing (WooCommerce posts only on `update_totals_on_change` fields); adds fragment plumbing. |
| Grouped address preset per block | Needs per-country address formatting in JS; stacking single-field blocks covers the mockup. |
| `parent: ["yaycheckout/order-summary"]` | Not insertable anywhere until the Order Summary branch merges, so untestable now. |

## Rollout

None. A new block; existing layouts are unaffected.

## Open questions

None.
