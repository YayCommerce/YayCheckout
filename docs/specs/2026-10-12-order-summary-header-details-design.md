# Order Summary Header and Details Design

**Status:** accepted 2026-10-12
**Related:** `~/Downloads/WooCommerce Checkout Templates_v1.1.html` (the template mocks: 02 "Multi-step wizard" opens its summary down from a card, 04 "Accordion one-page" opens it up from a bottom bar); builds on `docs/specs/2026-10-07-order-summary-zones-design.md`

## Goal

A merchant can split the Order Summary into a header and a details part, fill the header with any blocks laid out left and right, and make the summary collapsible: it opens downward or upward, starts collapsed or open, and opens or closes when the shopper clicks the header or any Summary Toggle placed anywhere in the layout.

## Context

- `packages/blocks/src/order-summary/` is a container: `render.php` prints `<h3 id="order_review_heading">{label}</h3>` and then its inner blocks (Items, Coupon Form, Totals, the merchant's blocks), or WooCommerce's `review-order.php` table when it has no inner blocks. `edit.tsx` seeds `TEMPLATE = [ items, totals ]`, allows `useBlocksAllowedExcept( isCheckoutSection )` and shows the `label` through `shared/section-label.tsx`.
- Items and Totals declare `ancestor: [ "yaycheckout/order-summary" ]`. `Renderer\CheckoutFragments::BLOCK_FRAGMENTS` finds them by block name and refreshes only `.yaycheckout-order-summary-items` and `.yaycheckout-order-summary-totals` on `update_order_review`; nothing else in the summary is replaced.
- Hide/show precedent: `Services\Disclosure` and `packages/blocks/src/shared/disclosure-view.ts` (Field, Coupon Form). The panel is `hidden` on the server so it never flashes, and the checkout already needs JS. The Apartment field's collapsed editor preview is dimmed to `opacity: 0.5`.
- The live-value text format (`packages/blocks/src/live-value/`, `docs/specs/2026-10-12-live-value-text-design.md`) puts a live Order total in any rich text and refreshes it with the cart.
- Template 01's summary is in `templates/layouts/classic-two-column/markup.php` (lines 175–183): Order Summary (`label` "Order summary") holding Items, Coupon Form and Totals. Its comment and `order-summary/edit.tsx` say to change both together.
- Mock 02: a card whose full-width header shows "Show order summary ⌄" on the left and the total on the right; the details open below it. Mock 04: a bar whose header stacks "Show summary ⌃" over the total on the left and has a Continue button on the right; the details open above it. Templates 02 and 04 are "Coming soon" in `LayoutTemplateCatalogue` (slugs `multi-step-wizard`, `accordion-one-page`).

## Scope

**In:**
- New blocks `yaycheckout/order-summary-header`, `yaycheckout/order-summary-details` and `yaycheckout/order-summary-toggle`.
- Order Summary settings Collapsible, Starts (collapsed or open), and Opens (down or up, the block order).
- Storefront collapse: the header and every Toggle open and close the details, all in sync.
- An editor action that moves an old-shape Order Summary into the new shape.
- A new Order Summary and template 01 start in the new shape, with Collapsible off.

**Out:**
- Pinning the summary to the bottom of the screen (mock 04's sticky bar).
- Building templates 02 and 04. They stay "Coming soon"; this design makes them buildable.
- Text that changes with the state ("Show"/"Hide"). Only the chevron and `aria-expanded` change.
- Place Order inside the header. It stays a checkout section, excluded inside Order Summary.
- Open/close animation, remembering the state across reloads, a per-breakpoint start state, and scrolling to the summary when a far-away Toggle opens it.
- Converting old-shape layouts automatically or on load.
- Any Pro gating.

## Acceptance criteria

1. Given a stored layout whose Order Summary has no Header or Details child, the storefront prints the same HTML as before this change: `<h3 id="order_review_heading">{label}</h3>`, then the children.
2. Given a new Order Summary inserted in the editor, it holds a Summary Header containing a Summary Toggle containing a Heading (h3, "Order summary"), then a Summary Details containing Order Summary Items and Order Summary Totals. Collapsible is off.
3. Given a new-shape Order Summary, the inserter inside the summary itself offers only Summary Header (only while it is absent). Inside Summary Details, the inserter offers no checkout section and no Summary Toggle. Inside Summary Header, it offers no checkout section. Inside a Summary Toggle, it offers only Paragraph, Heading, Image, Group (with its Row and Stack variations) and Spacer.
4. Given Collapsible off, the storefront prints no header button, no `hidden` on Details and no `aria-expanded` anywhere. Every Toggle prints its inner blocks without `role`, `tabindex` or chevron.
5. Given Collapsible on and Starts "Collapsed", the page loads with `#yaycheckout-order-summary-details` carrying `hidden` and every toggle element with `aria-expanded="false"`. With Starts "Open", Details has no `hidden`, the wrapper has `is-open`, and every toggle has `aria-expanded="true"`.
6. Given Collapsible on, a click anywhere on the Summary Header, a click on any Summary Toggle, or Enter or Space on a focused Toggle flips Details' `hidden`, the wrapper's `is-open`, and `aria-expanded` on the header button and on every Toggle. One click on a Toggle inside the header flips the state exactly once.
7. Given a Step button or a link in the Summary Header, clicking it performs its own action and does not toggle the details.
8. Given the Header after Details in the block order (Opens "Up"), the storefront DOM prints Details before the Header. The sidebar's Opens control shows "Up", and choosing "Down" moves the Header before Details.
9. Given Collapsible on and the layout has no Summary Header and no Summary Toggle, Details always shows. In the editor, Collapsible is disabled with the help text "Add a Summary Header or a Summary Toggle to make the summary collapsible."
10. Given the summary open and a Coupon Form inside Details with typed text, when `update_order_review` runs (shipping method change), Details stays open, the typed text remains, and Items and Totals show the new amounts.
11. Given an old-shape Order Summary in the editor, its sidebar shows a **Use header and details** button. Clicking it leaves a Header holding a Toggle holding a Heading (h3, the label's text, anchor `order_review_heading`), and a Details holding the old children in their old order, with Collapsible off.
12. Given template 01 (`classic-two-column`) created as a new layout, its Order Summary is in the new shape with Collapsible off, and no block is invalid in the editor.
13. End-to-end verification: run `pnpm build:blocks`. Create a new layout from template 01. In Order Summary, turn on Collapsible, set Starts "Collapsed" and Opens "Up", add a Paragraph with the live Order total to the header, put a Summary Toggle containing "View cart" at the top of the Checkout block, and publish and activate. On the storefront with two products in the cart:
    - the details are hidden and sit above the header;
    - clicking the top "View cart" Toggle opens them, and both chevrons flip;
    - changing the shipping method updates the header total and the Totals while the details stay open;
    - clicking the header closes them;
    - placing an order with a test gateway succeeds.

## Constraints

- Product PHP runs on PHP 7.4 (CLAUDE.md hard rule 4).
- Free and Pro both ship it. No `Plan::is_pro()` check and no reference to `YayCheckout\Pro`.
- Edit block sources in `packages/blocks/src/`, never `assets/dist/blocks/`. Blocks are dynamic: `save()` emits only inner blocks (`saveNestedBlocks`).
- `render.php` must treat missing `collapsible` as `false` and missing `initialState` as `"collapsed"`, because stored layouts lack them.
- The Details wrapper must not carry `.yaycheckout-order-summary-items`, `.yaycheckout-order-summary-totals` or `.woocommerce-checkout-review-order-table`, or `update_order_review` replaces it.
- Merchant-typed text stays in the inner blocks (core RichText); the new blocks add no text attributes besides Order Summary's existing `label`.
- `templates/layouts/classic-two-column/markup.php` and the `TEMPLATE` in `packages/blocks/src/order-summary/edit.tsx` change together. Build the markup in the editor and copy it from the Code editor, as the file's header says.

## Approach

**Chosen:** dedicated child blocks: Header and Details under Order Summary, plus a Summary Toggle that can go anywhere. The block order sets the direction.

**Architecture:**
- Order Summary has two shapes:
  - **Old:** no Header or Details child. It prints as today.
  - **New:** only Header and Details as direct children, printed in block order, so Header first opens down and Header last opens up. The DOM order, tab order and visual order always match.
- Collapsing is decided once per layout by a pure service and read by every block involved. A Toggle outside the summary can't receive block context, so they all ask that one owner.
- One view script binds the header's overlay button and every Toggle to the single Details panel. Only one Order Summary is allowed per layout (`multiple: false`), so the panel id is fixed.

**Components:**
- `packages/blocks/src/order-summary/`
  - `block.json`: adds attributes `collapsible` (`boolean`, default `false`) and `initialState` (`string`, enum `collapsed | open`, default `collapsed`); `"viewScript": "file:./view.js"`; `"style": "file:./style-index.css"`.
  - `render.php`: old shape as today. New shape: the wrapper class gains `is-collapsible` (and `is-open`) when `OrderSummaryCollapse::state()` says so, there is no `<h3>`, and `$content` follows.
  - `edit.tsx`:
    - The new `TEMPLATE`.
    - `allowedBlocks`: `[]` in the new shape, so only children with a `parent` rule naming it (Header, Details) fit; the old shape keeps `useBlocksAllowedExcept( isCheckoutSection )`.
    - The sidebar controls Label, Collapsible, Starts and Opens. Opens moves the Header with `moveBlockToPosition` and reads its value from the Header's index relative to Details.
    - The **Use header and details** notice and button.
    - Dimming Details at `opacity: 0.5` while Collapsible is on and Starts is Collapsed.
  - `view.ts`: binds `.yaycheckout-order-summary__header-button` and every `[data-yaycheckout-summary-toggle]`. A click on either, and Enter or Space on a Toggle (with `preventDefault`), flip the state. `stopPropagation` isn't needed, because the overlay is a sibling and not an ancestor. Flipping sets Details' `hidden`, the wrapper's `is-open`, and `aria-expanded` on every bound element.
  - `style.css`:
    - The header is `position: relative`. The overlay button is `position: absolute; inset: 0`, transparent, with a visible focus ring.
    - `.yaycheckout-order-summary-header :is(a, button:not(.yaycheckout-order-summary__header-button), input, select, textarea, label, [role="button"], [tabindex])` gets `position: relative; z-index: 1`.
    - The chevron rotates 180° under `[aria-expanded="true"]`.
- `packages/blocks/src/order-summary-header/` (`block.json`, `index.ts`, `edit.tsx`, `render.php`)
  - `block.json`: `parent: [ "yaycheckout/order-summary" ]`; `supports: { html: false, multiple: false, reusable: false, color: { text, background }, __experimentalBorder, spacing: { margin, padding, blockGap }, layout: { default: { type: "flex", justifyContent: "space-between", verticalAlignment: "center", flexWrap: "wrap" }, allowSwitching: false } }`.
  - Allows `useBlocksAllowedExcept( isCheckoutSection )`.
  - `render.php` prints the wrapper `.yaycheckout-order-summary-header`. When collapsible, it first prints `<button type="button" class="yaycheckout-order-summary__header-button" aria-expanded="{true|false}" aria-controls="yaycheckout-order-summary-details" aria-label="{label}"></button>`, then `$content`.
- `packages/blocks/src/order-summary-details/` (same files)
  - `block.json`: `parent: [ "yaycheckout/order-summary" ]`; `supports: { html: false, multiple: false, reusable: false }` plus color, border and spacing. Order Summary's `TEMPLATE` and the conversion insert it with `lock: { remove: true }`.
  - Allows `useBlocksAllowedExcept( type => isCheckoutSection( type ) || type.name === 'yaycheckout/order-summary-toggle' )`.
  - `render.php` prints `<div id="yaycheckout-order-summary-details" class="yaycheckout-order-summary-details" {hidden}>` around `$content`.
- `packages/blocks/src/order-summary-toggle/` (same files)
  - `block.json`: no `parent` or `ancestor`; attribute `showChevron` (`boolean`, default `true`); `supports: { html: false, reusable: false }` plus color, border, spacing and typography.
  - `allowedBlocks`: `core/paragraph`, `core/heading`, `core/image`, `core/group`, `core/spacer`.
  - `render.php`:
    - **When collapsible:** `<div class="yaycheckout-order-summary-toggle" role="button" tabindex="0" aria-expanded="{state}" aria-controls="yaycheckout-order-summary-details" data-yaycheckout-summary-toggle>`, then `$content` with links unwrapped through `wp_kses` without `a`, then the `aria-hidden` chevron SVG (the one `Disclosure::open()` uses) when `showChevron` is on.
    - **Otherwise:** a plain `<div class="yaycheckout-order-summary-toggle">` around `$content`.
  - The editor sidebar shows the help text "Opens and closes the Order Summary while it is collapsible." and the **Show chevron** toggle.
- `includes/Services/OrderSummaryCollapse.php`: pure. `state( string $content ): array` returns `[ 'collapsible' => bool, 'open' => bool ]`.
  - `collapsible` is true when the layout's Order Summary has `collapsible: true` and the layout has a `yaycheckout/order-summary-header` or a `yaycheckout/order-summary-toggle` (`has_block()`).
  - `open` is `! collapsible || 'open' === initialState`.
  - The result is memoised per content string.
- The three new `render.php` files and Order Summary's read the content through `CheckoutPage::get_instance()->layout()->content`. When the layout is null they use `[ 'collapsible' => false, 'open' => true ]`.
- `includes/Blocks/BlockRegistry.php`: `ORDER` adds `order-summary-header`, `order-summary-details` and `order-summary-toggle` after `order-summary`.
- `packages/blocks/src/shared/block-icons.tsx`: an icon for each of the three blocks.
- `templates/layouts/classic-two-column/markup.php`: Order Summary in the new shape. The Header holds a Toggle holding a Heading (h3, "Order summary"). Details holds Items (`showLabels: false`, `quantityDisplay: badge`), the Coupon Form (collapsible "Add coupons") and Totals.
- `docs/architecture.md`: one bullet under the storefront section for summary collapsing and `OrderSummaryCollapse`.

**Data and storage:** None besides block attributes in layout markup (`collapsible`, `initialState`, `showChevron`). No option, meta or migration. The open state lives in the DOM only.

**Integration points:**
- `update_order_review` and `CheckoutFragments` are unchanged. Items and Totals keep their fragments inside Details.
- The live-value format refresh updates a total placed in the header or in a Toggle.
- Core block-editor actions `moveBlockToPosition` and `replaceInnerBlocks` (for the Use header and details conversion).
- `BlockGap` already covers `order-summary`. Header uses core's flex layout gap.

**UI:** Block editor only: the Order Summary sidebar, the Toggle sidebar, and the old-shape notice. No admin SPA change.

**Edge cases and failure modes:**
- **No Header and no Toggle, with Collapsible on:** the details always show (criterion 9).
- **Header removed:** the inserter offers it again inside Order Summary. A Toggle elsewhere still collapses the summary.
- **Toggle in a layout with no Order Summary**, or one that isn't collapsible: it prints its content plainly.
- **Toggle inside a Step that isn't open:** it's invisible until the step opens, and it still works then.
- **Toggle inside Details:** the inserter blocks it there. Inside a nested Group in Details, `useBlocksAllowedExcept` only binds direct children, so it's possible there. Then a Toggle inside a Group inside Details hides itself when it closes the panel. That's accepted; the header or another Toggle reopens it.
- **No JavaScript:** a summary that starts collapsed can't be opened. Accepted, as for the Apartment link.
- **Themes styling `#order_review_heading`:** old-shape layouts keep it. The conversion keeps it through the Heading's anchor. New layouts get a core Heading inside a Toggle without that id.
- **Several Toggles:** they share one state, and each one's `aria-expanded` is updated on every flip.

**Decision record:** None. This follows the existing child-block pattern of Order Summary Items and Totals.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Header built from core Row and Group blocks, marked by class names | The merchant can rename, ungroup or restyle the markers away and silently break collapsing, and it can't be locked. |
| Header blocks only, everything after it counts as details | "Up" needs the header last, and the merchant's blocks between are ambiguous. |
| Header Start and Header End side blocks inside the Header | Core's flex layout on the Header gives the same left/right alignment with one fewer block. A Group stacks items on one side. |
| Direction as a stored attribute plus CSS `column-reverse` | The visual order and the tab order would differ (an accessibility failure). The block order is the setting instead. |
| Only Toggles toggle (the header is a plain row) | The user wanted a whole-header click as well. |
| Toggle with built-in Show/Hide text, or two "When open"/"When closed" areas | The user chose static inner blocks, with only the chevron changing. |
| Real `<button>` for the Toggle | A `<button>` may hold only inline content, not the `<p>` and `<div>` that inner blocks print. |
| Converting old layouts on editor open | It changes markup the merchant didn't touch, and autosave writes it. It's an opt-in button instead. |
| Pinning to the bottom of the screen | Out of scope. It brings layout-shift, mobile-keyboard and theme-overlap issues and deserves its own design. |

## Rollout

No migration and no setting. Stored layouts keep the old shape and print as today until the merchant clicks **Use header and details** and saves. New summaries and new template 01 layouts get the new shape with Collapsible off, so they look as before. The release note mentions the collapsible summary and the conversion button.

## Open questions

None.
