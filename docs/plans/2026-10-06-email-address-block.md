# Email Address Block Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec: the requirements below are the brief.

**Status:** active
**Goal:** the email field has one home: the Contact block becomes the `yaycheckout/email` "Email address" block, which can be placed anywhere inside Checkout, and the Field block stops offering `billing_email`.
**Spec:** none, planned from the request
**Architecture:**
- The block is renamed outright, with no compatibility for saved `yaycheckout/contact` markup. The plugin has not been released (1.0.0, no release tag), so only dev and test layouts hold the old name.
- Its section slot becomes `email`.
- Address sections stop excluding it, so its `ancestor: checkout` rule is the only placement limit.
- The Field picker drops `billing_email`, but a saved `billing_email` Field still renders and keeps its key in the picker.

**Touches:**
- Blocks:
  - `packages/blocks/src/contact/` → `packages/blocks/src/email/` (`block.json`, `edit.tsx`, `index.ts`, `render.php`)
  - `packages/blocks/src/shared/{address-fields.ts,allowed-blocks.ts,block-icons.tsx}`
    - shared: `allowed-blocks.ts` is consumed by `shared/address-section-edit.tsx` (Billing and Shipping) and `step/edit.tsx`
  - `packages/blocks/src/field/edit.tsx`
  - `packages/blocks/src/checkout/block.json`
  - `packages/blocks/src/slot/{block.json,edit.tsx}`
- PHP and templates:
  - `includes/Blocks/BlockRegistry.php`
  - `includes/Services/LayoutMarkupReader.php`
  - `templates/default-layout.php`
- SPA: `apps/admin/src/components/shared/PreviewPane.tsx`
- Backlog items that cite `contact/` paths or the Contact block (listed in Task 2).

**Tests:** none. No logic CLAUDE.md's Tests rule names changes: `RequiredFieldOverlay::apply()` and `publish_error()` are untouched, and `LayoutMarkupReader::fields()` changes only the block name it matches and how the label is decoded. Each task gets a manual check.

## Requirements

- **Goal:** a merchant adds the customer email with one block, "Email address", placed anywhere in the checkout. The Field block no longer offers Email address.
- **WooCommerce check (done while planning, WooCommerce 11.1.2):** the email can stand alone.
  - `billing_email` is defined in the `billing` fieldset (`WC_Countries::get_address_fields()`, priority 110, required, `validate: email`).
  - `WC_Checkout::get_posted_data()` and `validate_posted_data()` read each field from `$_POST` by name and never look at where it sits in the form.
  - `maybe_skip_fieldset()` only ever skips `shipping` and `account`, so `billing_email` is always read, required and validated wherever it is rendered inside `form.checkout`.
  - Two side effects stay as they are today:
    - WooCommerce's error says "Billing Email address is a required field".
    - A layout with no email input at all fails every order, because `RequiredFieldOverlay::is_required()` keeps `billing_email` required.
- **Out of scope:**
  - Any compatibility for `yaycheckout/contact`: no read-time rewrite, no database migration, no import rewrite.
  - Converting or refusing existing `billing_email` Fields. The publish-time refusal stays open in the backlog item.
  - Moving the block inside Billing in `templates/default-layout.php`.
- **Acceptance criteria:**
  1. The block is registered as `yaycheckout/email` and titled "Email address". Its sidebar panel is "Email address", and its wrapper class is `yaycheckout-email`. No `yaycheckout/contact` is registered, and no `contact` directory is in `assets/dist/blocks/`.
  2. The block can be inserted anywhere under Checkout, including directly inside Billing and Shipping. It is still limited to one per layout and is always required.
  3. `LayoutMarkupReader::slots()` reports `email` for the block, and the Slot block's example reads `after_email`.
  4. A new Field never offers or auto-picks `billing_email`. A saved Field with `fieldKey` `billing_email` still renders, and its picker still shows that key selected.
  5. The SPA design preview's first section label reads "EMAIL ADDRESS".
  6. The block's label can be edited on the canvas and follows the List View label rules of Field. A label containing `&` prints as `&` on the storefront.
  7. End to end: with the Email address block placed in the right-hand column under Order Summary, a guest order with an email succeeds. Placing it with the email left empty fails with "Billing Email address is a required field."
- **Decided with the user (2026-10-06):**
  - Rename the block to `yaycheckout/email`, with no compatibility for saved `yaycheckout/contact` markup.
  - Rename the `contact` section slot to `email`.
  - The block can be placed anywhere in Checkout.
  - It stays one per layout and always required.
  - Saved `billing_email` Fields keep rendering. The only change is that the picker stops offering the key.
  - The SPA's static preview label changes too.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/<block>/` and run `pnpm build:blocks`. The wp-scripts build cleans its output directory, so the old `contact/` build disappears on the next build.
