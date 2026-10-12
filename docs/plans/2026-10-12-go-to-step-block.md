# Go to Step Block Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-12
**Goal:** Add a `yaycheckout/go-to-step` block that opens a picked step once the shopper has completed it, and is disabled until then.
**Spec:** `docs/specs/2026-10-12-go-to-step-block-design.md`
**Architecture:** A leaf dynamic block shaped like Progress. `render.php` prints a disabled `<button data-step="N">`; its own `view.ts` mirrors the target step's `data-state` onto `disabled` on `yaycheckout:step-change` and turns a click into `yaycheckout:step-reopen`, which `steps/view.ts` already handles. Steps code does not change.
**Touches:**
- New `packages/blocks/src/go-to-step/` (`block.json`, `index.ts`, `edit.tsx`, `render.php`, `style.css`, `view.ts`).
- `includes/Renderer/CheckoutProgress.php`: `layout_titles()` becomes public. Consumers: `CheckoutProgress::markup()` (unchanged), new `go-to-step/render.php`.
- `includes/Blocks/BlockRegistry.php`: `ORDER`.
- `packages/blocks/src/shared/block-icons.tsx` (shared; also the SPA's `@blocks-shared`): one new key. Consumers: `register-dynamic-block.tsx` and any SPA import of `blockIcons`; adding a key changes nothing for them.

**Revised after /yaytidy (2026-10-12):** the block's markup is a block-level `div` wrapper holding `button.yaycheckout-go-to-step__button`, as in Return to Cart, so it aligns with the content in the editor and on the storefront. Moving the step list out of `CheckoutProgress` and sharing the step code with Progress was built, then taken out of this PR to keep it to the new block; it is `docs/backlog/2026-10-12-step-list-owned-by-progress.md`.
**Tests:** none. A disabled/enabled button driven by an existing event is not logic the CLAUDE.md test rule lists; each task has an exact manual check instead.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- WordPress floor 6.5: no `__experimental*` components. `block.json` supports keys like `__experimentalFontFamily` are fine, as in `return-to-cart/block.json`.
- Free names Pro only in `boot_pro()`. This is Free code, not gated by `Plan::is_pro()`, and must pass `scripts/check-pro-boundary.sh` (run by `composer lint`).
- Never hand-edit `assets/dist/`. Rebuild with `pnpm build:blocks`. Edit `render.php` in `packages/blocks/src/go-to-step/`.
- Every new string uses the `yaycheckout` text domain.
- A TypeScript copy of a PHP value carries a `Mirrors …` comment naming the PHP owner (`docs/conventions.md`).
- Every attribute has a default in `render.php` too, because stored layouts may lack it (`.claude/rules/blocks.md`).
- Merchant-typed text stays RichText on the canvas with `allowedFormats={ [] }` (as in `return-to-cart/edit.tsx`).
- Never test edits on an existing draft layout, because autosave writes them. Create a new draft for every editor check.
- Branch `alan/feat/go-to-step-block` (already created from `dev/edd`). Commit messages follow Conventional Commits, end with CLAUDE.md's trailer, and stage files by name; `composer.json` has an unrelated local change and stays unstaged.

## Review focus

- **Open step invalid on click:** a step completed earlier, edited to an invalid value, then left via Go to step must report the field and stay open (spec criterion 9). Covered by Task 2 Step 3.
- **Block outside Checkout:** `checkoutFor()` must reach the page's Checkout both for painting and for the reopen event; otherwise Steps ignores it. Covered by Task 2 Step 3.
- **Target becomes current:** after a jump, the clicked button turns disabled again, while other Go to step blocks keep their own state (criteria 8, 10). Covered by Task 2 Step 3.
- **Missing target:** a saved `step` greater than the step count prints nothing on the storefront and shows the Notice in the editor (criteria 4, 7). Covered by Task 1 Steps 9 and 10.

---

### Task 1: Block, editor and server render

**Files:**
- Create: `packages/blocks/src/go-to-step/block.json`
- Create: `packages/blocks/src/go-to-step/index.ts`
- Create: `packages/blocks/src/go-to-step/edit.tsx`
- Create: `packages/blocks/src/go-to-step/render.php`
- Create: `packages/blocks/src/go-to-step/style.css`
- Modify: `includes/Renderer/CheckoutProgress.php` (`layout_titles()` visibility and docblock)
- Modify: `includes/Blocks/BlockRegistry.php` (`ORDER`)
- Modify: `packages/blocks/src/shared/block-icons.tsx`

**Interfaces:**
- Consumes: `CheckoutProgress::layout_titles(): array` (string[]), `LayoutMarkupReader::plain_text( array $attributes, string $key, string $fallback ): string`, `stepTitle()` (`shared/step-title.ts`), `registerDynamicBlock()`.
- Produces: block `yaycheckout/go-to-step` with attributes `step` (integer, default `1`) and `buttonText` (string, default `""`); storefront markup `<div class="wp-block-yaycheckout-go-to-step yaycheckout-go-to-step …"><button type="button" class="yaycheckout-go-to-step__button" data-step="N" disabled>label</button></div>`, consumed by Task 2.

- [ ] **Step 1: Make `CheckoutProgress::layout_titles()` public**

`private static function layout_titles(): array` → `public static`. Add to its docblock that Go to Step reads it to count steps.

- [ ] **Step 2: Create `go-to-step/block.json`**

Values exactly as the spec's Components entry: `name` `yaycheckout/go-to-step`, `title` "Go to Step Button", `category` `yaycheckout`, `description` "Opens the step you pick once the shopper has completed it. Place it anywhere in the layout.", `textdomain` `yaycheckout`, `keywords` `edit`, `step`, `jump`, `example` `{ "attributes": { "buttonText": "Edit" } }`; attributes `step` `{ "type": "integer", "default": 1 }`, `buttonText` `{ "type": "string", "default": "" }`; `supports`: `html: false`, `reusable: false`, `color` `{ "text": true, "background": true, "__experimentalDefaultControls": { "background": true, "text": true } }` (without the defaults the editor shows no Color panel), `typography` copied key-for-key from `return-to-cart/block.json`, `spacing` `{ "padding": true, "margin": true }`; no `__experimentalBorder`, `parent` or `ancestor`; `editorScript` `file:./index.js`, `viewScript` `file:./view.js`, `style` `file:./style-index.css`, `render` `file:./render.php`.

Until Task 2 lands, create `view.ts` as an empty module (`export {};`) so the `viewScript` entry builds; Task 2 fills it.

- [ ] **Step 3: Create `go-to-step/index.ts`**

Same three lines as `progress/index.ts`: import `./block.json`, `./edit`, `./style.css`; `registerDynamicBlock( metadata, Edit )`.

- [ ] **Step 4: Create `go-to-step/edit.tsx`**

`export default function Edit( { attributes, setAttributes }: EditProps )` with `attributes: { step: number; buttonText: string }`.
- Step ids: the same `useSelect` as `progress/edit.tsx` (`getBlocksByName( STEPS )[ 0 ]` → `getBlockOrder( stepsId )`, `null` without Steps), with the comment `Mirrors CheckoutProgress::layout_titles(): the layout's first Steps block, in document order.` Titles: one `useSelect` mapping those ids to `getBlockAttributes( id )?.title` strings.
- Sidebar: `InspectorControls` → `PanelBody` title `__( 'Go to step', 'yaycheckout' )`. With steps: `SelectControl` label `__( 'Step', 'yaycheckout' )`, value `String( attributes.step )`, options `{ label: \`${ n }. ${ stepTitle( decodeEntities( title ) ) }\`, value: String( n ) }` for n = 1…count, `onChange` → `setAttributes( { step: Number( value ) } )`.
- Notices (`<Notice status="warning" isDismissible={ false }>`, in the panel): no Steps → `__( 'Add a Steps block to use Go to Step.', 'yaycheckout' )` (no select); `attributes.step > count` → `sprintf( __( 'Step %d no longer exists. Pick another step.', 'yaycheckout' ), attributes.step )` with a `/* translators: %d: saved step number. */` comment, shown below the select.
- Canvas: `<div { ...useBlockProps( { className: 'yaycheckout-go-to-step' } ) }>` holding `<RichText tagName="div" className="yaycheckout-go-to-step__button" placeholder={ __( 'Edit', 'yaycheckout' ) } allowedFormats={ [] } withoutInteractiveFormatting … />`, as `return-to-cart/edit.tsx` does, with a comment that the placeholder mirrors the fallback in `go-to-step/render.php`.

- [ ] **Step 5: Create `go-to-step/render.php`**

Header docblock in the style of `step-button/render.php` (what the script does, `@var` lines, `@package yaycommerce/yaycheckout`), then `defined( 'ABSPATH' ) || exit;`.
- `$yaycheckout_step = (int) ( $attributes['step'] ?? 1 );`
- If `$yaycheckout_step < 1 || $yaycheckout_step > count( \YayCheckout\Renderer\CheckoutProgress::layout_titles() )`: `return;` (prints nothing).
- Label: `\YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Edit', 'yaycheckout' ) )`.
- Print `<div class="wp-block-yaycheckout-go-to-step yaycheckout-go-to-step …"><button type="button" class="yaycheckout-go-to-step__button" data-step="N" disabled>label</button></div>`: `get_block_wrapper_attributes( [ 'class' => 'yaycheckout-go-to-step' ] )` on the div with the same phpcs:ignore comment as `return-to-cart/render.php`, `esc_attr()` on `data-step`, `esc_html()` on the label. The wrapper is block-level, as in Return to Cart, so a constrained layout aligns it with the content in the editor and on the storefront; a `fit-content` button as the wrapper was centred by the editor's auto margins only.

- [ ] **Step 6: Create `go-to-step/style.css`**

Wrapper `.yaycheckout-go-to-step` (where the block's settings land): `margin-block: 16px 0; color: var(--yaycheckout-primary-color, #3858e9); font-size: 16px; font-weight: 600; line-height: 1.2; text-decoration: none;`
`.yaycheckout-go-to-step__button`: `padding: 0; border: 0; background: transparent; color: inherit; font: inherit; letter-spacing: inherit; text-transform: inherit; text-decoration: inherit; cursor: pointer;`
Its `:is(:hover, :focus)` keeps `background: transparent; color: inherit`, with the comment `/* Classic themes (Storefront) repaint button:hover. */`.
Its `:disabled`: `opacity: .5; cursor: not-allowed;`

- [ ] **Step 7: Register order and icon**

`BlockRegistry::ORDER`: insert `'go-to-step',` after `'return-to-cart',`.
`block-icons.tsx`: add `'yaycheckout/go-to-step'` after `'yaycheckout/return-to-cart'`, in the same `icon()` family: a rounded pill with a return-arrow, e.g. `<rect x="3" y="7" width="18" height="10" rx="5" /><path d="M9 10.5h5a2 2 0 0 1 0 4H9m2-6-2 2 2 2" />` (adjust only if it renders poorly at 24px).

- [ ] **Step 8: Build and lint**

Run `pnpm build:blocks`. Expected: the build finishes with no errors and `assets/dist/blocks/go-to-step/` holds `block.json`, `index.js`, `view.js`, `style-index.css`, `render.php`.
Run `composer lint -- includes/Renderer/CheckoutProgress.php includes/Blocks/BlockRegistry.php packages/blocks/src/go-to-step/render.php`. Expected: no errors.
Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. Expected: no new errors compared with `dev/edd` (`block-icons.tsx` is shared).

- [ ] **Step 9: Verify in the editor (new draft layout with Steps "Information", "Shipping", "Payment")**

- Open the inserter. Expected: "Go to Step Button" in the YayCheckout category directly after "Return to Cart Button", with the new icon.
- Insert it inside the Billing section of step 1, then above the Checkout block. Expected: both insert; the canvas shows the placeholder "Edit"; clicking it edits in place with no formatting toolbar.
- Sidebar. Expected: panel "Go to step" with select "Step" listing `1. Information`, `2. Shipping`, `3. Payment`, `1` selected; Styles show Color (text, background), Typography, Dimensions (padding, margin) and no Border.
- Clear step 2's title. Expected: the option reads `2. Step`.
- Pick `3. Payment`, then remove the third Step. Expected: Notice "Step 3 no longer exists. Pick another step." below the select. Undo.
- In a second new draft with no Steps block, insert the block. Expected: Notice "Add a Steps block to use Go to Step." and no select.

- [ ] **Step 10: Verify the storefront markup**

Preview the first draft with one block set to Step 1 and label "Change contact", another set to Step 3. Expected in DevTools: `<div class="yaycheckout-go-to-step wp-block-yaycheckout-go-to-step"><button type="button" class="yaycheckout-go-to-step__button" data-step="1" disabled>Change contact</button></div>` in the primary colour, left-aligned with Checkout, at 50% opacity; the Step 3 one has text "Edit".
Edit the layout so a block's `step` is `4` (pick 3, delete step 3, preview). Expected: that block prints nothing.

- [ ] **Step 11: Commit**

Stage: `packages/blocks/src/go-to-step/block.json`, `packages/blocks/src/go-to-step/index.ts`, `packages/blocks/src/go-to-step/edit.tsx`, `packages/blocks/src/go-to-step/render.php`, `packages/blocks/src/go-to-step/style.css`, `packages/blocks/src/go-to-step/view.ts`, `includes/Renderer/CheckoutProgress.php`, `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/shared/block-icons.tsx`
Message: `feat(blocks): add Go to Step button block`

### Task 2: Storefront enable and jump

**Files:**
- Modify: `packages/blocks/src/go-to-step/view.ts`

**Interfaces:**
- Consumes: Task 1's storefront markup; event `yaycheckout:step-change` (dispatched on a `.yaycheckout-step`, bubbles) and `yaycheckout:step-reopen` with `detail: { step: string }` (both owned by `steps/view.ts`); `CHECKOUT_SELECTOR`, `checkoutFor()` (`shared/checkout-root.ts`); `onDomReady()` (`shared/dom-ready.ts`).
- Produces: nothing other blocks consume.

- [ ] **Step 1: Write `go-to-step/view.ts`**

Header comment: "Storefront enable/jump for yaycheckout/go-to-step. Steps owns the open step; this script mirrors data-state and asks Steps to reopen." Shape follows `progress/view.ts`:
- `boot()`: return if `<html>` has `data-yaycheckout-go-to-step-bound`; else set it, listen `document` `yaycheckout:step-change` → `onStepChange`, `document` `click` → `onClick`.
- `onStepChange( event: Event ): void`: from `event.target.closest( CHECKOUT_SELECTOR )`, map each `.yaycheckout-step`'s `data-step` → `data-state` (first wins, as Progress does); then for every `button.yaycheckout-go-to-step__button` on the page (it may sit outside Checkout): `button.disabled = states.get( button.dataset.step ?? '' ) !== 'complete'`.
- `onClick( event: MouseEvent ): void`: `target.closest< HTMLButtonElement >( 'button.yaycheckout-go-to-step__button' )`; return if none, `disabled`, no `dataset.step`, or no `checkoutFor( button )`; else `event.preventDefault()` and dispatch `new CustomEvent( 'yaycheckout:step-reopen', { bubbles: true, detail: { step: button.dataset.step } } )` on that Checkout.
- `onDomReady( boot );`

- [ ] **Step 2: Build**

Run `pnpm build:blocks`. Expected: no errors.

- [ ] **Step 3: End-to-end verification (spec criterion 11)**

New draft layout with Steps "Information", "Shipping", "Payment". Place a Go to step block (Step `1. Information`, label "Change contact") inside step 3, and one (Step `2. Shipping`) above the Checkout block; publish it as the active layout (or preview).
On the storefront checkout:
- On load. Expected: both buttons are disabled.
- Fill step 1, Continue. Expected: "Change contact" is enabled (step 1 complete); the Shipping one stays disabled.
- Continue to step 3. Expected: both enabled.
- Click "Change contact". Expected: step 1 opens; "Change contact" is disabled again; the Shipping one stays enabled.
- In step 1, clear the email, then click the Shipping button above Checkout. Expected: the browser reports the email field, step 1 stays open, and the Shipping button stays enabled.
- Fix the email and click the Shipping button. Expected: step 2 opens and the Shipping button is disabled.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/go-to-step/view.ts`
Message: `feat(blocks): enable Go to Step once its step is complete`
