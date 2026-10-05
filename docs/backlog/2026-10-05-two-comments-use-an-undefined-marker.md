# Two comments use a `ponytail:` marker that nothing defines

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 code audit for the harness renewal (`docs/plans/2026-10-05-harness-renewal.md`), on `trace/docs/harness-renewal`.

## Today

`CheckoutPage::shopper_context()` (`includes/Renderer/CheckoutPage.php`) and `DesignStyles` (`includes/Renderer/DesignStyles.php`, the font-family token) each carry a `// ponytail:` comment describing a known gap: tablets count as mobile, and a serif font falls back to sans until fonts are loaded. No doc, tool or convention defines the marker.

## Why it matters

A reader can't tell whether `ponytail:` means planned work, an accepted limit or a tool's annotation, and a search for `TODO` misses both gaps.

## Suggested fix

Rewrite each as `TODO(<topic>): what, and why not now`, as `docs/conventions.md` asks, for example `TODO(device-detection)` and `TODO(fonts)`. If either gap is accepted for good, state it as a plain why-comment instead.

## Done when

- [ ] `git grep -n 'ponytail'` prints nothing.
- [ ] Each of the two gaps is still stated where it applies, as a `TODO(<topic>)` or a plain comment.
