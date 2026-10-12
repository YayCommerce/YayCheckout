# The layout's step list is owned by the Progress block, and Go to Step copies Progress's step code

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** /yaytidy on the Go to Step block (`docs/plans/2026-10-12-go-to-step-block.md`), 2026-10-12, branch `alan/feat/go-to-step-block`, PR #90. Kept out of that PR so it only adds the block.

## Today

- The rule "the layout's steps are the Step children of its first Steps block" lives in `Renderer\CheckoutProgress::titles_in()`, memoised per request by `CheckoutProgress::layout_titles()`. `layout_titles()` was made public so `packages/blocks/src/go-to-step/render.php` can count the steps.
- In the editor, `packages/blocks/src/progress/edit.tsx` (`Edit`) and `packages/blocks/src/go-to-step/edit.tsx` (`Edit`) each find the first Steps block with `getBlocksByName()` and read its children. Their comments name different PHP owners (`titles_in()` and `layout_titles()`).
- On the storefront, `packages/blocks/src/progress/view.ts` (`sync()`, `onClick()`) and `packages/blocks/src/go-to-step/view.ts` (`onStepChange()`, `onClick()`) each read a step's `data-state` by its `data-step` and each dispatch `yaycheckout:step-reopen`, the event `packages/blocks/src/steps/view.ts` owns.
- `packages/blocks/src/go-to-step/style.css` says it matches `.yaycheckout-return-to-cart`'s link look, but `packages/blocks/src/return-to-cart/style.css` names only back-button as the copy to change together.

## Why it matters

If Steps changes how it marks steps (`data-step`, `data-state`, the event's `detail`) or which Steps block counts, two editors and two view scripts must be found and changed together, and nothing type-checks or links them. The next block that needs the step list will reach into Progress's renderer again. A change to the link look in Return to Cart won't point anyone at Go to Step.

## Suggested fix

- Move `titles_in()` to a pure `LayoutMarkupReader::step_titles( string $markup ): array` (raw titles, `''` when untitled), memoise it for the rendered layout in `CheckoutPage::step_titles(): array` (CheckoutPage already owns `layout()`), and have `CheckoutProgress::markup()` and `go-to-step/render.php` read that. `CheckoutProgress::layout_titles()` then goes away.
- Add `stepState( checkout, step )` and `reopenStep( checkout, step )` to `packages/blocks/src/shared/checkout-root.ts` and use them in both view scripts.
- Add `useLayoutStepIds()` to `packages/blocks/src/shared/step-title.ts`, commented as mirroring `LayoutMarkupReader::step_titles()`, and use it in both editors.
- Name `go-to-step/style.css` in `return-to-cart/style.css`'s "change both together" comment.

This was built and browser-tested on PR #90 before being taken out, so it is known to keep Progress's output and behaviour unchanged.

## Done when

- [ ] `git grep -n "titles_in\|layout_titles" -- includes packages` finds nothing, and `LayoutMarkupReader::step_titles()` is the only PHP code that looks up the first Steps block's Step children.
- [ ] `packages/blocks/src/go-to-step/render.php` counts steps through `CheckoutPage::step_titles()`.
- [ ] `git grep -n "yaycheckout:step-reopen" -- packages/blocks/src` finds only `steps/view.ts` and `shared/checkout-root.ts`.
- [ ] `git grep -n "getBlocksByName( STEPS\|getBlocksByName( stepsMeta" -- packages/blocks/src` finds only `shared/step-title.ts`.
- [ ] `packages/blocks/src/return-to-cart/style.css` names `go-to-step/style.css` among the copies to change together.
