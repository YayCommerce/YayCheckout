# Progress Styles Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec: the requirements below are the brief.

**Status:** active
**Goal:** the Progress block offers five looks, picked in its sidebar: Numbered circles (today's), Dots, Segmented bar, Thin bar with "Step N of M" and Text only ("Step N of M" alone; added 2026-10-07 at the user's request). All four read the same Steps titles and the same step state.
**Spec:** none, planned from the request and the template review below
**Depends on:** [2026-10-07-step-controls-outside-checkout.md](2026-10-07-step-controls-outside-checkout.md). It edits the same `progress/` files (`block.json`, `edit.tsx`, `render.php`, `view.ts`, `style.css`) and `CheckoutProgress.php`. Branch from `dev/edd` after its Task 2 has merged; if it has not, say so and stop.
**Architecture:**
- One block, one attribute: `variant` on `yaycheckout/progress`, enum `circles | dots | segments | bar | text`, default `circles`. A layout saved before this change has no `variant`, so it renders as `circles`, as it does now.
- Same data, different paint: every variant keeps the `<ol>` of `<li data-state data-step>` items with the jump `<button>`, so `view.ts` painting, jump-to-reopen, `aria-current` and the step titles are shared. The variant changes CSS only, except `bar`, which adds a summary (track, fill, "Step N of M").
- Wrapper change: today the block root is the `<ol>`. It becomes `<div class="yaycheckout-progress is-variant-<variant>">` holding `<ol class="yaycheckout-progress__list">`, because `bar` needs a sibling for the summary. CSS selectors move from `ol.yaycheckout-progress` to `.yaycheckout-progress__list`. A merchant's custom CSS aimed at `ol.yaycheckout-progress` would stop matching: accepted, noted in Review focus.
- `text` is `bar` without the track: the same summary and hidden list, and `view.ts` rewrites the same current span. Its sidebar label is "Text only".
- `bar` hides the list on the storefront (visually hidden, still read by screen readers) and shows the count instead. In the editor Progress titles are read-only for every style (decided 2026-10-07): they are edited on the Step blocks, and `bar` and `text` hide the list in the editor too.
- Count text is built in PHP with `sprintf( __( 'Step %1$s of %2$s' ) )`, the current number inside `<span class="yaycheckout-progress__current">`. `view.ts` rewrites only that span and the fill, so there is one translated string and no copy in JavaScript.
- Fill: custom property `--yaycheckout-progress-value` (0 to 1) on the block root, equal to `current / total`. PHP prints it for step 1; `view.ts` updates it on every `yaycheckout:step-change`.
- Colours: `var(--yaycheckout-primary)` for current and complete, the greys already in `style.css` for locked. No new design token.

**Template review** (the HTML the user supplied, 11 templates): step-position indicators come in 6 looks.

| Look | Templates | Steps-driven | In this plan |
|---|---|---|---|
| Numbered circles on a connecting line | none as a header; today's block | yes | `circles` (exists) |
| Dots and labels joined by hairlines | 09 Soft steps | yes | `dots` |
| Segmented bar with numbered labels | 02 Multi-step wizard | yes | `segments` |
| Thin full-width bar + "Step N of M" | 06 Minimal steps (04 has the text) | yes | `bar` |
| Breadcrumb "Cart › Checkout › Order received" | 01 Classic | no, page level | out |
| Numbered or connected accordion headers | 04 Accordion, 11 Material stepper | part of Steps | out |

03, 05 and 12 have none. 07 and 08 only number their section headings, with no step state.

**Touches:**
- Blocks: `packages/blocks/src/progress/{block.json,edit.tsx,render.php,style.css,view.ts,index.ts}`, `packages/blocks/src/progress/editor.css` (new)
- PHP: `includes/Renderer/CheckoutProgress.php`
- Docs: none. `docs/architecture.md` names Progress only in the block order.
- Not touched: `includes/Renderer/CheckoutColour.php` (the root class `wp-block-yaycheckout-progress` is unchanged), `templates/`, `LayoutTemplateCatalogue.php`. No shipped template adopts a new variant here.

**Tests:** none. None of the logic CLAUDE.md's Tests rule names changes. Each task gets a manual check.

## Requirements

