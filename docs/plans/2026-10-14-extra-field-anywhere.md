# Extra Field: anywhere in the checkout, explicit single/multiple checkbox

**Status:** active
**Branch:** `trace/feat/extra-field-anywhere` from `dev/edd`
**Spec:** none, planned from the request and the answers below.

## Context

Merchants can't drop an Extra Field straight into Billing or Shipping. The only thing stopping them is an editor rule in `packages/blocks/src/shared/allowed-blocks.ts:18-32`. Extra Field's `ancestor` names Checkout, so `isCheckoutSection()` counts it as a section and `useBlocksAllowedExcept()` drops it from the address sections. It can already get in through a core Group.

Saving doesn't depend on where the field sits:
- `LayoutMarkupReader::extra_fields()` flattens the whole tree.
- Values post under the field's Key.

The real risk is a field inside a section the order doesn't use. For example, Shipping when "Ship to a different address" is unticked, or Billing when "Use same address for billing" is ticked. Today a required field there still fails validation and blocks the order. That is already reachable through a Group.

The checkbox is "single" only because it has no options, and nothing in the UI says so. The storefront already puts the label beside a single box, through `woocommerce_form_field`. The editor preview draws the label above a bare box (`shared/field-preview.tsx:127`). The multi-option group has its own problems:
- Its `<label for>` points at no element.
- Its options have no wrapper, so they flow inline instead of stacking.

**Decisions (from the user):**
1. A field in an unused address section is skipped: not validated, not saved.
2. An explicit **Single checkbox / Multiple choices** switch for the Checkbox type.
3. The field label allows links (and bold/italic) for checkbox fields, in both modes.

**Tests:** none. Each task has a manual check.

## Task 1: Allow Extra Field in Billing and Shipping (editor)

- `packages/blocks/src/shared/allowed-blocks.ts`:
  - Import `../extra-field/block.json` and add its name to `ALLOWED_IN_ADDRESS_SECTIONS`.
  - Update the comment: Extra Field posts under its Key from anywhere.
- Check: `pnpm build:blocks`. In the editor, the inserter inside Billing and inside Shipping offers Extra Field as a direct child.

## Task 2: Skip extra fields in an unused address section (PHP)

**`includes/Services/LayoutMarkupReader.php`**
- `extra_fields()` gains `'section' => 'billing'|'shipping'|''`, the nearest Billing or Shipping ancestor.
- Do this with a small private recursive walk over `parse_blocks()` that carries the current section. Use the `BILLING`/`SHIPPING` constants. Keep `flatten()` unchanged for its other callers.

**`includes/Renderer/CheckoutDifferentAddress.php`**
- Add `public static function billing_copied(): bool`, built from the body of the private `copies()`.
- `copies()` then calls it. One rule, two callers.

**`includes/Renderer/CheckoutExtraFields.php`**
- Add a private `submitted_fields( array $data )`: `fields()` minus the rows whose section is unused.
  - **Shipping is unused** when `! CheckoutDifferentAddress::needs_shipping_address() || empty( $data['ship_to_different_address'] )`. WooCommerce has already computed `ship_to_different_address` in `$data`. When shipping is the main address, the Shipping block posts the box checked, so it stays used.
  - **Billing is unused** when `CheckoutDifferentAddress::billing_copied()`.
- `validate( $data, $errors )` and `persist( $order, $data )` use `submitted_fields( $data )`. Confirm both hooks receive WooCommerce's posted `$data`; `woocommerce_checkout_create_order` passes it as the 2nd argument.
- `persist()` still removes the previous attempt's meta for **every** key in `fields()`. That way a value from an earlier attempt can't survive on a field that is now skipped.

**Docs**
- `docs/architecture.md` line ~70, "Extra fields and fragments": add one sentence. Fields inside an address section the order doesn't use are neither validated nor stored.

**Check**
1. Put a required text Extra Field inside Shipping (billing is main).
2. Untick "Ship to a different address" and place the order. It succeeds and the order has no meta for that Key.
3. Tick it and leave the field empty. You get "… is a required field."
4. Switch WooCommerce > Shipping destination to shipping-main. Put a required field in Billing and tick "Use same address for billing". The order succeeds.
5. Run `composer lint -- <changed files>`.

## Task 3: Explicit single / multiple checkbox

**Attribute**
- In `packages/blocks/src/extra-field/block.json`, add `"checkboxMode": { "type": "string", "enum": [ "single", "multiple" ] }`.
- It has **no default**: a missing value means "derive from options". Existing fields keep working: options present → multiple, else single.

**One rule, two copies** (a JS copy of PHP, per `docs/conventions.md`; each names the other)
- PHP: `LayoutMarkupReader::checkbox_multiple( array $attrs ): bool`. The attribute if valid, else `! empty( self::options( … ) )`.
- JS: `checkboxMode( attributes )` in `packages/blocks/src/extra-field/` (or `shared/` if the preview needs it).

