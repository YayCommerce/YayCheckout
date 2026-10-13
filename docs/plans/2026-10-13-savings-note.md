# Savings note block

> **For agentic workers:** execute tasks in order. Steps use `- [ ]`. Save this plan to `docs/plans/2026-10-13-savings-note.md` before coding so a later check can read it. Do not start the next task until the current one is green.

**Status:** active
**Goal:** A merchant can insert a Savings note that shows how much the shopper is saving, and the amount follows the cart.
**Spec:** none, planned from the request
**Architecture:** A leaf block `yaycheckout/savings-note` prints one sentence. `Renderer\SavingsNote::amount()` sums three cart figures WooCommerce does not combine. `CheckoutFragments` refreshes the sentence on `update_order_review`, the same way Items and Totals refresh. A live-value chip was rejected because [docs/specs/2026-10-12-live-value-text-design.md](docs/specs/2026-10-12-live-value-text-design.md) leaves savings out of scope, and a fixed sentence is the requested block.
**Touches:** `packages/blocks/src/savings-note/` (new), `packages/blocks/src/shared/block-icons.tsx` (shared; every block icon, and the SPA via `@blocks-shared`), `includes/Renderer/SavingsNote.php` (new), `includes/Renderer/CheckoutFragments.php` (shared; consumers: every summary fragment and `render.php` of Items, Totals, shipping, payment), `includes/Blocks/BlockRegistry.php`, `docs/architecture.md`
**Tests:** none. The amount is display. A wrong figure does not stop the shopper placing the order, so it misses the CLAUDE.md test bar, the same call as live values. The formula is checked by the manual steps below.

## Requirements

