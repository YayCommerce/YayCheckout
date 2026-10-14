# Block visibility is a block attribute, decided by PHP and mirrored in TypeScript

**Purpose:** why a block's show or hide conditions are stored in the block's own markup, why PHP decides them at render while a TypeScript copy re-decides them in the browser, and why the conditions are all-must-match with no field handling.

**Update when:** never edit it. Supersede it with a new record if visibility moves out of block markup, if the browser stops re-deciding it, if conditions gain "any of" matching or negation, or if fields inside a hidden block stop being validated.

## Status

Accepted, 2026-10-13, by Trace Bui.

## Context

- A merchant needs a block to show only on some steps or for some carts: a pinned bar shows Continue on every step but the last and Place Order only on the last, and a note shows only while a coupon is applied. No block setting could express this.
- What decides it changes at different speeds. The open step changes in the browser with no request. The cart (coupon, subtotal, whether it needs shipping) changes on `update_order_review`. Whether the shopper is logged in is fixed for the page load, since WooCommerce's checkout login reloads the page.
- YayCheckout already adds settings to blocks as attributes decorated at render: Hide When Empty (`Renderer\HideWhenEmpty`) and Pin to Bottom (`Renderer\PinToBottom`). Live values (`Renderer\LiveValues`) already send the cart's values to the browser as a JSON element that `update_order_review` swaps as a fragment.
- Options turned down:
  - Server only, re-rendering each conditional block as an `update_order_review` fragment: fragments are whole sections, arbitrary blocks would need a new fragment path, and step changes still need the browser.
  - Cart and shopper decided once at page load, steps by CSS `:has()`: applying a coupon or changing the address would not update visibility until a reload, exactly where those conditions matter.
  - Making fields inside a hidden block optional and leaving them out of the post: the server and the browser would have to agree on every hidden field at order time.
  - Only containers and buttons can take the setting: wrapping a block in a Group just to gate it adds markup.
  - "Any of" matching, or one condition per block: harder to explain, and one condition per block forces nesting.
  - Pro only: Steps and Pin to Bottom ship in Free, and the setting is small.

## Decision

- A block's visibility is the attribute `yaycheckoutVisibility` in the layout's block markup: an action, show or hide, and a list of conditions that must all match. Each condition type appears once. There is no option, meta or migration.
- Every block can take it except Checkout, Steps and Step, because step navigation can't skip a step.
- `Renderer\BlockVisibility` owns the rules. It decides each block when the layout renders, with step 1 open, so the page prints in the right state with no flash. It prints the cleaned setting on the block's first tag and hides a failing block with a class, not the `hidden` attribute, so a theme's `display` rule can't show it.
- `packages/blocks/src/shared/visibility-rules.ts` mirrors `BlockVisibility::normalize()` and `is_visible()`. The storefront script re-decides on every step change and after every `update_order_review`, from a state element the server refreshes as a fragment. The editor's preview uses the same copy, so the editor, the browser and the server agree. PHP is the owner: a change to the rules lands on both sides in the same commit.
- A malformed setting, or one with no conditions, leaves the block shown.
- Fields inside a hidden block keep WooCommerce's rules. Making them optional is the merchant's choice; the panel warns when a block holds fields.

## Consequences

- A merchant can gate any block by step, cart and login state without wrapping it, and the storefront follows step and cart changes without a reload.
- Layouts carry the setting in their markup, so it travels with a duplicated, exported or imported layout, and a copied block keeps it. Moving visibility out of markup later means migrating every stored layout.
- The rules exist twice. A change to a condition's meaning has to change PHP and TypeScript together, and only review catches a drift between them.
- A layout without the setting prints no state element and loads no script.
- A required field inside a hidden block still blocks the order. The merchant has to make it optional, or keep it out of the hidden block.
- The subtotal compares in the cart's currency, so a multi-currency plugin's converted cart compares against the amount as typed.
