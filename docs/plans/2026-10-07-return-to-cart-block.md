# Return to Cart Block Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-07
**Goal:** Add the `yaycheckout/return-to-cart` block: a link to the cart, placeable anywhere in a layout, with an Icon setting (none / ← / ‹) and a label edited inline in the editor.
**Spec:** [docs/specs/2026-10-07-return-to-cart-block-design.md](../specs/2026-10-07-return-to-cart-block-design.md)
**Architecture:** A leaf dynamic block whose `render.php` prints `<a href="wc_get_cart_url()">` at render time, so the URL follows WooCommerce's cart page. Chosen over extending the Previous Step Button's cart link, which shows on step 1 only and can't be placed on its own.
**Touches:** `packages/blocks/src/return-to-cart/` (new), `includes/Blocks/BlockRegistry.php`, `includes/Renderer/CheckoutColour.php`, `packages/blocks/src/shared/block-icons.tsx` (shared: read by `packages/blocks/src/shared/register-dynamic-block.tsx` for every block; this change only adds a key)
**Tests:** none. CLAUDE.md's test rule does not cover this block (no publish, assignment, required-field or import logic), and it fixes no bug.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- WordPress floor 6.5 (`yaycheckout.php`): no `__experimental*` components.
- Free names Pro only in `boot_pro()`; this block is in Free and Pro and is not gated by `Plan::is_pro()`.
- Never hand-edit `assets/dist/`; edit `packages/blocks/src/return-to-cart/` and rerun `pnpm build:blocks`.
- A TypeScript copy of a PHP value carries a comment naming its PHP owner (`docs/conventions.md`, "TypeScript copies of PHP name their owner").
- Code reads like its neighbours: `back-button/` is the model for file layout, docblocks and phpcs ignores.

## Review focus

- Clicking the label in the editor must edit it, never follow a link: the canvas element is a `div`, not an `a`. Verified in Task 1 Step 7.
- Unknown `arrow` value (hand-edited markup, e.g. `"arrow":"x"`) falls back to the default `‹` (core validates the enum) with no warning. Verified in Task 1 Step 8.
- The link must not be caught by the steps script: no `yaycheckout-back-button` class on it, so `steps/view.ts` never calls `preventDefault()` on its click. Verified in Task 3.
- Empty label falls back to the translated "Return to cart" on the storefront. Verified in Task 1 Step 8.
- Placed as a direct child of a constrained Group (outside Checkout), on a viewport wider than the content size, the link lines up with the content's left edge in both editor and storefront. Verified in Task 3.

---

### Task 1: The block

**Files:**
- Create: `packages/blocks/src/return-to-cart/block.json`
- Create: `packages/blocks/src/return-to-cart/render.php`
- Create: `packages/blocks/src/return-to-cart/edit.tsx`
- Create: `packages/blocks/src/return-to-cart/index.ts`
- Create: `packages/blocks/src/return-to-cart/style.css`
- Modify: `includes/Blocks/BlockRegistry.php:39` (`ORDER`)
- Modify: `includes/Renderer/CheckoutColour.php:33-35`

**Interfaces:**
- Consumes: `registerDynamicBlock( metadata, Edit )` from `packages/blocks/src/shared/register-dynamic-block.tsx`; `\YayCheckout\Services\LayoutMarkupReader::plain_text( array $attrs, string $name, string $fallback = '' ): string`.
- Produces: block `yaycheckout/return-to-cart`; attributes `buttonText` (string, default `""`), `arrow` (string, default `"chevron"`, enum `none` | `arrow` | `chevron`); storefront classes `yaycheckout-return-to-cart`, `yaycheckout-return-to-cart__icon`.

- [ ] **Step 1: Write `block.json`**

Copy `back-button/block.json`'s shape. `name` `yaycheckout/return-to-cart`; `title` "Return to Cart Button"; `category` `yaycheckout`; `description` "Links to the cart. Shows on every step. Place it anywhere in the layout."; `keywords` `[ "cart", "return", "back" ]`; `example.attributes.buttonText` "Return to cart"; attributes as in Interfaces; `supports` `{ "html": false, "reusable": false }`; no `parent` or `ancestor`; `editorScript` `file:./index.js`, `style` `file:./style-index.css`, `render` `file:./render.php`.

- [ ] **Step 2: Write `render.php`**

Docblock like `back-button/render.php` (what it prints; `@var $attributes`, `$content`, `$block`; `@package yaycommerce/yaycheckout`), `defined( 'ABSPATH' ) || exit;`, `$yaycheckout_` variable prefix.
- Label: `LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Return to cart', 'yaycheckout' ) )`.
- Icon: `[ 'arrow' => '←', 'chevron' => '‹' ]`, plus `'none' => ''`, indexed by `$attributes['arrow']` (core fills the default and enforces the enum); `none` prints no span.
- Markup: `<div {get_block_wrapper_attributes( [ 'class' => 'yaycheckout-return-to-cart' ] )}>`, then `<a class="yaycheckout-return-to-cart__link" href="{esc_url( wc_get_cart_url() )}">`, then `<span class="yaycheckout-return-to-cart__icon" aria-hidden="true">{esc_html( icon )}</span>` when there is one, then `esc_html( label )`, then `</a></div>`. The div is block-level so a constrained layout's auto margins align it with the content; an inline-level root ignores them. Same phpcs ignore comment on the wrapper echo as `back-button/render.php`.

- [ ] **Step 3: Write `style.css`**

