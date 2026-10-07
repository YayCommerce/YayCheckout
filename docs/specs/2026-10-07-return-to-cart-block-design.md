# Return to Cart Block Design

**Status:** accepted 2026-10-07
**Related:** None

## Goal

Merchants can place a "Return to cart" link anywhere in a checkout layout, with an optional icon (none, arrow or chevron) and a label they edit by clicking it in the editor. Shoppers see it on every step and it takes them to the cart.

## Context

- The only cart link today is inside the Previous Step Button (`packages/blocks/src/back-button/render.php`): with `firstStep` `cart`, an `<a class="yaycheckout-back-button yaycheckout-back-button--cart">` is printed after the button, shown on step 1 only, and `packages/blocks/src/steps/view.ts` (`syncBackButtons()`) toggles it as steps change. Its label (`cartText`) is edited in the sidebar, and it has no icon.
- Blocks are dynamic: `block.json` + `render.php` per block, leaf `save()` returns null via `registerDynamicBlock()` (`packages/blocks/src/shared/register-dynamic-block.tsx`). `Blocks\BlockRegistry::ORDER` sets inserter order. `Renderer\CheckoutColour` adds `--yaycheckout-primary` to each listed block's style handle.
- Inline label editing exists in `packages/blocks/src/shared/step-nav-button-edit.tsx` (`RichText` on a `div`, `allowedFormats={[]}`, `withoutInteractiveFormatting`). Labels are read on the server with `LayoutMarkupReader::plain_text()`.
- The icon setting follows `core/post-navigation-link`'s `arrow` attribute (`none` / `arrow` / `chevron`). WooCommerce's Checkout Actions block (`showReturnToCart`, `returnToCartButtonLabel`) shows a single chevron, which is where the `‹` glyph comes from.
- Plugin floor is WordPress 6.5 (`yaycheckout.php`). `ToggleGroupControl` is still exported only as `__experimentalToggleGroupControl` in WordPress 7.1.

## Scope

**In:**
- New block `yaycheckout/return-to-cart`: a link to `wc_get_cart_url()`, shown on every step, placeable anywhere in a layout, in Free and Pro.
- `arrow` setting: `none`, `arrow` (←) or `chevron` (‹), default `chevron`.
- Label edited inline on the editor canvas; empty label falls back to translatable "Return to cart".
- Link styling matching the Previous Step Button (transparent, primary colour, 16px/600).

**Out:**
- Any change to the Previous Step Button or its cart link (`cartText`, `firstStep`, `steps/view.ts`).
- RTL mirroring of the icon.
- Choosing the target page (`cartPageId`), icon position (right side), or button variants (outline/filled).
- Hiding the link on some steps or when the cart is empty.
- Adding the block to `templates/default-layout.php` or any template.

## Acceptance criteria

1. Given the blocks are built, when the merchant opens the layout editor inserter, then "Return to Cart Button" appears in the YayCheckout category directly after "Previous Step Button".
2. Given a newly inserted block, then the canvas shows `‹` and the placeholder "Return to cart", and the sidebar "Icon" select shows Chevron.
3. When the merchant clicks the label on the canvas and types "Back to bag", then the text changes in place with no formatting toolbar, and the click does not navigate.
4. Given Icon is None / Arrow / Chevron, then the canvas and storefront show no icon / `←` / `‹` before the label.
5. On the storefront, the block renders `<div class="wp-block-yaycheckout-return-to-cart yaycheckout-return-to-cart">` holding `<a class="yaycheckout-return-to-cart__link" href="<wc_get_cart_url()>">`, which contains `<span class="yaycheckout-return-to-cart__icon" aria-hidden="true">` (omitted for None) followed by the label, coloured `var(--yaycheckout-primary)`.
6. Given an empty `buttonText`, the storefront label is `__( 'Return to cart', 'yaycheckout' )`; given a saved label, it is printed as plain text (`LayoutMarkupReader::plain_text()`).
7. Given a layout with Steps, the link is visible on every step, and clicking it loads the cart page.
8. Given `arrow` holds a value outside its enum, core replaces it with the default, so `‹` is printed.
9. End-to-end verification: run `pnpm build:blocks`; in a layout with Steps, insert the block, set Icon to Arrow, change the label to "Back to bag", publish; on the storefront checkout, "← Back to bag" shows in the primary colour on step 1 and on the last step, and clicking it opens the cart.

## Constraints

- Product PHP runs on PHP 7.4 (no `match`, enums, `readonly`, promoted properties, `?->`, named arguments, union types).
- WordPress floor 6.5: no `__experimental*` components.
- Free build: no reference to `YayCheckout\Pro`; the block is not gated by `Plan::is_pro()`.
- Never hand-edit `assets/dist/`; edit `packages/blocks/src/return-to-cart/` and rebuild.
- TypeScript copies of PHP values carry a comment naming the PHP owner (`docs/conventions.md`).

