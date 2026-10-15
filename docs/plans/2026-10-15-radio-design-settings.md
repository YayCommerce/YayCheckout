# Radio design settings Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract.

**Status:** done 2026-10-15
**Goal:** Give radios their own section in Layout design, Radios, with size, border width, and unticked border, unticked background and checked colours. Loose radios use it directly, and Payment and Shipping's drawn indicators fall back to its colours.
**Spec:** none, planned from the request
**Architecture:** A new `radios` section in `LayoutDesign::schema()`, after `checkboxes`, with five empty-by-default tokens. `DesignStyles::vars()` and its TS copy print a `--yaycheckout-radio-*` property for each one that is set. In `checkout-base.css`, the checkbox and radio rules each read their own section's properties, falling back to the input's. This undoes the earlier commit `7b7e11b`, where radios read the checkbox tokens. Payment and Shipping's indicator rules insert the Radios colours between their block setting and today's fallback, so a block setting still wins. It is a section of its own rather than more Checkboxes keys, so the sidebar shows a separate Radios panel. Builds on `2026-10-15-checkbox-unticked-colours.md`, on the same branch.
**Touches:**
- `includes/Models/LayoutDesign.php` (shared: projected by `LayoutPostType` meta registration and its REST `prepare_callback`, `LayoutTemplateCatalogue::design()`, `LayoutRepository` reset and insert, and Pro export, import and `LayoutImportReview`. All of them go through `schema()`, `from_meta()` and `to_meta()`, so none needs editing.)
- `includes/Renderer/DesignStyles.php`
- `packages/blocks/src/shared/design-css.ts` (shared: also the SPA's `@blocks-shared`)
- `packages/blocks/src/checkout/layout-design.tsx`
- `assets/css/checkout-base.css`
- `packages/blocks/src/payment/style.css`
- `packages/blocks/src/shipping-methods/style.css`
- `docs/architecture.md`
- `templates/layouts/*/design.json` (all six built templates): an empty `radios` section, added during implementation (see Decided with the user)

**Tests:** none new. CLAUDE.md's test rule doesn't cover design tokens, and `tests/Integration/LayoutTemplateCatalogueTest.php` already fails on `dev/edd` (see Global constraints).

## Requirements

- **Goal:** Layout design gains a Radios panel after Checkboxes. It has:
  - Size and Border width sliders;
  - Border, Background and Checked colour pickers under Custom colours.

  A loose radio (an Extra Field of type Radio, or a third-party checkout radio) follows these settings, on the storefront and in the editor canvas. Payment and Shipping's drawn radios follow the Radios colours wherever the block sets none of its own.
- **Out of scope:**
  - **Payment and Shipping size and ring width:** their indicators keep their own size (18px, or 22px in Cards with checkmark) and ring widths (1px unticked, 5px ticked). Only their colours gain the fallback.
  - **Payment and Shipping indicator background:** their indicators have no fill of their own. `radios.background` applies to loose radios only.
  - **Radio radius:** a radio is always round.
  - **Template `design.json` files:** beyond the empty `radios` section, see Global constraints.
- **Acceptance criteria:**
  1. `LayoutDesign::schema()['radios']`, placed after `checkboxes`, has these keys in this order:
     - `size`: int, default null, 14–24
     - `border_width`: int, default null, 1–3
     - `border`: color, default `''`
     - `background`: color, default `''`
     - `checked`: color, default `''`
  2. Each token prints one property, and only when it is set:
     - `size` 20 → `--yaycheckout-radio-size: 20px`
     - `border_width` 2 → `--yaycheckout-radio-border-width: 2px`
     - `border` → `--yaycheckout-radio-border-color`
     - `background` → `--yaycheckout-radio-background`
     - `checked` → `--yaycheckout-radio-checked-color`

     A theme swatch prints its `var(--wp--preset--color--<slug>)`.
  3. A loose radio reads:
     - `width`/`height`: `--yaycheckout-radio-size`, else 18px
     - `border-width`: `--yaycheckout-radio-border-width`, else 1px
     - `border-color`: `--yaycheckout-radio-border-color`, else `--yaycheckout-input-border-color`, else `--yaycheckout-border-color`
     - `background-color`: `--yaycheckout-radio-background`, else `--yaycheckout-input-background`, else `--yaycheckout-color-background`, else `#fff`
     - ticked: a 5px ring in `--yaycheckout-radio-checked-color`, else `--yaycheckout-primary-color`
  4. A checkbox no longer reads any `--yaycheckout-radio-*` property, and a radio no longer reads any `--yaycheckout-checkbox-*` property.
  5. Each `--yaycheckout-payment-radio` / `--yaycheckout-shipping-radio` fallback becomes `var(--yaycheckout-radio-border-color, <today's fallback>)`. Each `--yaycheckout-payment-radio-selected` / `--yaycheckout-shipping-radio-selected` fallback becomes `var(--yaycheckout-radio-checked-color, var(--yaycheckout-primary-color, #3858e9))`. A block's own Radio colour still wins.
  6. With every Radios token empty, a loose radio looks as it does on `dev/edd` at `74d0178`, and Payment and Shipping indicators are unchanged. A checkbox looks as it does at `66663c6`.
  7. The PHP `css()` output and the TS `designCss()` output are identical for the same tokens.
  8. End to end, in a **new** layout from Classic two-column:
     1. Add an Extra Field of type Radio with two options.
     2. Set Radios > Size 20, Border width 2, Border `#949494`, Background `#ffffff`, Checked `#1e1e1e`, then Save.
     3. The canvas updates while you edit.
     4. In the storefront preview, the Extra Field's radios are 20px with a 2px `#949494` ring on white, and a ticked one shows a 5px `#1e1e1e` ring.
     5. The Shipping and Payment indicators (no block Radio colours set) show a `#949494` ring unticked and `#1e1e1e` ticked.
     6. Setting a Payment block Radio colour overrides it for Payment only.
     7. Clearing every Radios token brings back the criterion 6 look.
- **Decided with the user:**
  - Tokens: size, border width, border, background, checked.
  - An empty radio token falls back to the input's, not to the checkbox's. Checkbox tokens apply to checkboxes only.
  - Payment and Shipping's drawn radios use the Radios colours as a fallback under their block settings.
  - Found during implementation: without a `radios` section in the templates' `design.json`, `LayoutTemplateCatalogueTest` stopped at `classic-two-column: radios` instead of `help_text`. The user chose to add an empty `radios` section (all null/`''`) to all six built templates, between `checkboxes` (or `inputs`) and `custom_css`, so the test fails exactly as on `dev/edd` and nothing is added to it.
- **Decided in planning:**
  - **Size range 14–24, not 12:** the ticked ring stays 5px, so at 14px the dot is still 4px wide.
  - **Payment and Shipping take only `border` and `checked`:** their indicators are drawn with `box-shadow` and have no background or ring width of their own to fall back to.

## Global constraints

- **PHP 7.4:** product PHP must run on it. No enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- **Design tokens** are declared once, in `LayoutDesign::schema()`. Each one is that entry, plus its line in `DesignStyles::vars()`, plus the copy in `packages/blocks/src/shared/design-css.ts` (docs/conventions.md). There is no defaults array and no per-key sanitizer.
- **TypeScript copies** of PHP name their owner in a `Mirrors …` comment and change in the same commit as the PHP.
- **Defaults are empty** (`''` for a colour, `null` for an int), and an empty token prints nothing. Tokens are values, never selectors, and CSS reads each one with a fallback.
- **Generated output:** never hand-edit `assets/dist/`. Rebuild it with `pnpm build:blocks` (Node 22: `nvm use`).
- **Already failing on `dev/edd`:** `LayoutTemplateCatalogueTest::test_every_available_template_is_publishable_and_sets_every_section` stops at `classic-two-column: help_text`, because no template's `design.json` has every section. This plan adds a section without touching those files and adds no new failure mode: the expected result is the same first failure.
- **Git:** on `trace/feat/checkbox-unticked-colours`, after `66663c6`. Conventional Commits, files staged by name, and the trailers CLAUDE.md and the session ask for.

## Review focus

- **Specificity after the split.** The shared `:is(checkbox, radio)` rule keeps the `background` shorthand (`center no-repeat`). The checkbox and radio rules then set only `border-color` and `background-color`, and come after it, so they win at equal specificity. `:checked` rules must still win over both. Verified in Task 1, Step 6, item 5.
- **A saved layout without `radios`** (every existing layout and older imports) must load and save. `from_meta()` fills it, and `normalize()` must list it, or the wp/v2 save is refused, since `rest_schema()` marks every section's keys required. Verified in Task 1, Step 6, item 4.
- **Checkmark layouts:** in Payment and Shipping's Cards with checkmark layout, the ticked indicator is a filled circle (`background-color`), not a ring. The checked fallback must reach that `background-color` rule too. Verified in Task 2, Step 2, item 2.
- **Radios no longer follow Checkboxes > Border and Background,** which `7b7e11b` briefly made them do. That commit never reached `dev/edd`, so no stored layout depends on it. Verified in Task 1, Step 6, item 5.
- **`design-css.ts` is shared with the SPA.** Type-check the SPA after the change. Verified in Task 3, Step 2, item 3.

---

### Task 1: Radios section, from storage to the loose radio

**Files:**
- Modify:
  - `includes/Models/LayoutDesign.php`: the `schema()` docblock, and a `radios` section after `checkboxes`
  - `includes/Renderer/DesignStyles.php`: `vars()`, after the checkbox block
  - `packages/blocks/src/shared/design-css.ts`: `vars()`, after the checkbox block, and its destructuring of `tokens`
  - `packages/blocks/src/checkout/layout-design.tsx`: `LayoutDesignMeta`, `Section`, `SECTIONS`, `normalize()`
  - `assets/css/checkout-base.css`: the drawn checkbox-and-radio comment and rules (from about :372)

**Interfaces:**
- Produces:
  - Design section `radios` with keys `size`, `border_width`, `border`, `background`, `checked`
  - Custom properties `--yaycheckout-radio-size`, `--yaycheckout-radio-border-width`, `--yaycheckout-radio-border-color`, `--yaycheckout-radio-background`, `--yaycheckout-radio-checked-color`
  - TS: `Section` includes `'radios'`, and `LayoutDesignMeta.radios: SectionTokens`

- [ ] **Step 1: Add the `radios` section to `LayoutDesign::schema()`, after `checkboxes`**

The rules come from acceptance criterion 1. In the docblock, the checkbox sentence now says `checkboxes.border` and `checkboxes.background` colour an unticked checkbox (drop "or loose radio"). Add one sentence: `radios` styles loose radios, and its colours are the fallback for Payment and Shipping's indicators. An empty radio colour falls back to the input's or, for checked, to primary.

- [ ] **Step 2: Print the tokens in `DesignStyles::vars()`, after the checkbox colours and before `help_text`, then mirror them in `design-css.ts`**

- **Ints:** `radio-size` and `radio-border-width`, each `<n>px`, only when not null.
- **Colours:** `radio-border-color`, `radio-background` and `radio-checked-color`, through `$not_empty` / `notEmpty`, the way the checkbox colours go.
- **TS:** add `radios` to the destructuring, and keep the `Mirrors DesignStyles::vars()` comment accurate.

- [ ] **Step 3: Add the section to the editor's meta shape in `layout-design.tsx`**

- `LayoutDesignMeta` gets `radios: SectionTokens`, after `checkboxes`.
- `type Section` gets `'radios'`.
- `SECTIONS` gets `radios: __( 'Radios', 'yaycheckout' )`, after `checkboxes`.
- `normalize()` gets `radios: sectionTokens( design.schema.radios, meta.radios )`.

`TOKEN_LABELS` already labels every key.

- [ ] **Step 4: Split the token reads in `checkout-base.css`**

- **Shared `:is(input[type="checkbox"], input[type="radio"])` rule:** back to its `dev/edd` form, reading the input chain directly: `border: 1px solid var(--yaycheckout-input-border-color, var(--yaycheckout-border-color))`, and `background: var(--yaycheckout-input-background, var(--yaycheckout-color-background, #fff)) center no-repeat`.
- **`input[type="checkbox"]` rule:** add `border-color: var(--yaycheckout-checkbox-border-color, var(--yaycheckout-input-border-color, var(--yaycheckout-border-color)))` and `background-color: var(--yaycheckout-checkbox-background, var(--yaycheckout-input-background, var(--yaycheckout-color-background, #fff)))`.
- **`input[type="radio"]` rule:**
  - `width`/`height`: `var(--yaycheckout-radio-size, 18px)`
  - `border-width`: `var(--yaycheckout-radio-border-width, 1px)`
  - `border-color` and `background-color`: the radio properties, with the same input fallbacks as the checkbox rule
- **`input[type="radio"]:checked` rule:** `border: 5px solid var(--yaycheckout-radio-checked-color, var(--yaycheckout-primary-color))`.
- **Comment above:** each control reads its own section (Checkboxes or Radios), falling back to the input's border and background; Payment and Shipping draw their own indicator, which falls back to the Radios colours.

- [ ] **Step 5: Build**

Run: `pnpm build:blocks`
Expected: compiles with no errors.

- [ ] **Step 6: Verify**

1. **PHP output.** Run with the per-site socket wrapper from the local-site notes:
   ```
   wp eval 'echo (new \YayCheckout\Renderer\DesignStyles())->css( \YayCheckout\Models\LayoutDesign::from_meta( [ "radios" => [ "size" => 20, "border_width" => 2, "border" => "#949494", "background" => "#ffffff", "checked" => "#1e1e1e" ] ] ), "", false );'
   ```
   Expected: the five `--yaycheckout-radio-*` properties, with the values of criterion 2. With `[]` instead, the output contains no `radio-` property.
2. **Parity.** For the same designs, the TS `designCss()` output is identical to PHP's. Run a copy of `design-css.ts` under `node --experimental-strip-types`, as the previous plan's review did.
3. **Lint:** `composer lint -- includes/Models/LayoutDesign.php includes/Renderer/DesignStyles.php` reports nothing.
4. **Existing layout.** Open an existing layout in the editor (not a draft you're testing on), change nothing and Save. The save succeeds with no REST error, and the stored meta has `radios` with all-null/empty values.
5. **Storefront preview** of a **new** layout from Classic two-column, with an Extra Field of type Radio:
   1. With every Radios and Checkboxes token empty, radios and checkboxes look as they do on `dev/edd`.
   2. Set only Checkboxes > Border `#949494`: the radios keep the input border.
   3. Set Radios as in criterion 8: the loose radios take them, and a ticked one shows the 5px checked ring.
   4. A ticked checkbox still fills its checked colour with a white tick.

- [ ] **Step 7: Commit**

- Stage: `includes/Models/LayoutDesign.php`, `includes/Renderer/DesignStyles.php`, `packages/blocks/src/shared/design-css.ts`, `packages/blocks/src/checkout/layout-design.tsx`, `assets/css/checkout-base.css`
- Message: `feat(design): add a Radios design section for size, border width and colours`

### Task 2: Payment and Shipping indicators fall back to the Radios colours

**Files:**
- Modify:
  - `packages/blocks/src/payment/style.css`: every `var(--yaycheckout-payment-radio, …)` and `var(--yaycheckout-payment-radio-selected, …)` (5 today)
  - `packages/blocks/src/shipping-methods/style.css`: every `var(--yaycheckout-shipping-radio, …)` and `var(--yaycheckout-shipping-radio-selected, …)` (9 today)

**Interfaces:**
- Consumes: `--yaycheckout-radio-border-color` and `--yaycheckout-radio-checked-color` from Task 1

- [ ] **Step 1: Insert the Radios colour into each fallback**

Per acceptance criterion 5:
- `var(--yaycheckout-<block>-radio, X)` → `var(--yaycheckout-<block>-radio, var(--yaycheckout-radio-border-color, X))`, keeping each layout's own `X`: `var(--yaycheckout-border-color)`, the `color-mix(…)`, or `currentColor`.
- `var(--yaycheckout-<block>-radio-selected, var(--yaycheckout-primary-color, #3858e9))` → `var(--yaycheckout-<block>-radio-selected, var(--yaycheckout-radio-checked-color, var(--yaycheckout-primary-color, #3858e9)))`, in both `box-shadow` and `background-color` rules.

In each file's header comment, say that the radio colours fall back to the layout's Radios design.

Check that `git grep -n "radio-selected, var(--yaycheckout-primary" -- packages/blocks/src/payment/style.css packages/blocks/src/shipping-methods/style.css` finds nothing afterwards.

- [ ] **Step 2: Verify**

1. Run `pnpm build:blocks`. It compiles.
2. **Storefront preview** of the Task 1 layout (Radios set as criterion 8, no block Radio colours):
   - Shipping and Payment indicators show the `#949494` ring unticked and `#1e1e1e` ticked.
   - Switch Payment to Cards with checkmark: the ticked indicator is a filled `#1e1e1e` circle.
   - Set Payment's Radio selected colour to `#ff0000`: Payment uses it, and Shipping keeps `#1e1e1e`.
3. With every Radios token empty, the indicators match `dev/edd`.

- [ ] **Step 3: Commit**

- Stage: `packages/blocks/src/payment/style.css`, `packages/blocks/src/shipping-methods/style.css`
- Message: `feat(blocks): fall back to the Radios design colours in Payment and Shipping indicators`

### Task 3: Docs and end-to-end check

**Files:**
- Modify: `docs/architecture.md:45` and `:64`

**Interfaces:**
- Consumes: Tasks 1 and 2

- [ ] **Step 1: Update `docs/architecture.md`**

- **:45:** the section list becomes "(colours, typography, inputs, checkboxes, radios, help text)".
- **:64:** the checkbox clause no longer mentions loose radios. Add a radio clause: a loose radio reads `--yaycheckout-radio-size`, `-border-width`, `-border-color`, `-background` and `-checked-color`, falling back to 18px, 1px, the input border, the input background and `--yaycheckout-primary-color`. Payment and Shipping's indicator colours fall back to `--yaycheckout-radio-border-color` and `--yaycheckout-radio-checked-color` under their block settings.

- [ ] **Step 2: Verify end to end**

1. Run `pnpm build:blocks`.
2. **Sidebar:** in the criterion 8 layout, Layout design shows a Radios panel after Checkboxes. It has Border, Background and Checked under Custom colours, and Size and Border width sliders (px). Each Reset empties its token.
3. Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. It exits 0.
4. Run acceptance criterion 8.
5. Run `composer test -- --filter LayoutTemplateCatalogueTest`. Expected: the same single failure as `dev/edd`, `classic-two-column: help_text`.

- [ ] **Step 3: Commit**

- Stage: `docs/architecture.md`, `docs/plans/2026-10-15-radio-design-settings.md` (Status set to `done <date>`)
- Message: `docs(architecture): describe the Radios design section`