- Run commands on Node 22 (`nvm use`).
- Branch from `dev/edd` as `<name>/<type>/<topic>`, for example `<name>/feat/email-address-block`. Commit messages are Conventional Commits and end with the `Co-Authored-By` trailer CLAUDE.md names.
- The working tree on `dev/edd` holds uncommitted work: inline label editing, Field List View labels and `LayoutMarkupReader::plain_text()`. Task 4 consumes it. Land that work on its own branch first, or branch this plan from it. Never commit to `dev/edd`.

## Base branch state

- `pnpm build:blocks` passes. It is the only check on the blocks' TS: the blocks package has no working `tsc`.
- `composer lint -- <files>` passes on the PHP files this plan touches.
- `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` is the check for the SPA change. `pnpm --filter @yaycheckout/admin-page lint` already fails on `dev/edd` (`docs/known-issues.md`), so the bar for lint is no new failure in `PreviewPane.tsx`.

## Review focus

- **A dev or test layout saved with `yaycheckout/contact`.** By decision, it renders no email input after this change, and every order on it fails with "Billing Email address is a required field" until the merchant adds Email address again. The editor flags the old block as unsupported. Task 2, Step 6 confirms that this is the behaviour and that nothing fatals.
- **A saved layout whose Billing holds a `billing_email` Field.** The merchant expects it to keep working and its picker to keep showing its key, not to switch silently to the first free key. Task 1, Step 3 checks this.
- **A second copy of Email address**, now that address sections accept it. `"multiple": false` must still block it. Task 3, Step 3 checks this.
- **Email address in a section that is not Billing,** such as Shipping or the right-hand column. It must still post as `billing_email` inside `form.checkout`. The final verification covers this.

---

### Task 1: The Field picker stops offering `billing_email`

**Files:**
- Modify: `packages/blocks/src/shared/address-fields.ts` (`billingAddressFields()`)
- Modify: `packages/blocks/src/field/edit.tsx` (`keyOptions`)

**Interfaces:**
- Produces: `billingAddressFields(): AddressFieldDef[]` returns `addressFields( 'billing' )`, with no `billing_email` entry.

- [ ] **Step 1: Drop email from `billingAddressFields()`**

It returns `addressFields( 'billing' )`. Remove the `email` def and the splice after `billing_last_name`.

- [ ] **Step 2: Keep a saved key the catalog no longer offers in the picker**

In `field/edit.tsx`, when `fieldKey !== ''` and `catalog` has no entry with that key, put `{ label: \`${ label || fieldKey } (${ fieldKey })\`, value: fieldKey }` first in `keyOptions`. Leave `isEmail` as it is, so a saved `billing_email` Field keeps its locked required toggle.

- [ ] **Step 3: Verify in the editor**

Run `pnpm build:blocks` and expect `compiled successfully`.

On the default layout:

