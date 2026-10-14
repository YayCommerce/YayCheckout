# Order Summary Total Block Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-13
**Goal:** Add a `yaycheckout/order-summary-total` block that prints only the order total row, and take the Total row and its settings out of Order Summary Breakdown.
**Spec:** `docs/specs/2026-10-13-order-summary-total-block-design.md`
**Architecture:** A leaf dynamic block that reuses the Items/Breakdown fragment pattern (`CheckoutFragments::print_summary_block()` plus a `BLOCK_FRAGMENTS` entry). Because the block may appear several times with different settings, its fragment selector carries an 8-character hash of its normalized options, and `summary_block_fragments()` dedupes by selector instead of block name. The extension hooks stay in Breakdown so copies never repeat them. A client-side Live value refresh was rejected because it loses Woo's total HTML and filters.
**Touches:**
- New `packages/blocks/src/order-summary-total/` (`block.json`, `index.ts`, `edit.tsx`, `render.php`, `style.css`).
- `includes/Renderer/CheckoutFragments.php`: new constant and three methods; `summary_block_fragments()` dedupes by selector; `order_breakdown_options()` / `order_breakdown_html()` lose the Total row. Consumers: `fragments()`, `skip_review_order_table()`, `print_summary_block()` (all in the same class), `order-summary-items/render.php`, `order-summary-breakdown/render.php`.
- `packages/blocks/src/order-summary-breakdown/` (`block.json`, `edit.tsx`, `style.css`).
- `includes/Blocks/BlockRegistry.php`: `ORDER`.
- `packages/blocks/src/shared/block-icons.tsx` (shared; also the SPA's `@blocks-shared`): one new key. Consumers: `register-dynamic-block.tsx` and any SPA import of `blockIcons`; adding a key changes nothing for them.
- `packages/blocks/src/order-summary/edit.tsx` (`TEMPLATE`), `templates/layouts/classic-two-column/markup.php`, `packages/blocks/src/order-summary-details/block.json` and `templates/review-order-skipped.php` (wording).
**Renamed after implementation:** Order Summary Totals became Order Summary Breakdown (Task 5). Tasks 1–4 use the final names; Task 2 changed the block while it was still `order-summary-totals`.
**Tests:** none. Nothing here is in CLAUDE.md's test list (publish rules, layout assignment, required fields, import review). Each task has an exact manual check instead.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- WordPress floor 6.5. `block.json` supports keys like `__experimentalFontFamily` are fine, as in `order-summary-items/block.json`.
- Free names Pro only in `boot_pro()`. This is Free code, not gated by `Plan::is_pro()`, and must pass `scripts/check-pro-boundary.sh` (run by `composer lint`).
- Never hand-edit `assets/dist/`. Rebuild with `pnpm build:blocks`. Edit `render.php` in `packages/blocks/src/order-summary-total/`.
- Every new string uses the `yaycheckout` text domain, except Woo's own `__( 'Total', 'woocommerce' )` fallback, which carries the same `phpcs:ignore WordPress.WP.I18n.TextDomainMismatch` comment as `order_breakdown_options()`.
- A TypeScript copy of a PHP shape carries a `Mirrors …` comment naming its PHP owner (`docs/conventions.md`). `edit.tsx` mirrors `CheckoutFragments::order_total_html()`.
- Every attribute has a default in PHP too (`order_total_options()`), because parsed markup omits defaults.
- The `{hash}` is computed from `order_total_options()` output, never from raw attrs, so `render.php` and AJAX produce the same selector.
- `tr.order-total` keeps Woo's class name.
- The Total label is an inspector `TextControl`, as the spec decides and as Breakdown's Subtotal label already is. `.claude/rules/blocks.md` prefers canvas RichText for merchant text; the spec chose otherwise, so do not switch.
- Never test edits on an existing draft layout, because autosave writes them. Create a new draft for every editor check.
- Storefront checks: `http://yaydevs.local/?page_id=9` with a real product in the cart (`?add-to-cart=29`). Preview an unactivated layout with `\YayCheckout\Helpers\PreviewToken::mint( <id>, 60 )` and `?page_id=9&yaycheckout_preview=<token>`.
- Branch `trace/feat/order-summary-total-block` from `dev/edd`. Commit messages follow Conventional Commits and end with CLAUDE.md's trailer; stage files by name.

## Review focus

- **Selector drift between first paint and AJAX:** a Total block left at its defaults is `{}` in parsed markup but has defaults filled in `render.php`. If the hash differs, the table never refreshes. Covered by Task 1 Step 9 (default block refreshes).
- **Two copies with different settings:** each must refresh with its own label and currency code, not the first block's. Covered by Task 1 Step 9.
- **Total in the collapsible Toggle:** the toggle's `wp_kses` must keep the table and amount; the summary collapsed must still show it. Covered by Task 1 Step 10.
- **Extension rows:** must print once, in Breakdown, never in a Total block. Covered by Task 2 Step 5.
- **Old layout markup:** a Breakdown block carrying `showTotal`/`totalLabel` must open without "This block contains unexpected or invalid content" and print no Total. Covered by Task 2 Step 6.

---

### Task 1: Order Summary Total block and its fragments

**Files:**
- Create: `packages/blocks/src/order-summary-total/block.json`
- Create: `packages/blocks/src/order-summary-total/index.ts`
- Create: `packages/blocks/src/order-summary-total/edit.tsx`
- Create: `packages/blocks/src/order-summary-total/render.php`
- Create: `packages/blocks/src/order-summary-total/style.css`
- Modify: `includes/Renderer/CheckoutFragments.php` (constants near line 23, `BLOCK_FRAGMENTS` at 75-78, `summary_block_fragments()` at 143-155, new methods after `order_breakdown_html()`)
- Modify: `includes/Blocks/BlockRegistry.php` (`ORDER`, line 73)
- Modify: `packages/blocks/src/shared/block-icons.tsx` (after line 175)
- Modify: `packages/blocks/src/order-summary-toggle/edit.tsx` (`ALLOWED_BLOCKS`, line 18)

**Interfaces:**
- Consumes: `CheckoutFragments::print_summary_block( \WP_Block $block ): void`, `LayoutMarkupReader::plain_text( array $attrs, string $name, string $fallback = '' ): string`, `plainText()` (`shared/plain-text.ts`), `registerDynamicBlock()`.
- Produces:
  - Block `yaycheckout/order-summary-total`, attributes `label` (string, no default), `showLabel` (boolean, default `true`), `showCurrencyCode` (boolean, default `false`).
  - `CheckoutFragments::ORDER_TOTAL_CLASS = 'yaycheckout-order-summary-total'` (public const).
  - `CheckoutFragments::order_total_options( array $attributes ): array{show_label: bool, label: string, show_currency_code: bool}` (public static).
  - `CheckoutFragments::order_total_hash_class( array $options ): string` (private static; takes `order_total_options()` output), e.g. `yaycheckout-order-summary-total--1a2b3c4d`.
  - `CheckoutFragments::fragment_selector( string $name, array $attrs ): string` (private static): the hashed selector for a Total, `BLOCK_FRAGMENTS[ $name ][0]` for the others.
  - `CheckoutFragments::order_total_html( array $attributes = [] ): string` (public static).
  - Storefront markup `<div class="wp-block-yaycheckout-order-summary-total …"><table class="shop_table yaycheckout-order-summary-total yaycheckout-order-summary-total--{hash}"><tbody><tr class="order-total">[<th>label</th>]<td>[<span class="yaycheckout-order-summary-total__currency">USD</span>] {wc_cart_totals_order_total_html()}</td></tr></tbody></table></div>`. Task 3 seeds this block in templates.

- [ ] **Step 1: Branch**

Run: `git switch dev/edd && git pull && git switch -c trace/feat/order-summary-total-block`
Expected: `Switched to a new branch 'trace/feat/order-summary-total-block'`; `git status` shows only the untracked spec and this plan.

- [ ] **Step 2: Create `order-summary-total/block.json`**

Exactly the spec's Components entry: `name` `yaycheckout/order-summary-total`, `title` "Order Summary Total", `category` `yaycheckout`, `description` "The amount the shopper pays. Updates as the cart changes. Add it more than once, for example in the summary header.", `keywords` `total`, `grand total`, `amount`, `price`, `pay`, `textdomain` `yaycheckout`, `ancestor: [ "yaycheckout/order-summary" ]`, the three attributes above. `supports`: `html: false`, `reusable: false`, `color { text, background }`, `__experimentalBorder { color, radius, style, width }`, `spacing { margin, padding }`, and `typography` with the eight keys of `order-summary-items/block.json`. No `multiple`. `editorScript` `file:./index.js`, `style` `file:./style-index.css`, `render` `file:./render.php`, as in `order-summary-breakdown/block.json`.

- [ ] **Step 3: Create `index.ts` and `render.php`**

`index.ts` as in `order-summary-breakdown/index.ts`. `render.php`: docblock "Prints the order total row, which CheckoutFragments refreshes on AJAX totals updates.", `defined( 'ABSPATH' ) || exit;`, then `\YayCheckout\Renderer\CheckoutFragments::print_summary_block( $block );`.

- [ ] **Step 4: Add the PHP builders to `CheckoutFragments`**

- `public const ORDER_TOTAL_CLASS = 'yaycheckout-order-summary-total';` after `ORDER_BREAKDOWN_CLASS`.
- `BLOCK_FRAGMENTS` gains `'yaycheckout/order-summary-total' => [ '.' . self::ORDER_TOTAL_CLASS, 'order_total_html' ]`. Update its docblock: the Total's selector is replaced per block by `summary_block_fragments()`.
- `order_total_options()`: defaults `show_label` `true`, `show_currency_code` `false` (same `$bool` closure as `order_breakdown_options()`), `label` = `LayoutMarkupReader::plain_text( $attributes, 'label', __( 'Total', 'woocommerce' ) )`. Docblock says why defaults are repeated (parsed attrs omit them).
- `order_total_hash_class()`: `self::ORDER_TOTAL_CLASS . '--' . substr( md5( (string) wp_json_encode( $options ) ), 0, 8 )`, where `$options` is `order_total_options()` output.
- `order_total_html()`: `''` without `WC()->cart instanceof \WC_Cart`; otherwise the table in Interfaces, with the `th` only when `show_label` and the currency span only when `show_currency_code`. Escape the label with `esc_html()`, the classes with `esc_attr()`. No hooks.

- [ ] **Step 5: Dedupe summary fragments by selector**

In `summary_block_fragments()`, compute the selector per block with `fragment_selector( $name, $block['attrs'] )` (a Total: `'.' . order_total_hash_class( order_total_options( $attrs ) )`; others: `BLOCK_FRAGMENTS[ $name ][0]`), and key `$summary` by that selector instead of `$name` (first wins). Update the docblock: one entry per distinct selector; Total copies with identical options share one. `fragments()`, `skip_review_order_table()` and `print_summary_block()` stay as they are.

- [ ] **Step 6: Lint the PHP**

Run: `composer lint -- includes/Renderer/CheckoutFragments.php packages/blocks/src/order-summary-total/render.php`
Expected: the boundary check passes and phpcs reports no errors.

- [ ] **Step 7: Create `edit.tsx`, `style.css`; register the block**

- `edit.tsx`: comment `// Mirrors CheckoutFragments::order_total_html(), so its style.css draws it as on the storefront.` Inspector `PanelBody` "Order summary total" with `ToggleControl` Show label, `TextControl` Total label (placeholder `__( 'Total', 'yaycheckout' )`, rendered only while `showLabel`), `ToggleControl` Show currency code. Canvas: the Interfaces table with `$20.00` and `USD`, label `plainText( label ) || placeholder`.
- `style.css`: the table rules of `order-summary-breakdown/style.css` re-scoped to `.yaycheckout-order-summary table.yaycheckout-order-summary-total` (width, fixed layout, collapse, `6px 0` padding, `th` normal weight, first cell start-aligned, last cell `width: 40%` end-aligned), plus `tr.order-total th { font-weight: bold; }`, the `__currency` rule (`margin-inline-end: 0.35em; font-weight: 400`), and a rule making a lone `td` (`td:only-child`) full width and end-aligned. Cells use `font: inherit` so the wrapper's typography reaches them.
- `BlockRegistry::ORDER`: `'order-summary-total'` after `'order-summary-breakdown'`.
- `order-summary-toggle/edit.tsx`: `ALLOWED_BLOCKS` adds `'yaycheckout/order-summary-total'`, with a comment that the Total's ancestor rule keeps it out of a Toggle outside the summary. (Found during implementation; agreed with the user.)
- `block-icons.tsx`: `'yaycheckout/order-summary-total'` with a distinct icon (e.g. a bold sum line `<path d="M4 9h16M4 15h16" />`).

- [ ] **Step 8: Build and check the editor**

Run: `pnpm build:blocks`, then `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`
Expected: build succeeds with `assets/dist/blocks/order-summary-total/` present; tsc reports no errors.
Then: create a **new** draft layout from Default. Open the inserter inside Order Summary Details and inside the Order Summary Toggle.
Expected: "Order Summary Total" is offered in both, not at the Checkout root; inserting it shows `Total` / `$20.00`; turning Show label off hides the label and the Total label field; Show currency code shows `USD`; font size from the Typography panel changes the preview; a Total label of only spaces previews as `Total`.

- [ ] **Step 9: Check the storefront refresh**

In that draft, add one Total in Details with label "Amount due" and one in the Toggle with Show label off and Show currency code on; leave a third in Details at defaults with font size Large and a border, and a fourth at defaults with no styles. Save; preview it (Global constraints) with product 29 in the cart.
Expected:
- Four tables print; the two non-default ones have different `--{hash}` classes, and the two default ones share a third.
- Change the shipping country so shipping cost changes. In DevTools, the `?wc-ajax=update_order_review` response's `fragments` has exactly three `.yaycheckout-order-summary-total--…` keys, and every table on the page shows the new total with its own label/currency code; the Large/bordered copy keeps its font size and border after the refresh.

- [ ] **Step 10: Check the collapsible Toggle**

Turn on Collapsible on the Order Summary (initial state collapsed), save, reload the preview.
Expected: the Toggle row shows `USD` and the amount; the details are collapsed; after a shipping change the amount in the Toggle updates.

- [ ] **Step 11: Commit**

Stage: `docs/specs/2026-10-13-order-summary-total-block-design.md`, `docs/plans/2026-10-13-order-summary-total-block.md`, `packages/blocks/src/order-summary-total/block.json`, `packages/blocks/src/order-summary-total/index.ts`, `packages/blocks/src/order-summary-total/edit.tsx`, `packages/blocks/src/order-summary-total/render.php`, `packages/blocks/src/order-summary-total/style.css`, `includes/Renderer/CheckoutFragments.php`, `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/shared/block-icons.tsx`, `packages/blocks/src/order-summary-toggle/edit.tsx`
Message: `feat(blocks): add an Order Summary Total block`

### Task 2: Take the Total out of Order Summary Breakdown

**Files:**
- Modify: `includes/Renderer/CheckoutFragments.php` (`order_breakdown_options()` at ~933-946, `order_breakdown_html()` at ~955-1017)
- Modify: `packages/blocks/src/order-summary-breakdown/block.json`
- Modify: `packages/blocks/src/order-summary-breakdown/edit.tsx`
- Modify: `packages/blocks/src/order-summary-breakdown/style.css`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `order_breakdown_options( array $attributes ): array{show_subtotal: bool, subtotal_label: string}`; Breakdown's table ends with the two hooks and has no `tr.order-total`.

- [ ] **Step 1: PHP**

`order_breakdown_options()`: drop `show_currency_code`, `show_total`, `total_label` and their `@return` keys. `order_breakdown_html()`: delete the `tr.order-total` block (the `if ( $options['show_total'] )` row); keep `woocommerce_review_order_before_order_total` and `woocommerce_review_order_after_order_total` where they are, adjacent. Update its docblock: the Total row is the Order Summary Total block's.

- [ ] **Step 2: block.json**

Remove `showCurrencyCode`, `showTotal`, `totalLabel`. `description`: "Subtotal, discounts, shipping, fees and taxes: the lines that add up to the order total. Updates as the cart changes.", `keywords` `subtotal`, `discount`, `coupon`, `shipping`, `fee`, `tax`, `totals`

- [ ] **Step 3: edit.tsx and style.css**

`edit.tsx`: remove the three attributes from the interface and destructuring, the Show currency code, Show total and Total label controls, `totalPlaceholder`, and the `tr.order-total` mock row. `style.css`: remove the `tr.order-total th` and `__currency` rules.

- [ ] **Step 4: Lint and build**

Run: `composer lint -- includes/Renderer/CheckoutFragments.php`, then `pnpm build:blocks`
Expected: no phpcs errors; build succeeds.

- [ ] **Step 5: Check the storefront**

Preview the Task 1 draft with product 29 in the cart and a coupon applied.
Expected: the Breakdown table lists subtotal, coupon, shipping (and tax if enabled) and no Total row; each Total block still shows the total. Then add `add_action( 'woocommerce_review_order_before_order_total', function () { echo '<tr class="probe"><th>Probe</th><td>1</td></tr>'; } );` to a temporary mu-plugin, reload, change the shipping country.
Expected: exactly one `tr.probe` on the page, inside `table.yaycheckout-order-summary-breakdown`, before and after the refresh. Delete the mu-plugin.

- [ ] **Step 6: Check old markup**

Create a **new** draft layout, open the Code editor, and replace the Breakdown comment with `<!-- wp:yaycheckout/order-summary-breakdown {"showTotal":true,"totalLabel":"Grand total","showCurrencyCode":true} /-->`; switch back to the Visual editor.
Expected: no invalid-block warning; the Breakdown preview has no Total row. Delete the draft.

- [ ] **Step 7: Commit**

Stage: `includes/Renderer/CheckoutFragments.php`, `packages/blocks/src/order-summary-breakdown/block.json`, `packages/blocks/src/order-summary-breakdown/edit.tsx`, `packages/blocks/src/order-summary-breakdown/style.css`
Message: `refactor(order-summary-totals): move the order total to the Order Summary Total block`

### Task 3: Seed new layouts with the Total block

**Files:**
- Modify: `packages/blocks/src/order-summary/edit.tsx` (imports near line 18, `TEMPLATE` at 45-52)
- Modify: `templates/layouts/classic-two-column/markup.php` (line 193)
- Modify: `packages/blocks/src/order-summary-details/block.json` (`description`)
- Modify: `templates/review-order-skipped.php` (docblock)

**Interfaces:**
- Consumes: block `yaycheckout/order-summary-total` (Task 1).
- Produces: nothing new.

- [ ] **Step 1: Templates**

`order-summary/edit.tsx`: `import totalMeta from '../order-summary-total/block.json';`; Details children `[ [ itemsMeta.name ], [ breakdownMeta.name ], [ totalMeta.name ] ]`; the comment above `TEMPLATE` says "Items, Breakdown and Total". `classic-two-column/markup.php`: `<!-- wp:yaycheckout/order-summary-total /-->` on the line after `<!-- wp:yaycheckout/order-summary-breakdown /-->`.

- [ ] **Step 2: Wording**

`order-summary-details/block.json` description: "The Order Summary's items, totals, order total and your own blocks." `review-order-skipped.php` docblock: "Order Summary Items, Breakdown and Total blocks".

- [ ] **Step 3: Lint, build, check**

Run: `composer lint -- templates/layouts/classic-two-column/markup.php templates/review-order-skipped.php`, then `pnpm build:blocks`
Expected: no phpcs errors; build succeeds.
Then create a **new** layout from Default and one from Classic two-column.
Expected: in both, Order Summary Details holds Items, (Coupon Form in Classic), Breakdown, Total, in that order, and the editor preview shows the total once.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/order-summary/edit.tsx`, `templates/layouts/classic-two-column/markup.php`, `packages/blocks/src/order-summary-details/block.json`, `templates/review-order-skipped.php`
Message: `feat(layouts): add the Order Summary Total block to new layouts`

### Task 4: End-to-end verification

- [ ] **Step 1: Spec criterion 14**

Run `pnpm build:blocks`; create a new layout from Default; add a second Order Summary Total to the Order Summary Toggle with Show label off and Show currency code on; set the Details Total's label to "Amount due"; turn on Collapsible; publish. On the storefront checkout (`?page_id=9`) with product 29 in the cart, change the shipping address so shipping cost changes.
Expected: the Toggle shows `USD` and the new total with no label, the Details shows "Amount due" with the new total, and the Breakdown table shows no Total row.

- [ ] **Step 2: Release note and plan status**

In the PR description, include the spec's Rollout note: "Order Summary Totals is now Order Summary Breakdown and no longer shows the order total. Add the new Order Summary Total block to your layouts." Set this plan's Status to `done YYYY-MM-DD` in the PR that finishes it.

### Task 5: Rename Order Summary Totals to Order Summary Breakdown

**Files:**
- Move: `packages/blocks/src/order-summary-totals/` → `packages/blocks/src/order-summary-breakdown/`
- Modify: `includes/Renderer/CheckoutFragments.php`, `includes/Renderer/LiveValues.php`, `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/order-summary/{edit.tsx,render.php,style.css}`, `packages/blocks/src/order-summary-details/{block.json,render.php}`, `packages/blocks/src/order-summary-toggle/edit.tsx`, `packages/blocks/src/order-summary-total/{block.json,style.css}`, `packages/blocks/src/shared/block-icons.tsx`, `templates/layouts/classic-two-column/markup.php`, `templates/review-order-skipped.php`, `docs/architecture.md`

**Interfaces:**
- Produces: block `yaycheckout/order-summary-breakdown`, title "Order Summary Breakdown"; class `yaycheckout-order-summary-breakdown` (and `__shipping`); `CheckoutFragments::ORDER_BREAKDOWN_CLASS`, `order_breakdown_options( array $attributes ): array{show_subtotal: bool, subtotal_label: string}`, `order_breakdown_html( array $attributes = [] ): string`.
- Unchanged names: `yaycheckout/order-summary-total` ("Order Summary Total"), `ORDER_TOTAL_CLASS`, `order_total_*()`, `fragment_selector()`.

- [ ] **Step 1: Rename**

Move the folder; replace the slug, class, constant and method names; set both blocks' `description` and `keywords` as in the spec's Components; update the comments and `docs/architecture.md` that name the block. WooCommerce's own `tr.order-total` class is not renamed.

- [ ] **Step 2: Check**

Run: `composer lint -- <changed PHP files>`, `pnpm build:blocks`, `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`
Expected: no phpcs errors; build succeeds and `assets/dist/blocks/` holds `order-summary-breakdown` and `order-summary-total` and no `order-summary-totals`; tsc reports no errors.
Then create a **new** layout from Default.
Expected: the inserter shows "Order Summary Breakdown" and "Order Summary Total"; Details holds Items, Coupon Form, Order Summary Breakdown, Order Summary Total; on the preview every table refreshes after a cart change under `.yaycheckout-order-summary-breakdown` and `.yaycheckout-order-summary-total--{hash}`.

- [ ] **Step 3: Commit**

Message: `refactor(blocks): rename to Order Summary Breakdown and Order Summary Total`