## Approach

**Chosen:** Standalone dynamic block.

**Architecture:** A leaf dynamic block. `render.php` builds the link at render time, so the cart URL always follows WooCommerce's cart page. The editor shows a `div` (not a link) with the icon span and a `RichText` label, so clicking edits instead of navigating. No storefront script: it is a plain link, unknown to `steps/view.ts`.

**Components:**
- `packages/blocks/src/return-to-cart/block.json`: `name` `yaycheckout/return-to-cart`, `title` "Return to Cart Button", `category` `yaycheckout`, `keywords` `cart`, `return`, `back`; attributes `buttonText` (string, default `""`), `arrow` (string, default `"chevron"`, enum `none`, `arrow`, `chevron`); `supports` `html: false`, `reusable: false`; no `parent`/`ancestor`; `editorScript`, `style`, `render` as in `back-button/block.json`.
- `packages/blocks/src/return-to-cart/render.php`: label via `LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Return to cart', 'yaycheckout' ) )`; icon map `[ 'arrow' => '←', 'chevron' => '‹' ]`; wrapper via `get_block_wrapper_attributes( [ 'class' => 'yaycheckout-return-to-cart' ] )`.
- `packages/blocks/src/return-to-cart/edit.tsx`: canvas `div` with `useBlockProps( { className: 'yaycheckout-return-to-cart' } )`, icon `<span className="yaycheckout-return-to-cart__icon" aria-hidden="true">`, `RichText` `tagName="span"` on `buttonText` with placeholder "Return to cart", `allowedFormats={ [] }`, `withoutInteractiveFormatting`. Sidebar `PanelBody` "Return to cart" with `SelectControl` "Icon": None / Arrow (←) / Chevron (‹). Comment: the icon map and placeholder mirror `return-to-cart/render.php`.
- `packages/blocks/src/return-to-cart/index.ts`: `registerDynamicBlock( metadata, Edit )`, imports `./style.css`.
- `packages/blocks/src/return-to-cart/style.css`: wrapper `.yaycheckout-return-to-cart` is block-level with `margin-block: 16px 0`, so a constrained layout aligns it with the content; `.yaycheckout-return-to-cart__link` takes the link rules of `.yaycheckout-back-button` (padding `10px 0`, `color: var(--yaycheckout-primary)`, 16px/600, line-height 1.2, no underline, same on `:hover`/`:focus`) plus `display: inline-flex; align-items: center; gap: 6px`.
- `includes/Blocks/BlockRegistry.php`: `'return-to-cart'` after `'back-button'` in `ORDER`.
- `includes/Renderer/CheckoutColour.php`: add `.wp-block-yaycheckout-return-to-cart` to the selector list and `'yaycheckout/return-to-cart'` to the handle list.
- `packages/blocks/src/shared/block-icons.tsx`: `'yaycheckout/return-to-cart'` inserter icon in the same stroke family as `back-button`.

**Data and storage:** None beyond the two block attributes in layout post content.

**Integration points:** `wc_get_cart_url()`; `Renderer\CheckoutColour` inline style on the block's style handle; `Blocks\BlockRegistry::ORDER`. No hooks, filters or REST routes added.

**UI:** Gutenberg block in the embedded layout editor; sidebar `SelectControl`.

**Edge cases and failure modes:**
- Empty label: storefront falls back to translated "Return to cart"; editor shows the placeholder.
- `arrow` outside its enum: core swaps in the default (`chevron`) before `render.php` runs.
- Several instances in one layout: allowed (`multiple` defaults to true).
- Inside Billing/Shipping sections: allowed, as with the step buttons (no `ancestor` rule).

**Decision record:** None.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Extend the Previous Step Button's cart link with `arrow` and inline editing | Shows on step 1 only and is tied to the Back button, so it can't be placed or shown independently. |
| `core/button` variation with the cart URL | URL is saved into post content, so it breaks when the cart page changes; no `--yaycheckout-primary` colour or translatable default label. |
| Reuse `StepNavButtonEdit` | It puts block props on the `RichText` itself, leaving no place for the icon; changing shared code for one block is riskier than ~15 lines. |
| `ToggleGroupControl` for Icon, as core does | Still `__experimentalToggleGroupControl` in WordPress 7.1; plugin floor is 6.5. |
| Chevron `«` (core) | Merchant chose the single `‹` used by WooCommerce Checkout. |
| SVG icon | Text glyphs inherit colour and size with no extra CSS, as core does. |

## Rollout

New block only; existing layouts are unchanged. Release note: "New block: Return to Cart Button."

## Open questions

None.
