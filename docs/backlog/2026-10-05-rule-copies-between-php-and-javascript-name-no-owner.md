# Rules copied between PHP and JavaScript don't name their owner

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`docs/conventions.md` asks every copy of a PHP rule to name its PHP owner. These copies don't:

- **Email is always required**, in five places: `RequiredFieldOverlay::is_required()` (`includes/Services/RequiredFieldOverlay.php`, for `billing_email` or an `email` type), `LayoutMarkupReader::fields()` (`includes/Services/LayoutMarkupReader.php`, Contact is `required => true`), `packages/blocks/src/contact/render.php` (`$yaycheckout_required = true`), `packages/blocks/src/field/edit.tsx` (`isEmail`) and `packages/blocks/src/contact/edit.tsx` ("Email is always required."). None names the others.
- **The "optional" fallback** has four defaults. `RequiredIndicator::span()` (`includes/Services/RequiredIndicator.php`) prints an untranslated `optional`. `assets/js/checkout-field-requirements.js` uses WooCommerce's `i18n_optional_text`, then `optional`. `packages/blocks/src/shared/field-preview.tsx` shows an untranslated `optional`. The inspector placeholder in `packages/blocks/src/shared/required-indicator-controls.tsx` is `__( 'optional' )`. The storefront script also repeats `span()`'s `*` fallback, its parentheses and its markup.
- **Publish rules:** `packages/blocks/src/checkout/publish-guard.ts` names `LayoutMarkupReader::publish_error()` as its owner, but two of its four messages differ: "Add a Checkout block to save this layout." against "…to this layout before publishing it.", and "…before this layout can be saved." against "…can be published.". `publish_error()` names no copy. `packages/blocks/src/extra-field/edit.tsx` repeats the Key rules a third time, in its own words, and names no owner. "A layout that cannot take an order still publishes" has the guard take its message from `publish_error()`, which settles the wording; the guard's draft lock is in "Closing the layout editor discards unsaved edits without asking".
- **Seed list:** `LayoutRepository::address_field_blocks()` (`includes/Repositories/LayoutRepository.php`) seeds 7 suffixes per address; `packages/blocks/src/shared/address-fields.ts` lists 10 per prefix, plus `billing_email`. The 7 shared keys agree on type, label and required. The TypeScript calls the PHP list "a PHP copy of this list", which makes TypeScript the owner, against "the owner is always PHP". The PHP docblock says the keys "mirror" the TypeScript file, though they are a subset.

The `datetime` mismatch is in "A Date / Time extra field renders as a plain text box". Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

When one copy changes, nothing points at the others. The "optional" text already differs between the editor and the storefront, and on a non-English store the PHP fallback stays English. An extra field's inline Key error and the notice that blocks the save word the same rule differently.

## Suggested fix

Pick an owner for each rule, in PHP, and name it in every copy. For email, `RequiredFieldOverlay::is_required()` owns the rule. For "optional", have `RequiredIndicator` own one translated fallback, and send it through `RequiredIndicator::script_payload()` and the editor data in `packages/blocks/src/shared/editor-data.ts`. List the JavaScript copies in `publish_error()`'s docblock, and have `packages/blocks/src/extra-field/edit.tsx` name its owner and word its errors like `publish_error()`. Make PHP own the address list, or record the pair in `docs/conventions.md` as the one TypeScript-owned list, and fix the "mirror" docblock. If the fix changes what `RequiredFieldOverlay::apply()` or `publish_error()` decide, not only their comments, an integration test there is warranted under CLAUDE.md's Tests rule.

## Done when

- [ ] Each of the four email copies outside `RequiredFieldOverlay::is_required()` names that method as its owner.
- [ ] `RequiredIndicator::span()`, `assets/js/checkout-field-requirements.js`, `packages/blocks/src/shared/field-preview.tsx` and the inspector placeholder show the same translated "optional" text, taken from one PHP owner.
- [ ] `assets/js/checkout-field-requirements.js` names `RequiredIndicator::span()` as the owner of the markup it repeats.
- [ ] `publish_error()`'s docblock names every JavaScript copy of its rules, including `packages/blocks/src/extra-field/edit.tsx`.
- [ ] `packages/blocks/src/extra-field/edit.tsx` names `publish_error()` as the owner of the Key rules it repeats.
- [ ] `packages/blocks/src/shared/address-fields.ts` and `LayoutRepository::address_field_blocks()` name the same owner for the address list.
- [ ] The TypeScript copies section of `docs/conventions.md` lists the address-list pair.