- **Goal:** Inserting Savings note prints `You're saving $X` (the merchant's sentence), where `$X` is sale savings + coupon savings + shipping savings, formatted like the Totals block, and the note disappears at $0.
- **Out of scope:** Built-in layout templates (inserter only; saved layouts stay as they are). A live-value key. Fees. Per-line "you save" in Items. A Pro gate.
- **Acceptance criteria:**
  1. The layout inserter lists Savings note once. It can be placed in Summary Header, Summary Details, and elsewhere in the layout. A second copy cannot be inserted.
  2. The canvas shows the sentence with `$5.00` in place of `%s`. The sidebar Text control edits that sentence. Clearing it restores `You're saving %s`.
  3. On the storefront, with prices entered exclusive of tax: a product whose regular price is $30 and active price is $20, quantity 1, no coupon, and the cheapest paid shipping selected, prints `You're saving $10.00`. The $10 is only the sale.
  4. Applying a 10% coupon changes it to `You're saving $12.00` without a reload. The coupon is $2, not a second copy of the sale.
  5. With that coupon still applied, choosing a free rate while a $8 paid rate is also available changes it to `You're saving $20.00` without a reload. Choosing the $8 rate again returns it to `You're saving $12.00`.
  6. Removing the sale (active price equals regular), removing the coupon, and keeping the cheapest paid rate removes the note without a reload. A Group set to hide when this block is empty hides with it, and shows again when a coupon brings the amount back.
  7. With `tax_display_cart` = `incl`, each of the three parts includes its tax, and with `excl` none of them do. The coupon part matches the coupon row in Totals.
- **Decided with the user:** The classic two-column template does not gain this block. Shipping savings are the cheapest paid rate minus the chosen rate, per package, never the gap up to a more expensive rate.

## Global constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, or union types.
- Free and Pro. No code in `includes/Pro/`, no `Plan::is_pro()` check.
- Edit `packages/blocks/src/`, never `assets/dist/blocks/`. Rebuild with `pnpm build:blocks`.
- One blocks build serves both editions.
- The PHP default sentence and the editor placeholder name each other with a `Mirrors` comment ([docs/conventions.md](docs/conventions.md)).

## Review focus

- A sale computed from `line_total` would count the coupon twice. Criterion 4 expects $12, not $14.
- No address yet means no shipping rates, so shipping savings are $0 and the note still shows sale and coupon.
- Prices entered inclusive of tax must use the same incl/excl split as Totals (criterion 7).
- A chosen express rate above the cheapest paid rate adds $0 of shipping savings.

---

### Task 1: The note on first paint

**Files:**
- Create: `includes/Renderer/SavingsNote.php`
- Create: `packages/blocks/src/savings-note/block.json`, `edit.tsx`, `index.ts`, `render.php`, `style.css`
- Modify: `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php` (`ORDER`)

**Interfaces:**
- Consumes: `WC()->cart`, `WC()->shipping()->get_packages()`, `WC()->session` `chosen_shipping_methods`, `LayoutMarkupReader::plain_text()`, `HideWhenEmpty::wrapper()`
- Produces:
  - `SavingsNote::DEFAULT_TEXT` = `You're saving %s`
  - `SavingsNote::amount(): float` — sale + coupon + shipping for the current cart; `0.0` without a cart
  - `SavingsNote::is_zero(): bool` — `amount()` rounded with `wc_get_price_decimals()` is `0.0`
  - `SavingsNote::sentence_html( array $attributes ): string` — `esc_html`'d sentence with every `%s` replaced by `wc_price( amount() )`; a sentence with no `%s` uses `DEFAULT_TEXT`
  - Block `yaycheckout/savings-note`

- [ ] **Step 1: `SavingsNote::amount()` in `includes/Renderer/SavingsNote.php`**

Three private static floats, summed. Money follows `WC()->cart->display_prices_including_tax()`, the same switch as `LiveValues::values()` and `CheckoutFragments::print_order_shipping_row()`.

Sale, per cart line that passes the same visibility checks as `CheckoutFragments::order_items_html()` (`woocommerce_cart_item_product`, `woocommerce_checkout_cart_item_visible`, product exists, quantity > 0): skip a line whose `get_regular_price()` is not numeric. Regular display is `wc_get_price_including_tax()` or `wc_get_price_excluding_tax()` with `qty` = the line quantity and `price` = that regular price. Paid display is `line_subtotal`, plus `line_subtotal_tax` when prices include tax. Add `max( 0, regular_display - paid )`. This counts a plugin that lowers the active price under regular. It does not use `line_total`, so coupons stay in the next part.

Coupon: `get_discount_total()`, plus `get_discount_tax()` when prices include tax. This is `cart_discount_total` in `LiveValues::values()`. A free-shipping coupon with no product discount adds $0 here.

Shipping, per package from `WC()->shipping()->get_packages()`: the chosen id is `chosen_shipping_methods[ $index ]`, matched with `$rate->id` the way `print_order_shipping_row()` matches. A rate's display cost is `get_cost()` plus the sum of `get_taxes()` when prices include tax. Paid rates are those with a display cost that rounds above 0. When the chosen rate exists and at least one paid rate exists, add `max( 0, cheapest_paid - chosen )`. No chosen rate, or no paid rate, adds 0. Sum packages.

- [ ] **Step 2: Block `packages/blocks/src/savings-note/`**

`block.json`: `apiVersion` 3, title `Savings note`, no `parent` and no `ancestor` (Summary Header and Summary Details exclude blocks whose ancestor is Checkout, via `isCheckoutSection()`). `supports.multiple` false, `reusable` false, `html` false, `yaycheckoutWatchable` true. Attributes: `text` string default `""`, `blockId` string default `""`. Color, typography, border, and spacing supports match [packages/blocks/src/order-note/block.json](packages/blocks/src/order-note/block.json). `editorScript` `file:./index.js`, `style` `file:./style-index.css`.

`render.php` calls `CheckoutFragments::print_savings_note( $block )` only after Task 2. Until that method exists, `render.php` prints the wrapper with `HideWhenEmpty::wrapper( [], $attributes, SavingsNote::is_zero() )` and, when not zero, the sentence. Task 2 replaces that body with the one call.

`edit.tsx`: sidebar Text control, label `Text`, help `Use %s where the amount goes.` Placeholder and the empty-attribute fallback are `You're saving %s`, with a `Mirrors` comment pointing at `SavingsNote::DEFAULT_TEXT`. The canvas replaces `%s` with `$5.00`. A sentence with no `%s` previews the default sentence.

`index.ts`: `registerDynamicBlock`. `style.css`:

```css
.wp-block-yaycheckout-savings-note[data-yaycheckout-empty],
.yaycheckout-savings-note[data-yaycheckout-empty] { display: none; }
```

`block-icons.tsx`: a `yaycheckout/savings-note` icon beside the totals icon. `BlockRegistry::ORDER`: `'savings-note'` immediately after `'order-summary-totals'`. `block_settings()` already gives every `ORDER` entry padding, margin, and border.

- [ ] **Step 3: Verify first paint**

Run `pnpm build:blocks`. Expected: compiled successfully, and `assets/dist/blocks/savings-note/` exists.
Run `composer lint -- includes/Renderer/SavingsNote.php packages/blocks/src/savings-note/render.php includes/Blocks/BlockRegistry.php`. Expected: no errors.

Editor: insert Savings note into Summary Header. Canvas reads `You're saving $5.00`. Set Text to `You save %s today`. Canvas reads `You save $5.00 today`. The inserter does not offer a second Savings note.

Storefront, tax display exclusive, a $30 regular / $20 active product, qty 1, cheapest paid shipping, no coupon: the note reads `You're saving $10.00`. Reload after changing the quantity to 2: `You're saving $20.00`. Live update is Task 2.

- [ ] **Step 4: Commit**

Stage: `includes/Renderer/SavingsNote.php`, `packages/blocks/src/savings-note/block.json`, `packages/blocks/src/savings-note/edit.tsx`, `packages/blocks/src/savings-note/index.ts`, `packages/blocks/src/savings-note/render.php`, `packages/blocks/src/savings-note/style.css`, `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php`
Message: `feat(blocks): add a savings note for sale, coupon and shipping`

Trailer: `Co-Authored-By: Claude <model> <noreply@anthropic.com>`

### Task 2: Keep the amount live

**Files:**
- Modify: `includes/Renderer/CheckoutFragments.php` (`BLOCK_FRAGMENTS`, new `print_savings_note()` and `savings_note_html()`)
- Modify: `packages/blocks/src/savings-note/render.php`
- Modify: `docs/architecture.md` (the Order Summary fragments sentence)

**Interfaces:**
- Consumes: `SavingsNote::is_zero()`, `SavingsNote::sentence_html()`, `HideWhenEmpty::wrapper()`, `CheckoutFragments::live_attributes()` (stays private)
- Produces:
  - `CheckoutFragments::SAVINGS_NOTE_CLASS` = `yaycheckout-savings-note`
  - `CheckoutFragments::savings_note_html( array $attributes = [] ): string` — one `<p class="yaycheckout-savings-note" data-yaycheckout-live="1">` holding the sentence, plus `data-yaycheckout-empty="1"` when `SavingsNote::is_zero()`; empty means no sentence
  - `CheckoutFragments::print_savings_note( \WP_Block $block ): void` — wrapper from `HideWhenEmpty::wrapper()` with that same empty flag, then `savings_note_html()`
  - `BLOCK_FRAGMENTS['yaycheckout/savings-note']` = `[ '.yaycheckout-savings-note', 'savings_note_html' ]`

- [ ] **Step 1: Fragment**

`render.php` becomes the `print_savings_note( $block )` call. `summary_block_fragments()` already passes the first parsed block's attributes, and `supports.multiple` is false, so one sentence is refreshed. `checkout.js` replaces the inner `<p>` and leaves the wrapper, which is what keeps `data-yaycheckout-block-id`. `assets/js/checkout-hide-when-empty.js` already copies `data-yaycheckout-empty` from a `[data-yaycheckout-live]` node onto that wrapper on `updated_checkout`. No change to that script.

In the architecture fragments sentence, name Savings note next to Items and Totals as a block found by name in `BLOCK_FRAGMENTS`.

- [ ] **Step 2: Verify the live amount**

Run `pnpm build:blocks`, then `composer lint -- includes/Renderer/CheckoutFragments.php packages/blocks/src/savings-note/render.php`. Expected: no errors.

On the storefront note from Task 1, criterion 3 still reads `You're saving $10.00`. Apply a 10% coupon: `You're saving $12.00` with no reload. Select the free rate beside an $8 paid rate: `You're saving $20.00`. Select the $8 rate: `You're saving $12.00`. Remove the coupon and set the product's active price to its regular price, cheapest paid rate still selected: the note is gone. Put the coupon back: the note returns.

Wrap the note in a Group, tick the note under Hide when empty, and repeat the zero case: the group hides, then reappears with the coupon. Before the address is calculated, shipping adds $0 and the sale note still shows.

With tax display inclusive, repeat the $30/$20 line and the 10% coupon and confirm the coupon portion matches the Totals coupon row.

- [ ] **Step 3: Commit**

Stage: `includes/Renderer/CheckoutFragments.php`, `packages/blocks/src/savings-note/render.php`, `docs/architecture.md`
Message: `feat(blocks): refresh the savings note with the cart`

Trailer: `Co-Authored-By: Claude <model> <noreply@anthropic.com>`
