# Templates follow the Design colours Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec: the Requirements below are the request.

**Status:** done 2026-10-15
**Goal:** A layout created from any prebuilt template restyles when the merchant changes a Design colour (primary, text, background, border), because its blocks no longer carry copies of those colours.
**Spec:** none, planned from the request
**Architecture:** First, delete a template colour when the block's CSS default already paints that token. If the colour equals a token but no default covers it, bind it to that token's "Design: X" swatch. Text on core blocks (paragraph, group, heading, icon) is left unset unless the colour is specific, so it inherits the body text. For the muted grey on YayCheckout blocks, which has no token, add a Muted colour token (`colours.muted`). Left empty, it is derived in CSS from text and background, and a merchant can override it in the Design panel. Colours that are deliberately fixed stay as hex. The shipping and payment hover gets a derived CSS default, so the templates' `#f0f0f0` can go.
**Touches:** `assets/css/design-presets.css` (shared: the storefront and the editor canvas both load it, through `CheckoutBaseStyles`); `packages/blocks/src/checkout/layout-design.tsx`; `packages/blocks/src/{shipping-methods,payment}/style.css`; `templates/layouts/*/markup.php` (9 templates); `docs/architecture.md`; `.claude/skills/build-checkout-template/references/pitfalls.md`
**Tests:** none. The change is CSS and template markup, which the CLAUDE.md test rule doesn't cover. The existing `LayoutTemplateCatalogueTest` runs as a check.

## Requirements

- **Goal:** In a new layout made from a template, changing a Design colour changes every block that showed that colour.
- **Out of scope:**
  - Migration: the plugin has no shipped users, so block defaults change directly and no stored layout needs updating.
  - New stored Design tokens other than `colours.muted`.
  - `preview.html`, which is built from the mock.
  - `design.json` values.
  - Deliberately fixed colours (see the mapping, row F).
- **Acceptance criteria:**
  1. No template's `markup.php` contains `#3858e9` or `#dddddd`. `#f0f0f0` appears only as Upsell's savings-note background and Split express's dark-column text (row F). Editorial's contains no `#d9472f`. `#757575` stays only inside Split express's dark column.
  2. The block picker offers "Design: Muted". With text `#1e1e1e` and background `#ffffff`, it computes to about `rgb(120, 120, 120)`.
  3. A Joined, Cards, Card grid or Single box shipping/payment row with no hover colour set tints on hover to `color-mix(in srgb, currentColor 6%, <row background>)`.
  4. Every template still opens in the editor with no invalid block, and still publishes.
  5. End to end: create a new layout from Classic two-column and set Design primary to `#d9472f`, border to `#1e1e1e` and text to `#0b3d2e`. On the storefront preview, every element that was blue is now red, every `#dddddd` line is `#1e1e1e`, the paragraphs and grey notes are `#0b3d2e`, and the YayCheckout muted parts (order breakdown, terms, steps progress) are a lighter green-grey. Nothing stays `#3858e9` or `#757575`.
- **Decided with the user:**
  - Q: Bind presets or remove values? A: remove, and rely on the defaults. Bind to a Design swatch only where no default covers the colour.
  - Q: What about colours with no token? A: derive them from existing tokens.
  - Q: How does a core block mark a muted note? A: with a derived "Design: Muted" swatch (narrowed by the revised answer below). Remove `#ffffff` where the Design background already covers it.
  - Q (revised): Which core paragraph and group text keeps a colour? A: only specific colours: primary-coloured text, the green trust text, and text inside an inverted column. Everything else, including the `#757575` grey, is unset and inherits the body text. Design: Muted is used only on YayCheckout blocks.
  - Q: How is the panel colour derived? A: panels stay white `#ffffff` on a grey page.
  - Q: What happens to inverted columns and the green trust icons? A: they stay fixed.

## Global constraints

