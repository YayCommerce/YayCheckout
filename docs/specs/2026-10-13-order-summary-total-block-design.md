# Order Summary Total Block Design

**Status:** accepted 2026-10-13
**Renamed after acceptance (2026-10-13):** Order Summary Totals became Order Summary Breakdown (`yaycheckout/order-summary-breakdown`) so its name no longer differs from Order Summary Total by one letter; the sections below use the final names, and Context describes the code as it was.
**Related:** None. Builds on the Order Summary zones design (`docs/specs/2026-10-07-order-summary-zones-design.md`) and the header/details design (`docs/specs/2026-10-12-order-summary-header-details-design.md`).

## Goal

Merchants can place the order total separately from the rest of the totals: a new Order Summary Total block shows the Total row, and Order Summary Breakdown keeps subtotal, coupons, shipping, fees and tax. The Total can sit anywhere inside the Order Summary, more than once, for example at the bottom of the details and as an amount-only total in the collapsible toggle header.

## Context

- `yaycheckout/order-summary-totals` (`packages/blocks/src/order-summary-totals/`, "Order Summary Totals"; this change renames it to `yaycheckout/order-summary-breakdown`, "Order Summary Breakdown") is a leaf block, `multiple: false`, `ancestor: ["yaycheckout/order-summary"]`. Its `render.php` calls `CheckoutFragments::print_summary_block()`, which prints the block wrapper around `CheckoutFragments::order_totals_html()`.
- `order_totals_html()` (`includes/Renderer/CheckoutFragments.php`) prints `<table class="shop_table yaycheckout-order-summary-totals">` with subtotal, coupons, one read-only shipping row, fees, tax, the `woocommerce_review_order_before_order_total` hook, the `tr.order-total` row (label, optional currency code, `wc_cart_totals_order_total_html()`), then `woocommerce_review_order_after_order_total`. The Total row is driven by the attributes `showTotal`, `totalLabel` and `showCurrencyCode`, read by `order_totals_options()`.
- Refresh: `CheckoutFragments::BLOCK_FRAGMENTS` maps a block name to `[ selector, builder ]`. `summary_block_fragments()` finds those blocks in the layout markup (first of each name, with its parsed attrs), and `fragments()` adds their HTML to `woocommerce_update_order_review_fragments`. `skip_review_order_table()` swaps Woo's review-order template for `templates/review-order-skipped.php` whenever any `BLOCK_FRAGMENTS` block is in the layout.
- During AJAX the attrs come from `parse_blocks()`, which omits attributes left at their default. `render.php` sees `$block->attributes` with defaults filled in. `order_totals_options()` applies the defaults itself for that reason.
- The Order Summary Toggle (`packages/blocks/src/order-summary-toggle/render.php`) passes its inner HTML through `wp_kses()` with the post rules minus `a` while the summary is collapsible. Post rules keep `table`, `tbody`, `tr`, `th`, `td` and `span` with `class` and `style`.
- New layouts get their Order Summary tree from `TEMPLATE` in `packages/blocks/src/order-summary/edit.tsx` and from `templates/layouts/classic-two-column/markup.php`; a comment in `edit.tsx` pairs the two.
- Labels edited in the inspector are read with `plainText()` (`packages/blocks/src/shared/plain-text.ts`) in the editor and `LayoutMarkupReader::plain_text()` in PHP.
- Live value (`cart_total`) can already show the order total inside text, as a plain amount.

## Scope

**In:**
- Renaming Order Summary Totals to Order Summary Breakdown: slug `yaycheckout/order-summary-breakdown`, folder `packages/blocks/src/order-summary-breakdown/`, class `yaycheckout-order-summary-breakdown`, PHP `ORDER_BREAKDOWN_CLASS`, `order_breakdown_html()`, `order_breakdown_options()`. Not released, so stored layouts are not migrated.
- New block `yaycheckout/order-summary-total`: the Total row only, with an editable label, a Show label toggle, a Show currency code toggle, colour, border, spacing and typography supports. Allowed anywhere inside Order Summary, several per layout.
- Each Total block refreshes on `update_order_review` with its own settings.
- Order Summary Breakdown loses its Total row and the `showTotal`, `totalLabel` and `showCurrencyCode` settings. It keeps firing the before/after order total hooks.
- New layouts (the Order Summary template and the Classic two-column layout template) include a Total block after Breakdown.
- Free and Pro.