`.yaycheckout-return-to-cart`: `margin-block: 16px 0`. `.yaycheckout-return-to-cart__link`: `display: inline-flex; align-items: center; gap: 6px; padding: 10px 0; color: var(--yaycheckout-primary); font-family: inherit; font-size: 16px; font-weight: 600; line-height: 1.2; text-decoration: none;`. Same `color` and `text-decoration: none` on `:is(:hover, :focus)` for classic themes, with `back-button/style.css`'s comment.

- [ ] **Step 4: Write `edit.tsx` and `index.ts`**

`edit.tsx`: `export default function Edit( { attributes, setAttributes }: EditProps )`, with `type Arrow = 'none' | 'arrow' | 'chevron'`.
- `InspectorControls` → `PanelBody` title "Return to cart" → `SelectControl` label "Icon", options None `none` / Arrow (←) `arrow` / Chevron (‹) `chevron`.
- Canvas: `<div { ...useBlockProps( { className: 'yaycheckout-return-to-cart' } ) }>` containing `<div className="yaycheckout-return-to-cart__link">` with the icon span (same class, `aria-hidden="true"`, omitted for `none`) and `RichText` with `tagName="span"`, `value={ attributes.buttonText }`, `placeholder={ __( 'Return to cart', 'yaycheckout' ) }`, `allowedFormats={ [] }`, `withoutInteractiveFormatting`.
- Comment above the icon map: it and the placeholder mirror `return-to-cart/render.php`.

`index.ts`: same four lines as `back-button/index.ts`.

- [ ] **Step 5: Register the order and colour**

`BlockRegistry::ORDER`: insert `'return-to-cart'` after `'back-button'`.
`CheckoutColour`: append `,.wp-block-yaycheckout-return-to-cart` to the selector list on line 33 and `'yaycheckout/return-to-cart'` to the handle array on line 35.

- [ ] **Step 6: Build and lint**

Run: `pnpm build:blocks`
Expected: exits 0; `assets/dist/blocks/return-to-cart/` holds `block.json`, `render.php`, `index.js`, `index.asset.php`, `style-index.css`.

Run: `composer lint -- includes/Blocks/BlockRegistry.php includes/Renderer/CheckoutColour.php packages/blocks/src/return-to-cart/render.php`
Expected: "✓ Pro boundary intact", phpcs reports no errors (these files pass on `dev/edd` today).

- [ ] **Step 7: Verify in the editor**

Open: wp-admin → YayCheckout → Layouts → edit any layout.
Action: open the inserter's YayCheckout category; insert "Return to Cart Button"; click its label and type "Back to bag"; switch Icon to None, Arrow, Chevron.
Expected: the block is listed right after "Previous Step Button"; a new block shows `‹` and the placeholder "Return to cart"; typing edits in place with no formatting toolbar and no navigation; the icon shows nothing / `←` / `‹`.

- [ ] **Step 8: Verify on the storefront**

Action: publish the layout with the label "Back to bag" and Icon Arrow, open the checkout page; then switch to the Code editor, clear the label and set `"arrow":"x"` in the block comment, update, reload the checkout.
Expected: first load shows "← Back to bag" in the primary colour, as `<div class="wp-block-yaycheckout-return-to-cart yaycheckout-return-to-cart"><a class="yaycheckout-return-to-cart__link" href="<cart URL>">` with `<span class="yaycheckout-return-to-cart__icon" aria-hidden="true">←</span>`; second load shows "‹ Return to cart" (core swaps the invalid value for the default) and no PHP notice in `wp-content/debug.log`.

- [ ] **Step 9: Commit**

Stage: `packages/blocks/src/return-to-cart/block.json`, `packages/blocks/src/return-to-cart/render.php`, `packages/blocks/src/return-to-cart/edit.tsx`, `packages/blocks/src/return-to-cart/index.ts`, `packages/blocks/src/return-to-cart/style.css`, `includes/Blocks/BlockRegistry.php`, `includes/Renderer/CheckoutColour.php`
Message: `feat(blocks): add Return to Cart Button block`

### Task 2: Inserter icon

**Files:**
- Modify: `packages/blocks/src/shared/block-icons.tsx` (after the `'yaycheckout/back-button'` entry)

**Interfaces:**
- Consumes: block name `yaycheckout/return-to-cart` from Task 1.
- Produces: `blockIcons['yaycheckout/return-to-cart']`.

- [ ] **Step 1: Add the icon**

Use the `icon()` helper, same 24×24 stroke family: a cart outline with a left arrow (e.g. `path` for a basket body and wheels, plus `M10 9 7 12l3 3M7 12h7`). Keep it distinct from `back-button`'s pill outline.

- [ ] **Step 2: Verify**

Run: `pnpm build:blocks`, then reload the layout editor.
Expected: exits 0; "Return to Cart Button" shows the new icon in the inserter and in List View, tinted like its neighbours.

- [ ] **Step 3: Commit**

Stage: `packages/blocks/src/shared/block-icons.tsx`
Message: `feat(blocks): add Return to Cart Button inserter icon`

### Task 3: End-to-end verification

- [ ] **Step 1: Walk the spec's end-to-end check**

Run: `pnpm build:blocks`. In a layout with Steps (two or more), insert the block outside the Steps block (once inside a Checkout column, once as a direct child of the outer constrained Group), set Icon to Arrow, change the label to "Back to bag", publish.
Expected: on the storefront checkout "← Back to bag" shows in the primary colour on step 1 and still on the last step after clicking Continue; at a 1600px viewport its left edge equals the header's and the form column's; clicking it opens the cart page (it navigates, the steps script does not intercept it).