- **Goal:** a merchant picks how checkout progress looks without changing the Steps, and every look tracks the open step.
- **Out of scope:** the breadcrumb, the accordion/vertical stepper looks, per-variant colour or size settings, restyling shipped templates, the Free/Pro split (all four ship in Free, no REST route).
- **Acceptance criteria:**
  1. The Progress sidebar has a "Style" select: Numbered circles, Dots, Segmented bar, Progress bar. Default Numbered circles.
  2. A layout saved before this change renders exactly as before on the storefront and in the editor.
  3. On the storefront each style marks the open step current, earlier steps complete and later ones locked as the shopper moves, and a click on a complete step reopens it, in all four.
  4. `bar` shows a track filled to `current / total` and "Step N of M" that follow Next, Previous and the Progress jump. Its list is not visible but is still in the DOM.
  5. In the editor each style shows its look with step 1 current; `bar` and `text` show the count, not the list. Progress titles are read-only there.
  6. Two Progress blocks with different styles on one page both paint correctly.
  7. A layout with no Steps block: the storefront prints nothing (as today), the editor shows the placeholder, in every style.
  8. End to end: a layout with 3 steps and Progress set to each style in turn, a guest goes forward and back and places an order, and the indicator tracks every move.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/progress/` and run `pnpm build:blocks`.
- Run commands from the repo root on Node 22 (`nvm use`). Use pnpm only.
- Branch from `dev/edd` as `<name>/feat/progress-styles`. Commit messages are Conventional Commits with the `Co-Authored-By` trailer CLAUDE.md names. Land through a PR into `dev/edd` (squash merge).

## Base branch state

- `pnpm build:blocks` passes on `dev/edd`; it is the only check on the blocks' TypeScript.
- `composer lint -- includes/Renderer/CheckoutProgress.php` passes.

## Review focus

- **Saved layout without `variant`, and custom CSS on `ol.yaycheckout-progress`.** The first must look identical (Task 1, Step 5). The second breaks by design; mention it in the PR description.
- **`bar` with the list hidden.** Hidden must mean visually hidden (clip), not `display: none`, or screen readers lose the steps. Task 3, Step 4 checks it.
- **A layout with a single step.** `current / total` is 1, "Step 1 of 1". It must not divide by zero when there are no items: PHP returns `''` before it gets there. Task 3, Step 5.
- **Progress outside Checkout** (the active plan's feature) in each style. Task 4, Step 3.
- **Two Progress blocks, mixed styles.** `view.ts` must update every root's summary, not the first. Task 3, Step 5.
- **A theme that styles `ol`, `li` or `button`.** The `button.yaycheckout-progress__jump` reset in `style.css` must keep applying in all variants. Task 2, Step 3.

---

## Interfaces

Block attribute (`block.json`):

```json
"variant": { "type": "string", "default": "circles", "enum": [ "circles", "dots", "segments", "bar", "text" ] }
```

PHP (`includes/Renderer/CheckoutProgress.php`):

```php
private const VARIANTS = [ 'circles', 'dots', 'segments', 'bar', 'text' ]; // Mirrors progress/block.json "variant" enum.
public static function markup( array $attributes = [] ): string;   // $attributes from render.php
private static function variant( array $attributes ): string;      // narrows to VARIANTS, else 'circles'
```

Storefront markup, all variants:

```html
<div class="wp-block-yaycheckout-progress yaycheckout-progress is-variant-{variant}" [style="--yaycheckout-progress-value:0.333" bar only]>
  <!-- bar only -->
  <div class="yaycheckout-progress__summary">
    <div class="yaycheckout-progress__track"><span class="yaycheckout-progress__fill"></span></div>
    <p class="yaycheckout-progress__count">Step <span class="yaycheckout-progress__current">1</span> of 3</p>
  </div>
  <ol class="yaycheckout-progress__list"><li class="yaycheckout-progress__item" data-state data-step>…unchanged…</li></ol>
