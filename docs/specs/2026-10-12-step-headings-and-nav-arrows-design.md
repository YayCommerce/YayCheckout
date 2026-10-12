# Step Headings and Navigation Arrows Design

**Status:** accepted 2026-10-12
**Related:** `~/Downloads/WooCommerce Checkout Templates_v1.1.html` (template 02, "Multi-step wizard"); gaps 2 and 8 of the template 02 review

## Goal

A merchant can hide the numbered heading inside each checkout step, and add an arrow to the Continue and Back buttons. Template 02 needs both: its step card has no heading, because the Progress bar already names the step, and its buttons read "‹ Return to information" and "Continue to payment ›".

## Context

- `packages/blocks/src/steps/`: `yaycheckout/steps`, attribute `layout` (`list` | `single`). `render.php` prints the wrapper with `data-layout`. `style.css` hides non-current steps in `single` and keeps the finished last step on screen once nothing is current. `edit.tsx` has a Steps panel with the Layout select. `view.ts` runs Continue/Back, `complete()`, `reopen()` and the `yaycheckout:step-reopen` event.
- `packages/blocks/src/step/`: `yaycheckout/step`, attribute `title`. `render.php` prints `.yaycheckout-step__header` (index, title, summary, Edit button) and `.yaycheckout-step__body`. In `list` layout, the header is all a locked or completed step shows, and clicking a completed step's header reopens it.
- `includes/Renderer/CheckoutProgress.php`: Progress takes its labels from the Step titles (`titles_in()`). `progress/view.ts` lets a shopper click a completed step, which sends `yaycheckout:step-reopen`.
- `packages/blocks/src/step-button/` (Continue) and `packages/blocks/src/back-button/` (Back): attribute `buttonText`. Back also has `firstStep` (`cart` | `hide`) and `cartText`, and prints a `<button>` plus, on the first step, an `<a>` to the cart. Both blocks' `style.css` use `display: block; width: fit-content`. Both blocks' editors use `packages/blocks/src/shared/step-nav-button-edit.tsx`, which Apply coupon (`coupon-button`) uses too.
- `packages/blocks/src/return-to-cart/`: attribute `arrow` (`none` | `arrow` | `chevron`, default `chevron`). `render.php` and `edit.tsx` each hold a glyph map (`arrow` → `←`, `chevron` → `‹`), and the icon prints as `<span class="yaycheckout-return-to-cart__icon" aria-hidden="true">`. The editor control is a SelectControl labelled "Icon" with "None", "Arrow (←)", "Chevron (‹)".
- `docs/conventions.md`: a TypeScript copy of a PHP value names its PHP owner in a `Mirrors …` comment.

## Scope

**In:**
- A `showHeadings` setting on Steps that hides step headings on the storefront, in both layouts.
- An `arrow` setting on Continue and on Back.
- One PHP glyph helper and one TypeScript mirror, used by Continue, Back and Return to Cart.

**Out:**
- A per-step heading setting.
- Changing the step card's default border, radius or padding. A template sets those through block supports.
- Any change to Progress.
- SVG icons.
- An arrow on Place Order or Apply coupon.
- Mirroring the glyphs in RTL.
- Changing Return to Cart's default (`chevron`) or its output.
- Template 02's markup itself.

## Acceptance criteria

