# Checkbox design settings Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract.

**Status:** done 2026-10-13
**Goal:** Let a merchant set the checkout checkbox's size, corner radius, border width and checked colour in the editor's Layout design sidebar, stored with the layout's design.
**Spec:** none, planned from the request
**Architecture:** A new `checkboxes` section in `LayoutDesign::schema()` with four empty-by-default tokens. `DesignStyles::vars()` prints a `--yaycheckout-checkbox-*` property for each token that is set, and the drawn checkbox rule in `checkout-base.css` (commit `8ad86f4`) reads each one with today's value as its fallback. This is a section of its own, not more `inputs` keys, so the sidebar shows a separate "Checkboxes" panel and doesn't crowd Inputs. Presets were considered and rejected by the user.
**Touches:**
- `includes/Models/LayoutDesign.php` (shared: projected by `LayoutPostType` meta registration, `LayoutTemplateCatalogue::design()`, `LayoutRepository` reset and insert, and Pro export, import and `LayoutImportReview`. All of them go through `from_meta()`/`to_meta()`, so none needs editing.)
- `includes/Renderer/DesignStyles.php`
- `includes/PostTypes/LayoutPostType.php`: the design meta's REST read gets a `prepare_callback` that projects through `from_meta()`. Without it, core answers `null` for a stored design that is missing the new section, and the editor would then save an empty design over it. Found during implementation.
- `packages/blocks/src/shared/design-css.ts`
- `packages/blocks/src/checkout/layout-design.tsx`
- `assets/css/checkout-base.css`
- `templates/layouts/classic-two-column/design.json`
- `docs/architecture.md`

**Tests:** none new. The existing `tests/Integration/LayoutTemplateCatalogueTest.php` already guards the one risky part: a `design.json` that doesn't match the schema exactly.

## Requirements

- **Goal:** In Layout design > Checkboxes, a merchant can change the checkbox's size, corner, border width, and the colour a ticked box fills with (a colour picker, empty = primary). The storefront and the editor canvas both show the change.
- **Out of scope:**
  - **Radios:** loose radios keep 18px, 1px and primary. Payment and Shipping keep their block-level settings.
  - **Presets:** none.
  - **Unticked border colour:** a separate setting is out. It keeps following Inputs > Border.
  - **Other templates' `design.json`:** only `classic-two-column` has a folder today. The others are Coming soon. Their mockup values are recorded under "Template values" for when they are added.
- **Acceptance criteria:**
  1. `LayoutDesign::schema()['checkboxes']` is:
     - `size`: int, null, 12–24
     - `radius`: int, null, 0–12
     - `border_width`: int, null, 1–3
     - `checked`: color, default `''` (empty = primary). Changed from an enum `[ '', 'text' ]` to a colour picker at the user's request during implementation.
  2. With every token empty, the storefront checkbox looks exactly as it does at `8ad86f4`: 16px, 2px corner, 1px border, primary fill.
  3. Each set token prints one property:
     - size 20 → `--yaycheckout-checkbox-size: 20px`
     - radius 0 → `--yaycheckout-checkbox-radius: 0px`. 0 is a value, not empty.
     - border_width 2 → `--yaycheckout-checkbox-border-width: 2px`
     - checked `#1e1e1e` → `--yaycheckout-checkbox-checked-color: #1e1e1e`; a theme swatch prints its preset custom property
  4. The tick stays 12px, and shrinks only when the box would leave less than ¾ of its size (`min(12px, size × 0.75)`).
  5. Loose radios are unaffected by every token.
  6. The PHP `css()` output and the TS `designCss()` output are identical for the same tokens.
  7. End to end: in a **new** layout from Classic two-column, set Size 20, Radius 6, Border width 2 and Checked `#1e1e1e`, then Save. The editor canvas updates while you edit. The storefront preview then shows a 20px box with 6px corners and a 2px border, which fills with `#1e1e1e` and a white tick when ticked. Resetting each control brings back the acceptance criterion 2 look.
- **Decided with the user:**
  - Settings: size, corner radius, border width, checked colour. No presets.
  - Unticked border: follows the input border colour.
  - Scope: checkboxes only, not radios.

### Template values (from `~/Downloads/WooCommerce Checkout Templates_v1.1.html`)

| Template | size | radius | border_width | checked |
|---|---|---|---|---|
| 01 Classic, 02 Multi step, 03 Split express, 04 Accordion, 05 Upsell, 07 Live receipt | 16 | 2 | 1 | `''` |
| 09 Soft steps | 20 | 6 | 1 | `''` |
| 11 Material | 18 | 2 | 2 | `''` |
| 08 Editorial fashion | 16 | 0 | 1 | `#1e1e1e` |
| 06 Minimal steps, 12 Digital product | no checkbox: leave all null | | | |

