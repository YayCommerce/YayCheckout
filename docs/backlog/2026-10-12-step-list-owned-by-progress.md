# The layout's step list is owned by the Steps Progress block, and Go to Step copies its step code

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** /yaytidy on the Go to Step block, 2026-10-12, branch `alan/feat/go-to-step-block`, PR #90. Kept out of that PR so it only adds the block.

## Today

- The rule "the layout's steps are the Step children of its first Steps block" lives in `Renderer\CheckoutStepsProgress::titles_in()`, memoised per request by `CheckoutStepsProgress::layout_titles()`. `layout_titles()` was made public so `packages/blocks/src/go-to-step/render.php` can count the steps.
- In the editor, `packages/blocks/src/steps-progress/edit.tsx` (`Edit`) and `packages/blocks/src/go-to-step/edit.tsx` (`Edit`) each find the first Steps block with `getBlocksByName()` and read its children. Their comments name different PHP owners (`titles_in()` and `layout_titles()`).
- On the storefront, `packages/blocks/src/steps-progress/view.ts` (`sync()`) and `packages/blocks/src/go-to-step/view.ts` (`onStepChange()`) each look a step up by its `data-step`. Which step can be reopened (`canReopenStep()`) and the `yaycheckout:step-reopen` event (`reopenStep()`) are already shared in `packages/blocks/src/shared/checkout-root.ts`, and `steps/view.ts` uses the same rule for a click on a step's header.
- `packages/blocks/src/go-to-step/style.css` says it matches `.yaycheckout-return-to-cart`'s link look, but `packages/blocks/src/return-to-cart/style.css` names only back-button as the copy to change together.

## Why it matters

If Steps changes how it numbers steps (`data-step`) or which Steps block counts, two editors and two view scripts must be found and changed together, and nothing type-checks or links them. The next block that needs the step list will reach into Steps Progress's renderer again. A change to the link look in Return to Cart won't point anyone at Go to Step.

## Suggested fix

- Move `titles_in()` to a pure `LayoutMarkupReader::step_titles( string $markup ): array` (raw titles, `''` when untitled), memoise it for the rendered layout in `CheckoutPage::step_titles(): array` (CheckoutPage already owns `layout()`), and have `CheckoutStepsProgress::markup()` and `go-to-step/render.php` read that. `CheckoutStepsProgress::layout_titles()` then goes away.
- Add `stepFor( checkout, step )` (the step element by its `data-step`) to `packages/blocks/src/shared/checkout-root.ts` and use it in both view scripts.
- Add `useLayoutStepIds()` to `packages/blocks/src/shared/step-title.ts`, commented as mirroring `LayoutMarkupReader::step_titles()`, and use it in both editors.
- Name `go-to-step/style.css` in `return-to-cart/style.css`'s "change both together" comment.

This was built and browser-tested on PR #90 before being taken out, so it is known to keep Steps Progress's output and behaviour unchanged.

## Done when

- [ ] `git grep -n "titles_in\|layout_titles" -- includes packages` finds nothing, and `LayoutMarkupReader::step_titles()` is the only PHP code that looks up the first Steps block's Step children.
- [ ] `packages/blocks/src/go-to-step/render.php` counts steps through `CheckoutPage::step_titles()`.
- [x] `git grep -n "yaycheckout:step-reopen" -- packages/blocks/src` finds only `steps/view.ts` and `shared/checkout-root.ts`.
- [ ] `git grep -n "getBlocksByName( STEPS\|getBlocksByName( stepsMeta" -- packages/blocks/src` finds only `shared/step-title.ts`.
- [ ] `packages/blocks/src/return-to-cart/style.css` names `go-to-step/style.css` among the copies to change together.
