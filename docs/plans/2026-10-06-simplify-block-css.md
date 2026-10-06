# Simplify Block CSS Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec: the requirements below are the brief.

**Status:** active
**Goal:** strip the block CSS down to a small base look with low specificity, so block supports and block style variations added later can restyle the blocks without `!important`.
**Spec:** none, planned from the request
**Architecture:** Continue and Back lose the `align` support and every rule that existed only for it. Their base rule drops to one class (0,1,0) and keeps only the declarations that give the look; a `:hover, :focus` rule restates their colours so a classic theme's `button:hover` can't repaint them. Editor-only rules leave the storefront stylesheets for `editor.css`, and the editor preview CSS loses declarations that restate browser defaults. `render.php` markup doesn't change.
**Touches:** `packages/blocks/src/{step-button,back-button}/{block.json,style.css}`, `packages/blocks/src/steps/style.css`, `packages/blocks/src/progress/style.css`, `packages/blocks/src/{place-order,express-pay}/editor.css`. No PHP changes.
**Branch:** the CSS exists only on `onyx/feat/accordion-block` (not on `dev/edd`), so commit there; it reaches `dev/edd` with that branch's PR.
**Tests:** none. CSS and editor UI are outside CLAUDE.md's guarded risks, so each task gets a manual check.

## Requirements

- **Goal:** each block's CSS holds only what draws its base look; nothing that exists to support alignment, to win specificity fights, or that restates a browser default.
- **Scope (decided with the user, 2026-10-06):** storefront `style.css` (step-button, back-button, steps, progress) and the editor preview `editor.css` files.
- **Out of scope:** `checkout/import-export.css` (it styles editor chrome outside the canvas, and its root-class prefix is deliberate); adding block supports (colour, typography, spacing, border) or style variations: that is the follow-up this prepares for; `render.php`; the `.yaycheckout-step__edit` hover leak noted under Review Focus.
- **Decisions:**
  - Continue and Back have no alignment control. Both sit at the start of the line in natural flow. A merchant who wants Continue on the right puts it in a Row and justifies it. Saved layouts holding `"align"` are not migrated; the attribute is ignored and dropped on next save.
  - Base selectors are a single class (0,1,0), with no `.yaycheckout-checkout` prefix: the blocks can only live in Checkout (`ancestor`), so the prefix only adds specificity.
  - Inner controls a merchant doesn't style (`.yaycheckout-progress__jump`, `.yaycheckout-step__edit`) keep their current selectors.
- **Acceptance criteria:**
  1. `step-button` and `back-button` `block.json` have no `align` attribute or support, and their `style.css` has no `align*`, `:is(.is-layout-flex, .is-layout-grid)` or `.yaycheckout-checkout` selector.
  2. No `.block-editor-*` selector is left in any `style.css`.
  3. On Storefront and Twenty Twenty-Five, Continue, Back and Progress look as before (except Continue now sits left), including on hover and focus.
  4. End to end: `pnpm build:blocks` succeeds, and a layout saved with `{"align":"right"}` on Continue opens with no block-validation warning or console error.

## Base branch state

- `pnpm build:blocks` is the only check on the blocks' CSS/TS (`.claude/rules/blocks.md`). `composer lint` is unaffected (no PHP changes).
- Storefront (installed) styles `button:hover { color:#fff }` and, from its customizer, `button:hover { background-color: … }` (both 0,1,1). That is why a one-class base rule needs the hover/focus rule below.

## Task 1: Continue and Back without alignment

**Files:**
- Modify: `packages/blocks/src/step-button/block.json`, `packages/blocks/src/back-button/block.json`, `packages/blocks/src/step-button/style.css`, `packages/blocks/src/back-button/style.css`

- [x] **Step 1: block.json.** In both files remove the `align` attribute and `supports.align`. Keep `buttonText`, `html: false`, `reusable: false`.
- [x] **Step 2: step-button/style.css.** Replace the file with exactly three rules:
  - `.yaycheckout-step-button`: `display: block; width: fit-content; margin: 16px 0 0; padding: 10px 18px; border: 0; border-radius: 6px; background: var(--yaycheckout-primary); color: #fff; font-family: inherit; font-size: 16px; font-weight: 600; line-height: 1.2; cursor: pointer;`
  - `.yaycheckout-step-button:is(:hover, :focus)`: `background: var(--yaycheckout-primary); color: #fff;` with a one-line comment: classic themes (Storefront) repaint `button:hover`.
  - `.yaycheckout-step-button[hidden] { display: none; }` (kept: `steps/view.ts` hides Continue with `hidden`, and `display: block` would override it).
  Dropped on purpose: `appearance`, `box-sizing`, `min-height`, `height`, `box-shadow`, `letter-spacing`, `text-transform`, `text-decoration`, the three `.align*` rules and the flex/grid margin rule.
