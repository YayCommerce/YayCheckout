# A failed render leaves the layout's scripts and styles on WooCommerce's fallback page

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** whole-feature review of the safe mode fallback, 2026-10-14, branch `alan/feat/safe-mode`, PR #114. Kept out of that PR as low risk.

## Today

- `CheckoutPage::page_template()` renders the layout with `do_blocks()` before choosing the template. The blocks enqueue their styles, view scripts and script modules while rendering.
- When the render throws and safe mode falls back, `stand_down()` removes `CheckoutPage::enqueue_scripts`, but what the blocks enqueued stays, and WooCommerce's checkout page prints it.

## Why it matters

A layout's view script can run on a form it was not written for. Usually harmless, as the scripts look for their own markup, but not checked.

## Suggested fix

Record the script, script module and style handles queued before rendering, and in `stand_down()` dequeue the ones added since.

## Done when

- [ ] After a page render that throws with safe mode on, WooCommerce's checkout page loads no `yaycheckout` block script or style.
