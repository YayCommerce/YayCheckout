# Checkbox unticked colours Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract.

**Status:** done 2026-10-15
**Goal:** Let a merchant set the unticked border and background colours of the checkout's checkboxes and loose radios in Layout design > Checkboxes, separately from the inputs, so a layout can have filled, borderless inputs next to white checkboxes with a grey border (template 09, Soft steps).
**Spec:** none, planned from the request
**Architecture:** Two new empty-by-default `color` tokens, `checkboxes.border` and `checkboxes.background`, in `LayoutDesign::schema()`. `DesignStyles::vars()` and its TS copy print `--yaycheckout-checkbox-border-color` and `--yaycheckout-checkbox-background` when set. The shared checkbox-and-radio rule in `checkout-base.css` reads each one first and falls back to today's input chain. The previous checkbox plan (`2026-10-13-checkbox-design-settings.md`) rejected a separate unticked border; template 09 needs it because its inputs fill `#f5f5f7` with no visible border, while its checkboxes are white with a `#949494` border. The sidebar needs no new code: it renders every `color` rule of a section under "Custom colours", and `TOKEN_LABELS` already labels `border` and `background`.
**Touches:**
- `includes/Models/LayoutDesign.php` (shared: projected by `LayoutPostType` meta registration and its REST `prepare_callback`, `LayoutTemplateCatalogue::design()`, `LayoutRepository` reset and insert, and Pro export, import and `LayoutImportReview`. All of them go through `schema()`, `from_meta()` and `to_meta()`, so none needs editing.)
- `includes/Renderer/DesignStyles.php`
- `packages/blocks/src/shared/design-css.ts` (shared: also the SPA's `@blocks-shared`)
- `assets/css/checkout-base.css`
- `docs/architecture.md`

**Tests:** none new. CLAUDE.md's test rule doesn't cover design tokens, and the one existing guard, `tests/Integration/LayoutTemplateCatalogueTest.php`, already fails on `dev/edd` (see Global constraints).

## Requirements

- **Goal:** In Layout design > Checkboxes, under Custom colours, a merchant sees two new colour pickers, Border and Background. They recolour an unticked checkbox or loose radio on the storefront and in the editor canvas. Left empty, both follow Inputs > Border and Inputs > Background, as they do today.
- **Out of scope:**
  - **Payment and Shipping radios:** those blocks draw their own indicator with their own block settings. Their real radios also read the new tokens, but stay at opacity 0, so nothing shows (checked in the storefront preview).
  - **Ticked state:** the ticked checkbox keeps `checkboxes.checked`, then primary. A ticked radio keeps its 5px primary border.
  - **Focus outline:** it keeps `inputs.focus_border`, then primary.
  - **Template `design.json` files:** see Global constraints. Soft steps' `design.json` gets the values in its own template branch.
- **Acceptance criteria:**
  1. `LayoutDesign::schema()['checkboxes']` keys, in this order: `size`, `radius`, `border_width`, `border` (`color`, default `''`), `background` (`color`, default `''`), `checked`.
  2. With both new tokens empty, the storefront and canvas CSS is byte-identical to `dev/edd` at `74d0178` for the same design: no new property prints, and checkboxes and radios look unchanged.
  3. `border` `#949494` prints `--yaycheckout-checkbox-border-color: #949494`. `background` `#ffffff` prints `--yaycheckout-checkbox-background: #ffffff`. A theme swatch prints its `var(--wp--preset--color--<slug>)`.
  4. An unticked checkbox and an unticked loose radio read `--yaycheckout-checkbox-border-color`, then `--yaycheckout-input-border-color`, then `--yaycheckout-border-color`. Their fill reads `--yaycheckout-checkbox-background`, then `--yaycheckout-input-background`, then `--yaycheckout-color-background`, then `#fff`.
  5. A ticked checkbox still fills with `checkboxes.checked`, then primary, and shows its tick. A ticked radio still shows a 5px primary ring around the unticked background.
  6. The PHP `css()` output and the TS `designCss()` output are identical for the same tokens.
  7. End to end: in a **new** layout from Classic two-column, set Inputs > Background `#f5f5f7` and Inputs > Border `#f5f5f7`, then Checkboxes > Border `#949494` and Checkboxes > Background `#ffffff`, then Save. The canvas updates while you edit. In the storefront preview, text inputs are grey with no visible border, and the "Add a note" and "Billing address is the same" checkboxes are white with a `#949494` border. Ticking one fills it primary with a white tick. Clearing both checkbox colours brings back grey checkboxes with the input border.
- **Decided with the user:**
  - Tokens: both the unticked border and the unticked background.
  - Radios: loose radios read the new tokens too. The section keeps its name, Checkboxes.
  - Fallback: each token falls back to the input's token, so existing layouts are unchanged.

## Global constraints

- **PHP 7.4:** product PHP must run on it. No enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- **Design tokens** are declared once, in `LayoutDesign::schema()`. Each one is that entry, plus its line in `DesignStyles::vars()`, plus the copy in `packages/blocks/src/shared/design-css.ts` (docs/conventions.md). There is no defaults array and no per-key sanitizer.
- **TypeScript copies** of PHP name their owner in a `Mirrors …` comment and change in the same commit as the PHP.
- **Defaults are empty** (`''` for a colour) and an empty token prints nothing. Tokens are values, never selectors, and CSS reads each one with a fallback.
- **Generated output:** never hand-edit `assets/dist/`. Rebuild it with `pnpm build:blocks`.
- **Already failing on `dev/edd`:** `LayoutTemplateCatalogueTest::test_every_available_template_is_publishable_and_sets_every_section` fails for every built template. No `design.json` has the `help_text` section; `split-express` also lacks `checkboxes`; `classic-two-column`, `minimal-steps` and `multi-step-wizard` lack keys or carry renamed ones (`text`, `focus`). This plan doesn't touch those files and adds no new failure mode: the expected result is the same failures. Confirmed during implementation: on both `dev/edd` and the branch it stops at its first failure, `classic-two-column: help_text`. Fixing them is separate work.
- **Git:** branch `trace/feat/checkbox-unticked-colours` from an up-to-date `dev/edd`. Conventional Commits, files staged by name, the trailer CLAUDE.md asks for. Commit only when the user asks.

## Review focus

- **A saved layout without the new keys** (every existing layout, and imports of older files) must load and save. `from_meta()` fills them, and the editor's `normalize()` builds each section from `design.schema`, so both new keys reach the wp/v2 save. Verified in Task 1, Step 5, item 4.
- **The ticked state must still win over the new unticked properties.** The `:checked` checkbox rule sets `border-color` and `background-color` after the shared rule; the new properties change only the shared rule's values, so specificity and order stay as they are. Verified in Task 1, Step 5, item 5.
- **A radio's inner dot is the unticked background** showing through its 5px ring. A dark `checkboxes.background` therefore darkens a ticked radio's centre. That follows from the user's choice and is expected; check it once. Verified in Task 1, Step 5, item 5.
- **`design-css.ts` is shared with the SPA.** Type-check the SPA after the change. Verified in Task 2, Step 2, item 3.

---

### Task 1: Unticked colour tokens, from storage to the drawn control

**Files:**
- Modify:
  - `includes/Models/LayoutDesign.php:84-95`: the `schema()` docblock
  - `includes/Models/LayoutDesign.php:180-203`: the `checkboxes` section
  - `includes/Renderer/DesignStyles.php:186-203`: the checkbox part of `vars()`
  - `packages/blocks/src/shared/design-css.ts:121-138`: the checkbox part of `vars()`
  - `assets/css/checkout-base.css:373-386`: the drawn checkbox-and-radio comment and shared rule

**Interfaces:**
- Produces:
  - Design tokens `checkboxes.border` and `checkboxes.background`, type `color`, default `''`
  - Custom properties `--yaycheckout-checkbox-border-color` and `--yaycheckout-checkbox-background`

- [ ] **Step 1: Add `border` and `background` to `LayoutDesign::schema()['checkboxes']`, between `border_width` and `checked`**

Each is `[ 'type' => 'color', 'default' => '' ]`. In the docblock, replace "its unticked border is inputs.border" with: checkboxes.border and checkboxes.background colour an unticked checkbox or loose radio, and each falls back to the input's.

- [ ] **Step 2: Print the tokens in `DesignStyles::vars()`, after `checkbox-border-width` and before `checkbox-checked-color`**

When not `''`: `checkbox-border-color` ← `$checkboxes['border']`, `checkbox-background` ← `$checkboxes['background']`. Mirror both in `design-css.ts` `vars()` at the same position, with the same `String( … ?? '' )` and `'' !==` checks as `checked`.

- [ ] **Step 3: Read the properties in the shared rule in `checkout-base.css`**

In `.yaycheckout-checkout :is(input[type="checkbox"], input[type="radio"])`:
- `border`: `1px solid var(--yaycheckout-checkbox-border-color, var(--yaycheckout-input-border-color, var(--yaycheckout-border-color)))`
- `background`: `var(--yaycheckout-checkbox-background, var(--yaycheckout-input-background, var(--yaycheckout-color-background, #fff))) center no-repeat`

Leave the `:checked`, radio, focus and forced-colours rules as they are. In the comment above, replace "the input's border and background" with: "the Checkboxes design's unticked border and background, falling back to the input's". Also say that loose radios read them too.

- [ ] **Step 4: Build**

Run: `pnpm build:blocks`
Expected: compiles with no errors.

- [ ] **Step 5: Verify**

1. **PHP output.** Run it with the per-site socket wrapper from the local-site notes:
   ```
   wp eval 'echo (new \YayCheckout\Renderer\DesignStyles())->css( \YayCheckout\Models\LayoutDesign::from_meta( [ "checkboxes" => [ "border" => "#949494", "background" => "#ffffff" ] ] ), "", false );'
   ```
   Expected: the output contains `--yaycheckout-checkbox-border-color: #949494` and `--yaycheckout-checkbox-background: #ffffff`. With `[]` instead, the output contains neither, and it matches the same command run on `dev/edd` byte for byte.
2. **Invalid value.** `LayoutDesign::from_meta( [ "checkboxes" => [ "border" => "red;}" ] ] )->to_meta()["checkboxes"]["border"]` is `''`.
3. **Lint:** `composer lint -- includes/Models/LayoutDesign.php includes/Renderer/DesignStyles.php` reports nothing.
4. **Existing layout.** Open an existing layout in the editor, change nothing and press Save. The save succeeds with no REST error, and `wp post meta get <id> <Layout::META_DESIGN>` now has `checkboxes.border` and `checkboxes.background` as `''`. Don't edit an existing draft: autosave writes the edits into it.
5. **Storefront preview** of that layout: "Add a note" and "Ship to a different address" checkboxes and any loose radio look as they do on `dev/edd`. Then, in a **new** layout from Classic two-column, set both tokens through the meta (or the sidebar, Task 2). Expected: an unticked checkbox takes them; a ticked one still fills primary with a white tick; a ticked radio shows a 5px primary ring around the new background.

- [ ] **Step 6: Commit**

- Stage: `includes/Models/LayoutDesign.php`, `includes/Renderer/DesignStyles.php`, `packages/blocks/src/shared/design-css.ts`, `assets/css/checkout-base.css`
- Message: `feat(design): add unticked border and background colours for checkboxes and radios`

### Task 2: Sidebar check and docs

**Files:**
- Modify: `docs/architecture.md:45` and `:64`

**Interfaces:**
- Consumes: Task 1's tokens and custom properties

- [ ] **Step 1: Update `docs/architecture.md`**

- :64: in the checkbox clause, add `-border-color` and `-background` to the list of properties a checkbox reads. Say that a loose radio reads those two, and that they fall back to `--yaycheckout-input-border-color` and `--yaycheckout-input-background`. Give the input fallbacks in the same parenthesis as the existing ones.
- :45 needs no change: it names sections, not keys.

- [ ] **Step 2: Verify end to end**

1. Run `pnpm build:blocks`.
2. In a **new** layout from Classic two-column, open Layout design > Checkboxes. Under Custom colours, Border, Background and Checked are listed, in schema order. Labels come from `TOKEN_LABELS`; no code change is expected. If the order or a label is wrong, fix it in `packages/blocks/src/checkout/layout-design.tsx` and stage that file too.
3. Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. It passes, unchanged from `dev/edd`.
4. Run acceptance criterion 7.
5. Run `composer test -- --filter LayoutTemplateCatalogueTest` (setup in `phpunit.xml.dist`, Local socket in docs/known-issues.md). Expected: the same failures as on `dev/edd` (see Global constraints), and no new one.

- [ ] **Step 3: Commit**

- Stage: `docs/architecture.md`, plus `packages/blocks/src/checkout/layout-design.tsx` only if Step 2 item 2 changed it
- Message: `docs(architecture): note the checkbox unticked colour properties`