1. Insert a Field into Billing. It auto-picks a key that is not `billing_email`, and its "WooCommerce field" list has no "Email address (billing_email)".
2. In the Code editor, add `<!-- wp:yaycheckout/field {"fieldKey":"billing_email","type":"email","label":"Email address"} /-->` inside Billing, then return to the visual editor. The Field keeps `billing_email` selected, with Required on and locked.
3. Save and preview. The storefront has an input with `name="billing_email"`.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/shared/address-fields.ts`, `packages/blocks/src/field/edit.tsx`
Message: `feat(blocks): stop offering billing_email in the Field picker`

### Task 2: Rename the block to `yaycheckout/email`

**Files:**
- Move: `packages/blocks/src/contact/` → `packages/blocks/src/email/` (`git mv`)
- Modify:
  - `packages/blocks/src/email/{block.json,edit.tsx,render.php}`
  - `packages/blocks/src/shared/block-icons.tsx`
  - `packages/blocks/src/checkout/block.json`
  - `packages/blocks/src/slot/{block.json,edit.tsx}`
  - `includes/Blocks/BlockRegistry.php`
  - `includes/Services/LayoutMarkupReader.php`
  - `templates/default-layout.php`
  - `apps/admin/src/components/shared/PreviewPane.tsx`
  - backlog items:
    - `docs/backlog/2026-10-05-a-layout-that-cannot-take-an-order-still-publishes.md`
    - `docs/backlog/2026-10-05-account-fields-and-checkout-form-hooks-never-render.md`
    - `docs/backlog/2026-10-05-address-fields-never-refresh-totals-or-prefill.md`
    - `docs/backlog/2026-10-05-admin-and-editor-strings-cannot-be-translated.md`
    - `docs/backlog/2026-10-05-rule-copies-between-php-and-javascript-name-no-owner.md`

**Interfaces:**
- Produces:
  - block name `yaycheckout/email`
  - `BlockRegistry::ORDER` entry `'email'`, in place of `'contact'`
  - `LayoutMarkupReader` private const `EMAIL = 'yaycheckout/email'`, replacing `CONTACT`
  - `SECTION_SLOTS[ self::EMAIL ] => 'email'`
  - wrapper class `yaycheckout-email`

- [ ] **Step 1: Move the directory and rename the block**

Run `git mv packages/blocks/src/contact packages/blocks/src/email`. In `email/block.json`:
- `"name": "yaycheckout/email"`
- `"title": "Email address"`
- `"description": "The customer's email (billing_email). Always required; place it anywhere in the checkout."`
- `"keywords": [ "email", "contact", "customer" ]`
- Keep `"ancestor"` and `"multiple": false`.

- [ ] **Step 2: Rename inside the block**

- `email/edit.tsx`:
  - `useBlockProps` className becomes `yaycheckout-email`.
  - The `PanelBody` title becomes `__( 'Email address', 'yaycheckout' )`.
  - Rename `ContactAttributes` to `EmailAttributes`.
  - The doc comment becomes: "The Email address block: billing_email lives on this block, not on a Field. It is always required; that rule lives in RequiredFieldOverlay::is_required()."
- `email/render.php`:
  - The wrapper class becomes `yaycheckout-email form-row`.
  - In the `$content` docblock, change "contact is a leaf" to "the block is a leaf".

- [ ] **Step 3: Rename every reference outside the block**

- `shared/block-icons.tsx`: change the key `'yaycheckout/contact'` to `'yaycheckout/email'`.
- `checkout/block.json`: in `example.innerBlocks`, change `yaycheckout/contact` to `yaycheckout/email`.
- `slot/block.json`: the example `slotName` becomes `after_email`.
- `slot/edit.tsx`: the help text becomes `e.g. after_email.`
- `BlockRegistry::ORDER`: change `'contact'` to `'email'`, in the same position.
- `LayoutMarkupReader`:
  - `CONTACT` becomes `EMAIL = 'yaycheckout/email'`, with every use renamed.
  - `SECTION_SLOTS` maps it to `'email'`.
  - The `fields()` docblock says "the Email address block is billing_email".
- `templates/default-layout.php`: `<!-- wp:yaycheckout/email /-->`.
- `PreviewPane.tsx`: change `__('CONTACT', 'yaycheckout')` to `__('EMAIL ADDRESS', 'yaycheckout')`.

- [ ] **Step 4: Repoint the backlog items**

In the five backlog files listed above:
- Replace `packages/blocks/src/contact/` with `packages/blocks/src/email/`.
- Replace "Contact block" and "Contact" (meaning the block) with "Email address block".

Change no Done-when meaning and tick no box. In the "A `billing_email` Field beside Contact" bullet, also say:
- The picker no longer offers `billing_email` (Task 1).
- A layout saved before this change, or imported or hand-edited, can still hold such a Field beside the Email address block, with the same failure.

- [ ] **Step 5: Verify nothing still names the old block**

1. Run `git grep -n "yaycheckout/contact\|yaycheckout-contact\|after_contact\|contact/render\|contact/edit" -- ':!docs/plans' ':!docs/specs'`. Expect no output.
2. Run `pnpm build:blocks` and expect `compiled successfully`. Then run `ls assets/dist/blocks`: it lists `email` and not `contact`.
3. Run `composer lint -- includes/Blocks/BlockRegistry.php includes/Services/LayoutMarkupReader.php templates/default-layout.php packages/blocks/src/email/render.php`. Expect no errors.
4. Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. Expect no errors.

- [ ] **Step 6: Verify in the editor, the storefront and the SPA**

1. Create a layout from the default template. It shows "Email address" above Billing, with the envelope icon. The inserter finds the block by "email" and by "contact". Save and preview: the storefront has `div.yaycheckout-email` holding `input[name="billing_email"]`.
2. On a layout saved before this change, which still holds `yaycheckout/contact`:
   - The editor shows the block as unsupported, with no JS error.
   - The storefront renders without the email input, with no PHP warning in `debug.log`.
   - This is the accepted no-compatibility behaviour.
3. In the SPA, open the design screen. The preview's first section label reads "EMAIL ADDRESS".

- [ ] **Step 7: Commit**

Stage:
- `packages/blocks/src/email/` (the moved files)
- `packages/blocks/src/shared/block-icons.tsx`
- `packages/blocks/src/checkout/block.json`
- `packages/blocks/src/slot/block.json`
- `packages/blocks/src/slot/edit.tsx`
- `includes/Blocks/BlockRegistry.php`
- `includes/Services/LayoutMarkupReader.php`
- `templates/default-layout.php`
- `apps/admin/src/components/shared/PreviewPane.tsx`
- the five backlog files

Message: `feat(blocks)!: rename the Contact block to yaycheckout/email (Email address)`

### Task 3: Email address can go anywhere in Checkout

**Files:**
- Modify: `packages/blocks/src/shared/allowed-blocks.ts`

**Interfaces:**
- Consumes: block name `yaycheckout/email` (Task 2, Interfaces).
- Produces: `isCheckoutSection( type )` returns `false` for `yaycheckout/email`, as it already does for the step buttons. `address-section-edit.tsx` is unchanged, so Billing and Shipping both accept the block.

- [ ] **Step 1: Exempt the block in `allowed-blocks.ts`**

- Rename `STEP_BUTTONS` to `ALLOWED_IN_ADDRESS_SECTIONS`.
- Add `emailMeta.name`, importing `emailMeta` from `../email/block.json`.
- Rewrite its comment: "The step buttons find the open step from anywhere in the form, and Email address posts billing_email from anywhere in it, so both stay allowed inside an address section."

- [ ] **Step 2: Verify the build**

Run `pnpm build:blocks` and expect `compiled successfully`.

- [ ] **Step 3: Verify placement in the editor**

On the default layout:

1. Drag Email address into Billing, between Last name and Country. It drops there.
2. Drag it into Shipping. It drops there.
3. Drag it into the right-hand column, under Order Summary. It drops there.
4. Open the inserter at the top of Checkout. "Email address" is disabled or absent, because the layout is limited to one.
5. Save, reload, and open the browser console. There is no block-validation warning.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/shared/allowed-blocks.ts`
Message: `feat(blocks): allow the Email address block anywhere in Checkout`