**PHP users of the rule**
- `extra_fields()` adds `'multiple' => self::checkbox_multiple( $attrs )`.
  - Check whether the payload `CheckoutRequiredFields.php:167` sends to JS has a strict TS/zod copy. If it does, add the key there too.
- `ExtraFieldSubmission.php:61-62`: branch on `$field['multiple']` instead of `empty( $options )`.
- `extra-field/render.php:40`: `$yaycheckout_checkbox_choices` uses `checkbox_multiple()`.

**Editor (`extra-field/edit.tsx`)**
- When the type is Checkbox, show a `ToggleGroupControl` with "Single checkbox" and "Multiple choices".
- Show the Options panel only for Multiple. The options are kept when switching back, but ignored.
- Single checkbox help text: "One yes/no box with the label beside it."

**Preview (`shared/field-preview.tsx`)**
- Multiple checkbox with no usable options gets the existing `emptyOptionsHint`. Today the hint covers only select and radio.
- Single checkbox renders like the Create Account preview (`create-account/edit.tsx:46-52`, `editor.css:5-10`):
  - A `div` with `display:inline-flex; gap:8px`, not a `label`, so Space doesn't toggle the box while typing.
  - Inside it: the box, then the label RichText, then the required/optional mark.
- The Field block's checkbox shares this branch, so its preview now matches the storefront too.

**Storefront (`extra-field/render.php`)**
- Multiple:
  - Replace the dangling `<label for>` with `<span class="yaycheckout-extra-field__label" id="{key}-label">`.
  - Wrap the options in `<div class="yaycheckout-extra-field__choices" role="group" aria-labelledby="{key}-label">`.
- Single fallback (no WooCommerce, lines 99-100): print `<label class="checkbox"><input …/> label mark</label>`, matching WooCommerce.
- `assets/css/checkout-base.css`:
  - Add `.yaycheckout-extra-field__label` to the label rule at lines 161-167.
  - Add `.yaycheckout-extra-field__choices { display:flex; flex-direction:column; gap:8px }`.

**Check**
- An existing checkbox with options still renders as a group, and an existing one without options still renders single. Use a saved layout and don't touch its blocks.
- Toggle the mode on a **new** layout. Per memory, don't test edits on an existing draft, because autosave writes them.
- Group options stack one per line.
- A required multiple field with nothing ticked gives the required error. A ticked single box saves "Yes".

## Task 4: Links in the checkbox label

**Allowed tags (`LayoutMarkupReader.php`)**
- Add `LABEL_TAGS`: `strong`, `em`, and `a` with `href`, `target`, `rel`.
- Give `rich_text()` an optional `array $tags = self::RICH_TEXT_TAGS` parameter. The shared `RICH_TEXT_TAGS`, used by headings and the like, stays unchanged.

**Editor**
- `field-preview.tsx` takes `labelFormats?: string[]`. When it is set, it passes `allowedFormats={ labelFormats }` and drops `withoutInteractiveFormatting`.
- `extra-field/edit.tsx` passes `[ 'core/bold', 'core/italic', 'core/link' ]` only when the type is Checkbox. Other types stay plain.
- Added during implementation: a Checkbox hides the sidebar Label (and Placeholder) box through `FieldTextControls`' new `showLabel`, because a plain text box would show the label's HTML. The label is edited on the canvas, and the switch's help says so.

**Render**
- For Checkbox, compute `$yaycheckout_label_html = LayoutMarkupReader::rich_text( $attributes, 'label', __( 'Extra field', 'yaycheckout' ), LayoutMarkupReader::LABEL_TAGS )`.
  - Single goes through `woocommerce_form_field`'s `label`. WooCommerce runs `wp_kses_post` on it (`wc-template-functions.php:3534`).
  - Multiple prints it in the group label span.
- `Disclosure::open_if()`, validation errors, the order snapshot and Field Value keep the plain label from `plain_text()`, which already strips tags.

**Check**
- In a Single checkbox, type "I agree to the terms" and link "terms", with "open in new tab" on.
- On the storefront, the link opens in a new tab and clicking the text beside it still toggles the box.
- The required error and the order screen show plain text.
- A `<script>` typed via the code editor is stripped.

## Verification (end to end)

1. Run `nvm use`, `pnpm build:blocks`, `composer lint`, and `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` if any TS outside blocks changed.
2. On the Local site (checkout `?page_id=9`), build a **new** layout with these fields:
   - A required single checkbox with a link, in Billing.
   - A multiple checkbox, in Shipping.
   - A text field inside a Group in Shipping.
3. Walk the checks in Tasks 2–4.
4. Confirm order meta with `wp post meta list <order>` or the HPOS order screen, and confirm the order snapshot output.
5. Run `git status` before committing: `./scripts/run.sh dev` can rewrite `composer.json` and `scoper.inc.php`.