**Out:**
- Migrating, converting or preserving the Total of existing layouts. Their Breakdown block stops printing the Total; the merchant adds a Total block.
- Placing the Total block outside Order Summary (e.g. next to Place Order).
- Splitting Breakdown into per-row blocks (subtotal, shipping, fees, tax).
- Printing the before/after order total extension rows in the Total block.
- Inline (canvas) editing of the Total label.

## Acceptance criteria

1. The inserter inside Order Summary (Details, Header and Toggle) offers Order Summary Total; it is not offered outside Order Summary.
2. Given a layout created from Default, the Order Summary Details holds, in order: Items, Breakdown, Total.
3. Given a Breakdown block, its inspector has Show subtotal and Subtotal label, and no Show total, Total label or Show currency code; its editor preview and storefront table have no `tr.order-total` row.
4. Given a cart with a coupon and a fee, the storefront Breakdown table prints subtotal, coupon, shipping, fee and tax rows, then the rows extensions add on `woocommerce_review_order_before_order_total` and `woocommerce_review_order_after_order_total`.
5. Given a Total block with default settings, the storefront prints `<table class="shop_table yaycheckout-order-summary-total yaycheckout-order-summary-total--{hash}">` with one `tr.order-total` row whose `th` reads "Total" (Woo's translation) and whose `td` holds `wc_cart_totals_order_total_html()`, including "(includes $X tax)" when prices include tax.
6. Given Total label "Amount due", the `th` reads "Amount due"; given a label of only spaces or only tags, it reads "Total". The editor preview matches.
7. Given Show label off, the row prints only the amount `td` (no `th`), and the Total label control is hidden in the inspector.
8. Given Show currency code on, the amount is preceded by `<span class="yaycheckout-order-summary-total__currency">` holding the store currency (e.g. `USD`).
9. Given two Total blocks with different settings (one in the Toggle with Show label off and Show currency code on, one in Details with defaults), when `update_order_review` runs, each table is replaced by HTML built from its own settings.
10. Given two Total blocks with identical settings, both share one selector and both update.
11. Given a Total block in the Toggle of a collapsible Order Summary, the table and amount print inside the toggle button, and stay visible while the summary is collapsed.
12. Given a Total block's typography (font size, weight), colour and border set in the editor, the storefront wrapper carries them and keeps them after a refresh.
13. Without a `WC_Cart` (admin, REST), the Total block prints nothing.
14. End-to-end verification: run `pnpm build:blocks`; create a new layout from Default; add a second Order Summary Total to the Order Summary Toggle with Show label off and Show currency code on; set the Details Total's label to "Amount due"; turn on Collapsible; publish. On the storefront checkout with a cart, change the shipping address so shipping cost changes: the Toggle shows `USD` and the new total with no label, the Details shows "Amount due" with the new total, and the Breakdown table shows no Total row.

## Constraints

- Product PHP runs on PHP 7.4 (CLAUDE.md hard rule 4).
- WordPress floor 6.5.
- Never hand-edit `assets/dist/`; build with `pnpm build:blocks`.
- No Pro code; no reference to `YayCheckout\Pro` outside `boot_pro()`.
- The `{hash}` is computed from the normalized options (`order_total_options()` output), never from raw attrs, so `render.php` and AJAX produce the same selector.
- `tr.order-total` keeps Woo's class name, so themes and extensions targeting it still match.
- Block name `yaycheckout/order-summary-total`, directory `packages/blocks/src/order-summary-total/`, root class `yaycheckout-order-summary-total`.

## Approach

**Chosen:** A new server-rendered leaf block with one AJAX fragment per distinct settings hash.

**Architecture:** The Total block reuses the Items/Breakdown fragment pattern: `render.php` calls `CheckoutFragments::print_summary_block()`, which prints the block wrapper around a table built by `order_total_html()`, and `fragments()` replaces that table on `update_order_review`. Because the block can appear several times with different settings, its fragment selector carries a hash of its normalized options, and `summary_block_fragments()` dedupes by selector instead of by block name. The extension hooks stay in Breakdown, so several Total copies never print extension rows twice.

**Components:**
- `packages/blocks/src/order-summary-total/block.json`: `name` `yaycheckout/order-summary-total`, `title` "Order Summary Total", `category` `yaycheckout`, `description` "The amount the shopper pays. Updates as the cart changes. Add it more than once, for example in the summary header.", `keywords` `total`, `grand total`, `amount`, `price`, `pay`, `ancestor: ["yaycheckout/order-summary"]`. Attributes: `label` (string, no default), `showLabel` (boolean, default `true`), `showCurrencyCode` (boolean, default `false`). Supports: `html: false`, `reusable: false`, `color { text, background }`, `__experimentalBorder { color, radius, style, width }`, `spacing { margin, padding }`, `typography` with the same keys as `order-summary-items/block.json` (`fontSize`, `lineHeight`, `__experimentalFontFamily`, `__experimentalFontStyle`, `__experimentalFontWeight`, `__experimentalLetterSpacing`, `__experimentalTextDecoration`, `__experimentalTextTransform`). `multiple` not set (allowed). `editorScript`, `style`, `render` as in Breakdown.
- `packages/blocks/src/order-summary-total/edit.tsx`: inspector panel "Order summary total" with Show label (`ToggleControl`), Total label (`TextControl`, placeholder `__( 'Total', 'yaycheckout' )`, shown only while Show label is on), Show currency code (`ToggleControl`). Canvas: the same table markup as PHP with the mock amount `$20.00` and `USD`, label via `plainText( label ) || placeholder`.
- `packages/blocks/src/order-summary-total/index.ts`: `registerDynamicBlock( metadata, Edit )`, imports `style.css`.
- `packages/blocks/src/order-summary-total/render.php`: `\YayCheckout\Renderer\CheckoutFragments::print_summary_block( $block );`.
- `packages/blocks/src/order-summary-total/style.css`: the table rules from Breakdown's `style.css` scoped to `table.yaycheckout-order-summary-total` (full width, fixed layout, collapsed borders, `6px 0` cell padding, label start-aligned, amount end-aligned at 40% width), `tr.order-total th` bold, and the `__currency` rule (`margin-inline-end: 0.35em; font-weight: 400`). With no `th`, the single `td` is end-aligned. Cell font inherits from the wrapper so typography supports apply.
- `includes/Renderer/CheckoutFragments.php`:
  - `ORDER_TOTAL_CLASS = 'yaycheckout-order-summary-total'`.
  - `BLOCK_FRAGMENTS` gains `'yaycheckout/order-summary-total' => [ '.' . self::ORDER_TOTAL_CLASS, 'order_total_html' ]`.
  - `order_total_options( array $attributes ): array{show_label: bool, label: string, show_currency_code: bool}`: defaults as in `block.json`; `label` = `LayoutMarkupReader::plain_text( $attributes, 'label', __( 'Total', 'woocommerce' ) )`.
  - `order_total_selector( array $attributes ): string`: `'.' . self::ORDER_TOTAL_CLASS . '--' . substr( md5( (string) wp_json_encode( self::order_total_options( $attributes ) ) ), 0, 8 )`.
  - `order_total_html( array $attributes = [] ): string`: `''` without a `WC_Cart`; otherwise `<table class="shop_table yaycheckout-order-summary-total yaycheckout-order-summary-total--{hash}"><tbody><tr class="order-total">`, a `th` with the escaped label when `show_label`, a `td` with the optional `<span class="yaycheckout-order-summary-total__currency">` (`get_woocommerce_currency()`) and `wc_cart_totals_order_total_html()`. No hooks.
  - `summary_block_fragments()`: for the Total block the selector is `order_total_selector( $block['attrs'] )`; for the others, `BLOCK_FRAGMENTS[ $name ][0]`. Dedupe by selector (first block wins, which builds identical HTML).
  - `order_breakdown_options()` drops `show_total`, `total_label`, `show_currency_code` (and its `@return` shape); `order_breakdown_html()` drops the `tr.order-total` row and keeps both hooks as its last rows.
- `packages/blocks/src/order-summary-breakdown/`: `block.json` drops `showTotal`, `totalLabel`, `showCurrencyCode`, description becomes "Subtotal, discounts, shipping, fees and taxes: the lines that add up to the order total. Updates as the cart changes.", `keywords` `subtotal`, `discount`, `coupon`, `shipping`, `fee`, `tax`, `totals`; `edit.tsx` drops their controls and the Total mock row; `style.css` drops the `tr.order-total th` and `__currency` rules.
- `includes/Blocks/BlockRegistry.php`: `ORDER` adds `'order-summary-total'` after `'order-summary-breakdown'`.
- `packages/blocks/src/shared/block-icons.tsx`: an entry for `yaycheckout/order-summary-total`.
- `packages/blocks/src/order-summary-toggle/edit.tsx`: `ALLOWED_BLOCKS` adds `yaycheckout/order-summary-total` (added during implementation: the Toggle only allowed Paragraph, Heading, Image, Group and Spacer).
- `packages/blocks/src/order-summary/edit.tsx`: `TEMPLATE` Details children become `[ [ itemsMeta.name ], [ breakdownMeta.name ], [ totalMeta.name ] ]`; the comment naming the template file stays.
- `templates/layouts/classic-two-column/markup.php`: `<!-- wp:yaycheckout/order-summary-total /-->` after `<!-- wp:yaycheckout/order-summary-breakdown /-->`.
- `packages/blocks/src/order-summary-details/block.json` and `templates/review-order-skipped.php`: wording only where they name "Items and Breakdown" (add Total).

**Data and storage:** None. Attributes live in the layout's block markup. Old Breakdown markup that still holds `showTotal`, `totalLabel` or `showCurrencyCode` loads without a validation error (the editor drops unknown comment attributes) and PHP ignores them.

**Integration points:**
- `woocommerce_update_order_review_fragments` (existing filter in `CheckoutFragments::fragments()`): one extra key per distinct Total selector.
- `wc_get_template` (`skip_review_order_table()`): unchanged; a layout with only a Total block also skips Woo's table.
- `woocommerce_review_order_before_order_total` / `woocommerce_review_order_after_order_total`: fired by Breakdown only, at the end of its table.
- `wc_cart_totals_order_total_html()` and its `woocommerce_cart_totals_order_total_html` filter: used unchanged by the Total block.

**UI:** The block editor's inserter (Order Summary children) and the block inspector, as described in Components. No SPA change.

**Edge cases and failure modes:**
- No `WC_Cart`: Total prints `''` inside its wrapper.
- A layout with a Total block and no Breakdown block: extension rows don't print. Accepted.
- A layout with Breakdown and no Total block (every existing layout): no order total prints. Accepted; see Rollout.
- Total inside the Toggle while collapsible: passes the toggle's `wp_kses` on first paint. The post rules keep `strong`, `span` and `small` but not `bdi`, so Woo's `<bdi>` around the amount is stripped to its text; the amount still prints. After a refresh the fragment replaces the table outside kses, so `bdi` returns. Accepted: no visible difference in left-to-right stores.
- Two Total blocks with identical options but different typography: same selector, same table HTML; typography sits on each wrapper, which the refresh does not replace, so each keeps its own.
- Label holding tags or entities: printed as plain text via `plain_text()` / `plainText()`.

**Decision record:** None.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Total block refreshed by Live value's client-side `cart_total` | Plain amount: loses Woo's "(includes $X tax)" note and `woocommerce_cart_totals_order_total_html` filters (currency, deposits), so it can differ from the real total. |
| Breakdown as a container of per-row blocks | Six new blocks and per-row fragments for a request that only needs the Total separate; this design doesn't block it later. |
| Keep `showTotal` in Breakdown, or fall back to Breakdown's Total when a layout has no Total block | User chose to remove the setting and not preserve existing layouts. |
| One Total block per layout (`multiple: false`) | User wants several, e.g. one in the toggle header and one in Details. |
| Extension hooks in the Total block | Several copies would print extension rows several times; they stay in Breakdown. |

## Rollout

Existing layouts lose their order total on update until the merchant adds an Order Summary Total block; the release note says so: "Order Summary Totals is now Order Summary Breakdown and no longer shows the order total. Add the new Order Summary Total block to your layouts." New layouts include the block.

## Open questions

None.