1. Given a layout saved before this change (no `showHeadings`, no `arrow` on Continue or Back), when the checkout renders, then its Steps, Continue and Back HTML is the same as before: no `data-headings` attribute and no `__icon` span. Only the two buttons' CSS `display` changes, from `block` to `flex`.
2. Given Steps with `showHeadings: false` and `layout: list` or `single`, when a shopper opens the checkout, then only the open step is visible: locked and completed steps take no space. The open step's `.yaycheckout-step__header` is not visible but stays in the accessibility tree, so a screen reader reads its index and title.
3. Given the setup in 2, when the shopper clicks Back, or clicks a completed step in Progress, then that step opens and is the only one visible.
4. Given the setup in 2, when the shopper presses Continue on the last step, then that step stays on screen, as `single` does today.
5. Given Steps with `showHeadings: false`, when the merchant views the layout in the editor, then every step is still open, its header shows at opacity 0.5 with the note "Hidden on the checkout" after the title, and the title can still be edited in place. The Steps sidebar shows a "Show step headings" toggle under Layout, for both layouts.
6. Given Continue with `arrow: chevron` (or `arrow`), when it renders, then it prints `<span class="yaycheckout-step-button__icon" aria-hidden="true">›</span>` (or `→`) after the label, with a 6px gap.
7. Given Back with `arrow: chevron` (or `arrow`), when it renders, then both its `<button>` and its first-step cart `<a>` print `<span class="yaycheckout-back-button__icon" aria-hidden="true">‹</span>` (or `←`) before the label, with a 6px gap.
8. Given Continue or Back with an empty `buttonText`, when it renders with an arrow, then the glyph sits next to the fallback label ("Continue", "Back", "Return to cart").
9. In the editor, Continue and Back each have an "Icon" select with None, Arrow, Chevron. Continue's options read "Arrow (→)" and "Chevron (›)", and Back's read "Arrow (←)" and "Chevron (‹)". The canvas shows the chosen glyph beside the editable label. Apply coupon's canvas is unchanged.
10. Return to Cart's storefront HTML and editor canvas are unchanged for every `arrow` value.
11. End-to-end verification:
    - Build the blocks with `pnpm build:blocks`.
    - In a new draft layout (never an existing draft: autosave writes edits), add Steps with three Steps titled Information, Shipping, Payment. Add a Progress (segments). Put a Continue with `arrow: chevron` and a Back with `arrow: chevron` in each of the first two steps, and a Back plus Place Order in the third. Turn "Show step headings" off.
    - In the editor, every step header is faded with "Hidden on the checkout".
    - Preview the layout as a shopper with a product in the cart: only step 1 shows, with no heading, "‹ Return to cart" and "Continue ›". Fill step 1 and press Continue: only step 2 shows. Press Back: step 1 shows again. Continue twice, then click "Information" in Progress: step 1 shows.
    - Switch Steps to List and repeat the walk: the behaviour is the same.
    - Place an order with a test gateway, and it succeeds.

## Constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in `boot_pro()`. Everything here is Free code and must pass `scripts/check-pro-boundary.sh`.
- Never hand-edit `assets/dist/`. Rebuild with `pnpm build:blocks`. Edit each block's `render.php` in `packages/blocks/src/<block>/`.
- Every new string uses the `yaycheckout` text domain.
- A TypeScript copy of a PHP value carries a `Mirrors …` comment naming the PHP owner (`docs/conventions.md`), and both change in the same commit.
- No test is added. Verify with criterion 11.

## Approach

**Chosen:** a data attribute on Steps with CSS for the headings, and a shared text-glyph helper for the arrows.

**Architecture:** Steps owns the heading switch. When it is off, the wrapper carries `data-headings="hidden"`, and CSS does the rest: it hides every non-current step in either layout and visually hides the open step's header. Step's PHP and the step script don't change, and reopening keeps working through `reopen()`. The editor gets the value through block context only to fade the header. The arrows follow Return to Cart's pattern: an `arrow` enum per block, a glyph printed in an `aria-hidden` span, and one PHP map for all three blocks with one TypeScript mirror.

**Components:**
- `packages/blocks/src/steps/block.json`: attribute `showHeadings` (`boolean`, default `true`). `providesContext: { "yaycheckout/showHeadings": "showHeadings" }`.
- `packages/blocks/src/steps/render.php`: adds `data-headings="hidden"` to the wrapper attributes when `showHeadings` is `false`.
- `packages/blocks/src/steps/style.css`, under `.yaycheckout-steps[data-headings="hidden"]`:
  - `.yaycheckout-step:not([data-state="current"])` gets `display: none`.
  - The finished last step stays visible once nothing is current. Same rule as `single`, keyed on `data-headings`.
  - `.yaycheckout-step__header` is visually hidden: `position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0`.
  - `.yaycheckout-step__body` gets `margin-top: 0`.
