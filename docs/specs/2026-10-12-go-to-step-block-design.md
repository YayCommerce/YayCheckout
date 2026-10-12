# Go to Step Block Design

**Status:** accepted 2026-10-12
**Related:** None

## Goal

Merchants can place a "Go to step" button anywhere in a checkout layout that opens a step they pick. Shoppers can click it only once that step is complete; until then it is disabled.

## Context

- Steps own the open step in `packages/blocks/src/steps/view.ts`. Each `.yaycheckout-step` carries `data-step` (1-based position) and `data-state` (`current`, `locked`, `complete`). Every state change dispatches `yaycheckout:step-change` (bubbles). A `yaycheckout:step-reopen` event with `detail.step` sent from inside Checkout calls `reopen()`, which parks the open step (validating it if it was completed before) and opens the target.
- Progress (`packages/blocks/src/progress/`) already does this jump: `CheckoutStepsProgress::markup()` prints each item's `button.yaycheckout-progress__jump` `disabled`, `progress/view.ts` repaints `disabled = state !== 'complete'` on `yaycheckout:step-change` across the whole page, and a click dispatches `yaycheckout:step-reopen` on `checkoutFor( button )`. Progress may sit outside Checkout; `checkoutFor()` (`packages/blocks/src/shared/checkout-root.ts`) falls back to the page's Checkout.
- Step numbers come from the rendered layout's first Steps block: `Renderer\CheckoutStepsProgress::layout_titles()` (private, memoised) on the server, `getBlocksByName( 'yaycheckout/steps' )[ 0 ]` → `getBlockOrder()` in `progress/edit.tsx`. Step titles: `CheckoutStepsProgress::step_title()` / `shared/step-title.ts`.
- Leaf buttons edit their label inline with `StepNavButtonEdit` (`packages/blocks/src/shared/step-nav-button-edit.tsx`); the server reads it with `LayoutMarkupReader::plain_text()`. Blocks register via `registerDynamicBlock()`; `Blocks\BlockRegistry::ORDER` sets inserter order.
- Return to Cart (`packages/blocks/src/return-to-cart/`) is the text-link look and the `typography` supports set to copy.
- `isCheckoutSection()` (`shared/allowed-blocks.ts`) only keeps blocks whose `ancestor` names Checkout out of address sections, so a block with no `parent`/`ancestor` is allowed everywhere.

## Scope

**In:**
- New block `yaycheckout/go-to-step`, placeable anywhere in a layout, in Free and Pro.
- Target step picked by position in the first Steps block; enabled only while that step's state is `complete`.
- Inline-editable label; empty label prints translatable "Edit".
- Style supports: color (text, background), typography, padding, margin.

**Out:**
- Border supports, arrow/icon setting.
- A stable step id (the target follows the position when steps are reordered).
- Enabling the button for the `current` step, scrolling to the opened step, hiding instead of disabling.
- Any change to `steps/view.ts`, Progress's markup or script, or the other step buttons.
- Adding the block to `templates/default-layout.php` or any template.

## Acceptance criteria

1. Given the blocks are built, the inserter lists "Go to Step Button" in the YayCheckout category directly after "Return to Cart Button".
2. A newly inserted block shows the placeholder "Edit" on the canvas; clicking it edits the label in place with no formatting toolbar.
3. In a layout whose first Steps block holds "Information", "Shipping", "Payment", the sidebar panel "Go to step" has a "Step" select with options `1. Information`, `2. Shipping`, `3. Payment`, and `1` selected by default. An empty step title shows as "Step".
4. Given the layout has no Steps block, the sidebar shows a warning Notice "Add a Steps block to use Go to Step." Given `step` is greater than the number of steps, it shows "Step N no longer exists. Pick another step." (N = saved value). In both cases the block still shows on the canvas.
5. The sidebar offers Color (text, background), Typography, and Dimensions (padding, margin), and no Border.
6. On the storefront, with a valid target, the block renders `<div class="wp-block-yaycheckout-go-to-step yaycheckout-go-to-step …"><button type="button" class="yaycheckout-go-to-step__button" data-step="N" disabled>label</button></div>` with the label as plain text: `__( 'Edit', 'yaycheckout' )` when `buttonText` is empty, the saved text otherwise.
7. Given no Steps block, or `step` < 1, or `step` > number of steps, the block prints nothing.
8. With a target step of 1, the button is disabled on page load; after the shopper clicks Continue on step 1 it becomes enabled; clicking it opens step 1 and the button turns disabled again (step 1 is now `current`).
9. When the open step was completed before and now has an invalid value, clicking an enabled Go to step button reports the invalid field and the open step stays open.
10. Several Go to step blocks on one page, inside or outside the Checkout block, each enable/disable from their own target.
11. End-to-end verification: run `pnpm build:blocks`; in a layout with Steps "Information", "Shipping", "Payment", place a Go to step block (Step `1. Information`, label "Change contact") inside step 3 and one (Step `2. Shipping`) above Checkout; publish. On the storefront checkout both are disabled; Continue through to step 3; both are enabled; click "Change contact" → step 1 opens and that button is disabled; the Shipping one stays enabled.

## Constraints

- Product PHP runs on PHP 7.4 (no `match`, enums, `readonly`, promoted properties, `?->`, named arguments, union types).
- WordPress floor 6.5: no `__experimental*` components (block.json supports keys like `__experimentalFontFamily` are fine, as in Return to Cart).
- Free build: no reference to `YayCheckout\Pro`; the block is not gated by `Plan::is_pro()`.
- Never hand-edit `assets/dist/`; edit `packages/blocks/src/go-to-step/` and rebuild.
- TypeScript copies of PHP values carry a comment naming the PHP owner (`docs/conventions.md`).

