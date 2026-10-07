# Rules copied between PHP and JavaScript don't name their owner

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`docs/conventions.md` asks every copy of a PHP rule to name its PHP owner. The copies now name their owners: the four email copies name `RequiredFieldOverlay::is_required()`, `publish_error()` names its JavaScript copies and `packages/blocks/src/extra-field/edit.tsx` names `publish_error()`, and `packages/blocks/src/shared/address-fields.ts` owns the address list that `LayoutRepository::address_field_blocks()` seeds a subset of. Two gaps remain:

- **The "optional" fallback** has four defaults. `RequiredIndicator::span()` (`includes/Services/RequiredIndicator.php`) prints an untranslated `optional`. `assets/js/checkout-field-requirements.js` uses WooCommerce's `i18n_optional_text`, then `optional`. `packages/blocks/src/shared/field-preview.tsx` shows an untranslated `optional`. The inspector placeholder in `packages/blocks/src/shared/required-indicator-controls.tsx` is `__( 'optional' )`.
- **Publish messages:** two of `packages/blocks/src/checkout/publish-guard.ts`'s four messages differ from `publish_error()`: "Add a Checkout block to save this layout." against "…to this layout before publishing it.", and "…before this layout can be saved." against "…can be published.". `packages/blocks/src/extra-field/edit.tsx` words the Key errors its own way. "A layout that cannot take an order still publishes" has the guard take its message from `publish_error()`, which settles the wording; the guard's draft lock is in "Closing the layout editor discards unsaved edits without asking".

The `datetime` mismatch is in "A Date / Time extra field renders as a plain text box". Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

When one copy changes, nothing points at the others. The "optional" text already differs between the editor and the storefront, and on a non-English store the PHP fallback stays English. An extra field's inline Key error and the notice that blocks the save word the same rule differently.

## Suggested fix

Pick an owner for each rule, in PHP, and name it in every copy. For email, `RequiredFieldOverlay::is_required()` owns the rule. For "optional", have `RequiredIndicator` own one translated fallback, and send it through `RequiredIndicator::script_payload()` and the editor data in `packages/blocks/src/shared/editor-data.ts`. List the JavaScript copies in `publish_error()`'s docblock, and have `packages/blocks/src/extra-field/edit.tsx` name its owner and word its errors like `publish_error()`. Make PHP own the address list, or record the pair in `docs/conventions.md` as the one TypeScript-owned list, and fix the "mirror" docblock. If the fix changes what `RequiredFieldOverlay::apply()` or `publish_error()` decide, not only their comments, an integration test there is warranted under CLAUDE.md's Tests rule.

## Done when

- [x] Each of the four email copies outside `RequiredFieldOverlay::is_required()` names that method as its owner. — `LayoutMarkupReader::fields()`, `packages/blocks/src/email/render.php`, `packages/blocks/src/field/edit.tsx` (above `isEmail`), `packages/blocks/src/email/edit.tsx`
- [x] `RequiredIndicator::span()`, `assets/js/checkout-field-requirements.js`, `packages/blocks/src/shared/field-preview.tsx` and the inspector placeholder show the same translated "optional" text, taken from one PHP owner. — `RequiredIndicator::optional_text()`, via `script_payload()` and `editorData.optionalText`
- [x] `assets/js/checkout-field-requirements.js` names `RequiredIndicator::span()` as the owner of the markup it repeats. — `assets/js/checkout-field-requirements.js` header docblock
- [x] `publish_error()`'s docblock names every JavaScript copy of its rules, including `packages/blocks/src/extra-field/edit.tsx`. — `LayoutMarkupReader::publish_error()` docblock names `publish-guard.ts`, `extra-field-keys.ts`, `extra-field/edit.tsx`
- [x] `packages/blocks/src/extra-field/edit.tsx` names `publish_error()` as the owner of the Key rules it repeats. — `packages/blocks/src/extra-field/edit.tsx`, comment above `keyError`
- [x] `packages/blocks/src/shared/address-fields.ts` and `LayoutRepository::address_field_blocks()` name the same owner for the address list. — `packages/blocks/src/shared/address-fields.ts` header and `LayoutRepository::address_field_blocks()` docblock
- [x] The TypeScript copies section of `docs/conventions.md` lists the address-list pair. — `docs/conventions.md`, TypeScript copies of PHP name their owner, In code
