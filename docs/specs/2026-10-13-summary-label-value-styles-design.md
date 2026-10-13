# Summary Label and Value Styles Design

**Status:** accepted 2026-10-13
**Related:** None. Builds on [the Order Summary Total block design](2026-10-13-order-summary-total-block-design.md).

## Goal

Merchants can style the label column and the amount column of the Order Summary Breakdown and Order Summary Total blocks separately (colour and full typography), from the Styles tab, on top of the block's own Typography and Color settings.

## Context

- Both blocks are leaf blocks under Order Summary. Their `render.php` calls `CheckoutFragments::print_summary_block()` (`includes/Renderer/CheckoutFragments.php`), which prints the wrapper with `get_block_wrapper_attributes()` and, inside it, the table from `order_breakdown_html()` or `order_total_html()`. On every totals refresh checkout.js replaces only the table; the wrapper stays.
- Every row in both tables is `tr > th` (label) + `tr > td` (amount). Breakdown prints subtotal, coupon, shipping, fee and tax rows, plus any rows extensions print on `woocommerce_review_order_before_order_total` / `_after_order_total`. Total prints one `tr.order-total`; its `th` is omitted when Show label is off, and its `td` may hold `span.yaycheckout-order-summary-total__currency`.
- Order Summary Total's `block.json` supports colour (text, background), border, spacing and typography (`fontSize`, `lineHeight`, `__experimentalFontFamily`, `__experimentalFontStyle`, `__experimentalFontWeight`, `__experimentalLetterSpacing`, `__experimentalTextDecoration`, `__experimentalTextTransform`). Its `style.css` sets `:is(th, td) { font: inherit }` so the wrapper's typography reaches the cells, and `tr.order-total th { font-weight: bold }`. Order Summary Breakdown supports colour, border and spacing, no typography; its `style.css` sets `th { font-weight: normal }` and uppercases the shipping row's `strong`.
- Order Summary Total's fragment selector is a hash of `order_total_options()` (`order_total_hash_class()`); several Total blocks may exist.
- `includes/Renderer/BlockGap.php` is the precedent for per-block CSS: a unique wrapper class from `wp_unique_id()` and rules queued in core's `block-supports` stylesheet through the style engine. Its editor twin is `packages/blocks/src/shared/block-gap.ts`, printed in a `<style>` element scoped to `#block-<clientId>`.
- `packages/blocks/src/payment/style-panels.tsx` is the precedent for Styles-tab `ToolsPanel`s with per-item reset, Reset all, and `ColorGradientSettingsDropdown` for colours.
- `packages/blocks/src/shared/` is also the admin SPA's `@blocks-shared`.

## Scope

**In:**
- Order Summary Breakdown gains core typography support, the same eight properties as Order Summary Total.
- Two new Styles-tab panels on both blocks, **Label** and **Value**, each with: Text colour, Font family, Font size, Appearance (font style + weight), Line height, Letter spacing, Decoration, Letter case.
- Label applies to every `tr > th` in the block's table; Value to every `tr > td`. One style per part for all of Breakdown's rows.
- Editor preview and storefront from the same stored values.

**Out:**
- Per-row-type styles (e.g. a separate discount colour).
- Tablet / Mobile viewport values.
- Hover or other states.
- Background, border, padding or margin per part.
- Order Summary Items, Order Summary, and every other block.
- Any change to the blocks' existing supports beyond adding typography to Breakdown.
- Removing or migrating block-level typography.

## Acceptance criteria

