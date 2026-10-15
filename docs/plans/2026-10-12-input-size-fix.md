# Input Size Fix Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. No spec; the Requirements below come from the request and the measurements in the session that found the bug.

**Status:** done 2026-10-13
**Goal:** Make the layout design's Inputs → Size (Small / Medium / Large) visibly change the height of inputs, selects, the coupon field and buttons, with and without floating labels, and make every layout's base font size default to 16px.
**Spec:** none, planned from the request
**Architecture:** Today the size only sets `min-height` in px, and a fixed 8px padding + 1.4em line + border is already taller, so the setting is ignored (and floating labels floor at 3.25em, above Large). The fix makes each size an em height that the content can never exceed: when a size is set, the design prints the height, a floating height, and a zero vertical padding, so the box *is* the chosen height and the text centres in it. Em, not px, because that is how mature design systems size fields (Bootstrap, Material, Shopify checkout all derive height from font size), so a 22px-font layout gets proportionally larger fields instead of identical ones. When no size is set, nothing new is printed and the CSS fallbacks keep today's look exactly.
**Touches:** `includes/Renderer/DesignStyles.php` and its mirror `packages/blocks/src/shared/design-css.ts` (shared: storefront via `CheckoutBaseStyles`, editor canvas via `LayoutEditorCanvas`); `assets/css/checkout-base.css` (shared: storefront + editor canvas).
**Tests:** none. CSS/token mapping only; it isn't one of the CLAUDE.md test-warranted areas. Verified by exact measurement instead.

## Requirements

- **Goal:** each size step produces a distinct, predictable field height on the storefront and in the editor canvas, in both label modes.
- **Out of scope:** per-block button padding controls (the user wants them later; block-support inline padding already wins over base CSS and must keep winning); textarea sizing beyond its existing `2 ×` rule; the design panel UI and schema (enum stays `'' | small | medium | large`).
- **Acceptance criteria:**
  1. Size heights (border-box), set by the token, at any base font: Small `2.5em`, Medium `2.75em`, Large `3em` (40 / 44 / 48px at 16px; 55 / 60.5 / 66px at 22px).
  2. Floating-label heights: Small `3em`, Medium `3.25em`, Large `3.5em` (48 / 52 / 56px at 16px).
  3. Buttons (`.button`, `button[type="submit"]` inside `.yaycheckout-checkout`) default to the same height as inputs in the same label mode, so the coupon field and Apply line up.
  4. Size unset: every measured height equals today's (16px default: input 40.4, button 43.2; 16px floating: input 52.4; 22px floating: input 71.5).
  5. End to end: on a throwaway layout previewed on the storefront, Small < Medium < Large for the first-name input, the country select2, the coupon code and Place Order, in both label modes, at 16px and 22px base font, matching criteria 1–3 within 1px.
