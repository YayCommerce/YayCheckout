# Coupon Button Block Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract.

**Status:** active
**Goal:** The coupon form's Apply button becomes its own block, Coupon Button, so a merchant can style it from core's Styles panel: colour, typography, border, padding, and Fill/Outline.
**Spec:** none, planned from the request
**Architecture:** Coupon Button copies Place Order (`packages/blocks/src/place-order/`): the `<button>` is the block's root element, so `useBlockProps` and `get_block_wrapper_attributes()` print the style supports on it. Coupon Form becomes a parent that holds exactly one Coupon Button, locked by its template. Coupon Form's existing Background and Text colours become the button's starting look: the parent prints them as CSS custom properties, and the child reads them only while it has no colour of its own. This was chosen over adding more sidebar controls to Coupon Form (option 1) or a global Buttons section in Design (option 3). The block gets core's full style UI and theme presets, applied to each button separately.
**Touches:** `packages/blocks/src/coupon-button/` (new), `packages/blocks/src/coupon-form/{block.json,edit.tsx,index.ts,render.php}`, `packages/blocks/src/shared/block-icons.tsx` (shared: every block's icon, and the SPA via `@blocks-shared`), `includes/Blocks/BlockRegistry.php`, `templates/default-layout.php`, `docs/architecture.md`
**Tests:** none. Button styling doesn't decide whether a shopper can check out, and it isn't a reproduced bug, so CLAUDE.md's test rule doesn't apply. Each task has an exact manual check instead.

## Requirements

- **Goal:** Selecting the Apply button in the layout editor selects a Coupon Button block. Its Styles tab offers Color (text, background, gradient), Typography, Border (colour, width, style, radius), Dimensions (padding, margin) and the styles Fill and Outline. The storefront prints what the merchant picked.
- **Out of scope:**
  - Place Order, Login or step buttons.
  - The Design screen and its tokens.
  - `assets/js/checkout-coupon-form.js` behaviour. It keeps finding the button by `.yaycheckout-coupon-form__apply`.
  - Disclosure.
  - Hover colours as separate controls. Base CSS keeps its `filter: brightness(0.92)` hover.
- **Acceptance criteria:**
  1. **Default look:** with nothing set, the button looks as it does today. Background is `var(--yaycheckout-primary-color)`, text `#fff`, and it keeps the base padding, radius and weight.
  2. **Starting colours:** Coupon Form's sidebar keeps the **Button colors** panel (Background, Text). Those values colour the button while the Coupon Button block has no colour of its own.
  3. **Own colours win:** a colour picked on Coupon Button, whether a palette preset (`has-*` class) or a custom value (inline style), overrides the Coupon Form colours. This holds in the editor and on the storefront.
  4. **Other supports:** typography, border, padding and margin set on Coupon Button show on the storefront `<button>`.
  5. **Outline:** the button has a transparent background and a 1px solid border. Its text and border use the starting background colour, or `var(--yaycheckout-primary-color)` when none is set. A colour picked on the block still wins.
  6. **Locked:** a Coupon Form always holds exactly one Coupon Button. The inserter doesn't offer Coupon Button on its own, and it can't be removed or moved out.
  7. **Button text:** the button's text is edited on the canvas (RichText) and stored on Coupon Button as `buttonText`. It falls back to "Apply coupon".
  8. **Missing child:** a Coupon Form saved with no inner block, e.g. `<!-- wp:yaycheckout/coupon-form /-->`, still prints a working default Apply button on the storefront.
  9. **End to end:** after `pnpm build:blocks`, give the Coupon Button in a layout the Outline style and a custom font size, then activate the layout. On the storefront checkout, the Apply button shows both. Applying a valid coupon updates the totals, and an invalid one shows the inline error, as before.
- **Decided with the user:**
  - **Approach:** Apply becomes its own child block (option 2 of 3).
  - **Existing colours:** keep `buttonBackgroundColor` and `buttonTextColor` on Coupon Form as the starting look; the child's own colours win.
  - **Variations:** Fill (default) and Outline.
  - **Not released:** Coupon Form's `buttonText` moves to the child with no fallback.

## Global constraints

- Product PHP must run on PHP 7.4. That rules out enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types, `mixed`, `str_contains` and the other items in `.claude/rules/php-standards.md`.
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/<block>/` and run `pnpm build:blocks`.
- Run from the repo root on Node 22 (`nvm use`), with pnpm only.
- Blocks are dynamic. Coupon Button's `save` returns null, and Coupon Form's `save` becomes `saveNestedBlocks` (`packages/blocks/src/shared/register-dynamic-block.tsx`). A parent whose `save()` returns null loses its inner blocks on the next load.
- Text a merchant types is edited with `RichText` and `allowedFormats={ [] }`, stored with HTML entities, and printed with `esc_html()`.
- `composer lint -- <files>` must be clean for every changed PHP file. Nothing type-checks the blocks' TypeScript, so `pnpm build:blocks` is the check there. `shared/block-icons.tsx` also reaches the SPA, so run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` too.
- **Commits:** Conventional Commits, ending with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`. Stage files by name, and leave the unrelated `composer.json` change unstaged. The commit target is the user's call: CLAUDE.md says to branch as `trace/feat/coupon-button-block`, but this user has had each recent change committed directly to `dev/edd` with scope `trace`. Ask before the first commit.

## Review focus

- **Preset vs custom colour:** a palette preset arrives as a `has-<slug>-background-color` class, a custom value as an inline style, and both add `.has-background`. The starting-colour rule must step aside on `.has-background` and `.has-text-color`, not on an inline style. Checked in Task 1, Step 6.
- **Base CSS specificity:** `.yaycheckout-checkout :is(.button, button[type="submit"])` in `assets/css/checkout-base.css` (0,2,0) sets the background and colour. The child's starting-colour rules need higher specificity, or they lose on the storefront and not in the editor. Checked in Task 1, Step 6.
- **Unsafe colour value:** an attribute holding something like `red;}body{display:none` must not end up in the wrapper's `style`. Checked in Task 1, Step 5.
- **Old markup:** the default layout's self-closing `<!-- wp:yaycheckout/coupon-form /-->` must still render a button, and the editor fills in the child when it opens the layout. Checked in Task 1, Step 5.

---

### Task 1: Coupon Button block inside Coupon Form

**Files:**
- Create: `packages/blocks/src/coupon-button/block.json`, `edit.tsx`, `index.ts`, `render.php`, `style.css`
- Modify: `packages/blocks/src/coupon-form/block.json`, `edit.tsx`, `index.ts`, `render.php`
- Modify: `packages/blocks/src/shared/block-icons.tsx` (shared)
- Modify: `includes/Blocks/BlockRegistry.php` (`ORDER`, `block_settings()`)
- Modify: `templates/default-layout.php:124`

**Interfaces:**
- **Produces:**
  - The block `yaycheckout/coupon-button`, with attribute `buttonText: string` (default `""`) and root class `yaycheckout-coupon-form__apply`.
  - The CSS custom properties `--yaycheckout-coupon-button-background` and `--yaycheckout-coupon-button-text`, printed on the Coupon Form wrapper when its colours are set.

- [ ] **Step 1: `coupon-button/block.json`**
  - `apiVersion` 3, `"parent": [ "yaycheckout/coupon-form" ]`.
  - `attributes.buttonText`: `{ "type": "string", "default": "" }`.
  - `supports`:
    - `html: false`, `reusable: false`, `inserter: false`, `multiple: false`.
    - `color`: `text`, `background` and `gradients`, with `__experimentalDefaultControls` background and text.
    - `typography`: the same keys as Place Order's, without `textAlign` and `__experimentalWritingMode`.
    - `spacing`: `{ "padding": true, "margin": true }`.
    - `__experimentalBorder`: color, radius, style, width.
  - `"styles": [ { "name": "fill", "label": "Fill", "isDefault": true }, { "name": "outline", "label": "Outline" } ]`.
  - `editorScript`, `style` and `render` as in `place-order/block.json`.

- [ ] **Step 2: `coupon-button/render.php` and `edit.tsx`**
  - **render.php:** print `<button type="button" {get_block_wrapper_attributes( [ 'class' => ButtonClassHelper::classes( 'button' ) . ' yaycheckout-coupon-form__apply' ] )}>`.
    - The text is `LayoutMarkupReader::plain_text( $attributes, 'buttonText', __( 'Apply coupon', 'yaycheckout' ) )`, printed with `esc_html()`.
  - **edit.tsx:** follow `place-order/edit.tsx`.
    - `RichText` is the root, with `{ ...useBlockProps( { className: 'button wp-element-button yaycheckout-coupon-form__apply' } ) }`, `tagName="div"` and `allowedFormats={ [] }`.
    - Keep the comment from today's `coupon-form/edit.tsx` on why it's a div, not a button.
  - **index.ts:** `registerDynamicBlock( metadata, Edit )`, importing `./style.css`.

- [ ] **Step 3: `coupon-button/style.css`**
  Every selector is prefixed `.yaycheckout-checkout .wp-block-yaycheckout-coupon-button` (call it `B`) to beat the base rule's 0,2,0. The editor canvas has the same `.yaycheckout-checkout` ancestor (the Checkout block).
  - `B:not(.has-background)`: `background: var(--yaycheckout-coupon-button-background, var(--yaycheckout-primary-color))`.
  - `B:not(.has-text-color)`: `color: var(--yaycheckout-coupon-button-text, #fff)`.
  - `B.is-style-outline:not(.has-background)`: `background: transparent`.
  - `B.is-style-outline:not(.has-text-color)`: `color: var(--yaycheckout-coupon-button-background, var(--yaycheckout-primary-color))`.
  - `B.is-style-outline:not(.has-border-color)`: `border: 1px solid currentColor`.
  - The border-style fallback rule, copied from `place-order/style.css` (the `:where(.has-border-color, [style*="border-…"])` block).

- [ ] **Step 4: Coupon Form becomes the parent**
  - **block.json:** remove `buttonText`, and keep `buttonBackgroundColor` and `buttonTextColor`.
  - **index.ts:** `registerDynamicBlock( metadata, Edit, saveNestedBlocks )`.
  - **edit.tsx:**
    - Remove the Button text TextControl and the RichText.
    - Keep the **Button colors** `PanelColorSettings`.
    - Build the row with `const { children, ...innerBlocksProps } = useInnerBlocksProps( { className: 'yaycheckout-coupon-form__row' }, { template: [ [ 'yaycheckout/coupon-button' ] ], templateLock: 'all', allowedBlocks: [ 'yaycheckout/coupon-button' ] } )`, then render `<input …/>{ children }` inside it. This is the same pattern as `order-summary/edit.tsx`.
    - Pass the two custom properties in `useBlockProps( { style } )` when they're set.
  - **render.php:**
    - Drop `$yaycheckout_button_text`, `$yaycheckout_button_class` and the inline-style loop.
    - Build a style string of `--yaycheckout-coupon-button-background:<v>` and `--yaycheckout-coupon-button-text:<v>`. Include a value only if it's a non-empty string and `safecss_filter_attr( 'color:' . $v )` is non-empty. Pass it as `'style'` in the `HideWhenEmpty::wrapper()` array.
    - In the row, after the input, print `$content` when `'' !== trim( $content )`. Otherwise print `render_block( [ 'blockName' => 'yaycheckout/coupon-button', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => [] ] )`.
    - Update the header docblock: `$content` is now Coupon Button's markup.
  - **`templates/default-layout.php:124`:** replace the self-closing comment with `<!-- wp:yaycheckout/coupon-form -->\n<!-- wp:yaycheckout/coupon-button /-->\n<!-- /wp:yaycheckout/coupon-form -->`.
  - **BlockRegistry:** in `BlockRegistry::ORDER`, add `'coupon-button'` after `'coupon-form'`. In `block_settings()`, add a `'yaycheckout/coupon-button'` entry with `spacing` (`padding`, `margin`) and `border` (`color`, `radius`, `style`, `width`), as for Place Order, and update the method's docblock list.
  - **block-icons.tsx:** add a `'yaycheckout/coupon-button'` icon next to Coupon Form's.

- [ ] **Step 5: Build, lint and check the storefront**
  - Run: `pnpm build:blocks`. Expected: `compiled successfully`, and `assets/dist/blocks/coupon-button/` exists.
  - Run: `composer lint -- packages/blocks/src/coupon-button/render.php packages/blocks/src/coupon-form/render.php includes/Blocks/BlockRegistry.php templates/default-layout.php`. Expected: no errors.
  - Run: `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. Expected: exit 0.
  - **Storefront, old markup:** with an active layout whose markup still has `<!-- wp:yaycheckout/coupon-form /-->` (no child), the checkout shows the default Apply button. Applying a coupon works.
  - **Unsafe value:** temporarily set the layout's `buttonBackgroundColor` to `red;}body{display:none` in the post content, then load the checkout. The Coupon Form wrapper has no `style` attribute, and the page renders. Revert the post afterwards.

- [ ] **Step 6: Check the editor and the storefront**
  - Open a layout. The Coupon Form's row shows the input and a selectable Coupon Button. List View shows it nested, it can't be removed or moved, and the inserter doesn't list it.
  - **Starting colour:** set Coupon Form > Button colors > Background to `#d63638`. The button turns red in the canvas, and on the storefront after saving.
  - **Own colour, palette:** pick a palette colour in Coupon Button > Styles > Background. It replaces the red in both places.
  - **Own colour, custom:** clear it and pick a custom `#00a32a`. It wins in both places.
  - **Other supports:** set a font size of `20px`, a border radius of `0` and padding of `16px`. All three show on the storefront `<button>`.

- [ ] **Step 7: Commit**
  Stage: `packages/blocks/src/coupon-button/block.json`, `packages/blocks/src/coupon-button/edit.tsx`, `packages/blocks/src/coupon-button/index.ts`, `packages/blocks/src/coupon-button/render.php`, `packages/blocks/src/coupon-button/style.css`, `packages/blocks/src/coupon-form/block.json`, `packages/blocks/src/coupon-form/edit.tsx`, `packages/blocks/src/coupon-form/index.ts`, `packages/blocks/src/coupon-form/render.php`, `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php`, `templates/default-layout.php`
  Message: `feat(trace): make the coupon Apply button its own styleable block`

### Task 2: Outline end to end and docs

**Files:**
- Modify: `docs/architecture.md:67` (the Coupon form paragraph)

**Interfaces:**
- Consumes: `yaycheckout/coupon-button` and its `is-style-outline` rules from Task 1, Steps 1 and 3.

- [ ] **Step 1: Document the block in `docs/architecture.md`**
  Add one sentence to the Coupon form paragraph:
  - The Apply button is the `coupon-button` child block, locked in by the template.
  - Its style supports land on the `<button>`.
  - Coupon Form's Button colors reach it as `--yaycheckout-coupon-button-*` custom properties, which apply only while the child has no colour of its own.
  - `render.php` prints a default button when the child is missing.

- [ ] **Step 2: Run the end-to-end check (acceptance criterion 9)**
  - Run: `pnpm build:blocks`. Expected: `compiled successfully`.
  - **Editor:** on a layout, select Coupon Button and choose Styles > Outline, set a font size of `20px`, save and activate.
  - **Storefront:** the Apply button has a transparent background, a 1px border and text in the primary colour (or Coupon Form's Background, if set), at 20px.
  - **Coupons:** a valid coupon updates the totals; `invalid-code` shows the inline error under the field.
  - **Disclosure:** with Coupon Form > Show field set to "In a collapsible section", the header opens and closes, and the styled button is inside.

- [ ] **Step 3: Commit**
  Stage: `docs/architecture.md`
  Message: `docs(trace): describe the coupon button block`
