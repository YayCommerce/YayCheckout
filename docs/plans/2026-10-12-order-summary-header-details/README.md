# Order Summary Header and Details Implementation Plan

> **For agentic workers:** execute phases in order. Read this file, then the current phase file. Do not start a phase until the previous phase's exit criterion has been verified and `/yayplancheck` has passed on it.

**Status:** active
**Goal:** Split Order Summary into a Summary Header (any blocks, laid out left and right) and Summary Details, and make it collapsible. It starts collapsed or open, opens down or up, and toggles from the header or from any Summary Toggle in the layout.
**Spec:** `docs/specs/2026-10-12-order-summary-header-details-design.md`
**Architecture:**
- **Three new child blocks:** Header, Details and Toggle, plus two Order Summary attributes. A summary is in the "new shape" when it has a Header or a Details child; otherwise it prints exactly as today.
- **One owner for the collapse state:** a pure `Services\OrderSummaryCollapse` decides it from the layout markup, and every `render.php` involved asks it. A Toggle outside the summary can't get block context, which is why it's a service and not context.
- **One view script:** Order Summary's binds the header's overlay button and every Toggle to the single Details panel.
- **Direction is the block order:** no attribute, so DOM order and visual order always match.

**Touches:**
- **Blocks:**
  - `packages/blocks/src/order-summary/{block.json,edit.tsx,render.php}`;
  - new `packages/blocks/src/order-summary/{view.ts,style.css,editor.css,shape.ts}`;
  - new folders `packages/blocks/src/order-summary-header/`, `packages/blocks/src/order-summary-details/` and `packages/blocks/src/order-summary-toggle/`, each with `block.json`, `index.ts`, `edit.tsx` and `render.php`;
  - `packages/blocks/src/shared/block-icons.tsx` (shared);
  - `packages/blocks/src/shared/section-label.tsx` (shared), which gains an optional `showHeading` prop defaulting to `true`. Its consumers are `order-summary/edit.tsx` and `payment/edit.tsx`; Payment keeps today's behaviour.
- **PHP:** new `includes/Services/OrderSummaryCollapse.php`; `includes/Blocks/BlockRegistry.php`.
- **Template:** `templates/layouts/classic-two-column/markup.php`.
- **Docs:** `docs/architecture.md`.
- **Shared code:** `packages/blocks/src/shared/block-icons.tsx` is also the SPA's `@blocks-shared`. Adding entries changes no consumer, but the SPA type-check runs after it is edited.

**Tests:** none. The collapse state decides what shows, never whether an order places, so it falls outside CLAUDE.md's test rule. Every task has an exact manual check instead.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types, and none of the items in `.claude/rules/php-standards.md` ("PHP 7.4, beyond hard rule 4").
- Free and Pro both ship it: no `Plan::is_pro()` check, and no reference to `YayCheckout\Pro` in `includes/`.
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/<block>/` and run `pnpm build:blocks`.
- Blocks are dynamic. A container's `save` is `saveNestedBlocks`, and a leaf's is null. `render.php` treats a missing `collapsible` as `false`, a missing `initialState` as `"collapsed"`, and a missing `showChevron` as `true`.
- The Details wrapper must not carry `.yaycheckout-order-summary-items`, `.yaycheckout-order-summary-totals` or `.woocommerce-checkout-review-order-table`.
- Services are pure: `OrderSummaryCollapse` takes markup or a `Layout`, never calls `CheckoutPage`. Each `render.php` passes `\YayCheckout\Renderer\CheckoutPage::get_instance()->layout()`.
- The new blocks add no merchant-text attributes; text lives in inner blocks.
- `templates/layouts/classic-two-column/markup.php` and `TEMPLATE` in `packages/blocks/src/order-summary/edit.tsx` change together. The markup is copied from the editor's Code editor, never hand-written.
- Comments follow `docs/conventions.md` (Comments): no doc pointers, and no plan or feature IDs.
- Run from the repo root on Node 22 (`nvm use`), with pnpm only. `composer lint -- <files>` is clean for every changed PHP file. `pnpm build:blocks` is the only check on the blocks' TypeScript.
- Never test edits on an existing draft layout in the editor, because autosave writes them. Create a fresh layout for each check.
- **Commits:**
  - Conventional Commits, ending with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`.
  - Branch from `dev/edd` as `trace/feat/order-summary-header-details`.
  - Stage files by name.
  - Commit only when the user asks.

## Review focus

