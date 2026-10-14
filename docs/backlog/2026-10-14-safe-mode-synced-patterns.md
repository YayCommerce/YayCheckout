# Required blocks inside a synced pattern read as missing

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** whole-feature review of the safe mode fallback, 2026-10-14, branch `alan/feat/safe-mode`, PR #114. Kept out of that PR as low risk.

## Today

- `LayoutMarkupReader::publish_error()` finds the Checkout, Email, Place order and Payment blocks with `has_block()` on the layout's markup.
- The layout editor allows `core/block`. Blocks turned into a synced pattern are stored as `<!-- wp:block {"ref":…} /-->`, so `has_block()` doesn't see them, while the editor's publish guard, which counts blocks in the editor store, does.

## Why it matters

A layout that works is refused activation, and with safe mode on it is never served, with a "missing block" reason the merchant can't see a cause for.

## Suggested fix

Either keep YayCheckout's blocks out of synced patterns in the editor, or expand `core/block` references (`get_post( ref )->post_content`) before the publish checks, in both PHP and `publish-guard.ts`.

## Done when

- [ ] A layout whose Place order block sits in a synced pattern gets the same answer from the editor and from `publish_error()`.
