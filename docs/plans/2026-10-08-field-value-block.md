# Field Value Block Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-08
**Goal:** A `yaycheckout/field-value` block that shows one checkout field's current value (server-rendered, then updated live), or a muted placeholder while empty.
**Spec:** [docs/specs/2026-10-08-field-value-block-design.md](../specs/2026-10-08-field-value-block-design.md)
**Architecture:** Dynamic block: `render.php` prints the stored value from `CheckoutFieldDefaults::value()` so the first paint is right; `view.ts` re-syncs every `[data-field-key]` from the form on each jQuery `input change`. Chosen over a JS-only block (placeholder flash) and an `update_order_review` fragment (not live while typing).
**Touches:** `packages/blocks/src/field-value/` (new), `includes/Blocks/BlockRegistry.php`, `includes/Renderer/CheckoutFieldDefaults.php` (shared; consumers: `field/render.php`, `email/render.php`), `packages/blocks/src/shared/jquery.ts` (new, shared; consumers: `shared/reveal-link-view.ts`, `login/view.ts`, `field-value/view.ts`). Reads `packages/blocks/src/shared/address-fields.ts` (shared, unchanged; consumers: `field/edit.tsx`, `shared/address-template.ts`).
**Tests:** none (display only; outside CLAUDE.md's test bar, agreed in the spec).

## Global constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Plugin floor WordPress 6.5.
- Free and Pro; nothing in `includes/Pro/`.
- Edit `packages/blocks/src/field-value/`, never `assets/dist/blocks/`; rebuild with `pnpm build:blocks`.
- Every attribute and value is escaped on output (`esc_html`, `esc_attr`); `placeholder` is read with `LayoutMarkupReader::plain_text( $attributes, $name, $fallback )`.
- PHP locals in `render.php` are prefixed `$yaycheckout_` (as in every other `render.php`).
- jQuery is reached through `jquery()` in `packages/blocks/src/shared/jquery.ts` (shared with `reveal-link-view.ts` and `login/view.ts`); no `jquery` package import.
- Commit messages: Conventional Commits, `Co-Authored-By` trailer per CLAUDE.md. Commit only when the user asks.

## Review focus

- Picking a country through select2 (jQuery-only `change`): `billing_country` and `billing_state` blocks update. Task 2 Step 2.
- Changing country after a state was picked: the state block shows the new state field's value or the placeholder, never the old state's name. Task 2 Step 2.
- Layout without a Shipping block, block on `shipping_city`: shows billing Town / City. Task 1 Step 3 (server) and Task 2 Step 2 (live).
- Crafted `fieldKey` such as `account_password` or `<script>` in post content: block prints nothing. Task 1 Step 3.

---

### Task 1: Block, editor and server render

**Files:**
- Create: `packages/blocks/src/field-value/block.json`
- Create: `packages/blocks/src/field-value/index.ts`
- Create: `packages/blocks/src/field-value/edit.tsx`
- Create: `packages/blocks/src/field-value/render.php`
- Create: `packages/blocks/src/field-value/style.css`
- Modify: `includes/Blocks/BlockRegistry.php:32-54` (`ORDER`)
- Modify: `packages/blocks/src/shared/block-icons.tsx`

**Interfaces:**
- Consumes: `billingAddressFields()`, `shippingAddressFields()` (`AddressFieldDef { key, type, label, required }`) from `packages/blocks/src/shared/address-fields.ts`; `registerDynamicBlock( metadata, Edit )`; `\YayCheckout\Renderer\CheckoutFieldDefaults::value( string $key )`; `\YayCheckout\Services\LayoutMarkupReader::plain_text()`.
- Produces (Task 2 relies on): storefront markup
  `div.yaycheckout-field-value` > `div.yaycheckout-field-value__value[data-field-key="<key>"][data-placeholder="<text>"]`, with class `is-empty` on the value element when it shows the placeholder.

- [ ] **Step 1: `block.json`**

`name` `yaycheckout/field-value`, `title` `Field Value`, `category` `yaycheckout`, `description` `Shows what the shopper entered in one checkout field.`, `keywords` `[ "summary", "recap", "address" ]`, `ancestor` `[ "yaycheckout/checkout" ]`, `example` `{ "attributes": { "fieldKey": "billing_email" } }`.
Attributes: `fieldKey` string default `"billing_email"`; `placeholder` string default `""`.
Supports: `html: false`, `reusable: false`, `color` `{ text, background }`, `typography` `{ fontSize, lineHeight, __experimentalFontFamily, __experimentalFontStyle, __experimentalFontWeight, __experimentalLetterSpacing, __experimentalTextDecoration, __experimentalTextTransform }`, `__experimentalBorder` `{ color, radius, style, width }`, `spacing` `{ margin, padding }`.
`editorScript` `file:./index.js`, `style` `file:./style-index.css` (as `return-to-cart`), `render` `file:./render.php`. `viewScript` is added in Task 2 with `view.ts`.

- [ ] **Step 2: `index.ts`, `edit.tsx`, `style.css`**

- `index.ts`: `import './style.css';` and `registerDynamicBlock( metadata, Edit )`, as `return-to-cart/index.ts`.
- `edit.tsx`: `export default function Edit( { attributes, setAttributes }: EditProps )`, attributes `{ fieldKey: string; placeholder: string }`.
  - Inspector `PanelBody` "Field value": `SelectControl` "Field" with two `<optgroup>`s, "Billing" (`billing_email` labelled "Email address" first, then `billingAddressFields()`) and "Shipping" (`shippingAddressFields()`); changing it also sets `placeholder` to `""`.
  - Canvas (`useBlockProps( { className: 'yaycheckout-field-value' } )`): the placeholder is `RichText` (`tagName="div"`, `allowedFormats={ [] }`, `withoutInteractiveFormatting`), per `.claude/rules/blocks.md`, with class `yaycheckout-field-value__value is-empty` and the selected field's label as its hint.
- `packages/blocks/src/shared/block-icons.tsx`: add a `yaycheckout/field-value` icon in the existing outline style.
- `style.css`: `.yaycheckout-field-value__value.is-empty { opacity: 0.5; }`. No colours, sizes or case (merchant sets them through supports).

- [ ] **Step 3: `render.php`**

Order of decisions:
1. `$yaycheckout_key` = `fieldKey` if it is a string matching `/^(billing|shipping)_[a-z0-9_]+$/`; otherwise `return;` (print nothing).
2. `$yaycheckout_value` = `CheckoutFieldDefaults::value( $key )` cast to trimmed string (null → `''`). If empty and the key starts with `shipping_`, use the value of `'billing_' . substr( $key, 9 )`.
3. If the key ends in `_country` and `WC()->countries` exists, replace a known code with its name from `get_countries()`. If it ends in `_state`, look up the name in `get_states( <value of same-prefix _country> )`; unknown codes stay as typed. WooCommerce names carry entities (`Cura&ccedil;ao`); `esc_html()` does not double-encode them, so print with `esc_html()` and no decoding.
4. `$yaycheckout_placeholder` = `plain_text( $attributes, 'placeholder' )`; if `''`, `CheckoutFieldDefaults::label( $key )` (new public method; `classes()` and it share a private `field( string $key ): array` lookup).
5. Print the Interfaces markup through `get_block_wrapper_attributes( [ 'class' => 'yaycheckout-field-value' ] )` with the same phpcs ignore comment as `order-summary/render.php`; value element text is the value, or the placeholder plus class `is-empty` when the value is `''`.

File header docblock in the style of the other `render.php` files (`@var array $attributes`, `@var string $content Unused; the block is a leaf.`, `@var WP_Block $block`).

- [ ] **Step 4: Register in `ORDER`**

Add `'field-value'` right after `'order-summary'` in `BlockRegistry::ORDER`.

- [ ] **Step 5: Verify build and lint**

Run: `pnpm build:blocks`
Expected: completes; `assets/dist/blocks/field-value/` holds `block.json`, `index.js`, `render.php` and the style file.

Run: `composer lint -- includes/Blocks/BlockRegistry.php packages/blocks/src/field-value/render.php`
Expected: no errors.

- [ ] **Step 6: Verify in the editor and storefront (yaycheckout.test, Pro build)**

Editor, active layout:
- Inserter search "Field Value" inside the Order Summary column: the block is offered; insert it twice. Select a Field inside Billing and open its inserter: "Field Value" is not offered.
- Set Field `billing_email`, type `email@____` on the placeholder line: canvas shows a muted `email@____`. Clear the placeholder line: its hint shows `Email address`.

Storefront, item in cart, private window (empty session), after publishing a layout with blocks on `billing_email` (placeholder `email@____`), `shipping_city`, `billing_country`:
- Muted `email@____` prints; `billing_country` shows the store's default country name (WooCommerce pre-fills it), not a code.
- Fill the form, trigger a totals update, reload: the blocks print the saved values with no muted state (AC 5).
- Remove the Shipping block from the layout, republish, type a billing city, update totals, reload: the `shipping_city` block prints that city.
- In the code editor, change one block's `fieldKey` to `account_password`, publish, reload the checkout: that block prints no markup (view source has no extra `yaycheckout-field-value`).

- [ ] **Step 7: Commit**

Stage: `packages/blocks/src/field-value/block.json`, `packages/blocks/src/field-value/index.ts`, `packages/blocks/src/field-value/edit.tsx`, `packages/blocks/src/field-value/render.php`, `packages/blocks/src/field-value/style.css`, `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php`, `docs/specs/2026-10-08-field-value-block-design.md`, `docs/plans/2026-10-08-field-value-block.md`
Message: `feat(blocks): add Field Value block`

### Task 2: Live update

**Files:**
- Create: `packages/blocks/src/field-value/view.ts`
- Modify: `packages/blocks/src/field-value/block.json` (add `"viewScript": "file:./view.js"`)

**Interfaces:**
- Consumes: Task 1 markup (Interfaces block).
- Produces: none.

- [ ] **Step 1: `view.ts`**

- `function sync(): void`: for each `.yaycheckout-field-value__value[data-field-key]`, find the control `form.checkout [name="<key>"]`; if absent and the key starts with `shipping_`, use `[name="billing_<rest>"]`. Text = for a `HTMLSelectElement`, `selectedOptions[0]?.text` when its value is non-empty, else `''`; otherwise `control.value`; trimmed. Empty or no control → `dataset.placeholder` and add `is-empty`; else the text and remove `is-empty`. Set with `textContent`.
- On load (after `DOMContentLoaded` if the document is still loading): `sync()` once, then `jquery( document.body )?.on( 'input change', sync )`.
- `jquery()` and `Control` come from `packages/blocks/src/shared/jquery.ts`.

A state field that WooCommerce swaps between `<select>` and `<input>` on a country change is found again by name on each `sync()`, so no rebinding is needed.

- [ ] **Step 2: Verify build and behaviour (yaycheckout.test)**

Run: `pnpm build:blocks`
Expected: completes; `assets/dist/blocks/field-value/view.js` and `view.asset.php` exist.

Storefront, same layout as Task 1 Step 6 plus a block on `billing_state`:
- Type `a@b.co` into Email address: the `billing_email` block shows `a@b.co` unmuted on each keystroke; clear it: muted `email@____` returns.
- Pick United States (US) in the select2 country box: `billing_country` shows `United States (US)`. Pick California: `billing_state` shows `California`. Switch country to one without states (e.g. Vietnam) and type `Hanoi` in the state text input: `billing_state` shows `Hanoi`; leave it empty: placeholder.
- Layout without a Shipping block: typing `Austin` into billing Town / City shows `Austin` in the `shipping_city` block.
- Multi-step layout: fill email on step 1, Next: the block, if placed on step 2 or outside Steps, shows the email.
- Browser console: no errors.

- [ ] **Step 3: End-to-end check (spec acceptance 9)**

On yaycheckout.test, put in the active layout's Order Summary column two Field Value blocks (`billing_email`; `shipping_address_1` with placeholder `STREET ADDRESS`) and one on `billing_country`; publish; open the checkout with an item in the cart in a private window.
Expected: muted placeholders show; filling email, street address and country updates all three live; a reload keeps them.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/field-value/view.ts`, `packages/blocks/src/field-value/block.json`
Message: `feat(blocks): update Field Value live while the shopper types`