- Never hand-edit `assets/dist/`, `templates/layouts/*/preview.html`, `vendor*/` or `node_modules/`. Rebuild with `pnpm build:blocks` on Node 22 (`nvm use`).
- Product PHP must run on PHP 7.4. This plan changes no PHP logic, only `markup.php` templates.
- Commits follow `type(scope): summary` and end with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`. Branch: `trace/feat/templates-follow-design` from `dev/edd`. Stage files by name.
- Test layouts are always **new** layouts made from the library. Never edit an existing draft, because autosave writes to it.
- A Design swatch is stored two ways:
  - **Core colour attributes** store the preset slug: `"textColor":"yaycheckout-muted"`, `"backgroundColor":"yaycheckout-primary"`, `"borderColor":"yaycheckout-border"`. A per-side border stores `"border":{"bottom":{"color":"var:preset|color|yaycheckout-border",…}}`.
  - **Custom colour attributes** store what the picker stores, which is the swatch's colour: `badgeBackgroundColor`, `fillColor`, `trackColor`, `upcomingTextColor`, and `style.yaycheckout.radio` on shipping and payment. For example `"fillColor":"var(--yaycheckout-design-primary)"`. `CheckoutFragments::css_color()` passes that through.

## Colour mapping (used by Tasks 3–5)

Apply the first row that matches, separately for each value in each template. "Token" means that template's own `design.json` value.

| Row | When the value… | Do this |
|---|---|---|
| A | equals what the block's CSS already defaults to | **Remove it.** This covers: step-button and place-order with `color.background` = primary and `color.text` `#ffffff`; return-to-cart text = primary; steps-progress `fillColor` = primary; step `color.background` `#ffffff`; order-summary-items `badgeTextColor` `#ffffff`; shipping/payment `border` = border token; shipping/payment `radio` = radios.border token; shipping/payment `background` `#ffffff` when colours.background is `#ffffff`; shipping/payment `backgroundHover` `#f0f0f0` (after Task 2); text on a block that inherits page text (core paragraph, group, heading, icon; savings-note) unless it is specific (rows C and F): this includes `#1e1e1e` and the `#757575` grey. Inside such a block's HTML, drop the inline `color:#757575` and `color:#1e1e1e` from links and spans, keeping their other declarations. |
| B | is the outline coupon (transparent, primary text, primary 1px border) | Remove those colours and the border, and add `"className":"is-style-outline"`. The outline style draws all three from primary. |
| C | equals a token but no default covers it | **Design swatch:** `yaycheckout-primary` / `-text` / `-background` / `-border` in the stored form above. Examples: group/step/summary borders `#dddddd`; separator background; toggle, icon and paragraph text = primary; badge background = primary; place-order or coupon background = text; minimal-steps back-button background and border; steps-progress `trackColor` = border. |
| D | is `#757575` on a light area of a YayCheckout block | **Muted:** core colour attributes get `"textColor":"yaycheckout-muted"`; this covers terms, login, field-value, return-to-cart, order-summary-toggle/-breakdown and steps-progress text. A steps-progress whose upcoming steps fall back to the block's fixed `#757575` (Multi-step wizard's segments) gets `"upcomingTextColor":"var(--yaycheckout-design-muted)"`. Shipping/payment `radio` is not Muted: it is removed, so the indicator follows Design → Radios border, then the Inputs border (as loose radios do), which these templates set to `#757575`. Core blocks never reach this row: row A unsets their grey. |
| E | is a `#ffffff` panel or a group/billing/shipping background on a page whose background isn't white | **Keep `#ffffff`.** The user chose white panels. |
| F | is deliberately fixed | **Keep.** This covers: `#2f8a48` trust icons and text; everything inside an inverted column (Editorial's primary summary column, Split express's `#1e1e1e` column), meaning white text, `rgba(255,255,255,…)`, `#2f2f2f`, `#949494`, `#f0f0f0` and the `#757575` coupon border; `#cccccc` breadcrumb separators and Live receipt's `#cccccc` rules; the soft greys `#f0f0f1`, `#f5f5f7` and `#949494` in Minimal steps, Soft steps and Split express, and Upsell's savings-note background `#f0f0f0`; `#ffffff` text on a primary fill. An inverted column's own background still follows row C (`yaycheckout-primary`, or `yaycheckout-text` for `#1e1e1e`). |