1. Given an Order Summary Total block with no Label or Value setting, then its wrapper gets no `yaycheckout-summary-parts-*` class, no rule is queued, and it renders as before this change.
2. Given an Order Summary Breakdown block with no block typography, Label or Value setting, then on Storefront and on Twenty Twenty-Five it shows no visible change from before this change.
3. Given either block selected, then the Styles tab shows a **Label** panel and a **Value** panel, each listing Text colour, Font family, Font size, Appearance, Line height, Letter spacing, Decoration and Letter case; each item resets from the panel menu and Reset all clears the panel.
4. Given Order Summary Breakdown selected, then the Styles tab also shows core's Typography panel with the same eight properties Order Summary Total has.
5. Given block font size `16px` and Value font size `20px`, then every label cell computes to `16px` and every amount cell to `20px`, in the editor and on the storefront.
6. Given Label text colour `#777777` and no Value colour, with block text colour `#111111`, then labels are `#777777` and amounts `#111111`.
7. Given Order Summary Total with Label font weight `400`, then the label is not bold (beats `tr.order-total th { font-weight: bold }`).
8. Given Breakdown with a coupon, shipping, a fee and itemized tax, and Value colour `#0a7d00`, then every amount cell is `#0a7d00`; the shipping "Free" stays uppercase; the coupon "[Remove]" link keeps the theme link colour.
9. Given Order Summary Total with Show currency code on and Value font weight `700`, then the amount is `700` and the currency code stays `400`.
10. Given Order Summary Total with Show label off, then the Label panel is hidden and its stored values remain; turning Show label on shows them applied again.
11. Given a theme palette colour, font size preset or font family preset is picked, then it is stored as `var:preset|color|<slug>`, `var:preset|font-size|<slug>` or `var:preset|font-family|<slug>` and printed as `var(--wp--preset--color--<slug>)`, `var(--wp--preset--font-size--<slug>)` or `var(--wp--preset--font-family--<slug>)`.
12. Given two Order Summary Total blocks with the same options and different Value styles, then each shows its own styles.
13. Given the shopper changes shipping method or applies a coupon, then after the totals refresh the Label and Value styles still apply.
14. Given Label and Value set on block A, when Copy styles on A and Paste styles on block B of the same type, then B shows the same Label and Value styles.
15. End-to-end verification: in a draft layout created for this test, set block typography, Label and Value (colours, a font size preset, a custom line height, weight, letter case) on Order Summary Breakdown and on two Order Summary Total blocks (one in Summary Header, one in Summary Details). Check criteria 5–10 and 12 in the editor, then on the layout's preview link with a cart that has a coupon and a paid shipping method; apply another coupon and change shipping to trigger a refresh and check criterion 13.

## Constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types; nor `mixed`, `static` return types, trailing commas in declarations, or first-class callables.
- WordPress floor 6.5. Use only style-engine functions WordPress 6.5 ships: `wp_style_engine_get_styles()` (PHP) and `compileCSS()` from `@wordpress/style-engine` (JS).
- The feature is in Free; nothing goes under `includes/Pro/`.
- Never hand-edit `assets/dist/`; rebuild with `pnpm build:blocks`.
- `packages/blocks/src/shared/` changes reach the SPA: run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`.
- Lint changed PHP with `composer lint -- <files>`.
- No tests (not in CLAUDE.md's checkout-breaking list); verify with criterion 15.

## Approach

**Chosen:** A, a scoped stylesheet through the style engine, like Block spacing.

**Architecture:** Each part's settings live on the block's `style` attribute in core's style shape. On the storefront a new renderer class turns them into two rules (label cells, value cells) in core's `block-supports` stylesheet, under a unique class it adds to the wrapper. In the editor a shared component renders the two panels and compiles the same rules into a `<style>` element scoped to the block's id. The rules out-specify every existing cell rule, so a part's setting wins over the block's typography and colour, and an unset property falls back to them.

**Components:**
- `includes/Renderer/SummaryPartStyles.php` (new, `final class`): `wrapper_class( array $attributes, string $block_name, string $table_class ): string`. Reads `style.yaycheckout.label` and `style.yaycheckout.value`, calls `wp_style_engine_get_styles( $part, [ 'selector' => …, 'context' => 'block-supports' ] )` for each, and returns `'yaycheckout-summary-parts-N'` (from `wp_unique_id( 'yaycheckout-summary-parts-' )`) when a rule was queued, else `''`.
- `includes/Renderer/CheckoutFragments.php` `print_summary_block()`: for `yaycheckout/order-summary-breakdown` and `yaycheckout/order-summary-total`, append that class to `$wrapper['class']`.
- `packages/blocks/src/shared/summary-part-styles.tsx` (new): `SummaryPartPanels` (the Label and Value `ToolsPanel`s, with a prop to hide Label) and `summaryPartCSS( clientId, tableClass, style )` returning the compiled CSS.
- `packages/blocks/src/order-summary-breakdown/edit.tsx` and `order-summary-total/edit.tsx`: render `SummaryPartPanels` in `InspectorControls group="styles"` and the CSS in a `<style>` element.
- `packages/blocks/src/order-summary-breakdown/block.json`: add Order Summary Total's `typography` supports block.
- `packages/blocks/src/order-summary-breakdown/style.css`: add `.yaycheckout-order-summary table.yaycheckout-order-summary-breakdown :is(th, td) { font: inherit; }` before the existing `th` rule.

**Data and storage:** Under the existing `style` attribute:

```
style.yaycheckout.label = {
  typography: { fontSize, lineHeight, fontFamily, fontStyle, fontWeight, letterSpacing, textDecoration, textTransform },
  color: { text }
}
style.yaycheckout.value = { same shape }
```

Presets stored as `var:preset|color|<slug>`, `var:preset|font-size|<slug>`, `var:preset|font-family|<slug>`. Empty values are removed, and empty objects pruned, as `setAt()` in `payment/style-paths.ts` does. No migration; a missing key means no override. Nothing to remove on uninstall.

**Integration points:**
- Storefront selectors (specificity 0,4,3):
  - Total label: `.yaycheckout-order-summary .yaycheckout-summary-parts-N.wp-block-yaycheckout-order-summary-total table.yaycheckout-order-summary-total tr > th`; value: same ending `tr > td`.
  - Breakdown label: `.yaycheckout-order-summary .yaycheckout-summary-parts-N.wp-block-yaycheckout-order-summary-breakdown table.yaycheckout-order-summary-breakdown tr > th`; value: same ending `tr > td`.
- Editor selectors: `#block-<clientId> table.<table-class> tr > th` and `… tr > td`.
- No hooks, filters, REST or fragment changes; `order_total_hash_class()` stays keyed on options only.

