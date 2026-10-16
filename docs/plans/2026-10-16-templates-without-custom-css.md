# Templates without custom CSS Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract.

**Status:** active
**Goal:** The three templates that ship custom CSS (Editorial fashion, Minimal steps, Soft steps) get the same look from block settings and design tokens, with `custom_css` empty, so the editor canvas (which never prints layout CSS) matches the storefront.
**Spec:** none, planned from the request
**Architecture:** Each rule moves to the most general home that can carry it: a base-CSS fallback when it is right for every layout (an empty Inputs background), a block support or attribute when merchants need it on any layout (Place Order as a flex row with a Spread option; Width and text alignment on Step and Back buttons), and template markup or tokens otherwise (core Column width, Group min height and flex layout, the Link hover token). New block CSS gives sensible defaults, so a block with no setting looks right without CSS.
**Touches:** `assets/css/checkout-base.css` (shared: every layout, storefront and editor canvas), `packages/blocks/src/place-order/`, `packages/blocks/src/pro/step-button/`, `packages/blocks/src/pro/back-button/`, `includes/Blocks/BlockRegistry.php`, three templates' `markup.php` and `design.json`, `docs/architecture.md`, `.claude/skills/build-checkout-template/references/pitfalls.md`
**Tests:** none. CLAUDE.md's test rule doesn't cover CSS or template markup. The existing `LayoutTemplateCatalogueTest` already checks every template publishes and its `design.json` round-trips; every task ends in an exact manual check.

## Requirements

- **Goal:** No template ships custom CSS, and each still looks as it does today, except for the differences accepted below.
- **Out of scope:** the custom CSS features themselves. The layout's custom CSS (`LayoutDesign::custom_css`, Layout design sidebar) and the site-wide `advanced.custom_css` (Settings → Advanced) stay. The HTML mocks and `preview.html` files are not changed either.
- **Acceptance criteria:**
  1. `custom_css` is `""` in every `templates/layouts/**/design.json`: `git grep -n '"custom_css": "[^"]' -- templates/layouts` prints nothing.
  2. An input whose layout leaves Inputs → Background empty is transparent: it shows whatever is behind it (the page, or a coloured panel), not the layout's Background colour. Select2's dropdown, checkboxes and radios keep their solid fallback.
  3. Place Order lays out its label as a flex row: centred by default, following the block's text alignment, with a small gap between the text and each live value. A new **Spread** setting pushes the label and live values to opposite ends.
  4. Step Button and Back Button have Width (Styles → Dimensions, with the 25/50/75/100% presets) and text alignment; at 100% width with centre alignment, the label and arrow sit centred.
  5. End to end: a new layout created from each of the three templates shows no block-validation warning in the editor. On the storefront at 1280px and 375px it matches today's version of the same template, apart from the differences listed under **Accepted differences**.
- **Accepted differences:** Editorial fashion's coupon field in the terracotta panel is transparent, with the layout's dark text and dark underline, not white. Editorial's underline fields lose their 12px side padding, because the Inputs background is now empty. On phones, Soft steps' header bar wraps in source order where it lacks room: at 375px the logo, the dots and the total each take their own left-aligned row (checked and accepted), instead of logo and total sharing the first row.
- **Decided with the user:**
  - Remove custom CSS from the templates only, and keep the merchant custom CSS fields.
  - The coupon field in the dark panel gets no block-level input colours. Its background comes from an empty Inputs background token, which becomes transparent for every layout. Text and border stay on their current fallbacks.
  - Place Order becomes a flex button first, then gains a Spread option, with default CSS for the general case.

## Global constraints

- Never hand-edit generated output: `assets/dist/`, `templates/layouts/**/preview.html`. Edit `packages/blocks/src/<block>/`, then run `pnpm build:blocks`.
- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in `boot_pro()`. Block names as strings in `BlockRegistry::ORDER` and `block_settings()` are not a `YayCheckout\Pro` reference, so `scripts/check-pro-boundary.sh` allows them.
- Template markup must be exactly what the editor saves (the header of each `markup.php`): build the change in the block editor, copy it from the Code editor, then put back the PHP.
- Edit `design.json` values in place, keeping every key. `LayoutTemplateCatalogueTest` asserts the file equals `LayoutDesign::to_meta()`.
- Branch `trace/refactor/templates-without-custom-css` from `dev/edd`. Conventional Commits; stage files by name.
- Test template edits on a new layout created from the template, never on an existing draft, because autosave writes to it.