### Task 4: Email address label edits like a Field label

**Files:**
- Modify: `packages/blocks/src/email/edit.tsx`
- Modify: `packages/blocks/src/email/index.ts`
- Modify: `packages/blocks/src/email/render.php`
- Modify: `includes/Services/LayoutMarkupReader.php` (the `EMAIL` branch of `fields()`)

**Interfaces:**
- Consumes the uncommitted work named in Global constraints:
  - `FieldPreview` props `onLabelChange?: ( value: string ) => void` and `labelPlaceholder?: string` (`shared/field-preview.tsx`)
  - `LayoutMarkupReader::plain_text( array $attrs, string $name, string $fallback = '' ): string`
  - the `__experimentalLabel` callback in `field/index.ts`

- [ ] **Step 1: Make the canvas label editable**

In `email/edit.tsx`:
- Pass `label={ label }`, `onLabelChange` and `labelPlaceholder={ __( 'Email address', 'yaycheckout' ) }` to `FieldPreview`. Drop `canvasLabel`.
- The sidebar Label `TextControl` shows `decodeEntities( label )`.

- [ ] **Step 2: Set the List View label**

In `email/index.ts`, register with an `__experimentalLabel` that returns `metadata?.name || decodeEntities( label ) || undefined` in the `list-view` context. Copy `field/index.ts`, without `fieldKey`.

- [ ] **Step 3: Decode the label in PHP**

- `email/render.php`: `$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'label', __( 'Email address', 'yaycheckout' ) );`
- `LayoutMarkupReader::fields()`, `EMAIL` branch: `'label' => self::plain_text( $attrs, 'label' )`.

- [ ] **Step 4: Verify**

1. Run `pnpm build:blocks` and expect `compiled successfully`.
2. Run `composer lint -- includes/Services/LayoutMarkupReader.php packages/blocks/src/email/render.php` and expect no errors.
3. In the editor, click the block's label on the canvas and type `Email & receipts`. The sidebar Label and List View both show `Email & receipts`.
4. Clear the label. The canvas shows a grey "Email address" and List View shows "Email address".
5. Set the label to `Email & receipts` again, save, and preview. The storefront label reads `Email & receipts`, not `&amp;`.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/email/edit.tsx`, `packages/blocks/src/email/index.ts`, `packages/blocks/src/email/render.php`, `includes/Services/LayoutMarkupReader.php`
Message: `feat(blocks): edit the Email address label on the canvas`

### Final verification: an order goes through with the email away from Billing

Use the layout from Task 3, Step 3, with Email address in the right-hand column under Order Summary, and make it the active layout.

1. As a guest, add a product, open the checkout, fill every required field including the email, and place the order. The order is created, and its billing email in wp-admin is the one entered.
2. Repeat with the email left empty. The checkout shows "Billing Email address is a required field." and no order is created.
3. Repeat with `not-an-email`. The checkout shows "Billing Email address is not a valid email address." and no order is created.
4. Move the block into Shipping, save, and repeat step 1. The order is created with that billing email.