</div>
```

## Task 1: `variant` attribute, wrapper and sidebar, `circles` only

**Files:** `block.json`, `render.php`, `CheckoutProgress.php`, `edit.tsx`, `style.css`

- [ ] **Step 1:** `block.json`: add the `variant` attribute above. Update `description` to mention the style choice.
- [ ] **Step 2:** `render.php`: call `CheckoutProgress::markup( $attributes )` and fix the docblock (`$attributes` is now used).
- [ ] **Step 3:** `CheckoutProgress.php`: add `VARIANTS`, `variant()`, and `markup( array $attributes = [] )` building the wrapper `<div>` with `is-variant-<variant>` through `get_block_wrapper_attributes( [ 'class' => 'yaycheckout-progress is-variant-' . $variant ] )` around `<ol class="yaycheckout-progress__list">`. Item markup is unchanged. Return `''` for no titles, as now.
- [ ] **Step 4:** `edit.tsx`: add `attributes`/`setAttributes` props, an `InspectorControls` `PanelBody` "Progress" with `SelectControl` "Style" (options Numbered circles, Dots, Segmented bar, Progress bar), and render the same wrapper `<div>` + `<ol className="yaycheckout-progress__list">` as PHP. Add a `Mirrors CheckoutProgress::markup()` comment. The no-Steps placeholder stays.
- [ ] **Step 5:** `style.css`: change `ol.yaycheckout-progress` to `.yaycheckout-progress__list` and keep every other rule. Verify:
  - `pnpm build:blocks` passes.
  - `composer lint -- includes/Renderer/CheckoutProgress.php` passes.
  - Open a layout saved before the change (Progress with no `variant`) on the storefront and in the editor: it looks as it did, one row, steps connected by lines, current step bold.
  - With a 3-step layout, Next paints step 1 complete (check mark) and step 2 current; clicking the complete circle reopens step 1.
- [ ] **Step 6:** commit `packages/blocks/src/progress/{block.json,render.php,edit.tsx,style.css}` and `includes/Renderer/CheckoutProgress.php`: `feat(blocks): add a style attribute to Progress`.

## Task 2: `dots` and `segments`

**Files:** `style.css`, `edit.tsx`

- [ ] **Step 1:** `edit.tsx`: no markup change needed; confirm both options already appear in the select.
- [ ] **Step 2:** `style.css`, `.is-variant-dots`: items in one row, the marker an 8px dot (current: `--yaycheckout-primary`; complete: primary; locked: grey), the title beside it on one line (current dark and bold, locked grey), a 1px grey hairline between items that turns primary after a complete item. Reference: template 09.
- [ ] **Step 3:** `style.css`, `.is-variant-segments`: each item gets a 3px bar across its top (primary when current or complete, grey when locked), under it the number marker (small, 20px) and the title on one line. Reference: template 02. Check that `button.yaycheckout-progress__jump` still carries the theme reset in both variants (no new `button` rules that lose to the theme).
- [ ] **Step 4:** Verify with a 3-step layout, setting Style in the sidebar to each:
  - `pnpm build:blocks` passes.
  - Dots and Segmented bar match the references, step state follows Next and Previous, and the check on a complete step reopens it.
  - Narrow the viewport to 375px: titles wrap or truncate without overflowing the page.
- [ ] **Step 5:** commit `packages/blocks/src/progress/{style.css,edit.tsx}`: `feat(blocks): add the Dots and Segmented bar Progress styles`.

## Task 3: `bar` with "Step N of M"

**Files:** `CheckoutProgress.php`, `edit.tsx`, `style.css`, `editor.css` (new), `view.ts`

- [ ] **Step 1:** `CheckoutProgress.php`: for `bar`, add the `__summary` block from the Interfaces section before the `<ol>`, the count built with `sprintf( esc_html__( 'Step %1$s of %2$s', 'yaycheckout' ), '<span class="yaycheckout-progress__current">1</span>', (string) count( $titles ) )`, and `--yaycheckout-progress-value:` set to `round( 1 / count( $titles ), 3 )` through the wrapper's `style` attribute. Add a `/* translators: */` comment for the placeholders.
- [ ] **Step 2:** `edit.tsx`: for `bar`, render the same summary (current 1, fill `1 / stepIds.length`) and keep the list hidden, as on the storefront. Titles are read-only in every style. Use `sprintf` and `createInterpolateElement` or the equivalent already used in `packages/blocks`; the string must be the same one PHP uses.
- [ ] **Step 3:** `style.css`, `.is-variant-bar`: `__track` 4px, grey, rounded; `__fill` `width: calc( var( --yaycheckout-progress-value, 0 ) * 100% )`, primary, with a width transition that is off under `prefers-reduced-motion: reduce`; `__count` small and grey above or below the track. `.is-variant-bar .yaycheckout-progress__list` is visually hidden (`position:absolute; width:1px; height:1px; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap`).
- [ ] **Step 4:** `editor.css` holds only the segments grid (the editor's items have no button): import it from `edit.tsx` and load it with `"editorStyle": "file:./index.css"` in `block.json`, as the coupon form does.
- [ ] **Step 5:** `view.ts`: after painting items in `sync()`, for each `.yaycheckout-progress` root call a new `paintSummary( root: HTMLElement ): void`: find `[data-state="current"]`, read its `data-step` as `n`, the item count as `total`, set `--yaycheckout-progress-value` to `n / total` and `.yaycheckout-progress__current` text to `n`. Return without touching anything when the root has no summary or no current item.
- [ ] **Step 6:** Verify:
  - `pnpm build:blocks` passes and `composer lint -- includes/Renderer/CheckoutProgress.php` passes.
  - Storefront, 3 steps, Style "Progress bar": "Step 1 of 3", fill at a third. Next: "Step 2 of 3", two thirds. Previous: back to 1. The jump from a complete step is unreachable (list hidden), and the list is still present: `document.querySelectorAll('.is-variant-bar .yaycheckout-progress__item').length` is 3 in the console.
  - A 1-step layout shows "Step 1 of 1" with a full track; a layout with no Steps prints nothing and logs no console error.
  - Two Progress blocks (one `bar` in the header Group outside Checkout, one `dots` inside Checkout): both paint on Next.
  - Editor: `bar` shows the track and the count, with no list; titles are edited on the Step blocks only.
- [ ] **Step 7:** commit the files above: `feat(blocks): add the Progress bar style with a step count`.

## Task 4: Whole-feature check and PR

- [ ] **Step 1:** `pnpm build:blocks` from a clean state (`assets/dist/` is rebuilt, not edited): passes.
- [ ] **Step 2:** `composer lint`: no new failures; the base's result is the one CLAUDE.md and `docs/known-issues.md` state.
- [ ] **Step 3:** Acceptance 8: with a 3-step layout, set Progress to each of the four styles in turn, both inside Checkout and in the header Group outside it, and walk guest checkout forward, back, forward, and place an order. The indicator matches the open step at every move and nothing is logged in the console.
- [ ] **Step 4:** `git status`, then stage by name only. Open the PR into `dev/edd` when asked. The PR description notes the `ol.yaycheckout-progress` selector change and ends with the generated-by line CLAUDE.md names.