## Review focus

- **A merchant layout with an empty Inputs background whose fields sit on a coloured Group.** The fields now show the Group's colour, not the page Background. Check this in Task 1, Step 2.
- **Place Order labels that relied on inline whitespace.** Minimal steps' `Pay` + live value with prefix `" "` would collapse to `Pay$42` inside a flex row. The default gap has to restore a visible space, and every template's Place Order must still read correctly. Task 2, Step 4 covers this.
- **Place Order's Left / Centre / Right align classes.** They used `display: block; width: fit-content`, and must stay a fitted flex box in the chosen place. Task 2, Step 4.
- **Step and Back buttons in existing layouts with no Width.** They must stay `fit-content`, with their label at the start, exactly as today. Task 3, Step 3.
- **Editorial fashion's sticky panel.** The coupon must sit at the bottom of the viewport on a 1280×800 window while the right column scrolls. On phones, the panel fills the first screen as it does today. Task 4, Step 4.

---

### Task 1: An empty Inputs background is transparent

**Files:**
- Modify: `assets/css/checkout-base.css:322` (text inputs, selects, textareas, coupon code, select2 box) and `:472` (the same fields on focus)
- Modify: `docs/architecture.md:65` (the sentence "inputs read `--yaycheckout-input-background` first, then `--yaycheckout-color-background`")

**Interfaces:**
- Produces: the fallback chain `var(--yaycheckout-input-background, transparent)` for field backgrounds, and `var(--yaycheckout-input-focus-background, var(--yaycheckout-input-background, transparent))` on focus. Task 4 relies on it.

- [ ] **Step 1: Drop `--yaycheckout-color-background` from the two field background chains**

Line 322 becomes `var(--yaycheckout-input-background, transparent)`, and line 472 becomes `var(--yaycheckout-input-focus-background, var(--yaycheckout-input-background, transparent))`. Leave lines 374 (select2 dropdown), 406, 419 and 435 (checkbox and radio) on their solid `#fff` chain. Update the comment above line 322 if it names the layout background as a fallback. In `docs/architecture.md:65`, the inputs now read `--yaycheckout-input-background`, then transparent. The select2 dropdown, checkboxes and radios still fall back to `--yaycheckout-color-background`.

- [ ] **Step 2: Verify on the storefront**

Create a layout from **Classic two-column**. In Layout design, clear Inputs → Background, set Colours → Background to `#f0f0f1`, and wrap the Billing section in a Group with background `#222222`. Save, then open the Preview link.
Expected: the Billing inputs show `#222222` through them. In DevTools, the computed `background-color` of `#billing_first_name` is `rgba(0, 0, 0, 0)`. A select2 Country dropdown, when open, is solid `#f0f0f1`.

- [ ] **Step 3: Commit**

Stage: `assets/css/checkout-base.css`, `docs/architecture.md`
Message: `fix(checkout): show what is behind an input when its background token is empty`

### Task 2: Place Order is a flex row with a Spread option

**Files:**
- Modify: `packages/blocks/src/place-order/block.json` (attribute)
- Modify: `packages/blocks/src/place-order/style.css` (display, alignment, align classes)
- Modify: `packages/blocks/src/place-order/render.php` (modifier class)
- Modify: `packages/blocks/src/place-order/edit.tsx` (modifier class and the toggle)

**Interfaces:**
- Produces: attribute `spread` (`boolean`, default `false`), and the class `yaycheckout-place-order--spread` on the button when it is true. Task 4 sets `"spread":true`.

- [ ] **Step 1: Add `"spread": { "type": "boolean", "default": false }` to `attributes` in `block.json`**

- [ ] **Step 2: Make the button a flex row in `style.css`**

- `:where(.wp-block-yaycheckout-place-order)`: `display: inline-flex; align-items: center; justify-content: center; flex-wrap: wrap; column-gap: 0.25em;`, keeping `box-sizing: border-box`. The gap stands in for the space a flex row collapses between the text and a live value.
- `.wp-block-yaycheckout-place-order.has-text-align-left` → `justify-content: flex-start`, and `.has-text-align-right` → `flex-end`. Centre is the default.
- `.wp-block-yaycheckout-place-order.yaycheckout-place-order--spread` → `justify-content: space-between; flex-wrap: nowrap;`, after the alignment rules so it wins.
- The `.alignleft/.aligncenter/.alignright` rule changes `display: block` to `display: flex`, keeping `width: fit-content; float: none`.
- Update the file's first comment: the button is now an inline flex row sized to its label.