- [x] **Step 3: back-button/style.css.** Same shape with `.yaycheckout-back-button`: base differs only in `background: transparent; color: var(--yaycheckout-primary); padding: 10px 0;`; the hover/focus rule restates `background: transparent; color: var(--yaycheckout-primary);`. No `[hidden]` rule (Back is never hidden).
- [x] **Step 4: Verify.** `pnpm build:blocks` succeeds. `git grep -n -E 'align|is-layout|yaycheckout-checkout' -- packages/blocks/src/step-button packages/blocks/src/back-button` prints nothing. On the storefront checkout with Storefront active, then Twenty Twenty-Five: Continue is a filled primary button at the start of the line, Back is primary-coloured text; hovering and tabbing to each keeps those colours; Continue disappears on the last step as before. In the editor, both block toolbars have no alignment control.
- [x] **Step 5: Verify old markup.** In the layout editor's Code editor, set Continue to `<!-- wp:yaycheckout/step-button {"buttonText":"Continue","align":"right"} /-->`, switch back to Visual, save, reload: no "unexpected or invalid content" notice, no console error, and the storefront renders the button with no `alignright` class.
- [x] **Step 6: Commit.** Stage the four files. Message: `refactor(blocks): drop alignment and trim Continue and Back styles`

## Task 2: Editor-only rules out of the Steps stylesheet

**Files:**
- Modify: `packages/blocks/src/steps/style.css`

Changed during implementation (2026-10-06): the plan moved the `margin-block: 0` rule to a new `steps/editor.css`. Measured in the editor, that rule also zeroed Continue's 16px top margin, and once the gap rule is gone nothing else gives a Step body's blocks a margin, so both rules go and no `editor.css` is needed.

- [x] **Step 1:** Delete the `.yaycheckout-steps .block-editor-block-list__layout:not(.is-layout-flex):not(.is-layout-grid)` rule and its comment. `.yaycheckout-step__body` is already a flex column, and the rule also turned nested flow Groups into columns in the editor only.
- [x] **Step 2:** Delete `.yaycheckout-steps .block-editor-block-list__layout > .wp-block { margin-block: 0; }`, and inline `--yaycheckout-steps-gap` as `gap: 12px` (only one rule used it now).
- [x] **Step 3: Verify.** `pnpm build:blocks` succeeds. `git grep -n 'block-editor' -- 'packages/blocks/src/*/style.css'` prints nothing. In the editor, Continue and Back in a Step body get their 16px top margin, as on the storefront (before: 12px gap, 0 margin); a Group (flow layout) inside a Step is `display: block` with the theme's flow spacing; the Step list keeps its 12px gap. On the storefront, Steps is unchanged.
- [x] **Step 4: Commit.** Stage `packages/blocks/src/steps/style.css` and this plan. Message: `refactor(blocks): drop editor-only rules from the Steps stylesheet`

## Task 3: Default-restating declarations

**Files:**
- Modify: `packages/blocks/src/progress/style.css`, `packages/blocks/src/place-order/editor.css`, `packages/blocks/src/express-pay/editor.css`

- [x] **Step 1:** `progress/style.css`: remove `gap: 0` from `ol.yaycheckout-progress` (flex `gap` is already 0). Leave `button.yaycheckout-progress__jump` and its reset unchanged: its 0,2,1 selector is what keeps theme `button:hover` off it.
- [x] **Step 2:** `place-order/editor.css`: the preview is a `div` (`place-order/edit.tsx`), so remove `display: block`, `width: 100%` and `border: 0`.
- [x] **Step 3:** `express-pay/editor.css`: both previews are `div`s, so remove `display: block` and `width: 100%` from `.yaycheckout-express-pay__button`.
- [x] **Step 4: Verify.** Found during implementation: `width: 100%` without `box-sizing` made both previews overflow their parent (671 and 677px in 645px); without it they fit exactly, so they are slightly narrower than before. `pnpm build:blocks` succeeds. In the editor, Place order and Express pay previews look the same as a screenshot taken before; on the storefront, Progress is unchanged.
- [x] **Step 5: Commit.** Stage the three files. Message: `refactor(blocks): drop CSS that restates browser defaults`

The other `editor.css` files (order-review, order-summary, slot, `shared/field-preview.css`, `shared/section-preview.css`) were checked and hold no redundant declarations: each `display: block` there sits on a `label`, `input`, `select` or `textarea`.

## Final verification

`pnpm build:blocks` succeeds and `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` exits 0. Open an existing layout: no block-validation warnings, no console errors. On the storefront (Storefront theme), step through a multi-step checkout to an order: Continue/Back/Progress work and look as before apart from Continue sitting at the start of the line.

## Review Focus

- **Theme bleed after lowering specificity.** Properties no longer reset (`text-transform`, `letter-spacing`, `box-shadow`, `min-height`) can now come from a theme's `button` rule. Storefront and Twenty Twenty-Five set none of them; Divi is installed and worth one look in Task 1 Step 4.
- **Back turning invisible on hover.** Without the hover/focus rule, Storefront's `button:hover { color:#fff }` makes Back white on white. Task 1 Step 4 checks it.
- **Continue moves left on every existing layout.** Intended (decided above), but merchants who relied on the right-aligned default will see it move. Worth a line in the release notes.
- **Pre-existing, not fixed here:** `.yaycheckout-step__edit` (0,1,0) has the same Storefront hover leak. Suggest a backlog item via `/yaybacklog`.
