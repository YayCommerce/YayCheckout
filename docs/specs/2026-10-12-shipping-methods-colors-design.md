# Shipping Methods Colors Design

**Status:** accepted 2026-10-12
**Related:** None. Extends the Shipping Methods Styles → Methods panel (branch `onyx/feat/shipping-methods-layouts`).

## Goal

Merchants can colour the Shipping Methods block's radios and row borders, idle, selected and hovered, per block from the Styles tab, without changing the layout's design colours that every other block uses.

## Context

- `packages/blocks/src/shipping-methods/style.css` draws six layouts (`data-layout`: `default`, `stacked`, `columns`, `check`, `divided`, `boxed`). Rows already read `--yaycheckout-shipping-gap`, `-radius`, `-bg`, `-bg-selected` and `-bg-hover` from the wrapper.
- Those values live on the block's `style` attribute so Copy / Paste styles carries them. `style-paths.ts` maps each setting key to its path (`PATHS`) and turns them into wrapper CSS variables in the editor (`shippingStyleVars()`). `includes/Renderer/CheckoutFragments.php` `shipping_methods_style_attribute()` mirrors that on the storefront.
- Row radius and background were moved to `style.yaycheckout.radius` and `style.yaycheckout.background` when dev/edd was merged (`b09547d`), because core's border and background supports now style the wrapper and own `style.border.*` and `style.color.background`.
- `setting-controls.tsx` declares `ShippingSettingKey`, the colour keys (`BACKGROUND_SETTING_KEYS`) and their labels; `layouts.ts` lists which keys each layout shows; `edit.tsx` renders the range controls and one `PanelColorSettings` in `InspectorControls group="styles"` → `PanelBody` "Methods".
- Colours today come from `assets/css/checkout-base.css` tokens: the radio ring and idle borders use `--yaycheckout-border-color`, the selected radio dot and selected border use `--yaycheckout-primary-color` (fallback `#3858e9`). Exceptions: Check's idle ring is `color-mix(in srgb, currentColor 35%, transparent)` and its idle border is `2px solid transparent`; Divided's ring and divider lines use `currentColor`.
- The editor preview (`edit.tsx`) prints the same row markup as the storefront, so `style.css` draws both.
- Shipping rate AJAX refreshes swap the rate list only; the wrapper and its inline variables stay.

## Scope

**In:**
- Five colour settings: Radio, Radio (selected), Border, Border (selected), Border (hover).
- Per-layout visibility per the table under Approach.
- Editor preview and storefront output, both from the same CSS variables.

**Out:**
- Price and method-name text colours.
- The tick colour in the Check layout (stays `#fff`).
- The Payment block or any other block.
- Colour presets / block style variations.
- Changes to the layout design tokens (`--yaycheckout-primary-color`, `--yaycheckout-border-color`).
- Border width or style controls.

## Acceptance criteria

1. Given a Shipping Methods block with none of the new keys set, in every layout, then the editor and storefront render pixel-identical to before this change.
2. Given layout Default, Stacked or Columns, then Styles → Methods shows the colour list in this order: Background, Background (selected), Background (hover), Border, Border (selected), Border (hover), Radio, Radio (selected).
3. Given layout Check, then the same eight colours show. Given Divided or Boxed, then Border (selected) and Border (hover) are hidden and the other six show.
4. Given Radio set to `#ff0000`, then every unchecked row's ring is `#ff0000` (in Check: the empty circle's ring).
5. Given Radio (selected) set to `#00aa00`, then the checked row's dot is `#00aa00` (in Check: the filled circle's background; the tick stays `#fff`). A single rate (`:only-child`) counts as checked.
6. Given Border set to `#0000ff`:
   - Default, Stacked, Columns: each unchecked row's outline is `#0000ff`.
   - Check: each unchecked row shows a 2px `#0000ff` outline; unset, it stays transparent.
   - Divided: the lines between rows and the list's top and bottom lines are `#0000ff`.
   - Boxed: the outer outline and the lines between rows are `#0000ff`.
7. Given Border (selected) set to `#ff00ff` in Default, Stacked, Columns or Check, then the checked row's outline is `#ff00ff`.
8. Given Border (hover) set to `#ffaa00` in Default, Stacked, Columns or Check, when the shopper hovers an unchecked row (not a single rate), then its outline is `#ffaa00`. In Default the hovered row's outline shows on all four sides, and a checked neighbour's outline still shows on all four of its sides.
9. Given a theme palette colour is picked, then the saved value is the preset (`var:preset|color|<slug>`) and the output is `var(--wp--preset--color--<slug>)`, in the editor and on the storefront.
10. Given any of the five keys set on block A, when Copy styles on A and Paste styles on block B, then B gets the same values.
11. Given a key set while on one layout, when switching to a layout that hides it and back, then the value is still set.
12. Given the shopper changes address and the rates refresh, then the colours stay applied.
13. End-to-end verification: in a draft layout created for this test, set all five colours to distinct values on each of the six layouts in turn; in the editor, then on the layout's preview link with at least two shipping rates, check criteria 4–8 by inspection and a hover, then change the shipping country to trigger a rate refresh and check that the colours remain.

## Constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- WordPress floor 6.5.
- The feature is in Free; nothing goes under `includes/Pro/`.
- Never hand-edit `assets/dist/`; rebuild with `pnpm build:blocks`.
- `style.css` selectors keep the `.yaycheckout-checkout .yaycheckout-shipping-methods` prefix.
- Every new CSS variable falls back to the exact colour the rule uses today.
- No tests (not in CLAUDE.md's checkout-breaking list); verify with the manual walkthrough in criterion 13.

## Approach

**Chosen:** A, new colour keys in the existing Styles → Methods panel, stored under `style.yaycheckout.*` and printed as wrapper CSS variables.

**Architecture:** Same pipeline as the existing background settings. Each key has a path on `style`; the editor and the storefront turn set values into `--yaycheckout-shipping-*` variables on the wrapper; `style.css` reads each variable with today's colour as fallback. Layouts list which keys they show.

| Label | Setting key | Stored at | CSS variable |
|---|---|---|---|
| Radio | `radioColor` | `style.yaycheckout.radio` | `--yaycheckout-shipping-radio` |
| Radio (selected) | `radioColorSelected` | `style.yaycheckout.radioSelected` | `--yaycheckout-shipping-radio-selected` |
| Border | `borderColor` | `style.yaycheckout.border` | `--yaycheckout-shipping-border` |
| Border (selected) | `borderColorSelected` | `style.yaycheckout.borderSelected` | `--yaycheckout-shipping-border-selected` |
| Border (hover) | `borderColorHover` | `style.yaycheckout.borderHover` | `--yaycheckout-shipping-border-hover` |

| Layout | Radio | Radio (sel.) | Border | Border (sel.) | Border (hover) |
|---|---|---|---|---|---|
| Default | ✓ | ✓ | ✓ | ✓ | ✓ |
| Stacked | ✓ | ✓ | ✓ | ✓ | ✓ |
| Columns | ✓ | ✓ | ✓ | ✓ | ✓ |
| Check | ✓ | ✓ | ✓ | ✓ | ✓ |
| Divided | ✓ | ✓ | ✓ | — | — |
| Boxed | ✓ | ✓ | ✓ | — | — |

Fallbacks in `style.css`:

| Variable | Fallback |
|---|---|
| `-radio` | shared ring rule: `var(--yaycheckout-border-color)`; Check: `color-mix(in srgb, currentColor 35%, transparent)`; Divided: `currentColor` |
| `-radio-selected` | `var(--yaycheckout-primary-color, #3858e9)` (Check: the filled circle's background) |
| `-border` | Default, Stacked, Columns, Boxed: `var(--yaycheckout-border-color)`; Check: `transparent`; Divided: `currentColor` |
| `-border-selected` | `var(--yaycheckout-primary-color, #3858e9)` |
| `-border-hover` | that layout's `-border` expression (variable then its fallback) |

**Components:**
- `packages/blocks/src/shipping-methods/setting-controls.tsx`: add the five keys to `ShippingSettingKey`; rename `BACKGROUND_SETTING_KEYS` / `BACKGROUND_SETTING_LABELS` / `isBackgroundSetting` to `COLOR_SETTING_KEYS` / `COLOR_SETTING_LABELS` / `isColorSetting`, holding the three backgrounds then the five new keys in the panel order of criterion 2.
- `packages/blocks/src/shipping-methods/layouts.ts`: per-layout `settings` per the table.
- `packages/blocks/src/shipping-methods/style-paths.ts`: five `PATHS` entries; `shippingStyleVars()` emits the five variables through `cssColor()`.
- `packages/blocks/src/shipping-methods/edit.tsx`: use the renamed helpers; the single `PanelColorSettings` lists the layout's colour keys in `COLOR_SETTING_KEYS` order.
- `includes/Renderer/CheckoutFragments.php` `shipping_methods_style_attribute()`: emit the five variables from `$yay` through `shipping_css_color()`.
- `packages/blocks/src/shipping-methods/style.css`: wrap each radio and border colour in its variable, add the Default hover z-index rules, and update the header comment's variable list.

**Data and storage:** Five new keys under the block's `style.yaycheckout` attribute object, saved in the layout's post content like the existing keys. No migration: a missing key means today's colour. Nothing to remove on uninstall.

**Integration points:** None new. Core Copy / Paste styles carries `style` as before. No hooks, filters or REST changes.

**UI:** Block editor, Shipping Methods → Styles tab → "Methods" panel, inside the existing `PanelColorSettings` list.

**Edge cases and failure modes:**
- Default's joined rows overlap by 1px (`margin-top: -1px`). A hovered unchecked row gets `z-index: 1` so its hover border shows on all sides; the checked row moves from `z-index: 1` to `z-index: 2` so it stays above a hovered neighbour.
- A single rate prints a hidden input; it counts as checked (`:only-child`) and never gets hover styles, as today.
- Check keeps its `2px solid` border at all times, so setting Border does not shift the row's content.
- Invalid colour strings: the editor only saves picker values; the storefront passes them through `shipping_css_color()` as the backgrounds do: presets become `var(--wp--preset--color--<slug>)`, hex is sanitised, and a value that could break out of the declaration (`= < > { }` or `/*`) prints nothing.
- RTL: no new direction-specific properties.

**Decision record:** None. It follows the existing Methods settings pattern.

## Alternatives considered

| Option | Why rejected |
|---|---|
| B: block-level Accent + Border overrides of `--yaycheckout-primary-color` / `--yaycheckout-border-color` | Radio and selected border can't differ, no hover colour, and Divided and Check (`currentColor`) would still ignore it. |
| C: colour presets via `register_block_style` | No custom colours; every new look needs code. |
| D: rely on the layout's design colours | Changes Payment, buttons and every other block too, not just shipping. |
| Price / name text colours | Left out of scope by the user. |

## Rollout

None. Existing layouts render unchanged until a merchant sets a colour.

## Open questions

None.