- **Clicks in the header:** a click on a Step button, a link or a Toggle inside a collapsible header performs its own action. A Toggle flips the state exactly once, never twice through the overlay too. Owner: Phase 2 Task 2, Step "verify clicks".
- **AJAX refresh:** after a shipping-method change, the details stay open, typed coupon text stays, and the Items and Totals amounts update. Owner: Phase 2 Task 2, Step "verify refresh".
- **Old layouts:** an Order Summary saved before this change prints byte-for-byte as before (`<h3 id="order_review_heading">`, then children). Opening it in the editor changes nothing until the merchant clicks the button. Owners: Phase 1 Task 1 Step 6 and Phase 3 Task 1.
- **Opens Up:** a Header after Details prints after Details in the DOM, and the Opens control reads that order back. Owner: Phase 2 Task 3.
- **No trigger:** Collapsible on, with no Header and no Toggle in the layout, still shows the details. Owner: Phase 2 Task 1, Step "verify state".
- **Links inside a Toggle:** a link typed into a Toggle's paragraph prints as text while collapsible, so it never nests a link in a `role="button"`. Owner: Phase 2 Task 1.

## Phases

| Phase | Delivers (user-visible) | Exit criterion (exact check) | Status |
|---|---|---|---|
| 1 | A new Order Summary has a Header (Toggle with heading) and Details (Items, Totals); the header lays out left/right; old summaries unchanged | `pnpm build:blocks`; a new layout's Order Summary in the editor shows Header > Toggle > Heading "Order summary" and Details > Items, Totals; the storefront prints them with no `#order_review_heading`; an old-shape layout's storefront HTML is unchanged | pending |
| 2 | Collapsible, Starts and Opens settings; header and Toggles open and close the details on the storefront | Spec acceptance criteria 4–10 pass on a fresh layout | pending |
| 3 | The Use header and details conversion; template 01 in the new shape; architecture doc | Spec acceptance criterion 13 (end-to-end) passes | pending |

## Interface contract

**Block names:**

| Block | Produced by |
|---|---|
| `yaycheckout/order-summary-header` | Phase 1 Task 1 |
| `yaycheckout/order-summary-details` | Phase 1 Task 1 |
| `yaycheckout/order-summary-toggle` | Phase 1 Task 2 |

**Order Summary attributes** (Phase 2 Task 1):
- `collapsible`: `boolean`, default `false`.
- `initialState`: `string`, enum `[ "collapsed", "open" ]`, default `"collapsed"`.

**Toggle attribute** (Phase 1 Task 2): `showChevron`: `boolean`, default `true`.

**Shape helper** (Phase 1 Task 1, used by every Order Summary edit task): `packages/blocks/src/order-summary/shape.ts` exports `isNewShape( childNames: string[] ): boolean`. It returns true when `childNames` includes the Header or the Details block name.

**Markup** (Phase 1 creates the wrappers; Phase 2 adds the collapse parts):

| Element | Class, id or attribute | Phase |
|---|---|---|
| Header wrapper | `.yaycheckout-order-summary-header` | 1 |
| Details wrapper | `.yaycheckout-order-summary-details`, id `yaycheckout-order-summary-details` | 1 |
| Toggle wrapper | `.yaycheckout-order-summary-toggle` | 1 |
| Order Summary wrapper, collapsible | `.is-collapsible` | 2 |
| Order Summary wrapper, open | `.is-open` | 2 |
| Header overlay button | `button.yaycheckout-order-summary__header-button` | 2 |
| Toggle, when collapsible | `[data-yaycheckout-summary-toggle]` | 2 |
| Toggle chevron | `.yaycheckout-order-summary-toggle__chevron` | 2 |

**PHP** (Phase 2 Task 1, consumed by four `render.php` files): `YayCheckout\Services\OrderSummaryCollapse`, with:
- `public const DETAILS_ID = 'yaycheckout-order-summary-details';`
- `public static function state( string $content ): array` returns `[ 'collapsible' => bool, 'open' => bool ]`.
  - `collapsible` is the first `yaycheckout/order-summary` block's `collapsible === true`, and the markup has `yaycheckout/order-summary-header` or `yaycheckout/order-summary-toggle` (`has_block()`).
  - `open` is `! collapsible || 'open' === initialState`.
  - Memoised per `md5( $content )`.
- `public static function of_layout( ?\YayCheckout\Models\Layout $layout ): array` returns `[ 'collapsible' => false, 'open' => true ]` for null, else `state( $layout->content )`.

**Editor hook** (Phase 2 Task 3, consumed by the Order Summary and Toggle edit components): `packages/blocks/src/order-summary/shape.ts` exports `useSummaryCollapse(): { clientId: string | null; collapsible: boolean; open: boolean; hasTrigger: boolean }`. It reads the layout's Order Summary through `getBlocksByName`, mirroring `OrderSummaryCollapse::state()`; `hasTrigger` is true when a Header or Toggle exists.
