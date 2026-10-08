# Step and Steps Style Supports Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Step 0: copy this plan to `docs/plans/2026-10-08-step-style-supports.md` and commit it with Task 1.

**Status:** done 2026-10-08
**Goal:** Merchants can style each Step (text, background, padding, margin, border) and set the spacing between steps in a Steps block.
**Spec:** none, planned from the request
**Architecture:** Step uses core block supports declared in `block.json`. Core prints them on the wrapper through `useBlockProps` and `get_block_wrapper_attributes`, which both blocks already call. Steps can't use core's `blockGap` output, because that needs `supports.layout`, and layout would add core flex styles (`align-items: center`, wrap, the theme gap) that break the card column. So Steps declares `blockGap` with serialization skipped and prints `gap` itself. A `wp_theme_json_data_default` filter turns the needed settings on for these two blocks only, so the controls show on themes that leave them off.
**Touches:** `packages/blocks/src/step/block.json`, `packages/blocks/src/steps/block.json`, `packages/blocks/src/steps/style.css`, `packages/blocks/src/steps/edit.tsx`, `packages/blocks/src/steps/render.php`, `includes/Blocks/BlockRegistry.php`
**Tests:** none. Styling does not decide whether a shopper can check out, and it is not a reproduced bug, so CLAUDE.md's test rule does not apply. Each task has an exact manual check instead.

## Requirements

- **Goal:** The editor's Styles panel for a Step shows Color (Text, Background), Dimensions (Padding, Margin) and Border. The panel for Steps shows Dimensions > Block spacing. The storefront renders what the merchant picked.
- **Out of scope:** typography, gradients and shadow on Step; supports for Step Button, Progress or Checkout; changes to the step script (`steps/view.ts`) or to step state logic.
- **Acceptance criteria:**
  1. With no style set, a Step looks the same as before: border `1px solid #e5e7eb`, radius `12px`, background `#fff`, padding `16px 20px`. The open step's border is `var(--yaycheckout-primary)`. Steps in a Steps block are `12px` apart.
  2. A Step's background, text color, padding, margin, border color, width, style and radius each override the matching default, in the editor and on the storefront.
  3. With no border color set, the open step's border is still primary. With one set, every state uses it.
  4. Text color applies to the title, including a locked step's title, and to the step's inner content. The index badge, summary and Edit button keep their own colors.
  5. Steps' Block spacing sets the gap between steps. Resetting it brings back `12px`.
  6. End to end: after `pnpm build:blocks`, style one Step and set Steps' Block spacing to `32px` in a layout, then activate it. On the storefront checkout the styles show, the steps are `32px` apart, and Continue, Edit (reopen) and the One step layout behave as before.
- **Decided with the user:** turn the settings on for these two blocks through `wp_theme_json_data_default` (yes). Text color covers the title and inner content, not the badge, summary or Edit button.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types or `str_contains`.
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/<block>/` and run `pnpm build:blocks`.
- Run from the repo root on Node 22 (`nvm use`), with pnpm only.
- Branch from `dev/edd` as `<name>/feat/step-style-supports`. Commits follow Conventional Commits and end with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`. Stage files by name.
- Saved layouts must still parse without "invalid block" warnings. Both blocks are dynamic and `save` returns only inner blocks, so adding supports does not change saved markup.

## Review focus

- A preset color, picked from the palette rather than typed: it comes as a class (`has-<slug>-background-color`, `has-<slug>-border-color`), not inline style, so the card defaults must step aside on `.has-background`, `.has-text-color` and `.has-border-color`. Checked in Task 1, Step 4.
- A spacing preset for Block spacing (`var:preset|spacing|50`) must become `var(--wp--preset--spacing--50)` and not print raw. Checked in Task 2, Step 5.
- Step margin plus Steps gap: the two add up, so a step with margin should sit further from its neighbours and nothing else. Checked in Task 2, Step 5.

---

### Task 1: Step style supports

**Files:**
- Modify: `packages/blocks/src/step/block.json` (`supports`)
- Modify: `packages/blocks/src/steps/style.css` (card defaults, lines 7-16 and 52-54)
- Modify: `includes/Blocks/BlockRegistry.php` (constructor and a new method)

**Interfaces:**
- Produces: `BlockRegistry::step_style_settings( \WP_Theme_JSON_Data $theme_json ): \WP_Theme_JSON_Data`, hooked on `wp_theme_json_data_default`. Task 2 adds the `yaycheckout/steps` entry to it.

- [ ] **Step 1: Declare supports in `step/block.json`**

Add to `supports`, keeping `html: false` and `reusable: false`:
- `color`: `{ "text": true, "background": true }`
- `spacing`: `{ "margin": true, "padding": true, "__experimentalDefaultControls": { "margin": true, "padding": true } }`
- `__experimentalBorder`: `{ "color": true, "radius": true, "style": true, "width": true, "__experimentalDefaultControls": { "color": true, "radius": true, "style": true, "width": true } }`

`edit.tsx` and `render.php` need no change: `useBlockProps( { className: 'yaycheckout-step' } )` and `get_block_wrapper_attributes()` already print the support output.

- [ ] **Step 2: Make the card defaults yield to the supports in `steps/style.css`**

- Split `background: #fff` out of `.yaycheckout-step` into `.yaycheckout-step:not(.has-background)`. Keep border, radius and padding on `.yaycheckout-step`, since inline support styles beat them.
- Change the open-step rule to `.yaycheckout-step[data-state="current"]:not(.has-border-color)`.
- Change the locked-title rule to `.yaycheckout-step[data-state="locked"]:not(.has-text-color) .yaycheckout-step__title`.
- Leave the colors of `__index`, `__summary` and `__edit` as they are.