## Approach

**Chosen:** Standalone dynamic block with its own view script.

**Architecture:** A leaf dynamic block shaped like Progress. `render.php` prints a disabled button carrying `data-step`; `view.ts` mirrors the target step's state onto `disabled` and turns a click into `yaycheckout:step-reopen`, which `steps/view.ts` already handles (park/validate the open step, open the target). Steps stays the only owner of step state; no Steps code changes.

**Components:**
- `packages/blocks/src/go-to-step/block.json`: `name` `yaycheckout/go-to-step`, `title` "Go to Step Button", `category` `yaycheckout`, `description` "Opens the step you pick once the shopper has completed it. Place it anywhere in the layout.", `keywords` `edit`, `step`, `jump`; attributes `step` (`integer`, default `1`), `buttonText` (`string`, default `""`); `supports`: `html: false`, `reusable: false`, `color` `{ text: true, background: true }` with `__experimentalDefaultControls` for both (without them the editor shows no Color panel), `typography` (same keys as `return-to-cart/block.json`), `spacing` `{ padding: true, margin: true }`; no `parent`/`ancestor`; `editorScript`, `viewScript` `file:./view.js`, `style`, `render` as in `progress/block.json` (no `editorStyle`).
- `packages/blocks/src/go-to-step/index.ts`: `registerDynamicBlock( metadata, Edit )`, imports `./style.css`.
- `packages/blocks/src/go-to-step/edit.tsx`: a `useBlockProps` div `.yaycheckout-go-to-step` holding a `RichText` div `.yaycheckout-go-to-step__button` (placeholder `__( 'Edit', 'yaycheckout' )`, `allowedFormats={ [] }`), as `return-to-cart/edit.tsx` does; no icon. `InspectorControls` → `PanelBody` "Go to step" → `SelectControl` "Step" with options `${ n }. ${ stepTitle( decodeEntities( title ) ) }` from the first Steps block's children (same selector as `progress/edit.tsx`, with a comment naming `CheckoutStepsProgress::layout_titles()` as owner); the Notices in criterion 4 below the select (or alone when there are no steps).
- `packages/blocks/src/go-to-step/render.php`: `$count = count( CheckoutStepsProgress::layout_titles() )`; `$step = (int) ( $attributes['step'] ?? 1 )`; return `''` when `$step < 1 || $step > $count`; otherwise `<div class="wp-block-yaycheckout-go-to-step yaycheckout-go-to-step …"><button type="button" class="yaycheckout-go-to-step__button" data-step="N" disabled>label</button></div>`: `get_block_wrapper_attributes( [ 'class' => 'yaycheckout-go-to-step' ] )` on the div, the escaped label in the button. The wrapper is block-level, as in Return to Cart, so a constrained layout aligns it with the content in the editor and on the storefront; a `fit-content` button as the wrapper was centred by the editor's auto margins only.
- `packages/blocks/src/go-to-step/view.ts`: boot guard `data-yaycheckout-go-to-step-bound` on `<html>`; on `yaycheckout:step-change`, map `data-step` → `data-state` from the event target's Checkout and set `button.disabled = state !== 'complete'` on every `.yaycheckout-go-to-step` on the page; on click of a non-disabled `.yaycheckout-go-to-step`, `preventDefault()` and dispatch `new CustomEvent( 'yaycheckout:step-reopen', { bubbles: true, detail: { step: button.dataset.step } } )` on `checkoutFor( button )`. Uses `onDomReady`.
- `packages/blocks/src/go-to-step/style.css`: the look sits on the wrapper `.yaycheckout-go-to-step` (`margin-block: 16px 0; color: var(--yaycheckout-primary-color, #3858e9); font-size: 16px; font-weight: 600; line-height: 1.2`), where the block's settings land; `.yaycheckout-go-to-step__button` inherits it (`padding: 0; border: 0; background: transparent; color/font/letter-spacing/text-transform/text-decoration: inherit; cursor: pointer`); its `:is(:hover, :focus)` keeps background and color (classic themes repaint `button:hover`); `:disabled` → `opacity: .5; cursor: not-allowed`.
- `includes/Renderer/CheckoutStepsProgress.php`: `layout_titles()` from `private` to `public`; docblock notes Go to Step reads it too.
- `includes/Blocks/BlockRegistry.php`: add `'go-to-step'` to `ORDER` after `'return-to-cart'`.

**Data and storage:** None beyond the block's attributes in the layout markup.

**Integration points:** Listens to `yaycheckout:step-change`, dispatches `yaycheckout:step-reopen` (both owned by `steps/view.ts`, unchanged). Reads `CheckoutStepsProgress::layout_titles()`.

**UI:** Block editor canvas and sidebar (above); storefront button.

**Edge cases and failure modes:**
- Steps reordered: target follows the position number.
- Steps removed or fewer steps than `step`: storefront prints nothing; editor shows the Notice.
- Target is the open step: disabled.
- Open step invalid on click: `reopen()` reports the field and stays; the button stays enabled.
- Block outside Checkout: `checkoutFor()` finds the page's Checkout.

**Decision record:** None.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Fold the click and sync into `steps/view.ts` | Steps would own one more block; Progress already sets the separate-block-plus-events pattern. |
| Shared jump helper for Progress and Go to step | ~10 lines shared by two callers; refactors Progress for no gain. |
| Stable id attribute on Step | Needs a Step change and a backfill for existing layouts; position matches Progress. |

## Rollout

None. New block; existing layouts are unchanged.

## Open questions

None.
