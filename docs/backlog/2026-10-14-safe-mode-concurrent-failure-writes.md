# Two layouts failing at once can lose one failure

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** whole-feature review of the safe mode fallback, 2026-10-14, branch `alan/feat/safe-mode`, PR #114. Kept out of that PR as low risk.

## Today

- `CheckoutPage::record_failure()` reads `yaycheckout_render_failures`, adds the layout's entry and writes the whole option back, with no lock.

## Why it matters

Pro only: two active layouts failing in overlapping requests can each write back a copy without the other's entry. The lost one is recorded again on its next failure, so a blocking failure may be served once more.

## Suggested fix

Store each layout's failure in its own option (`yaycheckout_render_failure_<id>`) or in post meta on the layout, so writes don't overlap.

## Done when

- [ ] Recording one layout's failure never rewrites another layout's.