- [ ] **Step 3: Print the modifier class from the attribute**

In `render.php`, add ` yaycheckout-place-order--spread` to the `get_block_wrapper_attributes()` class when `! empty( $attributes['spread'] )`. In `edit.tsx`, add the same class to `useBlockProps`, which mirrors render.php's classes. Add an `InspectorControls` › `PanelBody` "Settings" with a `ToggleControl`:
- label `__( 'Spread', 'yaycheckout' )`
- help `__( 'Puts the label and the live values at opposite ends of the button.', 'yaycheckout' )`
- `checked={ attributes.spread }`

- [ ] **Step 4: Build and verify**

Run: `pnpm build:blocks`
Expected: it exits 0.

Run: `composer lint -- packages/blocks/src/place-order/render.php`
Expected: no errors.

Then create a layout from each of the eight templates (Pro build). Open each one's Preview with a product in the cart, at 1280px.
Expected:
- Every Place Order label reads as before, with a space between the text and the total. Minimal steps shows `Pay $…`, not `Pay$…`.
- A button set to Left alignment sits at the start of its row, at its own width.
- In the editor, toggling Spread on a 100%-width Place Order whose label holds the Order total moves the total to the right edge on the canvas. After Save, the Preview shows the same.

- [ ] **Step 5: Commit**

Stage: the four files above
Message: `feat(blocks): lay Place Order out as a flex row, with a Spread option`

### Task 3: Width and text alignment on Step and Back buttons

**Files:**
- Modify: `packages/blocks/src/pro/step-button/block.json`, `packages/blocks/src/pro/back-button/block.json` (supports)
- Modify: `packages/blocks/src/pro/step-button/style.css`, `packages/blocks/src/pro/back-button/style.css` (alignment)
- Modify: `includes/Blocks/BlockRegistry.php:156` and the `block_settings()` docblock (width presets)

**Interfaces:**
- Produces: `style.dimensions.width` and `style.typography.textAlign` on `yaycheckout/step-button` and `yaycheckout/back-button`. Task 5 sets `"dimensions":{"width":"var:preset|dimension|100"}` and `"typography":{"textAlign":"center"}`.

- [ ] **Step 1: Add the supports to both `block.json` files**

Add `"dimensions": { "width": true, "__experimentalDefaultControls": { "width": true } }`. Under `typography`, add `"textAlign": true`.

- [ ] **Step 2: Map text alignment to the flex row, and register the presets**

In both `style.css` files, add `.yaycheckout-step-button.has-text-align-center { justify-content: center; }` and `.has-text-align-right { justify-content: flex-end; }`, and the same for `.yaycheckout-back-button`. Also set `box-sizing: border-box` on both buttons, so a 100% width includes the padding. In `BlockRegistry::block_settings()`, give `yaycheckout/step-button` and `yaycheckout/back-button` the same `'dimensions' => [ 'dimensionSizes' => $sizes ]` as Place Order, and name them in the docblock's first bullet.

- [ ] **Step 3: Build and verify**

Run: `pnpm build:blocks`
Expected: it exits 0.

Run: `composer lint -- includes/Blocks/BlockRegistry.php`
Expected: no errors.

Create a layout from **Multi-step wizard**, whose buttons have no Width set, and open its Preview.
Expected: its Continue and Back buttons are their own width, with the label at the start, as on `dev/edd`.

In the editor, set a Continue button to Width 100% and Align text Centre.
Expected: the Width control offers 25/50/75/100%. On the canvas and the Preview, the button spans its column with the label and arrow centred.

- [ ] **Step 4: Commit**

Stage: the five files above
Message: `feat(blocks): add width and text alignment to Step and Back buttons`

### Task 4: Editorial fashion without custom CSS

**Files:**
- Modify: `templates/layouts/editorial-fashion/design.json`
- Modify: `templates/layouts/editorial-fashion/markup.php:101-140` (the bag column) and `:230` (Place Order)

**Interfaces:**
- Consumes: Task 1's transparent fallback, and Task 2's `spread` attribute.

- [ ] **Step 1: Move the tokens**

In `design.json`, set:
- `custom_css` to `""`
- `inputs.background` to `""`, so the field is transparent on the page and in the panel (Task 1)
- `colours.link_hover` to `"#d9472f"`, which replaces `a:hover`

`align-items: stretch` on the columns needs no replacement: core's `.wp-block-columns` already stretches.

