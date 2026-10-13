# Summary Label and Value Styles Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** active
**Goal:** Label and Value style panels (text colour + full typography) on Order Summary Breakdown and Order Summary Total, plus block-level Typography on Breakdown.
**Spec:** [docs/specs/2026-10-13-summary-label-value-styles-design.md](../specs/2026-10-13-summary-label-value-styles-design.md)
**Architecture:** Settings live under the block's `style.yaycheckout.label` / `.value` in core's style shape. The storefront queues one rule per part in core's `block-supports` stylesheet through `wp_style_engine_get_styles()` under a unique wrapper class, like `BlockGap`; the editor compiles the same rules with `compileCSS()` into a `<style>` element. Chosen over wrapper CSS variables because the style engine resolves presets on both sides with no hand-written mirror.
**Touches:** `includes/Renderer/SummaryPartStyles.php` (new), `includes/Renderer/CheckoutFragments.php`, `packages/blocks/src/shared/summary-part-styles.tsx` (new, shared: consumed by the two summary blocks; `shared/` is also the admin SPA's `@blocks-shared`, though the SPA does not import this file), `packages/blocks/src/order-summary-breakdown/{block.json,edit.tsx,style.css}`, `packages/blocks/src/order-summary-total/edit.tsx`.
**Tests:** none. Not in CLAUDE.md's checkout-breaking list; every task has a manual verification step.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types; nor `mixed`, `static` return types, trailing commas in declarations, or first-class callables.
- WordPress floor 6.5. Use only style-engine functions WordPress 6.5 ships: `wp_style_engine_get_styles()` (PHP) and `compileCSS()` from `@wordpress/style-engine` (JS).
- The feature is in Free; nothing goes under `includes/Pro/`.
- Never hand-edit `assets/dist/`; rebuild with `pnpm build:blocks`.
- `packages/blocks/src/shared/` changes reach the SPA: run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`.
- Lint changed PHP with `composer lint -- <files>`.
- No tests; verify with the spec's criterion 15 (Task 5).
- Run commands on Node 22 (`nvm use`), pnpm only.
- Branch from `dev/edd` as `trace/feat/summary-label-value-styles`; land through a PR into `dev/edd`. Commit messages are Conventional Commits with CLAUDE.md's trailer. Stage files by name.
- Test edits only in a draft layout created for the test, never an existing draft (autosave writes it).

## Review focus

- A part setting must beat Total's `tr.order-total th { font-weight: bold }` (0,3,3) and the flex-row rules (0,4,2) whatever order the stylesheets load in: Task 2 Step 3 and Task 4 Step 4 check Label weight `400` on a Total inside Summary Header.
- Totals refresh replaces the table, not the wrapper; styles must survive it: Task 2 Step 3.
- Two Total blocks with equal options share one fragment selector but need their own styles: Task 2 Step 3.
- A palette colour, font size preset or font family preset must be stored as a preset, not a resolved value, so theme changes flow through: Task 4 Step 4 inspects the saved markup.
- Breakdown's new `font: inherit` may change its look on a theme that styles `th`/`td` fonts: Task 1 Step 3 on Storefront and Twenty Twenty-Five.

---

### Task 0: Branch and docs

**Files:**
- Add: `docs/specs/2026-10-13-summary-label-value-styles-design.md`, `docs/plans/2026-10-13-summary-label-value-styles.md`

- [ ] **Step 1: Branch**

From an up-to-date `dev/edd`, create `trace/feat/summary-label-value-styles`. Expected: `git status` shows only the two docs as untracked.

- [ ] **Step 2: Commit**

Stage: the two docs above
Message: `docs(blocks): spec and plan for summary label and value styles`

### Task 1: Block typography on Order Summary Breakdown

**Files:**
- Modify: `packages/blocks/src/order-summary-breakdown/block.json` (`supports`)
- Modify: `packages/blocks/src/order-summary-breakdown/style.css`

**Interfaces:**
- Consumes: nothing
- Produces: nothing other tasks call

- [ ] **Step 1: Add typography supports**

Copy Order Summary Total's `supports.typography` object verbatim (`fontSize`, `lineHeight`, `__experimentalFontFamily`, `__experimentalFontStyle`, `__experimentalFontWeight`, `__experimentalLetterSpacing`, `__experimentalTextDecoration`, `__experimentalTextTransform`, all `true`) into Breakdown's `supports`, after `color`.

- [ ] **Step 2: Cells inherit the wrapper's font**

In `style.css`, before the existing `th { font-weight: normal }` rule, add `.yaycheckout-order-summary table.yaycheckout-order-summary-breakdown :is(th, td) { font: inherit; }` with a one-line comment matching Total's ("Cells take the wrapper's typography, so the block's font settings reach the rows.").

- [ ] **Step 3: Verify**

Run: `pnpm build:blocks`. Expected: `compiled successfully`.
In a new draft layout, select Order Summary Breakdown. Expected: Styles tab shows a Typography panel with the eight properties Total has; setting font size `20px` enlarges every row on the canvas and on the layout's preview link.
With nothing set, compare the preview link's Breakdown before and after this change (`git stash` the two files and rebuild for "before") on Storefront and on Twenty Twenty-Five. Expected: no visible change. If a theme changes, record it in the PR description; do not scope the rule down.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/order-summary-breakdown/block.json`, `packages/blocks/src/order-summary-breakdown/style.css`
Message: `feat(blocks): add Typography to Order Summary Breakdown`

### Task 2: Storefront rules

**Files:**
- Create: `includes/Renderer/SummaryPartStyles.php`
- Modify: `includes/Renderer/CheckoutFragments.php` `print_summary_block()` (~line 676)

**Interfaces:**
- Consumes: nothing
- Produces: `SummaryPartStyles::wrapper_class( array $attributes, string $block_class, string $table_class ): string` — returns `' yaycheckout-summary-parts-N'` (leading space) when at least one rule was queued, else `''`.
- Produces: stored shape, read by Task 3 and Task 4:
  `style.yaycheckout.label` / `style.yaycheckout.value` = `{ typography?: { fontSize?, lineHeight?, fontFamily?, fontStyle?, fontWeight?, letterSpacing?, textDecoration?, textTransform? }, color?: { text? } }`

- [ ] **Step 1: Implement `SummaryPartStyles` in `includes/Renderer/SummaryPartStyles.php`**

`final class`, namespace `YayCheckout\Renderer`, header per `.claude/rules/php-standards.md`. Constant `PARTS = [ 'label' => 'th', 'value' => 'td' ]`.
`wrapper_class()`: `$class = wp_unique_id( 'yaycheckout-summary-parts-' )`; for each part whose `$attributes['style']['yaycheckout'][ $part ]` is a non-empty array, call
`wp_style_engine_get_styles( $part_style, [ 'selector' => '.yaycheckout-order-summary .' . $class . '.' . $block_class . ' table.' . $table_class . ' tr > ' . $cell, 'context' => 'block-supports' ] )`.
Pass only the `typography` and `color` keys of the part (drop anything else). A part counts as queued when the result's `declarations` is non-empty. Return `' ' . $class` if any part queued, else `''`.

- [ ] **Step 2: Wire into `print_summary_block()`**

After the Items branch, for `yaycheckout/order-summary-breakdown` call `wrapper_class( $block->attributes, 'wp-block-yaycheckout-order-summary-breakdown', self::ORDER_BREAKDOWN_CLASS )`, and for `yaycheckout/order-summary-total` the same with `'wp-block-yaycheckout-order-summary-total'` and `self::ORDER_TOTAL_CLASS`. Set `$wrapper['class']` to the trimmed result when non-empty. Do not touch `order_total_options()` or `order_total_hash_class()`.

- [ ] **Step 3: Verify on the storefront**

Run: `composer lint -- includes/Renderer/SummaryPartStyles.php includes/Renderer/CheckoutFragments.php`. Expected: no errors.
In a new draft layout with an Order Summary Total in Summary Header and another in Summary Details (same options), open the Code editor and add to the header Total's `style`: `"yaycheckout":{"label":{"typography":{"fontWeight":"400"},"color":{"text":"#777777"}},"value":{"typography":{"fontSize":"20px"}}}`; to Breakdown's: `"yaycheckout":{"value":{"color":{"text":"#0a7d00"}}}`. Save the draft, open its preview link with a coupon and a paid shipping method in the cart.
Expected:
- The header Total's label is weight 400 and `#777777`, its amount `20px`; the details Total is unchanged.
- The page's `core-block-supports-inline-css` holds rules ending `tr > th` / `tr > td` under `.yaycheckout-summary-parts-N`.
- Every Breakdown amount is `#0a7d00`; "Free"/shipping row text stays uppercase; the coupon remove link keeps the theme link colour.
- A block with no `style.yaycheckout` has no `yaycheckout-summary-parts-*` class.
- Apply another coupon and change the shipping method: after the refresh all of the above still hold.

- [ ] **Step 4: Commit**

Stage: `includes/Renderer/SummaryPartStyles.php`, `includes/Renderer/CheckoutFragments.php`
Message: `feat(blocks): print summary label and value styles on the storefront`

### Task 3: Shared Label / Value panels and editor CSS

**Files:**
- Create: `packages/blocks/src/shared/summary-part-styles.tsx`

**Interfaces:**
- Consumes: stored shape from Task 2
- Produces:
  - `type SummaryPart = 'label' | 'value'`
  - `summaryPartCSS( clientId: string, tableClass: string, style: Record< string, unknown > | undefined ): string`
  - `SummaryPartPanels( props: { style: Record< string, unknown > | undefined; onChange: ( style: Record< string, unknown > | undefined ) => void; showLabel?: boolean } ): JSX.Element` — `showLabel` defaults to `true`.

- [ ] **Step 1: Implement `summaryPartCSS()`**

For each part, `compileCSS( { typography, color }, { selector: `#block-${ clientId } table.${ tableClass } tr > ${ cell }` } )` from `@wordpress/style-engine` (`th` for label, `td` for value); join the results.

- [ ] **Step 2: Implement `SummaryPartPanels`**

Two `ToolsPanel`s, labels `__( 'Label', 'yaycheckout' )` and `__( 'Value', 'yaycheckout' )`, `panelId` `yaycheckout-summary-label` / `yaycheckout-summary-value`; the Label panel is not rendered when `showLabel` is false. Follow `packages/blocks/src/payment/style-panels.tsx`: a `useRef` of the latest style so Reset all's per-item callbacks build on each other, `resetAll` clearing the part, one `ToolsPanelItem` per property with `hasValue` / `onDeselect`, `isShownByDefault` on all.
Items in this order, each from `@wordpress/block-editor`: Text colour (`ColorGradientSettingsDropdown`, palettes from `useMultipleOriginColorsAndGradients()`, `clearable`), Font family (`__experimentalFontFamilyControl`), Font size (`FontSizePicker`), Appearance (`__experimentalFontAppearanceControl`, writes `fontStyle` and `fontWeight`), Line height (`LineHeightControl`), Letter spacing (`__experimentalLetterSpacingControl`), Decoration (`__experimentalTextDecorationControl`), Letter case (`__experimentalTextTransformControl`). Theme lists come from `useSettings( 'typography.fontSizes', 'typography.fontFamilies' )`.
Presets: a picked palette colour is stored as `var:preset|color|<slug>` (match with `getColorObjectByColorValue()`), a picked font size preset as `var:preset|font-size|<slug>` (match with `getFontSizeObjectByValue()`), a picked font family preset as `var:preset|font-family|<slug>`; when reading, map each preset back to its value for the control. Writes go to `style.yaycheckout.<part>.typography.<prop>` / `.color.text`, removing empty values and pruning empty objects as `setAt()` in `payment/style-paths.ts` does (copy the helper; do not import from `payment/`).

- [ ] **Step 3: Verify**

Run: `pnpm build:blocks`. Expected: `compiled successfully`.
Run: `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. Expected: no errors.
(The panels are not mounted until Task 4; this task is accepted on the build.)

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/shared/summary-part-styles.tsx`
Message: `feat(blocks): add shared Label and Value style panels`

### Task 4: Mount the panels on both blocks

**Files:**
- Modify: `packages/blocks/src/order-summary-breakdown/edit.tsx`
- Modify: `packages/blocks/src/order-summary-total/edit.tsx`

**Interfaces:**
- Consumes: `SummaryPartPanels`, `summaryPartCSS()` from Task 3

- [ ] **Step 1: Breakdown**

Add `style?: Record< string, unknown >` to the attributes interface and take `clientId` from props. Render `<InspectorControls group="styles"><SummaryPartPanels style={ style } onChange={ ( next ) => setAttributes( { style: next } ) } /></InspectorControls>`, and inside the wrapper, before the table, `{ css && <style>{ css }</style> }` with `css = summaryPartCSS( clientId, 'yaycheckout-order-summary-breakdown', style )`.

- [ ] **Step 2: Total**

Same, with `'yaycheckout-order-summary-total'` and `showLabel={ showLabel }`.

- [ ] **Step 3: Build**

Run: `pnpm build:blocks`. Expected: `compiled successfully`.

- [ ] **Step 4: Verify in the editor**

In a new draft layout, on Order Summary Total in Summary Header, set block font size `16px`, block text colour `#111111`, Label colour `#777777`, Label weight `400`, Value font size `20px`. Expected: on the canvas the label is `#777777`, not bold, `16px`; the amount `#111111`, `20px`.
Turn Show label off. Expected: Label panel disappears; turn it on, the Label values are still applied.
On Breakdown, set Value colour to a palette colour and Value font size to a theme preset, and Label font family to a theme family. Open the Code editor. Expected: the values are `var:preset|color|<slug>`, `var:preset|font-size|<slug>`, `var:preset|font-family|<slug>`.
Use the panel menu to reset one item, then Reset all. Expected: the item, then the whole part, return to the block's style and the keys disappear from the Code editor.
Copy styles on the header Total, Paste styles on the details Total. Expected: the details Total shows the same Label and Value styles.
With Show currency code on and Value weight `700`, expected: the amount is bold and `USD` stays weight 400.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/order-summary-breakdown/edit.tsx`, `packages/blocks/src/order-summary-total/edit.tsx`
Message: `feat(blocks): style summary labels and amounts separately`

### Task 5: End-to-end verification and close

- [ ] **Step 1: Spec criterion 15**

In a draft layout created for this test, set block typography, Label and Value (colours, a font size preset, a custom line height, weight, letter case) on Order Summary Breakdown and on two Order Summary Total blocks (one in Summary Header, one in Summary Details). Check spec criteria 5–10 and 12 in the editor, then on the layout's preview link with a cart that has a coupon and a paid shipping method; apply another coupon and change shipping to trigger a refresh. Expected: every criterion holds before and after the refresh, and the editor and storefront match.

- [ ] **Step 2: Full checks**

Run: `./scripts/run.sh build`. Expected: succeeds.
Run: `composer lint`. Expected: passes (no new failures against `dev/edd`).

- [ ] **Step 3: Close the plan**

Set this plan's Status to `done YYYY-MM-DD`.
Stage: `docs/plans/2026-10-13-summary-label-value-styles.md`
Message: `docs(blocks): close summary label and value styles plan`