Two mockup border colours come from the template's `inputs.border`, not from a checkbox token: Soft steps uses `#949494`, and Editorial uses `#1e1e1e`.

## Global constraints

- **PHP 7.4:** product PHP must run on it. No enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- **Design tokens** are declared once, in `LayoutDesign::schema()`. Each one is that entry, plus its line in `DesignStyles::vars()`, plus the copy in `packages/blocks/src/shared/design-css.ts`. There is no defaults array and no per-key sanitizer (docs/conventions.md).
- **TypeScript copies** of PHP name their owner in a `Mirrors …` comment and change in the same commit as the PHP.
- **Defaults are empty:** every default is `''` for an enum or `null` for an int, and an empty token prints nothing. Tokens are values, never selectors. CSS reads each one with a fallback.
- **Generated output:** never hand-edit `assets/dist/`. Rebuild it with `pnpm build:blocks`.
- **Commits:** Conventional Commits, files staged by name, on `trace/style/checkbox-radio`. Commit only when the user asks.

## Review focus

- **Radius 0** must print `0px`, not be treated as empty. Both `vars()` copies must test `null !== …`, not truthiness. Verified in Task 1, Step 6.
- **A saved layout without the new section** (every existing layout, and imports of older files) must load and save. `from_meta()` fills the section with defaults, and the editor's `normalize()` must include it, or the wp/v2 save is refused (`rest_schema()` marks every section's keys required). Verified in Task 1, Step 6.
- **Clearing Checked** must print no property, so the box fills with the primary colour again. Verified in Task 1, Step 6.
- **A size smaller than 16** must not overflow the tick, per acceptance criterion 4. Verified in Task 1, Step 6.

---

### Task 1: Checkbox tokens, from storage to the drawn box

**Files:**
- Modify:
  - `includes/Models/LayoutDesign.php:85-163`: `schema()` and its docblock
  - `includes/Renderer/DesignStyles.php:115-176`: `vars()`
  - `packages/blocks/src/shared/design-css.ts:54-109`: `vars()`
  - `packages/blocks/src/checkout/layout-design.tsx`: lines 35-48 (`LayoutDesignMeta`, `Section`, `SECTIONS`) and 90-99 (`normalize()`)
  - `assets/css/checkout-base.css:365-399`: the drawn checkbox rules
  - `templates/layouts/classic-two-column/design.json`
  - `includes/PostTypes/LayoutPostType.php`: `show_in_rest.prepare_callback` returns `LayoutDesign::from_meta( $value )->to_meta()`

**Interfaces:**
- Produces:
  - Design section `checkboxes` with keys `size`, `radius`, `border_width`, `checked`
  - Custom properties `--yaycheckout-checkbox-size`, `--yaycheckout-checkbox-radius`, `--yaycheckout-checkbox-border-width` and `--yaycheckout-checkbox-checked-color`
  - TS: `Section` includes `'checkboxes'`, and `LayoutDesignMeta.checkboxes: SectionTokens`

- [ ] **Step 1: Add the `checkboxes` section to `LayoutDesign::schema()`, after `inputs`**

The rules come from acceptance criterion 1. Extend the docblock with one sentence: an empty `checkboxes.checked` fills a ticked box with the primary colour. Its unticked border is `inputs.border`.

- [ ] **Step 2: Print the tokens in `DesignStyles::vars()`, after the input colours, then mirror them in `design-css.ts` `vars()`**

The values and order follow acceptance criterion 3: size, radius, border width, checked colour, each only when set. Destructure `checkboxes` from the sections or tokens. Keep the TS `Mirrors DesignStyles::vars()` comment accurate.

- [ ] **Step 3: Add the section to the editor's meta shape in `layout-design.tsx`**

- `LayoutDesignMeta` gets `checkboxes: SectionTokens`.
- `type Section` gets `'checkboxes'`.
- `SECTIONS` gets `checkboxes: __( 'Checkboxes', 'yaycheckout' )`.
- `normalize()` gets `checkboxes: sectionTokens( design.schema.checkboxes, meta.checkboxes )`.

Check that the sidebar renders `SECTIONS` in key order, so Checkboxes comes after Inputs.

- [ ] **Step 4: Read the properties in `checkout-base.css`**

Split the shared `:is(input[type="checkbox"], input[type="radio"])` rule so that only checkboxes read the new properties:
- `width`/`height`: `var(--yaycheckout-checkbox-size, 16px)`
- `border-width`: `var(--yaycheckout-checkbox-border-width, 1px)`
- `border-radius`: `var(--yaycheckout-checkbox-radius, var(--yaycheckout-neutral-radius))`
- `background-size`: `min(12px, calc(var(--yaycheckout-checkbox-size, 16px) * 0.75))`
- (no `color` override: the checked colour is a stored colour, never `currentColor`)
- When checked, `border-color` and `background-color` are `var(--yaycheckout-checkbox-checked-color, var(--yaycheckout-primary-color))`