- [ ] **Step 2: Rebuild the bag column in the editor**

Build this in the editor, then copy the markup back as the file header says:
- **Column `yaycheckout-editorial-fashion-bag`:** Width `480px`, which replaces 42% plus the grow and 560px cap. A core Column's width is its content box, so with the 40px side padding the panel is 560px, as Split express sizes its panel. Drop its class name.
- **Group `yaycheckout-editorial-fashion-bag-inner`:**
  - Layout: Flex, vertical orientation, Justification Stretch, Vertical alignment Space between.
  - Minimum height `calc(100vh - 80px)`, where 80px is the column's 32px + 48px padding.
  - Keep its sticky position and top `0px`. Drop its class name.
- **Inside the inner Group:** wrap the header row, the "Your bag" heading group and the Order Summary in a new Group with Block spacing `32px`. The inner Group then holds two children, that Group and the Coupon Form, and Space between pins the coupon to the bottom.
- **Coupon Form:** drop the `yaycheckout-editorial-fashion-coupon` class name.

- [ ] **Step 3: Set Place Order (line 230) in the editor**

Turn on Spread. Set Padding to `18px` top and bottom and `24px` left and right, which with the 13px label and 1px border gives today's 56px height. Keep Width 100%, Left alignment and the other styles.

- [ ] **Step 4: Verify**

Run: `composer test -- --filter LayoutTemplateCatalogueTest`
Expected: PASS.

Create a new layout from Editorial fashion.
Expected: the editor shows no "This block contains unexpected or invalid content" notice, and the canvas shows the panel at 560px with the coupon at its foot.

Open its Preview at 1280×800 and scroll the form.
Expected:
- The panel is 560px wide and the coupon stays at the bottom of the viewport.
- Place Order is 56px tall, with `PLACE ORDER` at the left and the total at the right.
- Hovering the terms link turns it `#d9472f`.
- The coupon field is transparent on terracotta.

At 375px:
Expected: the panel fills the first screen and there is no horizontal scroll.

- [ ] **Step 5: Commit**

Stage: `templates/layouts/editorial-fashion/design.json`, `templates/layouts/editorial-fashion/markup.php`
Message: `refactor(templates): build Editorial fashion from block settings, without custom CSS`

### Task 5: Minimal steps and Soft steps without custom CSS

**Files:**
- Modify: `templates/layouts/pro/minimal-steps/design.json`, `templates/layouts/pro/minimal-steps/markup.php:184-200` (pinned button groups)
- Modify: `templates/layouts/pro/soft-steps/design.json`, `templates/layouts/pro/soft-steps/markup.php:94` (header bar) and `:220-227` (action group)
- Modify: `.claude/skills/build-checkout-template/references/pitfalls.md`

**Interfaces:**
- Consumes: Task 3's Width and text alignment on Step and Back buttons.

- [ ] **Step 1: Set `custom_css` to `""` in both `design.json` files**

- [ ] **Step 2: Set the buttons in the editor and copy the markup back**

In both templates, give every Step Button and Back Button Width 100% (preset) and Align text Centre. In Soft steps:
- Drop the `yaycheckout-soft-steps-actions` class.
- Set the header bar Group's flex layout to Allow to wrap, with a row Block spacing of `8px`. The column gap stays `16px`.
- Drop the bar's `yaycheckout-soft-steps-bar` class.

- [ ] **Step 3: Record the rule for new templates**

Add one item to `pitfalls.md`: a template's `design.json` keeps `custom_css` empty, because the editor canvas never prints it. A look that needs CSS is an Out item, built as a block setting or token first (Phase 3 of the skill).

- [ ] **Step 4: Verify**

Run: `composer test -- --filter LayoutTemplateCatalogueTest`
Expected: PASS.

Run: `git grep -n '"custom_css": "[^"]' -- templates/layouts`
Expected: no output.

Create a new layout from each template.
Expected: no block-validation notice, and on the canvas the buttons span the column with their labels centred.

Open each Preview at 1280px and 375px.
Expected:
- Minimal steps' pinned Continue, Back and Pay buttons are full width and centred, as on `dev/edd`.
- Soft steps' Continue and Back are full-width pills.
- At 375px, Soft steps' bar has no horizontal scroll and the logo, dots and total are all visible.

- [ ] **Step 5: Commit**

Stage: the four template files and `pitfalls.md`
Message: `refactor(templates): build Minimal steps and Soft steps without custom CSS`