Where row A removes a value that was a near-match rather than equal (Minimal steps' `#dddddd` against its border token `#e0e0e0`), accept the token's value.

## Review focus

- **Core block validity.** A static core block (paragraph, group, separator, column, heading) saves HTML that must match its attributes. Changing `style.color.text` to `textColor` changes its classes and inline style. The editor validity check in each template task catches a mismatch.
- **An explicit hover colour still wins.** Task 2 changes the unset hover from "same as idle" to a 6% tint. A row whose hover colour is set must keep it, which Task 2 Step 3 checks.
- **Muted on a dark Design.** With a dark background (`#111111`) and light text (`#f5f5f5`), Muted must stay legible, around 60% of the way from background to text. Task 1 Step 3 checks this.
- **Lost hierarchy on core notes.** Grey paragraphs (breadcrumbs, helper lines, trust notes) become full body text. The user accepted this. Task 3 Step 2 compares the storefront against the pre-change look, and any note that reads wrong is reported, not re-coloured.
- **Gradient background.** When the Design background is a gradient, `--yaycheckout-color-background` is unset and Muted mixes toward the base preset (`#fff`). That's acceptable, and Task 1 Step 3 confirms it doesn't break.

---

### Task 1: "Design: Muted" swatch

> Revised after review: Muted first shipped as a derived swatch only, and did not appear in the Design panel's Colours. It is now a token, `colours.muted` (default `''`). It is in `LayoutDesign::schema()`, prints `--yaycheckout-color-muted` from `DesignStyles::vars()` and `design-css.ts`, and is in every template's `design.json` as `"muted": ""`. `--yaycheckout-design-muted` reads that token first, then the 60% mix. The palette builds the swatch from the schema like the other four.

> Revised again: the `help_text.color` token is removed. Help text under every field is the Muted colour (`checkout-base.css`), and `help_text` keeps only `size`.

**Files:**
- Modify: `assets/css/design-presets.css`
- Modify: `packages/blocks/src/checkout/layout-design.tsx`, in `addDesignPalette()`, near line 221
- Modify: `docs/architecture.md`, in the design-presets sentence of the layouts bullet (line 45)

**Interfaces:**
- Produces:
  - The custom property `--yaycheckout-design-muted`.
  - The preset `--wp--preset--color--yaycheckout-muted`.
  - The classes `.has-yaycheckout-muted-color`, `.has-yaycheckout-muted-background-color` and `.has-yaycheckout-muted-border-color`.
  - The palette slug `yaycheckout-muted`, labelled "Design: Muted".

- [x] **Step 1: Define Muted in `design-presets.css`**

  In the `:root, .editor-styles-wrapper` block, add `--yaycheckout-design-muted: color-mix(in srgb, var(--yaycheckout-design-text) 60%, var(--yaycheckout-design-background));` and `--wp--preset--color--yaycheckout-muted: var(--yaycheckout-design-muted);`. Add the three `has-*` classes in the same form as the existing four. In the header comment, say that Muted is derived from text and background and is not a token.

- [x] **Step 2: Add the swatch in `addDesignPalette()`**

  Append one swatch after the token swatches: `{ name: __( 'Design: Muted', 'yaycheckout' ), slug: DESIGN_PRESET_PREFIX + 'muted', color: 'var(--yaycheckout-design-muted)' }`. Don't add it to `TOKEN_LABELS`, which also labels the Design panel's controls. Update the function's docblock, and update the architecture.md sentence so it lists Muted as a derived swatch with no token.

- [x] **Step 3: Verify**

  Run `pnpm build:blocks`. Expected: it compiles successfully.

  Create a new layout from Classic two-column, open it in the editor, select a paragraph, and open Text colour. Expected: "Design: Muted" appears after "Design: Border". Pick it, then in the canvas console run `getComputedStyle(<that p>).color`. Expected: about `rgb(120, 120, 120)`.

  In the Design panel, set background `#111111` and text `#f5f5f5`. Expected: the paragraph becomes a mid grey, about `rgb(154, 154, 154)`. Set a gradient background. Expected: the paragraph is still grey and there is no console error.

  The blocks' TypeScript has no lint config (`.claude/rules/blocks.md`: `pnpm build:blocks` is the only check), so the build above stands in for lint.

- [x] **Step 4: Commit**

  Stage: `assets/css/design-presets.css`, `packages/blocks/src/checkout/layout-design.tsx`, `docs/architecture.md`
  Message: `feat(blocks): add a derived Design: Muted swatch`

### Task 2: Derived hover default for shipping and payment rows

**Files:**
- Modify: `packages/blocks/src/shipping-methods/style.css`, in the hover rules of joined (~170), cards (~211), grid (~252) and box (~419)
- Modify: `packages/blocks/src/payment/style.css`, in the same four layouts (~172, ~204, ~236, ~378)

**Interfaces:**
- Consumes: nothing.
- Produces: an unset `--yaycheckout-{shipping,payment}-bg-hover` resolves to `color-mix(in srgb, currentColor 6%, var(--yaycheckout-{shipping,payment}-bg, var(--yaycheckout-color-background, #fff)))` in those four layouts. Cards with checkmark and Divider lines don't change.

- [x] **Step 1: Change the hover fallbacks**

  Replace the last fallback of each `-bg-hover` var with the mix above. For box, that replaces `transparent`. Next to the 4% selected-tint sentence from #175, extend the header comment of both files with "an unset hover tints the row with the text colour at 6%".

- [x] **Step 2: Build**

  Run `pnpm build:blocks`. Expected: it compiles successfully.

- [x] **Step 3: Verify**

  On a new layout from Minimal steps, open the storefront preview (`?page_id=9&add-to-cart=29&yaycheckout_preview=<token>`). Hover over an unselected shipping row. Expected: the background reads about `rgb(242, 242, 242)`.

  In the editor, set that block's Hover background to `#ffe0e0` and save. Expected: hover shows `#ffe0e0`.

- [x] **Step 4: Commit**

  Stage: both `style.css` files
  Message: `feat(blocks): tint hovered shipping and payment rows by default`

### Task 3: Grey-page templates follow Design

**Files:**
- Modify: `templates/layouts/{accordion-one-page,classic-two-column,live-receipt,multi-step-wizard,upsell-cross-sell}/markup.php`

**Interfaces:**
- Consumes: the Task 1 slug `yaycheckout-muted` and the `--yaycheckout-design-muted` var, plus the Task 2 hover default.

- [x] **Step 1: Apply the colour mapping to each block comment, and to its saved HTML for static core blocks**

  For each core block, edit the comment and the HTML together, so that the classes and inline style are what core's save would print. Copy the class pattern from an existing preset-coloured block if there is one. If there isn't, take it from the validity check in Step 2. List what remains with `grep -noE '#[0-9a-fA-F]{6}|rgba\([^)]*\)' <file>`. Expected: every remaining value is a row E or F colour.

- [x] **Step 2: Verify**

  Run `grep -nE '#3858e9|#dddddd|#f0f0f0|#757575' templates/layouts/{accordion-one-page,classic-two-column,live-receipt,multi-step-wizard,upsell-cross-sell}/markup.php`. Expected: only Upsell's savings-note line (row F).

  Run `composer lint -- <the five files>`. Expected: no errors.

  Run `composer test -- --filter LayoutTemplateCatalogueTest`. Use the per-site socket from `docs/known-issues.md`. Expected: OK. It used to fail with `classic-two-column: help_text` because some `design.json` files lacked sections; every template's `design.json` now equals its `to_meta()`.

  The editor validity check is lenient: `wp.blocks.parse()` auto-recovers. Also run `wp.blocks.validateBlock(block)[0]` on every parsed block. Expected: no invalid block except Live receipt's four numbered pill paragraphs, which are already invalid on `dev/edd`.

  For each of the five, create a new layout from the library and open it. Run the validity check from `.claude/skills/build-checkout-template/references/checklist.md` step 4. Expected: `[]`.

  Open the storefront preview at the default Design. Expected: it looks the same as before, except for the 6% hover and core paragraphs now in body text. Set primary to `#d9472f`. Expected: buttons, coupon, badge, toggle and selected rows all turn red.

- [x] **Step 3: Commit**

  Stage: the five `markup.php` files
  Message: `feat(templates): grey-page templates follow the Design colours`

### Task 4: White-page step templates follow Design

**Files:**
- Modify: `templates/layouts/{minimal-steps,soft-steps}/markup.php`

**Interfaces:**
- Consumes: the same as Task 3.

- [x] **Step 1: Apply the colour mapping**

  Same as Task 3 Step 1. Specific to these two:
  - The Minimal steps back-button follows row C. Its background is `yaycheckout-background`, its text is `yaycheckout-text`, and its border is `yaycheckout-border`.
  - Soft steps' coupon background is `yaycheckout-text` and its text is `yaycheckout-background`.
  - Soft steps' `#757575` paragraphs are unset (row A). Its `field-value` and `login` text become Muted (row D).
  - The `steps-progress` `trackColor` becomes `var(--yaycheckout-design-border)`, and `fillColor` is removed.
  - `upcomingTextColor` `#949494`, `#f5f5f7` and `borderHover` `#949494` stay (row F).
  - On these white pages, shipping/payment `background` `#ffffff` is removed (row A). Minimal steps' pinned footer group `#ffffff` binds to `yaycheckout-background`, because it must stay opaque.
  - Soft steps' back button (transparent background, primary text) is the block's default, so both colours are removed. Its toggle and cart icon `#1e1e1e` are unset.
  - Minimal steps' toggle `#757575` becomes Muted on the toggle, and its count paragraph inherits.

- [x] **Step 2: Verify**

  Run `grep -nE '#3858e9|#dddddd|#757575' templates/layouts/{minimal-steps,soft-steps}/markup.php`. Expected: no output.

  Run the lint, the catalogue test, the editor validity check (`[]`) and a storefront check as in Task 3 Step 2, for both templates. Set primary to `#d9472f`. Expected: the progress fill, step buttons, place order and selected rows turn red.

- [x] **Step 3: Commit**

  Stage: both `markup.php` files
  Message: `feat(templates): step templates follow the Design colours`

### Task 5: Inverted-column templates follow Design, and the template-building rule

**Files:**
- Modify: `templates/layouts/{editorial-fashion,split-express}/markup.php`
- Modify: `.claude/skills/build-checkout-template/references/pitfalls.md`, in the "Design tokens" section

**Interfaces:**
- Consumes: the same as Task 3.

- [x] **Step 1: Apply the colour mapping**

  Same as Task 3 Step 1. Inside the inverted column, everything stays as it is (row F) except the column background:
  - Editorial's `#d9472f` column becomes `yaycheckout-primary`.
  - Split express's `#1e1e1e` column becomes `yaycheckout-text`.

  On the light side:
  - Editorial's place-order `#1e1e1e` background becomes `yaycheckout-text`, and its coupon text becomes primary (row C).
  - The `#1e1e1e` border and radio on shipping/payment are removed (row A).
  - `#757575` and `#1e1e1e` on core paragraphs and icons are unset (row A). On YayCheckout blocks (terms, login, return-to-cart), `#757575` becomes Muted (row D).
  - Split express's separators become `yaycheckout-border`.
  - Editorial's Divider lines layout falls back to the text colour, not the border, so its shipping/payment `border` binds to `var(--yaycheckout-design-border)`.
  - Split express's white right-hand column is unset, because the page is white. Its badge text `#1e1e1e` binds to `var(--yaycheckout-design-text)`, so it matches the column, which now follows Text.
  - Editorial's `design.json` `radios.checked` `#d9472f` (a copy of primary) becomes `''`, so the selected radio follows primary.
  - The test order in Step 4 needs an active layout. A preview link can't place orders.

- [x] **Step 2: Add the rule to `pitfalls.md`**

  Add this to "Design tokens": "A block colour that equals a design token is not written as hex. Leave it unset when the block's default paints that token. Otherwise use the "Design: X" swatch (core attributes store the slug, custom colour attributes store `var(--yaycheckout-design-<token>)`). Leave text on core paragraphs and groups unset unless it is a specific colour, so it inherits the body text. Use "Design: Muted" for secondary text on YayCheckout blocks. Hex is only for colours the Design must not change: inverted panels, success green, soft greys."

- [x] **Step 3: Verify**

  Run `grep -nE '#3858e9|#dddddd|#d9472f' templates/layouts/{editorial-fashion,split-express}/markup.php`. Expected: no output.

  Run `grep -n '#757575' templates/layouts/split-express/markup.php`. Expected: only the coupon border inside the dark column.

  Run the lint, the catalogue test and the editor validity check (`[]`) for both templates. In Editorial, change primary. Expected: the summary column and the radio dots follow it. In Split express, change text. Expected: the dark column follows it.

- [x] **Step 4: End-to-end check (acceptance criterion 5)**

  Create a new layout from Classic two-column. Set primary `#d9472f`, border `#1e1e1e` and text `#0b3d2e`, then open the storefront preview. Expected: nothing outside the form fields and the unticked shipping/payment radios is `#3858e9`, `#dddddd` or `#757575`. Those two keep `#757575` because they read Classic's Inputs border, a Design setting this check does not change. In DevTools, run `[...document.querySelectorAll('.yaycheckout-checkout *')].filter(e=>!e.closest('.form-row')&&!e.matches('input,select,textarea,.yaycheckout-shipping-methods__indicator')&&!/payment_method/.test(e.className)&&/rgb\(56, 88, 233\)|rgb\(117, 117, 117\)|rgb\(221, 221, 221\)/.test(getComputedStyle(e).color+getComputedStyle(e).backgroundColor+getComputedStyle(e).borderColor)).length`. Expected: `0`.

  Place a test order. Expected: it completes.

- [x] **Step 5: Commit**

  Stage: both `markup.php` files and `pitfalls.md`
  Message: `feat(templates): inverted-column templates follow the Design colours`