- `packages/blocks/src/steps/edit.tsx`: a ToggleControl "Show step headings" under Layout in the Steps panel. It sets `showHeadings`.
- `packages/blocks/src/step/block.json`: `usesContext: [ "yaycheckout/showHeadings" ]`.
- `packages/blocks/src/step/edit.tsx`: when the context is `false`, the header gets class `is-hidden-on-checkout`, and a `<span className="yaycheckout-step__hidden-note">` with "Hidden on the checkout" follows the title.
- `packages/blocks/src/step/editor.css`, plus `editorStyle` in `step/block.json`: `.is-hidden-on-checkout { opacity: .5 }` and the note's style (12px, muted, normal weight).
- `includes/Renderer/ButtonArrow.php`: `ButtonArrow::glyph( string $style, string $direction ): string`. The map is `back` → `arrow: ←`, `chevron: ‹`, and `forward` → `arrow: →`, `chevron: ›`. `none`, an unknown style or an unknown direction returns `''`.
- `packages/blocks/src/shared/button-arrow.ts`: `/** Mirrors ButtonArrow::glyph() in PHP. */`. Exports the `Arrow` type, `arrowGlyph( style, direction )`, and an `arrowOptions( direction )` helper that returns the three SelectControl options with their glyph labels.
- `packages/blocks/src/step-button/block.json` and `packages/blocks/src/back-button/block.json`: attribute `arrow` (`string`, enum `none` | `arrow` | `chevron`, default `none`).
- `packages/blocks/src/step-button/render.php`: prints the label, then the `forward` glyph in `<span class="yaycheckout-step-button__icon" aria-hidden="true">` when it is not empty.
- `packages/blocks/src/back-button/render.php`: prints the `back` glyph in `<span class="yaycheckout-back-button__icon" aria-hidden="true">` before the label, in both the `<button>` and the cart `<a>`.
- `packages/blocks/src/step-button/style.css` and `packages/blocks/src/back-button/style.css`: `display: flex; align-items: center; gap: 6px`. Keep `width: fit-content`. `.yaycheckout-back-button[hidden]` keeps `display: none`.
- `packages/blocks/src/shared/step-nav-button-edit.tsx`: optional props `icon?: string` and `iconPosition?: 'before' | 'after'`. With an icon, the RichText and an `aria-hidden` icon span render inside the block wrapper. Without one, the output is exactly today's. Apply coupon passes neither.
- `packages/blocks/src/step-button/edit.tsx` and `packages/blocks/src/back-button/edit.tsx`: an "Icon" SelectControl from `arrowOptions()`, and the glyph passed to `StepNavButtonEdit`. Back's control goes in its existing "Previous step button" panel. Continue gets a new panel, "Continue button".
- `packages/blocks/src/return-to-cart/render.php`: replaces its local map with `ButtonArrow::glyph( $attributes['arrow'], 'back' )`.
- `packages/blocks/src/return-to-cart/edit.tsx`: replaces its local `ICONS` and options with `arrowGlyph()` and `arrowOptions( 'back' )`.

**Data and storage:** block attributes only, saved in the layout's `post_content`: `showHeadings` on `yaycheckout/steps`, and `arrow` on `yaycheckout/step-button` and `yaycheckout/back-button`. The defaults (`true`, `none`) keep saved layouts as they are, so there is no migration. Uninstall is unchanged.

**Integration points:**
- Block context key `yaycheckout/showHeadings`, which is new and editor-only in effect.
- New PHP class `YayCheckout\Renderer\ButtonArrow`, used by three block renders.
- No hooks, filters or REST changes.
- `yaycheckout:step-reopen` and `reopen()` are reused unchanged.

**UI:**
- Steps sidebar: a "Show step headings" toggle.
- Step canvas: the faded header and its note.
- Continue and Back sidebars: an "Icon" select.
- Storefront: as in the acceptance criteria.

**Edge cases and failure modes:**
- A layout with two Steps blocks: each wrapper carries its own attribute, so the CSS applies per Steps block.
- Headings hidden and Progress missing: the shopper still moves with Continue and Back, but nothing names the open step on screen. That is the merchant's choice, and the editor note makes it visible.
- Headings hidden in `list` layout: a completed step's summary and Edit are hidden with the step. Back and Progress are the ways back (criterion 3).
- Back is hidden on the first step when `firstStep: hide`: its icon is inside the hidden button, so nothing shows.
- An `arrow` value outside the enum: core swaps it for the default (`none`), and `glyph()` returns `''` for anything unknown anyway.
- RTL: the glyphs keep their direction, the same as Return to Cart today.
- A classic theme that styles `button` or `a`: the existing `:is(:hover, :focus)` resets stay, and the icon inherits the colour.

**Decision record:** None. This adds block options inside the existing Steps and button blocks and reverses no accepted record.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Heading toggle on each Step | The merchant would flip it on every step. You chose one switch on Steps. |
| Heading toggle that only works in `single` | You chose both layouts, with only the open step showing in `list`. |
| In `list`, keep a summary row and Edit for completed steps | You chose to show nothing for completed steps, with Back and Progress as the ways back. |
| Steps passes the setting to Step through context, and Step's PHP leaves the header out with an `aria-label` | It touches two renders and still needs the visibility CSS. The data attribute keeps Step's PHP unchanged and the title readable by screen readers. |
| Edit the hidden step title in the Step sidebar | You chose to keep it on the canvas, faded. |
| Arrow defaults to `chevron`, like Return to Cart | Every existing layout's buttons would change. You chose `none`. |
| Inline SVG icons, with or without converting Return to Cart | They wouldn't match Return to Cart's text glyphs, or they'd widen the change to a block that already works. |

## Rollout

Unreleased plugin. Saved layouts keep their headings and get no arrows. Template 02's markup, when it is written, sets `showHeadings: false` and `arrow: chevron`.

## Open questions

None.
