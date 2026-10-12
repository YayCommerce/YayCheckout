# Shipping Methods Colors Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** active
**Goal:** Five per-block colour settings for Shipping Methods (Radio, Radio (selected), Border, Border (selected), Border (hover)) in Styles → Methods, drawn the same in the editor and on the storefront.
**Spec:** [docs/specs/2026-10-12-shipping-methods-colors-design.md](../specs/2026-10-12-shipping-methods-colors-design.md)
**Architecture:** Same pipeline as the existing Methods backgrounds: keys on `style.yaycheckout.*` → `--yaycheckout-shipping-*` variables on the wrapper (editor: `shippingStyleVars()`, storefront: `CheckoutFragments::shipping_methods_style_attribute()`) → `style.css` reads each variable with today's colour as fallback. CSS lands first so it can be checked as a no-op before any control writes a value.
**Touches:** `packages/blocks/src/shipping-methods/style.css`, `setting-controls.tsx`, `layouts.ts`, `style-paths.ts`, `edit.tsx`; `includes/Renderer/CheckoutFragments.php`. No shared code: every file is used only by the Shipping Methods block.
**Tests:** none

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- WordPress floor 6.5.
- The feature is in Free; nothing goes under `includes/Pro/`.
- Never hand-edit `assets/dist/`; rebuild with `pnpm build:blocks`.
- `style.css` selectors keep the `.yaycheckout-checkout .yaycheckout-shipping-methods` prefix.
- Every new CSS variable falls back to the exact colour the rule uses today.
- No tests; verify with the manual walkthrough in the spec's criterion 13.
- Run commands from the repo root on Node 22 (`nvm use`).
- `npx tsc --noEmit` in `packages/blocks` already fails on `dev/edd` (missing JSX types across every block). Don't use it as a check; `pnpm build:blocks` is the build check.
- Editor checks run on a **new throwaway draft layout** created for this work, never an existing one: Gutenberg autosaves drafts into the post. Delete it at the end only with the user's OK.

## Review focus

- Default layout rows overlap by 1px: a hovered row with Border (hover) set must show its colour on all four sides, and a checked neighbour must still show all four of its sides (Task 1 Step 3, Task 2 Step 5).
- No new key set must mean no visual change in any of the six layouts, including Check (transparent 2px border) and Divided (`currentColor` lines and ring) (Task 1 Step 2).
- Theme palette picks are saved as `var:preset|color|<slug>` and must print as `var(--wp--preset--color--<slug>)` on both sides (Task 2 Step 4, Task 3 Step 2).
- Colour list order must follow `COLOR_SETTING_KEYS`, not the order in `layouts.ts` (Task 2 Step 4).

## Manual test setup (used by every verification step)

- Site: `http://yaydevs.local`, checkout `?page_id=9`. wp-cli needs Local's socket: `php -d mysqli.default_socket="$HOME/Library/Application Support/Local/run/DmdPSy05S/mysql/mysqld.sock" /opt/homebrew/bin/wp …`.
- Create a draft layout named "Shipping colors test" from YayCheckout → Layouts with a Shipping Methods block in it. Note its post ID as `<id>`.
- Preview link: `wp eval 'echo \YayCheckout\Helpers\PreviewToken::mint( <id>, 120 );'`, then open `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>` after `?add-to-cart=29` so the cart has a real product.
- The shipping zone for the test address must offer at least two rates. If it shows one, add Free shipping to that zone in WooCommerce → Settings → Shipping.

---

### Task 1: Read the colour variables in `style.css`

**Files:**
- Modify: `packages/blocks/src/shipping-methods/style.css`

**Interfaces:**
- Consumes: nothing.
- Produces: the CSS variables `--yaycheckout-shipping-radio`, `--yaycheckout-shipping-radio-selected`, `--yaycheckout-shipping-border`, `--yaycheckout-shipping-border-selected`, `--yaycheckout-shipping-border-hover`, read from the `.yaycheckout-shipping-methods` wrapper. Tasks 2 and 3 set them.

- [ ] **Step 1: Wrap each colour in its variable, keeping today's value as the fallback**

Replace exactly these values. `X` below means the expression the rule has today, kept verbatim:

| Variable | Rules |
|---|---|
| `-radio` | shared `.yaycheckout-shipping-methods__indicator` box-shadow colour (`var(--yaycheckout-border-color)`); Check `__indicator` box-shadow colour (`color-mix(in srgb, currentColor 35%, transparent)`); Divided `__indicator` box-shadow colour (`currentColor`) |
| `-radio-selected` | the checked `__indicator` `box-shadow: inset 0 0 0 5px …` colour in Default, Stacked, Columns, Divided, Boxed; Check's checked `__indicator` `background-color` |
| `-border` | Default, Stacked, Columns, Check option `border` colour; Divided list `border-block` and option `border-bottom` colour; Boxed list `border` and option `border-bottom` colour |
| `-border-selected` | the checked option's `border-color` in Default, Stacked, Columns, Check |
| `-border-hover` | add `border-color: var(--yaycheckout-shipping-border-hover, var(--yaycheckout-shipping-border, X))` to the existing `:hover:not(:has(input:checked)):not(:only-child)` option rule in Default, Stacked, Columns, Check, where `X` is that layout's idle border fallback |

