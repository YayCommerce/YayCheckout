# A required field hidden by a block visibility condition still blocks the order

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** scout of hidden and required field handling during the field-usage refactor, 2026-10-14, PR #155 (`trace/refactor/field-usage`).

## Today

- `packages/blocks/src/visibility/view.ts` `apply()` hides a failing block only by toggling `BlockVisibility::HIDDEN_CLASS`. The inputs inside stay enabled and keep their values, and nothing is posted to say they were hidden.
- On the server, `Renderer\CheckoutFieldUsage` knows only three reasons a field is out of use: `locale_hidden_keys()`, `copied_billing_keys()` and `unused_sections()`. Visibility isn't one of them. So:
  - `Services\RequiredFieldOverlay::apply()` (through `CheckoutRequiredFields::filter_fields()`) keeps such an address Field or Order note required;
  - `Services\ExtraFieldSubmission::collect()` (through `CheckoutExtraFields::validate()`) reports such an Extra field as required;
  - `CheckoutExtraFields::persist()` saves whatever value was typed before the block hid.
- `packages/blocks/src/pro/steps/view.ts` `visibleControls()` skips controls that `isShown()` says are hidden. The shopper gets past every step, and the error appears only at Place order.
- The editor warning in `packages/blocks/src/visibility/index.tsx` `VisibilityPanel` ("This block contains required fields…") has two gaps:
  - It shows only while a "Block is empty" condition is picked (`picked.length > 0`). Step, subtotal, coupon, needs shipping and logged-in conditions get no warning.
  - `useHasRequiredField()` counts only blocks with `attrs.required === true`. It skips the Email block and email-type Fields, which `RequiredFieldOverlay::is_required()` always requires.
- This is accepted behaviour today. `docs/decisions/2026-10-13-block-visibility-in-block-markup.md` says that "a required field inside a hidden block still blocks the order", and the accepted block visibility conditions design rejected the alternative.

## Why it matters

A merchant who hides a block that holds a required field by a condition (on a step, below a subtotal, when a coupon is applied) leaves the shopper stuck. At Place order the shopper gets "… is a required field" for a field they can't see or fill in, and can't place the order. A value typed before the block hid is also saved on the order as if the shopper had chosen it.

## Suggested fix

Add visibility as the next reason in `Renderer\CheckoutFieldUsage`:
1. When the order is placed, the visibility script posts the keys of the fields inside blocks it has hidden.
2. The server accepts only keys that sit inside a block whose markup carries `yaycheckoutVisibility`, so a tampered post can't skip any other required field.
3. Those keys become optional in `RequiredFieldOverlay::apply()`, are left out of `CheckoutExtraFields::submitted_fields()`, and aren't saved.

The server can't decide this alone: step and "Block is empty" conditions are known only in the browser. This reverses the 2026-10-13 decision, so it needs a decision record that supersedes it before any code. It was left out of PR #155, which was kept free of behaviour changes.

## Done when

- [ ] A decision record in `docs/decisions/` with status `accepted` supersedes `2026-10-13-block-visibility-in-block-markup.md` on hidden required fields, and that record's status says it is superseded.
- [ ] `Renderer\CheckoutFieldUsage` has a method that returns the field keys hidden by a visibility condition for the request, limited to keys inside a block carrying `yaycheckoutVisibility` in the served layout.
- [ ] On the storefront, a layout with a required Field and a required Extra field inside a Group hidden by a subtotal condition places an order with both left empty, and neither value is stored on the order (`CheckoutExtraFields::META_SNAPSHOT` has no row for the Extra field).
- [ ] A post that names a required field outside any conditional block as hidden still gets "… is a required field".
