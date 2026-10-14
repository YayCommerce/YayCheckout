# Collapse animation and Step title in settings

**Status:** active
**Goal:** Collapsible panels on the storefront open and close with an animation the layout's Design picks, and a Step's title is edited from its settings sidebar while its header hides on the canvas when Steps hides headings.
**Tests:** none. CLAUDE.md's test rule doesn't cover this (no publish, assignment, required-field or import logic); every task ends in an exact manual check.

> Two unrelated changes: ship Task 1 as its own PR (`trace/feat/step-title-sidebar`) and Tasks 2–5 as a second (`trace/feat/collapse-animation`), both from `dev/edd`.

## Requirements

1. These animate open and close on the storefront: a Field / Extra field / Order note / Coupon form whose Show field is "In a collapsible section" (Disclosure `collapsible` mode only; link and checkbox modes stay instant), the collapsible Order Summary's details, and a Step body in the Steps accordion and timeline layouts.
2. A new Layout design setting picks the animation: **Slide** (height and opacity, the default), **Fade** (opacity only) or **None**. `prefers-reduced-motion: reduce` always means None.
3. Only shopper-driven changes animate: clicks on a toggle, Continue / Edit / Back / Go to step. A panel opened on load, by a filled value or by a country locale opens instantly.
4. When Steps' "Show step headings" is off, the editor canvas shows no Step header at all (it is faded today).
5. A Step's title is editable in its settings sidebar as rich text with the same formats as the canvas (`TEXT_FORMATS_WITH_LIVE_VALUE`), as well as on the canvas while headings show. Both edit the same `title` attribute.

Constraints (CLAUDE.md): product PHP runs on PHP 7.4; never edit `assets/dist/`; a design token is one `LayoutDesign::schema()` entry plus `DesignStyles::vars()` plus its copy in `packages/blocks/src/shared/design-css.ts` (docs/conventions.md); `packages/blocks/src/shared/` is also the SPA's `@blocks-shared`, so type-check the SPA after touching it.

## Files

| File | Responsibility |
|---|---|
| `packages/blocks/src/step/edit.tsx` | Step: no header when headings are hidden; sidebar title |
| `packages/blocks/src/step/editor.css` | Drop the faded-header and hidden-note rules |
| `.claude/rules/blocks.md` | Note the Step title is the one display text also edited in the sidebar |
| `includes/Models/LayoutDesign.php` | New `motion` section, token `collapse` |
| `includes/Renderer/DesignStyles.php` | Print `--yaycheckout-collapse-animation` |
| `packages/blocks/src/shared/design-css.ts` | Mirror of the above |
| `packages/blocks/src/checkout/layout-design.tsx` | Motion section in the Layout design sidebar |
| `packages/blocks/src/shared/collapse.ts` (new) | The one animation helper every collapsible calls |
| `packages/blocks/src/shared/disclosure-view.ts` | Animate a collapsible field's click |
| `packages/blocks/src/order-summary/view.ts` | Animate the details in `flip()` |
| `packages/blocks/src/steps/view.ts` | Animate the body in `setState()`; scroll after it settles |
| `docs/architecture.md` | Line 45's section list gains motion; storefront note on the collapse variable |

## Interfaces

```ts
// packages/blocks/src/shared/collapse.ts
export type CollapseAnimation = 'slide' | 'fade' | 'none';

/** The layout's animation for `panel`: --yaycheckout-collapse-animation ('' reads as 'slide'), 'none' under reduced motion. */
export function collapseAnimation( panel: HTMLElement ): CollapseAnimation;

/**
 * Runs `apply` (which shows or hides `panel` by `hidden` or a state attribute), animating around it.
 * Resolves when the animation ends, at once when there is none.
 */
export function collapse( panel: HTMLElement, open: boolean, apply: () => void ): Promise< void >;
```