- **Decided with the user:**
  - Size unit → "what do pro designers use": em-based, from Bootstrap (sizes scale padding with font), Material (56px ≈ 3.5em filled/outlined floating field), Apple HIG / WCAG 2.5.5 (44px touch target → Medium = 44px at 16px), Shopify checkout (≈ 3.25em floating field, today's floor → Medium floating).
  - Buttons → default to match inputs; per-block padding may override later.
  - Default font size (asked after the plan was approved) → an empty Base size prints 16px on every layout, not the theme's; the template's 13px becomes 16px. This supersedes the decision record `2026-10-10-layout-owns-its-design.md`.
  - Floating → follow the same sources: one step taller than the non-floating size (+0.5em), Small 3em … Large 3.5em.

## Global constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit `assets/dist/`; rebuild with `pnpm build:blocks`.
- `DesignStyles` and `design-css.ts` print identical text for identical tokens (the editor reprints the storefront's CSS).
- Tokens are values, never selectors; an unset token prints nothing and the CSS fallback follows today's look.
- Branch `trace/fix/input-size` from `dev/edd`; Conventional Commits; PR into `dev/edd`.

## Review focus

- Underline style (`border-width: 0 0 1px`): box height still equals the size, text not clipped. → Task 3 Step 2.
- Native `<select>` and select2 at padding 0: value text vertically centred, not top-aligned. → Task 3 Step 2 (screenshot).
- Floating Small at 22px font: raised label and value don't overlap. → Task 3 Step 2 (screenshot).
- A block whose inline padding is set (Place Order block supports) keeps that padding over the new button padding. → Task 3 Step 2.

---

### Task 1: Print the size as em height, floating height and zero padding

**Files:**
- Modify: `includes/Renderer/DesignStyles.php` (`INPUT_HEIGHTS` ~line 55, the size branch in `vars()` ~line 146)
- Modify: `packages/blocks/src/shared/design-css.ts` (`INPUT_HEIGHTS` line 42, size branch line 77)

**Interfaces:**
- Produces custom properties, printed only when `inputs.size` is not `''`:
  - `--yaycheckout-input-height`: `2.5em` | `2.75em` | `3em`
  - `--yaycheckout-input-floating-height`: `3em` | `3.25em` | `3.5em`
  - `--yaycheckout-input-padding-block`: `0`

- [x] **Step 1:** In `DesignStyles.php`, replace `INPUT_HEIGHTS` (px ints) with `INPUT_SIZES = [ 'small' => [ '2.5em', '3em' ], 'medium' => [ '2.75em', '3.25em' ], 'large' => [ '3em', '3.5em' ] ]` (height, floating height); update its docblock and the `LayoutDesign::schema()` docblock line that says "the renderer decides its px" to say em. The size branch sets the three vars above, in that order, right after `input-radius`.
- [x] **Step 2:** Mirror it in `design-css.ts`: `INPUT_SIZES: Record< string, [ string, string ] >`, same values, same push order.
- [x] **Step 3: Verify PHP and TS print the same text**

Run: `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` → no new errors. Then `composer lint -- includes/Renderer/DesignStyles.php includes/Models/LayoutDesign.php` → no errors.
Run (wp-cli with the Local socket): `wp eval 'echo (new \YayCheckout\Renderer\DesignStyles())->css( \YayCheckout\Models\LayoutDesign::from_meta(["inputs"=>["size"=>"medium"]]), "", false );'`
Expected: contains `--yaycheckout-input-height: 2.75em;`, `--yaycheckout-input-floating-height: 3.25em;`, `--yaycheckout-input-padding-block: 0;`, and no `input-height` var for `size` `''`.

### Task 2: Make the base CSS honour the height in both label modes, and buttons follow

**Files:**
- Modify: `assets/css/checkout-base.css` (input rule ~273–275, floating block ~375 and ~417–420, button rule ~541–542)

**Interfaces:**
- Consumes: the three vars from Task 1.

- [x] **Step 1: Inputs.** In the shared input rule, `padding-block: var(--yaycheckout-input-padding-block, 8px);` (min-height line unchanged). Update the rule's comment: a set size fixes the height; unset keeps the theme-following fallback.
- [x] **Step 2: Floating.** `--yaycheckout-floating-height: var(--yaycheckout-input-floating-height, max(var(--yaycheckout-input-height, 40px), 3.25em));`. In the floating control rule keep `padding-block: 1.375em 0.375em` when unset; when a size is set the min-height must win, so change the bottom padding to `0` only via the token: `padding-block: 1.375em var(--yaycheckout-input-padding-block, 0.375em);`. Update the block comment's note on height.
- [x] **Step 3: Buttons.** In the button rule: `min-height: var(--yaycheckout-button-height, 40px); padding: var(--yaycheckout-input-padding-block, 8px) 20px;`. Before it, register `@property --yaycheckout-button-height { syntax: "<length>"; inherits: true; initial-value: 40px; }` and set it on `.yaycheckout-checkout` to `var(--yaycheckout-input-height, 40px)` and on `.yaycheckout-checkout--floating-labels` to `var(--yaycheckout-input-floating-height, var(--yaycheckout-input-height, 40px))`. Registered so the em resolves on the wrapper at the layout's font: a theme's `elements.button` font size (18px on the dev site) would otherwise make buttons taller than inputs. Unset, it resolves to today's 40px floor, so buttons stay 43.2px. Block padding (inline style) still overrides.
- [x] **Step 4: Build.** Run `pnpm build:blocks` → exits 0 (blocks don't change, but the editor canvas must be re-checked against a current build).

### Task 4: Default every layout's base font size to 16px

**Files:**
- Modify: `includes/Renderer/DesignStyles.php` (`DEFAULT_BASE_SIZE = 16`, always print `font-size-base`), `packages/blocks/src/shared/design-css.ts` (exported mirror), `packages/blocks/src/checkout/layout-design.tsx` (Base size control shows 16 while empty), `templates/layouts/classic-two-column/design.json` (13 → 16), `includes/Models/LayoutDesign.php` (schema docblock)
- Create: `docs/decisions/2026-10-12-empty-base-size-prints-16px.md`, superseding `docs/decisions/2026-10-10-layout-owns-its-design.md` whole (old Status set to superseded)
- Modify: `docs/architecture.md`, `docs/conventions.md` (mirror example)

- [x] **Step 1: Verify.** `DesignStyles::css( LayoutDesign::from_meta( [] ) )` prints `--yaycheckout-font-size-base: 16px;`. A layout with an empty base size on a 22px-body theme renders at 16px on the storefront and in the editor canvas, and the sidebar's Base size reads 16.

### Task 3: Verify end to end on a throwaway layout, then commit

- [x] **Step 1: Set up.** With wp-cli (Local socket, see memory), duplicate the active layout as a new draft (never edit the user's layouts), set its `_yaycheckout_design` meta per case, mint `PreviewToken::mint( <id>, 60 )`, add product 29 to the cart, open `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>`.
- [x] **Step 2: Measure.** For each of `typography.base_size` ∈ {16, 22} × `inputs.label` ∈ {'', floating} × `inputs.size` ∈ {'', small, medium, large}, and once with `inputs.style` = underline, read `getBoundingClientRect().height` of `#billing_first_name`, `.select2-selection--single`, `.yaycheckout-coupon-form__code`, `#place_order`.
Expected: acceptance criteria 1–4 within 1px; button = input height in the same mode; screenshots of floating Small @22px and select2 show centred, non-overlapping text. Then set inline padding on the Place Order block in the throwaway layout and confirm its height grows past the token height.
- [x] **Step 3: Editor canvas.** Open the throwaway layout in the editor, switch Size in the Design panel. Expected: the canvas fields change height live, matching Step 2 values.
- [x] **Step 4: Clean up.** Ask the user before deleting the throwaway layout.
- [x] **Step 5: Commit**

Stage: `includes/Renderer/DesignStyles.php`, `includes/Models/LayoutDesign.php`, `packages/blocks/src/shared/design-css.ts`, `packages/blocks/src/checkout/layout-design.tsx`, `assets/css/checkout-base.css`, `templates/layouts/classic-two-column/design.json`, `docs/decisions/2026-10-10-layout-owns-its-design.md`, `docs/decisions/2026-10-12-empty-base-size-prints-16px.md`, `docs/architecture.md`, `docs/conventions.md`, `docs/plans/2026-10-12-input-size-fix.md`
Message: `fix(design): make input size set the field height and default text to 16px`