The form is `var(--yaycheckout-shipping-<name>, X)`. For example, Stacked idle becomes `border: 1.5px solid var(--yaycheckout-shipping-border, var(--yaycheckout-border-color));`. Check keeps `2px solid` with fallback `transparent`.

In the Default layout's hover rule, add `z-index: 1`. In its checked rule (`:is(:has(input:checked), :only-child)`), change `z-index: 1` to `z-index: 2`.

In the header comment, extend the list of "Methods styles from the Styles tab" variables with the five new names.

- [ ] **Step 2: Verify nothing changes with no keys set (spec criterion 1)**

Run: `git diff -U0 packages/blocks/src/shipping-methods/style.css`
Expected: every removed colour expression reappears verbatim as the fallback of a `var(--yaycheckout-shipping-…)` on the matching added line. The only other changes are the added hover `border-color` lines, the two `z-index` changes and the header comment.

Run: `pnpm build:blocks`
Expected: `webpack … compiled successfully`.

Open the preview link and switch the test layout through all six layouts (Settings → Layout in the editor, then reload the preview).
Expected: each layout looks as it did before the change: idle, checked, and on hover.

- [ ] **Step 3: Verify Default's stacking with a hand-set variable**

On the preview link, with Default selected, in DevTools set `--yaycheckout-shipping-border-hover: #ffaa00` on `.yaycheckout-shipping-methods`. Hover the unchecked row next to the checked one.
Expected: the hovered row's outline is `#ffaa00` on all four sides, and the checked row's primary-colour outline stays visible on all four sides.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/shipping-methods/style.css`
Message: `feat(blocks): read Shipping Methods radio and border colour variables`

### Task 2: Editor controls and editor variables

**Files:**
- Modify: `packages/blocks/src/shipping-methods/setting-controls.tsx`
- Modify: `packages/blocks/src/shipping-methods/layouts.ts`
- Modify: `packages/blocks/src/shipping-methods/style-paths.ts`
- Modify: `packages/blocks/src/shipping-methods/edit.tsx`

**Interfaces:**
- Consumes: Task 1's variable names.
- Produces:
  - `ShippingSettingKey` gains `'radioColor' | 'radioColorSelected' | 'borderColor' | 'borderColorSelected' | 'borderColorHover'`.
  - `COLOR_SETTING_KEYS: ShippingSettingKey[]`, in this order: `backgroundColor`, `backgroundColorSelected`, `backgroundColorHover`, `borderColor`, `borderColorSelected`, `borderColorHover`, `radioColor`, `radioColorSelected`. It replaces `BACKGROUND_SETTING_KEYS`.
  - `COLOR_SETTING_LABELS: Record<…, string>` replaces `BACKGROUND_SETTING_LABELS`, and `isColorSetting( key: ShippingSettingKey ): boolean` replaces `isBackgroundSetting`.
  - Storage paths: `radioColor` → `[ 'yaycheckout', 'radio' ]`, `radioColorSelected` → `[ 'yaycheckout', 'radioSelected' ]`, `borderColor` → `[ 'yaycheckout', 'border' ]`, `borderColorSelected` → `[ 'yaycheckout', 'borderSelected' ]`, `borderColorHover` → `[ 'yaycheckout', 'borderHover' ]`. Task 3 mirrors them in PHP.

- [ ] **Step 1: Keys and labels in `setting-controls.tsx`**

Extend `ShippingSettingKey`, rename the three exports as above, and add these labels, each with `__( …, 'yaycheckout' )`:

| Key | Label |
|---|---|
| `borderColor` | "Border" |
| `borderColorSelected` | "Border (selected)" |
| `borderColorHover` | "Border (hover)" |
| `radioColor` | "Radio" |
| `radioColorSelected` | "Radio (selected)" |

Update the `SETTING_CONTROLS` doc comment, which says "Background colours", to say "Colours".

- [ ] **Step 2: Per-layout keys in `layouts.ts`**

Replace the `BACKGROUNDS` constant with these per-layout lists (keep it if it's still useful):
- `default`, `check`: `borderRadius`, then the eight colour keys.
- `stacked`, `columns`: `gap`, `borderRadius`, then the eight colour keys.
- `boxed`: `borderRadius`, then the three backgrounds, `borderColor`, `radioColor` and `radioColorSelected`.
- `divided`: the three backgrounds, `borderColor`, `radioColor` and `radioColorSelected`.

`default` and `check` keep their current gap setting (`check` has `gap`, `default` does not), so only the colour keys change.

- [ ] **Step 3: Paths and editor variables in `style-paths.ts`; panel in `edit.tsx`**

- `style-paths.ts`: add the five `PATHS` entries above. In `shippingStyleVars()`, set `--yaycheckout-shipping-radio`, `-radio-selected`, `-border`, `-border-selected` and `-border-hover` from each key run through `cssColor()`, skipping any that are unset.
- `edit.tsx`: switch to `isColorSetting` and `COLOR_SETTING_LABELS`. Build `colorKeys` as `COLOR_SETTING_KEYS.filter( ( key ) => selectedLayout.settings.includes( key ) )`, so the list follows the spec's order and not `layouts.ts`. Rename the `PanelColorSettings` `title` from "Background" to "Colors".

- [ ] **Step 4: Verify in the editor (spec criteria 2, 3, 9, 10, 11)**

Run: `pnpm build:blocks`
Expected: `webpack … compiled successfully`.

Run: `git grep -n "BACKGROUND_SETTING\|isBackgroundSetting" -- packages`
Expected: no output.

In the test layout, select Shipping Methods → Styles → Methods:
- Layout Default, Stacked, Columns or Check: the colour list reads Background, Background (selected), Background (hover), Border, Border (selected), Border (hover), Radio, Radio (selected).
- Layout Divided or Boxed: Border (selected) and Border (hover) are missing; the other six show in the same order.
- On Default, set Border to a theme palette colour. Expected: in the code editor, the block comment has `"yaycheckout":{"border":"var:preset|color|<slug>"}`, and the wrapper's inline style in the canvas has `--yaycheckout-shipping-border: var(--wp--preset--color--<slug>)`.
- Set Border (hover) on Default, switch to Divided, then back to Default. Expected: Border (hover) still shows its value.
- Copy styles from this block, add a second test layout with a Shipping Methods block, and Paste styles there. Expected: the second block shows the same colour values.

- [ ] **Step 5: Verify the colours draw in the canvas (spec criteria 4–8, editor side)**

On Default, set Radio `#ff0000`, Radio (selected) `#00aa00`, Border `#0000ff`, Border (selected) `#ff00ff` and Border (hover) `#ffaa00`.
Expected in the canvas:
- The unchecked row's ring is red and the checked row's dot is green.
- The unchecked row's outline is blue and the checked row's is magenta.
- Hovering the unchecked row turns its outline orange on all four sides, while the checked row's outline stays whole.

