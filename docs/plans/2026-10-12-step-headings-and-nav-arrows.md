# Step Headings and Navigation Arrows Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** active
**Goal:** Add a "Show step headings" switch to Steps and an arrow option to Continue and Back, sharing the arrow glyphs with Return to Cart.
**Spec:** `docs/specs/2026-10-12-step-headings-and-nav-arrows-design.md`
**Architecture:** Steps prints `data-headings="hidden"`, and CSS hides the non-open steps and visually hides the open step's header. Step's PHP and `steps/view.ts` don't change. Steps passes the value to Step through block context, only so the editor can fade the header. The arrows follow Return to Cart: an `arrow` enum per block, a glyph in an `aria-hidden` span, one PHP map (`ButtonArrow`) and one TypeScript mirror.
**Touches:**
- `packages/blocks/src/steps/` and `packages/blocks/src/step/`.
- `packages/blocks/src/step-button/`, `packages/blocks/src/back-button/` and `packages/blocks/src/return-to-cart/`.
- New `includes/Renderer/ButtonArrow.php`. Consumers: `step-button/render.php`, `back-button/render.php`, `return-to-cart/render.php`.
- New `packages/blocks/src/shared/button-arrow.ts` (shared; also the SPA's `@blocks-shared`). Consumers: `step-button/edit.tsx`, `back-button/edit.tsx`, `return-to-cart/edit.tsx`.
- `packages/blocks/src/shared/step-nav-button-edit.tsx` (shared). Consumers: `step-button/edit.tsx`, `back-button/edit.tsx`, `coupon-button/edit.tsx`.
- `docs/conventions.md`.

**Tests:** none. The change is block options and CSS, and none of it is logic the CLAUDE.md test rule lists. Each task has an exact manual check instead.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in `boot_pro()`. Everything here is Free code and must pass `scripts/check-pro-boundary.sh`, which `composer lint` runs.
- Never hand-edit `assets/dist/`. Rebuild with `pnpm build:blocks`. Edit each block's `render.php` in `packages/blocks/src/<block>/`.
- Every new string uses the `yaycheckout` text domain.
- A TypeScript copy of a PHP value carries a `Mirrors …` comment naming the PHP owner (`docs/conventions.md`), and both change in the same commit.
- An added attribute needs a default in `render.php` too, because stored layouts keep the old markup (`.claude/rules/blocks.md`).
- Merchant-typed text stays RichText on the canvas with `allowedFormats={ [] }`. Editor-only CSS goes in the block's `editor.css`, imported by `edit.tsx`, loaded through `"editorStyle": "file:./index.css"`, and scoped under `.editor-styles-wrapper`.
- Never test edits on an existing draft layout, because autosave writes them. Create a new draft for every editor check.
- Branch from `dev/edd` as `<name>/feat/step-headings-and-nav-arrows`. Commit messages follow Conventional Commits, end with CLAUDE.md's trailers, and stage files by name.

## Review focus

- **List layout with headings hidden:** completed steps vanish, so the only ways back are Back and the completed items in Progress. Both must open the step and leave it as the only visible one. Covered by Task 1 Step 6.
- **Finishing the last step:** Continue on the last step must not leave a blank Steps block. The spec says the finished last step stays visible, as in `single`. Covered by Task 1 Step 6.
- **Apply coupon shares `StepNavButtonEdit`:** its canvas must not change. Covered by Task 3 Step 6.
- **Back's two elements:** the arrow goes on the back `<button>` and the first-step cart `<a>`. When `firstStep: hide`, nothing shows on step 1. Covered by Task 3 Step 7.
- **Return to Cart refactor:** its output must stay byte-for-byte the same for `none`, `arrow` and `chevron`. Covered by Task 2 Steps 1 and 5.

---

### Task 1: Hide step headings

**Files:**
- Modify: `packages/blocks/src/steps/block.json`
- Modify: `packages/blocks/src/steps/render.php`
- Modify: `packages/blocks/src/steps/style.css` (after the `single` rules at lines 113–120)
- Modify: `packages/blocks/src/steps/edit.tsx`
- Modify: `packages/blocks/src/step/block.json`
- Modify: `packages/blocks/src/step/edit.tsx`
- Create: `packages/blocks/src/step/editor.css`

**Interfaces:**
- Produces: the attribute `showHeadings` on `yaycheckout/steps` (`boolean`, default `true`).
- Produces: the block context key `yaycheckout/showHeadings`.
- Produces: the wrapper attribute `data-headings="hidden"`, printed only when `showHeadings` is `false`.

- [ ] **Step 1: Add the attribute and context to `steps/block.json`**

Add `"showHeadings": { "type": "boolean", "default": true }` to `attributes`, and `"providesContext": { "yaycheckout/showHeadings": "showHeadings" }`.

- [ ] **Step 2: Print `data-headings="hidden"` in `steps/render.php`**

Read the value as `! isset( $attributes['showHeadings'] ) || false !== $attributes['showHeadings']`, so a missing value means shown. Add `'data-headings' => 'hidden'` to `$yaycheckout_wrapper_attrs` only when it is `false`.

- [ ] **Step 3: Add the hidden-headings rules to `steps/style.css`**

Under `.yaycheckout-steps[data-headings="hidden"]`:
- `.yaycheckout-step:not([data-state="current"])` gets `display: none`.
- Once nothing is current, keep the finished last step visible. Copy the selector at line 118 and replace `[data-layout="single"]` with `[data-headings="hidden"]`.
- `.yaycheckout-step__header` is visually hidden: `position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0`.
- `.yaycheckout-step__body` gets `margin-top: 0`.

Add a one-line comment saying the header stays for screen readers and that Back and Progress are the ways back.

- [ ] **Step 4: Add the "Show step headings" toggle in `steps/edit.tsx`**

Add `showHeadings: boolean` to `StepsAttributes`. Add a `ToggleControl` labelled `__( 'Show step headings', 'yaycheckout' )` under the Layout select in the existing Steps panel, with `checked={ showHeadings !== false }`, setting `showHeadings`. Show it for both layouts.

- [ ] **Step 5: Fade the header in the Step editor**

- `step/block.json`: add `"usesContext": [ "yaycheckout/showHeadings" ]` and `"editorStyle": "file:./index.css"`.
- `step/edit.tsx`:
  - Import `./editor.css`, and add `context: { 'yaycheckout/showHeadings'?: boolean }` to `EditProps`.
  - When the context is `false`, add the class `is-hidden-on-checkout` to `.yaycheckout-step__header`.
  - After the title's RichText, render `<span className="yaycheckout-step__hidden-note">{ __( 'Hidden on the checkout', 'yaycheckout' ) }</span>`. Place it in grid column 3, row 1, where the storefront's Edit button sits.
- `step/editor.css`, scoped under `.editor-styles-wrapper`:
  - `.yaycheckout-step__header.is-hidden-on-checkout { opacity: .5 }`.
  - `.yaycheckout-step__hidden-note`: 12px, normal weight, `#757575`.

- [ ] **Step 6: Verify**

Run `pnpm build:blocks`. Expected: the build finishes with no errors.

Run `composer lint -- packages/blocks/src/steps/render.php`. Expected: no errors.

Then check by hand:
- **Editor.** Open a new draft layout. Add Steps with three Steps titled Information, Shipping, Payment, add a Progress (segments) above it, and turn "Show step headings" off.
  - Expected: all three headers show at half opacity with "Hidden on the checkout", every step is open, and a title can still be edited.
- **Storefront, `single`.** Preview as a shopper with a product in the cart.
  - Expected: only step 1 shows, with no visible heading. In DevTools, the step-1 header is still in the accessibility tree with the name "1 Information".
  - Press Continue. Expected: only step 2 shows. Press Back. Expected: only step 1 shows.
  - Continue to step 3, then click "Information" in Progress. Expected: only step 1 shows.
  - Continue through to the last step and press Continue there. Expected: step 3 stays on screen.
- **Storefront, `list`.** Switch Steps to List and repeat the storefront walk. Expected: the same results.
- **Headings back on.** Turn the toggle on again and preview. Expected: the wrapper has no `data-headings` attribute, and headers show as today.

- [ ] **Step 7: Commit**

Stage: `packages/blocks/src/steps/block.json`, `packages/blocks/src/steps/render.php`, `packages/blocks/src/steps/style.css`, `packages/blocks/src/steps/edit.tsx`, `packages/blocks/src/step/block.json`, `packages/blocks/src/step/edit.tsx`, `packages/blocks/src/step/editor.css`
Message: `feat(blocks): let Steps hide step headings`

### Task 2: Shared arrow glyphs, used by Return to Cart

**Files:**
- Create: `includes/Renderer/ButtonArrow.php`
- Create: `packages/blocks/src/shared/button-arrow.ts`
- Modify: `packages/blocks/src/return-to-cart/render.php` (the `$yaycheckout_icons` map)
- Modify: `packages/blocks/src/return-to-cart/edit.tsx` (`ICONS`, the `Arrow` type and the select options)
- Modify: `docs/conventions.md` (the "In code" list of mirrors)

**Interfaces:**
- Produces: `YayCheckout\Renderer\ButtonArrow::glyph( string $style, string $direction ): string`.
  - `$direction` is `'back'` or `'forward'`.
  - `back`: `arrow` → `←`, `chevron` → `‹`. `forward`: `arrow` → `→`, `chevron` → `›`.
  - `none`, an unknown style or an unknown direction returns `''`.
- Produces, in `packages/blocks/src/shared/button-arrow.ts`:
  - `export type Arrow = 'none' | 'arrow' | 'chevron'`
  - `export type ArrowDirection = 'back' | 'forward'`
  - `export function arrowGlyph( style: Arrow, direction: ArrowDirection ): string`
  - `export function arrowOptions( direction: ArrowDirection ): { label: string; value: Arrow }[]`, which returns `None`, `Arrow (←)` or `Arrow (→)`, and `Chevron (‹)` or `Chevron (›)`, translated with the `yaycheckout` domain.

- [ ] **Step 1: Record Return to Cart's current HTML**

On `dev/edd` with the blocks built, preview a new draft layout as a shopper. It must have three Return to Cart blocks, with `arrow` set to `none`, `arrow` and `chevron`. In DevTools, run `[...document.querySelectorAll('.yaycheckout-return-to-cart')].map(e => e.outerHTML).join('\n')` and save the output to a scratch file.

- [ ] **Step 2: Create `includes/Renderer/ButtonArrow.php` with `glyph()` (see Interfaces)**

Use a private constant map keyed by direction, then style. Add a class docblock saying the map is mirrored by `packages/blocks/src/shared/button-arrow.ts`.

- [ ] **Step 3: Create `packages/blocks/src/shared/button-arrow.ts` (see Interfaces)**

Start it with `/** Mirrors ButtonArrow::glyph() in PHP. */`.

- [ ] **Step 4: Switch Return to Cart to the helpers**

- `return-to-cart/render.php`: replace `$yaycheckout_icons` and its lookup with `$yaycheckout_icon = \YayCheckout\Renderer\ButtonArrow::glyph( (string) $attributes['arrow'], 'back' );`.
- `return-to-cart/edit.tsx`: delete the local `Arrow` type and `ICONS`. Use `arrowGlyph( attributes.arrow, 'back' )` and `options={ arrowOptions( 'back' ) }`. Keep the comment that the values mirror block.json's enum, pointing at `ButtonArrow` as well.
- `docs/conventions.md`: add `packages/blocks/src/shared/button-arrow.ts` (`ButtonArrow::glyph()`) to the "In code" list of mirrors.

- [ ] **Step 5: Verify**

Run `pnpm build:blocks`. Expected: the build finishes with no errors.

Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. Expected: no new errors compared with `dev/edd`.

Run `composer lint -- includes/Renderer/ButtonArrow.php packages/blocks/src/return-to-cart/render.php`. Expected: no errors.

Reload the preview from Step 1 and run the same DevTools line. Expected: the output is identical to the saved file.

In the editor, Return to Cart's "Icon" select still lists None, Arrow (←), Chevron (‹), and the canvas shows the chosen glyph.

- [ ] **Step 6: Commit**

Stage: `includes/Renderer/ButtonArrow.php`, `packages/blocks/src/shared/button-arrow.ts`, `packages/blocks/src/return-to-cart/render.php`, `packages/blocks/src/return-to-cart/edit.tsx`, `docs/conventions.md`
Message: `refactor(blocks): share the button arrow glyphs`

### Task 3: Arrows on Continue and Back

**Files:**
- Modify: `packages/blocks/src/shared/step-nav-button-edit.tsx`
- Modify: `packages/blocks/src/step-button/block.json`, `render.php`, `style.css`, `edit.tsx`
- Modify: `packages/blocks/src/back-button/block.json`, `render.php`, `style.css`, `edit.tsx`

**Interfaces:**
- Consumes: `ButtonArrow::glyph()`, `arrowGlyph()` and `arrowOptions()` from Task 2.
- Produces: the attribute `arrow` on `yaycheckout/step-button` and on `yaycheckout/back-button` (`string`, enum `none` | `arrow` | `chevron`, default `none`).
- Produces: the props `icon?: string`, `iconPosition?: 'before' | 'after'` (default `'after'`) and `iconClassName?: string` on `StepNavButtonEdit`.
- Produces: the class names `yaycheckout-step-button__icon` and `yaycheckout-back-button__icon`.

- [ ] **Step 1: Add the `arrow` attribute to both block.json files**

Add `"arrow": { "type": "string", "default": "none", "enum": [ "none", "arrow", "chevron" ] }` to `step-button/block.json` and to `back-button/block.json`.

- [ ] **Step 2: Print the glyph in both renders**

In both, read the style as `isset( $attributes['arrow'] ) ? (string) $attributes['arrow'] : 'none'`.

- `step-button/render.php`: call `ButtonArrow::glyph( $style, 'forward' )`. When it isn't empty, print `<span class="yaycheckout-step-button__icon" aria-hidden="true">` + `esc_html( glyph )` + `</span>` after the label, inside the `<button>`.
- `back-button/render.php`: call `ButtonArrow::glyph( $style, 'back' )`. When it isn't empty, print `<span class="yaycheckout-back-button__icon" aria-hidden="true">` + `esc_html( glyph )` + `</span>` before the label, in both the `<button>` and the cart `<a>`.

With `none`, the HTML must match today's exactly.

- [ ] **Step 3: Lay out the icon in both stylesheets**

In `step-button/style.css` and `back-button/style.css`, change `display: block` to `display: flex; align-items: center; gap: 6px`. Keep `width: fit-content`. In `back-button/style.css`, `.yaycheckout-back-button[hidden] { display: none; }` must still come after the base rule.

- [ ] **Step 4: Let `StepNavButtonEdit` show an icon**

Add the props from this task's Interfaces.
- Without `icon`, return exactly today's RichText with the block props.
- With `icon`, render a `div` with the block props holding a RichText `span` (same RichText props as today) and `<span className={ iconClassName } aria-hidden="true">{ icon }</span>` on the `iconPosition` side.

Keep the comment explaining why the root is a `div` and not a `button`.

- [ ] **Step 5: Add the "Icon" select to both editors**

- `step-button/edit.tsx`: add a new `PanelBody` titled `__( 'Continue button', 'yaycheckout' )` with a `SelectControl` labelled `__( 'Icon', 'yaycheckout' )`, `options={ arrowOptions( 'forward' ) }`, setting `arrow`. Pass `icon={ arrowGlyph( arrow, 'forward' ) }`, `iconPosition="after"` and `iconClassName="yaycheckout-step-button__icon"`.
- `back-button/edit.tsx`: add the same `SelectControl` to the existing "Previous step button" panel, with `arrowOptions( 'back' )`. Pass `icon={ arrowGlyph( arrow, 'back' ) }`, `iconPosition="before"` and `iconClassName="yaycheckout-back-button__icon"`.

- [ ] **Step 6: Verify the editor**

Run `pnpm build:blocks`. Expected: the build finishes with no errors.

Run `composer lint -- packages/blocks/src/step-button/render.php packages/blocks/src/back-button/render.php`. Expected: no errors.

In a new draft layout:
- Continue's Icon select lists None, Arrow (→), Chevron (›), and Back's lists None, Arrow (←), Chevron (‹).
- Choosing Chevron shows `›` after Continue's label and `‹` before Back's on the canvas, and the label is still editable.
- With Icon None, both look as they do on `dev/edd`.
- An Apply coupon button inside a Coupon form looks and edits as on `dev/edd`.

- [ ] **Step 7: Verify the storefront**

Preview the layout with Continue and Back set to `chevron`. Expected:
- Step 1 shows `<a class="… yaycheckout-back-button yaycheckout-back-button--cart" …><span class="yaycheckout-back-button__icon" aria-hidden="true">‹</span>Return to cart</a>`.
- Continue ends with `<span class="yaycheckout-step-button__icon" aria-hidden="true">›</span>`, with a 6px gap.
- On step 2, Back reads "‹ Back".

Set both to `arrow`. Expected: `←` and `→`.

Clear Continue's text. Expected: "Continue ›".

Set Back's `firstStep` to Hide. Expected: nothing from Back shows on step 1.

Set both to None. Expected: no `__icon` span in either block.

- [ ] **Step 8: Commit**

Stage: `packages/blocks/src/shared/step-nav-button-edit.tsx`, `packages/blocks/src/step-button/block.json`, `packages/blocks/src/step-button/render.php`, `packages/blocks/src/step-button/style.css`, `packages/blocks/src/step-button/edit.tsx`, `packages/blocks/src/back-button/block.json`, `packages/blocks/src/back-button/render.php`, `packages/blocks/src/back-button/style.css`, `packages/blocks/src/back-button/edit.tsx`
Message: `feat(blocks): add an arrow option to Continue and Back`

### Task 4: End-to-end verification and close the plan

**Files:**
- Modify: `docs/plans/2026-10-12-step-headings-and-nav-arrows.md` (Status)

**Interfaces:**
- Consumes: everything from Tasks 1–3.

- [ ] **Step 1: Check a saved layout is unchanged (spec criterion 1)**

Preview an existing layout that has Steps, Continue and Back and was saved before this branch. Don't edit it. Expected:
- No `data-headings` attribute and no `__icon` span.
- The headers show as on `dev/edd`.
- The buttons look the same, apart from now being flex boxes.

- [ ] **Step 2: Run the spec's end-to-end check (criterion 11)**

Run `pnpm build:blocks`. Then, in a new draft layout:
1. Add Steps with three Steps titled Information, Shipping, Payment, and add a Progress (segments).
2. Put a Continue (`arrow: chevron`) and a Back (`arrow: chevron`) in each of the first two steps, and a Back plus Place Order in the third.
3. Turn "Show step headings" off.

Expected in the editor: every step header is faded with "Hidden on the checkout".

Preview as a shopper with a product in the cart. Expected:
- Only step 1 shows, with no heading, "‹ Return to cart" and "Continue ›".
- Fill step 1 and press Continue: only step 2 shows.
- Press Back: step 1 shows again.
- Continue twice, then click "Information" in Progress: step 1 shows.

Switch Steps to List and repeat the walk. Expected: the same behaviour.

Place an order with a test gateway. Expected: the order succeeds.

- [ ] **Step 3: Run the full lint**

Run `composer lint`. Expected: no errors, the same result as `dev/edd`.

- [ ] **Step 4: Close the plan**

Set this plan's `**Status:**` to `done YYYY-MM-DD`.

Stage: `docs/plans/2026-10-12-step-headings-and-nav-arrows.md`
Message: `docs(plans): close the step headings and nav arrows plan`