The radio rule keeps its literal 18px, 1px and 50%. Update the block comment above the rules: the corner, size and border width now come from the Checkboxes design.

- [ ] **Step 5: Add the section to `templates/layouts/classic-two-column/design.json`**

Insert it between `inputs` and `custom_css`, with keys in schema order and tab indentation:

```json
"checkboxes": { "size": 16, "radius": 2, "border_width": 1, "checked": "" }
```

Write it one key per line, like the rest of the file.

- [ ] **Step 6: Verify**

1. **Build:** `pnpm build:blocks` compiles successfully.
2. **Template test:** `composer test -- --filter LayoutTemplateCatalogueTest` passes. The setup is in `phpunit.xml.dist`, and the Local socket is in docs/known-issues.md.
3. **PHP output.** Run:
   ```
   wp eval 'echo (new \YayCheckout\Renderer\DesignStyles())->css( \YayCheckout\Models\LayoutDesign::from_meta( [ "checkboxes" => [ "size" => 20, "radius" => 0, "border_width" => 2, "checked" => "#1e1e1e" ] ] ), "", false );'
   ```
   Use the per-site socket wrapper from the local-site notes. The output contains `--yaycheckout-checkbox-size: 20px`, `--yaycheckout-checkbox-radius: 0px`, `--yaycheckout-checkbox-border-width: 2px` and `--yaycheckout-checkbox-checked-color: #1e1e1e`. With `[]` instead, the output contains no `checkbox-` property.
4. **Existing layout:** open it in the editor (layout 61), change nothing and press Save. The save succeeds with no REST error, and the stored meta now has `checkboxes` with all-null/empty values. Don't edit an existing draft: autosave writes the edits into it.
5. **Storefront preview** of that layout: "Ship to a different address" and "Add a note" match `8ad86f4`: 16px, 2px, 1px, primary.
6. **Small size:** in a new layout from Classic two-column, set size 12 through the sidebar (Task 2), or through the meta. The tick fits inside the box (about 9px).
7. **Checked colour:** a picked colour fills a ticked box; clearing it brings back the primary colour.

- [ ] **Step 7: Commit**

- Stage: `includes/Models/LayoutDesign.php`, `includes/Renderer/DesignStyles.php`, `packages/blocks/src/shared/design-css.ts`, `packages/blocks/src/checkout/layout-design.tsx`, `assets/css/checkout-base.css`, `templates/layouts/classic-two-column/design.json`
- Message: `feat(design): add checkbox size, radius, border width and checked colour tokens`

### Task 2: Sidebar labels and docs

**Files:**
- Modify:
  - `packages/blocks/src/checkout/layout-design.tsx`: lines 50-71 (`TOKEN_LABELS`, `ENUM_LABELS`) and the `TokenControl` enum options (~:380-392)
  - `docs/architecture.md:45` (and :63 if it lists the input properties)

**Interfaces:**
- Consumes: the Task 1 `checkboxes` section

- [ ] **Step 1: Label the controls**

- **`TOKEN_LABELS`:** add `border_width: __( 'Border width', 'yaycheckout' )` and `checked: __( 'Checked', 'yaycheckout' )`. `size` and `radius` already read "Size" and "Radius". `checked` is a `color` rule, so `ColourSettings` renders it as a picker with no other change.

- [ ] **Step 2: Update `docs/architecture.md`**

- At :45, the list of design sections becomes "(colours, typography, inputs, checkboxes)".
- If :63 lists the properties with their fallbacks, add the four `--yaycheckout-checkbox-*` properties and their fallbacks: 16px, `--yaycheckout-neutral-radius`, 1px and `--yaycheckout-primary-color`.

- [ ] **Step 3: Verify the end-to-end check**

1. Run `pnpm build:blocks`.
2. In a **new** layout from Classic two-column, open Layout design. A "Checkboxes" panel follows Inputs. It has Size, Radius and Border width sliders (px) and a Checked colour item under Custom colours.
3. Run acceptance criterion 7. Each slider's Reset empties its token, and the canvas falls back to the 16px, 2px, 1px look.
4. Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. It passes, unchanged from `dev/edd`. It covers only the SPA; the blocks build in item 1 is what type-checks this code.

- [ ] **Step 4: Commit**

- Stage: `packages/blocks/src/checkout/layout-design.tsx`, `docs/architecture.md`
- Message: `feat(design): label the checkbox settings in the layout design sidebar`