Switch to Check. Expected: the unchecked row has a 2px blue outline, and the checked row has a green filled circle with a white tick.

- [ ] **Step 6: Commit**

Stage: `packages/blocks/src/shipping-methods/setting-controls.tsx`, `packages/blocks/src/shipping-methods/layouts.ts`, `packages/blocks/src/shipping-methods/style-paths.ts`, `packages/blocks/src/shipping-methods/edit.tsx`
Message: `feat(blocks): add radio and border colours to Shipping Methods`

### Task 3: Storefront variables

**Files:**
- Modify: `includes/Renderer/CheckoutFragments.php` (`shipping_methods_style_attribute()`, about lines 148–196)

**Interfaces:**
- Consumes: Task 2's storage paths and Task 1's variable names.
- Produces: the wrapper's `style` attribute on the storefront carries the same five variables as the editor.

- [ ] **Step 1: Emit the five variables in `shipping_methods_style_attribute( array $attributes ): string`**

Read `$yay['radio']`, `$yay['radioSelected']`, `$yay['border']`, `$yay['borderSelected']` and `$yay['borderHover']` through `self::shipping_css_color()`. Append `--yaycheckout-shipping-radio:…`, `-radio-selected`, `-border`, `-border-selected` and `-border-hover` in that order, after the background variables and before `color:`, skipping any empty result. Add the five paths to the docblock.

- [ ] **Step 2: Verify (spec criteria 4–9 and 12, storefront side)**

Run: `composer lint -- includes/Renderer/CheckoutFragments.php`
Expected: phpcs `1 / 1 (100%)` with no errors.

Run: `wp eval 'echo \YayCheckout\Renderer\CheckoutFragments::shipping_methods_style_attribute( [ "style" => [ "yaycheckout" => [ "radio" => "#ff0000", "border" => "var:preset|color|primary", "borderHover" => "x{y}" ] ] ] );'` (with the socket flag from the setup).
Expected: `--yaycheckout-shipping-radio:#ff0000;--yaycheckout-shipping-border:var(--wp--preset--color--primary)`, and nothing for `borderHover`.

Save the test layout with the Step 5 colours from Task 2, then open the preview link.
Expected: the same colours as in the editor canvas for Default and for Check, including the orange hover outline on all four sides. Change the billing country to trigger a rate refresh: the colours stay.

- [ ] **Step 3: Commit**

Stage: `includes/Renderer/CheckoutFragments.php`
Message: `feat(blocks): print Shipping Methods radio and border colours on the storefront`

### Final verification (spec criterion 13)

- [ ] On the test layout, set all five colours to distinct values. On each of the six layouts in turn, check the spec's criteria 4–8, first in the editor and then on the preview link with at least two rates (inspect, and hover an unchecked row). Then change the shipping country to trigger a rate refresh.
  Expected: every criterion holds in both places, Divided and Boxed show no selected or hover border, and the colours survive the refresh.
- [ ] Ask the user before deleting the throwaway test layouts.
- [ ] Set this plan's Status to `done YYYY-MM-DD` in the PR that finishes it.