**UI:** Block editor, Styles tab of each block: core's Color, Typography, Border and Dimensions panels as today (Typography newly on Breakdown), then **Label** and **Value** panels. Controls are core's block-editor ones: `ColorGradientSettingsDropdown`, `__experimentalFontFamilyControl`, `FontSizePicker`, `__experimentalFontAppearanceControl`, `LineHeightControl`, `__experimentalLetterSpacingControl`, `__experimentalTextDecorationControl`, `__experimentalTextTransformControl`, fed from the theme's settings via `useSettings()`. On Order Summary Total the Label panel is hidden while Show label is off.

**Edge cases and failure modes:**
- Children of a value cell keep their own rules: Breakdown's shipping `strong` stays uppercase, the coupon remove link keeps the theme link colour, Total's currency code keeps `font-weight: 400`. Price markup without its own rule (`del`, `ins`, `small`) inherits as today.
- Extension rows printed on the review-order hooks inside Breakdown's table are `tr > th/td` and take the styles.
- Several Total blocks: each gets its own unique class, so styles stay per block even when they share one fragment.
- Total in a flex row keeps its `nowrap` / auto-width rules; only typography and colour come from the parts.
- Show label off: no `th` printed; the label rule matches nothing.
- Invalid values: `safecss_filter_attr()` in the style engine drops them on the storefront; the editor saves only control values.
- Breakdown's new `font: inherit` makes theme `th`/`td` font rules stop applying inside it, as for Total already; criterion 2 checks this on Storefront and Twenty Twenty-Five.
- RTL: no direction-specific properties.

**Decision record:** None. It follows the Block spacing pattern (`BlockGap`).

## Alternatives considered

| Option | Why rejected |
|---|---|
| B: CSS variables on the wrapper, like Payment / Shipping Methods colours | About 18 variables per block and hand-written preset resolution in both PHP and TS; more code to keep in step. |
| Colour only, or colour + size + weight | User chose full typography. |
| Per-row-type styles in Breakdown | Much larger UI; not needed now. |
| Controls in the Settings tab | Styles belong in the Styles tab and travel with Copy/Paste styles. |
| Remove block-level typography and keep only Label/Value | Would drop settings saved on existing Total blocks; block typography stays as the base. |

## Rollout

None. Existing layouts render unchanged until a merchant sets a Label or Value style (Breakdown's `font: inherit` aside, per criterion 2). Release note: "Order Summary Breakdown and Order Summary Total: style labels and amounts separately; Breakdown gains Typography."

## Open questions

None.