Each new or changed selector gets a one-line comment saying the default holds until the support sets a value, the way `place-order/style.css` does.

- [ ] **Step 3: Enable the settings for Step in `BlockRegistry.php`**

Add `add_filter( 'wp_theme_json_data_default', [ $this, 'step_style_settings' ] );` in the constructor, next to the `place_order_width_presets` filter. The new method returns `$theme_json->update_with( [ 'version' => \WP_Theme_JSON::LATEST_SCHEMA, 'settings' => [ 'blocks' => [ 'yaycheckout/step' => [ 'color' => [ 'text' => true, 'background' => true ], 'spacing' => [ 'padding' => true, 'margin' => true ], 'border' => [ 'color' => true, 'radius' => true, 'style' => true, 'width' => true ] ] ] ] ] )`. Its docblock says these are default-origin settings, so a theme's own block settings win.

- [ ] **Step 4: Verify**

Run: `pnpm build:blocks`, then `composer lint -- includes/Blocks/BlockRegistry.php`
Expected: build succeeds; lint reports no errors.

Open a layout in the layout editor and select a Step.
Expected: Styles shows Color (Text, Background), Dimensions (Padding, Margin) and Border. An unstyled Step looks the same as before.

Action: pick a palette background, a custom text color, padding `40px`, a palette border color, border width `3px` and radius `0`, then activate the layout.
Expected: the editor and storefront checkout both show those values. The title is in the text color, and so is a locked step's title. The index badge stays primary.

Action: clear the border color.
Expected: on the storefront the open step's border goes back to primary, and locked steps go back to `#e5e7eb`.

- [ ] **Step 5: Commit**

Stage: `docs/plans/2026-10-08-step-style-supports.md`, `packages/blocks/src/step/block.json`, `packages/blocks/src/steps/style.css`, `includes/Blocks/BlockRegistry.php`
Message: `feat(blocks): add color, spacing and border supports to Step`

### Task 2: Steps block spacing

**Files:**
- Modify: `packages/blocks/src/steps/block.json` (`supports`)
- Modify: `packages/blocks/src/steps/edit.tsx`
- Modify: `packages/blocks/src/steps/render.php`
- Modify: `includes/Blocks/BlockRegistry.php` (`step_style_settings()`)

**Interfaces:**
- Consumes: `BlockRegistry::step_style_settings()` from Task 1.
- Produces: the attribute path `style.spacing.blockGap`, which is a string (a CSS length or `var:preset|spacing|<slug>`). Steps prints it as inline `gap`.

- [ ] **Step 1: Declare the support in `steps/block.json`**

Add `"spacing": { "blockGap": true, "__experimentalSkipSerialization": [ "blockGap" ], "__experimentalDefaultControls": { "blockGap": true } }`. Do not add `layout`. The `gap: 12px` on `.yaycheckout-steps` in `style.css` stays as the default.

- [ ] **Step 2: Print the gap in the editor (`steps/edit.tsx`)**

Add `style?: { spacing?: { blockGap?: unknown } }` to `StepsAttributes`. Add a module function `gapValue( blockGap: unknown ): string | undefined`. It returns `undefined` unless `blockGap` is a non-empty string. It turns `var:preset|spacing|<slug>` into `var(--wp--preset--spacing--<slug>)` and returns any other string unchanged. Pass `style: { gap }` to `useBlockProps` only when `gap` is defined.

- [ ] **Step 3: Print the gap on the storefront (`steps/render.php`)**

Read `$attributes['style']['spacing']['blockGap']`, using it only when it is a non-empty string. Convert the preset prefix with `strpos`/`substr`, not `str_contains`. Build the declaration with `safecss_filter_attr( 'gap:' . $value )`. When the result is non-empty, add it as `'style'` in the array passed to `get_block_wrapper_attributes()`; core merges it with other inline styles.

- [ ] **Step 4: Enable the setting for Steps**

In `step_style_settings()`, add `'yaycheckout/steps' => [ 'spacing' => [ 'blockGap' => true ] ]` next to the Step entry.

- [ ] **Step 5: Verify**

Run: `pnpm build:blocks`, then `composer lint -- includes/Blocks/BlockRegistry.php packages/blocks/src/steps/render.php`
Expected: build succeeds; lint reports no errors.

Select a Steps block in the layout editor.
Expected: Styles > Dimensions shows Block spacing. With no value, steps are `12px` apart.

Action: set Block spacing to `32px`, activate, then load the storefront checkout.
Expected: in the editor and on the storefront, `.yaycheckout-steps` has inline `gap: 32px` and the steps are `32px` apart.

Action: pick a spacing preset instead.
Expected: the storefront wrapper has `gap: var(--wp--preset--spacing--<slug>)`, not the raw `var:preset|...` string.

Action: give one Step a top margin of `20px`.
Expected: that step sits `20px` further from the one above; the other gaps stay `32px`.

Action: reset Block spacing.
Expected: the gap is `12px` again, and no inline `gap` is printed.

- [ ] **Step 6: Final check (acceptance criterion 6)**

On the storefront, with the styled layout from Task 1 and the `32px` gap: fill step 1 and click Continue, click the completed step's header to reopen it, then switch Steps to the One step layout and repeat.
Expected: the styles and gap stay applied; steps open, lock and reopen as before; in One step only the current step shows.

- [ ] **Step 7: Commit**

Stage: `packages/blocks/src/steps/block.json`, `packages/blocks/src/steps/edit.tsx`, `packages/blocks/src/steps/render.php`, `includes/Blocks/BlockRegistry.php`
Message: `feat(blocks): add block spacing to Steps`