`collapse()` algorithm (the signature doesn't fix it):
1. Cancel any running animation on `panel` (its cleanup runs on cancel too). Mode = `collapseAnimation( panel )`.
2. Closing only: read `getComputedStyle( panel ).display` before `apply()`.
3. `apply()`. If mode is `none`, or `panel.parentElement` has no client rects (a hidden step in one-at-a-time or headings-hidden Steps), resolve.
4. Closing: set inline `display` to the value from step 2 so it stays drawn while it shrinks. Both: inline `overflow: hidden`.
5. `panel.animate()` for 200ms `ease`. Slide: `height`, `margin-block-start`, `padding-block` from their computed values to 0 (closing) or 0 to computed (opening), plus opacity 1↔0. Fade: opacity only.
6. On finish or cancel, remove the inline `display` and `overflow`, then resolve.

Design token: `LayoutDesign::schema()['motion']['collapse'] = [ 'type' => 'enum', 'default' => '', 'enum' => [ '', 'fade', 'none' ] ]`. `''` is Slide. `DesignStyles::vars()` prints `collapse-animation` => the token only when it is not `''`, so the property is `--yaycheckout-collapse-animation`.

## Task 1: Step title in the sidebar, no header when headings hide (PR 1)

1. `packages/blocks/src/step/edit.tsx`: when `context['yaycheckout/showHeadings'] === false`, render no `.yaycheckout-step__header` (drop the `is-hidden-on-checkout` class and the "Hidden on the checkout" note).
2. Same file: add `<InspectorControls><PanelBody title={ __( 'Settings', 'yaycheckout' ) }>` holding a `BaseControl` labelled "Title" around a `RichText` (`tagName="div"`, `value={ title }`, same `onChange`, `placeholder={ stepTitle( '' ) }`, `allowedFormats={ TEXT_FORMATS_WITH_LIVE_VALUE }`, no `withoutInteractiveFormatting`). The control always shows; its help text shows only while headings hide: "Step headings are hidden. Steps Progress still shows this title." Style the box as a `components-text-control__input` look via a class in `step/editor.css` (sidebar styles sit outside `.editor-styles-wrapper`, so scope it under `.block-editor-block-inspector`).
3. `packages/blocks/src/step/editor.css`: delete the `.is-hidden-on-checkout` and `.yaycheckout-step__hidden-note` rules.
4. `.claude/rules/blocks.md`, "Text a merchant types": add that the Step title is also edited in the Step's sidebar, so it stays editable while Steps hides headings.
5. Verify: `pnpm build:blocks` succeeds. In wp-admin open a **new** layout (memory: never test on an existing draft, autosave writes it) from a Steps template (Accordion one-page), select a Step: the sidebar Title shows the title; type there, the canvas header updates; bold a word and add a Live value there, both survive switching back to the canvas. Turn Steps' "Show step headings" off: no step header on the canvas, the sidebar title still edits, and Steps Progress shows the new title. Save, preview: storefront unchanged except the title.
6. Commit `packages/blocks/src/step/edit.tsx`, `packages/blocks/src/step/editor.css`, `.claude/rules/blocks.md`: `feat(step): edit the step title in the sidebar and hide hidden headings in the editor`.

## Task 2: Motion design token (PR 2)

1. `includes/Models/LayoutDesign.php`: add the `motion` section per Interfaces, after `help_text`. `from_meta()` and `rest_schema()` pick it up from `schema()`.
2. `includes/Renderer/DesignStyles.php` `vars()`: print `collapse-animation` per Interfaces. Update the class docblock only if it lists tokens.
3. `packages/blocks/src/shared/design-css.ts`: the same line, in the same order.
4. `packages/blocks/src/checkout/layout-design.tsx`: `motion: SectionTokens` in `LayoutDesignMeta`, `'motion'` in `Section`, `SECTIONS.motion = __( 'Motion', 'yaycheckout' )`, `normalize()` line, `TOKEN_LABELS.collapse = __( 'Collapsible sections', 'yaycheckout' )`, and a `COLLAPSE_LABELS` map (`''` Slide, `fade` Fade, `none` None) used where `FOCUS_STYLE_LABELS` is chosen (~line 412).
5. `docs/architecture.md` line 45: add motion to the section list.
6. Verify: `composer lint -- includes/Models/LayoutDesign.php includes/Renderer/DesignStyles.php` passes; `pnpm build:blocks` succeeds; `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` has no new errors. In a new layout's Layout design sidebar, Motion → Collapsible sections offers Slide / Fade / None; pick Fade, Save, view the storefront source: `--yaycheckout-collapse-animation: fade` in the design style. Back to Slide: the property is gone. An existing layout opens and saves with no REST 400.
7. Commit those files: `feat(design): add a collapse animation setting`.

## Task 3: Collapse helper and the collapsible field

1. Create `packages/blocks/src/shared/collapse.ts` per Interfaces, with the file docblock naming its callers and `DesignStyles::vars()` as the owner of the property.
2. `packages/blocks/src/shared/disclosure-view.ts`: in the click listener only, when `toggle.dataset.mode === 'collapsible'`, call `collapse( panel, panel.hidden, () => setOpen( panel.hidden, true ) )` (compute `open` before calling). Link clicks, the checkbox, fills, the locale handler and the on-load open keep calling `setOpen()` directly.
3. Verify: `pnpm build:blocks`; checkout (`?page_id=9`, memory: Local site testing) with a field set to "In a collapsible section": clicking the header slides it open and closed with the chevron turning; Fade and None from Task 2 behave as named; macOS Reduce motion on → instant; rapid double-click ends in a consistent state with no stuck inline `display`. Link and checkbox modes are still instant.
4. Commit `packages/blocks/src/shared/collapse.ts`, `packages/blocks/src/shared/disclosure-view.ts`: `feat(blocks): animate collapsible fields`.

## Task 4: Order Summary details

1. `packages/blocks/src/order-summary/view.ts` `flip()`: move the `hidden`, `is-open` and `aria-expanded` writes into the `apply` passed to `collapse( details, open, apply )`.
2. Verify: Minimal steps (summary opens downward) and Accordion one-page (opens upward from the pinned bar) templates: header and Summary Toggle animate open and closed, every toggle's `aria-expanded` stays in sync, a cart update (coupon applied) mid-open leaves the panel open and drawn.
3. Commit `packages/blocks/src/order-summary/view.ts`: `feat(order-summary): animate the collapsible details`.

## Task 5: Steps accordion

1. `packages/blocks/src/steps/view.ts` `setState( step, state )`: keep its body as `apply`; when the state changes from or to `current`, run it through `collapse( step.querySelector( ':scope > .yaycheckout-step__body' ), state === CURRENT, apply )`, otherwise call `apply()`. State writes still happen synchronously inside `apply`, so `currentStep()` and `syncBackButtons()` see the new state at once.
2. Same file: `setState()` returns the `collapse()` promise (`Promise< void >`); the Continue and reopen paths call `revealStep()` after the closing step's promise resolves, so the scroll target doesn't move under a smooth scroll.
3. Verify: Accordion one-page and a timeline Steps: Continue slides the current step shut and the next open, then scrolls only if its header is off-screen; Edit on a complete step and a Go to step block do the same in reverse; an invalid step doesn't animate. One-at-a-time and headings-hidden Steps switch instantly with no flicker. Place order still posts every step's fields.
4. Commit `packages/blocks/src/steps/view.ts`: `feat(steps): animate opening and closing steps`.

Final check for PR 2: `./scripts/run.sh build` succeeds, `composer lint` passes (judge failures against `dev/edd`), and the Task 3–5 checks pass with the design set to Slide, Fade and None in turn.

## Review focus

- Steps: the closing step's body stays drawn by inline `display` while `data-state` says hidden; a stuck inline style would leave a locked step open. Check rapid Continue / Back.
- `revealStep()` measures while a step above is still shrinking (Task 5 step 2).
- Sidebar `RichText` and the Live value format popover: confirm the format toolbar appears for the sidebar box, not only the canvas.
- Default is Slide, so every existing layout gains animation on deploy.
