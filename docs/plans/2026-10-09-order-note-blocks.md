
# Order Note Blocks Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract.

**Status:** done 2026-10-09
**Goal:** a merchant can put WooCommerce's order note on a layout, either as a single configurable Order note block or as an Additional fields block that prints WooCommerce's whole `order` fieldset, and the shopper's note is saved as the order's customer note.
**Spec:** none, planned from the request
**Architecture:**
- WooCommerce already reads `order_comments` from the posted form (`WC_Checkout::get_posted_data()`, `order` fieldset) and saves it with `$order->set_customer_note()`. Today no YayCheckout block prints it, because `woocommerce_checkout_shipping` never fires (`docs/architecture.md`, Storefront, Fields). Both blocks only print markup, and neither saves anything itself.
- **Order note** works like a Field block for one fixed key, `order_comments`. It takes the label, placeholder, help text, Required with its mark or optional text, and the full Show field (`Disclosure`) choice. The layout's Required wins over WooCommerce through a new pure `RequiredFieldOverlay::apply_order_note()`, which `CheckoutRequiredFields` runs after the address overlay. `RequiredFieldOverlay::apply()` is not changed.
- **Additional fields** prints what WooCommerce's `checkout/form-shipping.php` prints under `div.woocommerce-additional-fields`, without the heading. WooCommerce and plugins decide its fields and their required flags. When the served layout also has an Order note block, it leaves out `order_comments`, so the form never posts that name twice.

**Touches:**
- Create: `packages/blocks/src/order-note/{block.json,index.ts,edit.tsx,view.ts,render.php}`, `packages/blocks/src/additional-fields/{block.json,index.ts,edit.tsx,render.php}`
- Modify:
  - `packages/blocks/src/shared/block-icons.tsx` (shared: every block's icon, and the SPA through `@blocks-shared`)
  - `includes/Blocks/BlockRegistry.php` (`ORDER`)
  - `includes/Services/LayoutMarkupReader.php` (shared: read by `CheckoutRequiredFields`, `CheckoutExtraFields`, `CheckoutFragments`, `field-value/render.php` and the REST layer; this plan only adds a method)
  - `includes/Services/RequiredFieldOverlay.php` (shared: `CheckoutRequiredFields`, `RequiredIndicator`; this plan only adds a method)
  - `includes/Renderer/CheckoutRequiredFields.php`
  - `docs/architecture.md`

**Tests:** none. The user declined `RequiredFieldOverlayOrderNoteTest` (2026-10-09), so each task ends in an exact manual check instead.

## Requirements

- **Goal:** a shopper on a layout with either block can leave an order note. It appears as the "Customer provided note" on the order in wp-admin, on the order-received page and in the order emails, exactly as it does on classic checkout.
- **Out of scope:**
  - Adding either block to `templates/default-layout.php`.
  - Allowing either block inside Billing or Shipping (`shared/allowed-blocks.ts`). They go only under Checkout.
  - WooCommerce's "Additional information" `<h3>`. The merchant adds a heading block if they want one.
  - A special checkbox text such as "Add a note to your order". The fallback comes from `Disclosure::text()`, and the merchant can type their own.
  - Clearing the note when the shopper unticks "Behind a checkbox". `disclosure-view.ts` hides the panel and keeps the value for every block that uses it, so an unticked note still posts. Changing that changes Field, Extra field and Coupon form too.
  - Showing the note in Field Value, and the block checkout (`woocommerce/checkout`).
  - Private notes that store staff add to an order in wp-admin. This plan covers only the shopper's note.
- **Acceptance criteria:**
  1. Block `yaycheckout/order-note`, titled "Order note": one per layout, placed only under Checkout.
  2. Order note prints, through `woocommerce_form_field()`, a `textarea` with `name="order_comments"` and `id="order_comments"` in the row `#order_comments_field.notes`. The label is the block's `label`, or WooCommerce's translated "Order notes" when it is empty. The placeholder is the block's `placeholder`, or WooCommerce's "Notes about your order, e.g. special notes for delivery." The help text prints under the field. `&` prints as `&`.
  3. Show field offers Always, Behind a link, In a collapsible section and Behind a checkbox, through `DisclosureControls`. With an empty label the fallback texts are "+ Add order notes", "Order notes" and "Add order notes". A required note is always shown, and a note that already holds a value prints open.
  4. Required follows the layout in both directions. Unticked, an empty note places the order even when a plugin's `woocommerce_checkout_fields` callback made `order_comments` required. Ticked, an empty note stops checkout with WooCommerce's "**<label>** is a required field." and creates no order. The label shows the block's required mark or optional text, as a Field does.
  5. Block `yaycheckout/additional-fields`, titled "Additional fields": one per layout, placed only under Checkout, with no settings. In this order it prints:
     - a wrapper with classes `yaycheckout-additional-fields woocommerce-additional-fields`;
     - `do_action( 'woocommerce_before_order_notes', WC()->checkout() )`;
     - only while `apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) )` is true, `div.woocommerce-additional-fields__field-wrapper` holding every `get_checkout_fields( 'order' )` field through `woocommerce_form_field( $key, $field, $checkout->get_value( $key ) )`;
     - `do_action( 'woocommerce_after_order_notes', WC()->checkout() )`.
     Required flags, labels and placeholders are WooCommerce's or the plugins'.
  6. When the served layout has an Order note block, Additional fields leaves out `order_comments`, so the page has exactly one `[name="order_comments"]`. The Additional fields editor preview then shows a notice that says so.
  7. End to end: on a layout with Order note set to Behind a checkbox, a guest ticks the checkbox, types `Leave at the back door & ring`, and places the order. The note reads exactly `Leave at the back door & ring` in wp-admin (Order > "Customer provided note"), on the order-received page ("Note:") and in the new-order admin email.
- **Decided with the user (2026-10-09):**
  - Two blocks, which merchants use separately: a single note textarea that fires no hooks, and one that prints every field of `get_checkout_fields( 'order' )`.
  - Order note gets the whole Show field setting.
  - Order note has Required with its mark and optional text, like the Field block. In Additional fields, WooCommerce decides.
  - No automated test; manual checks instead.
- **Approved with the plan (2026-10-09):**
  - Additional fields fires `woocommerce_before_order_notes` and `woocommerce_after_order_notes`, because "no hooks" was asked only for the single block.
  - Order note ignores `woocommerce_enable_order_notes_field`, because placing the block is the merchant's explicit choice. Additional fields honours that filter, as WooCommerce's template does.
  - Neither block goes into the default layout.
  - Work continues on `trace/feat/disclosure-checkbox`, which holds the "Behind a checkbox" mode that this plan depends on.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/<block>/` and run `pnpm build:blocks`.
- Run commands on Node 22 (`nvm use`). Use pnpm only.
- Pro boundary: nothing here is Pro. Name no `YayCheckout\Pro` class.
- Name every `render.php` global `$yaycheckout_*`. A verbatim WooCommerce string or hook needs the same phpcs disables that `create-account/render.php` uses (`WordPress.WP.I18n.TextDomainMismatch`, `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound`).
- Git: work on `trace/feat/disclosure-checkbox`, as the request says. Commit messages follow Conventional Commits and end with the `Co-Authored-By` trailer CLAUDE.md names. Stage files by name.
- Base checks: `pnpm build:blocks` and `composer lint -- <files>` pass on the base branch. `pnpm --filter @yaycheckout/admin-page lint` already fails there, and this plan doesn't touch the SPA.
- Every storefront check runs in a private window as a guest with a product in the cart, through the layout's preview link or the active layout.

## Review focus

- **A plugin made `order_comments` required, and Order note's Required is off.** The shopper must still be able to place the order with an empty note (Task 2, Step 4, case B).
- **Both blocks are in one layout.** The page must have exactly one `order_comments` textarea, and the note must still save (Task 3, Step 4, case C).
- **A required note behind a checkbox.** The note must print open with no checkbox, so checkout never fails on a field the shopper can't see (Task 1, Step 4, case D).
- **A note with `&`, `<` or line breaks.** It must save as the shopper typed it, sanitised by WooCommerce's `wc_sanitize_textarea`, and print escaped (Final verification, step 1).
- **Additional fields while a plugin has turned order notes off with `woocommerce_enable_order_notes_field`.** The hooks still fire, and no textarea prints (Task 3, Step 4, case D).

---

### Task 1: The Order note block

**Files:**
- Create: `packages/blocks/src/order-note/block.json`, `index.ts`, `edit.tsx`, `view.ts`, `render.php`
- Modify: `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php`

**Interfaces:**
- Produces:
  - block `yaycheckout/order-note`, wrapper classes `yaycheckout-order-note form-row`
  - attributes, all `string` with default `""` unless noted: `label`, `placeholder`, `description`, `required` (`boolean`, default `false`), `requiredMark`, `optionalText`, `disclosure` (enum `none|link|collapsible|checkbox`, no default, as in `field/block.json`), `disclosureText`
  - `BlockRegistry::ORDER` entry `'order-note'`, right after `'extra-field'`

- [ ] **Step 1: `block.json`**

Copy `field/block.json`, with these changes:
- `"name": "yaycheckout/order-note"`, `"title": "Order note"`
- `"description": "The shopper's note about their order, saved as the order's customer note."`
- `"keywords": [ "note", "comment", "instructions", "delivery" ]`
- `"ancestor": [ "yaycheckout/checkout" ]`
- `"example": { "attributes": { "label": "Order notes" } }`
- Attributes: only those in Interfaces. Drop `blockId`, `fieldKey`, `type`, `autocomplete`, `validate` and `width`.
- `supports`: Field's, without `yaycheckoutWatchable`, plus `"multiple": false`.
- `editorScript`, `viewScript` and `render` stay as `file:` entries, as in Field. Drop `editorStyle`.

- [ ] **Step 2: `render.php`**

Docblock: it prints WooCommerce's `order_comments` field, and `WC_Checkout` saves it as the customer note. Then, in order:
1. Return without printing when `! function_exists( 'woocommerce_form_field' )`.
2. Set the label to `LayoutMarkupReader::plain_text( $attributes, 'label', __( 'Order notes', 'woocommerce' ) )`. Set the placeholder to the `placeholder` attribute when it is a non-empty string, else `__( 'Notes about your order, e.g. special notes for delivery.', 'woocommerce' )`. Read the help text with `plain_text( $attributes, 'description' )` and `$required` with `! empty( $attributes['required'] )`.
3. `$yaycheckout_value = CheckoutFieldDefaults::value( 'order_comments' )`, then `$yaycheckout_disclosure = Disclosure::mode( $attributes, 'order_comments', $yaycheckout_required, $yaycheckout_value )`.
4. Print `<div ` + `get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-note form-row' ] )` + `>`. Then `Disclosure::open()`/`text()` when the mode isn't `NONE`, exactly as `field/render.php` does.
5. `woocommerce_form_field( 'order_comments', [ 'type' => 'textarea', 'class' => [ 'notes' ], 'label', 'required', 'placeholder', 'description' => esc_html( … ), 'return' => false ], $yaycheckout_value )`.
6. Close the panel when it was opened (`Disclosure::close()`), then the wrapper.

There is no fallback markup for a missing WooCommerce, unlike Field: without WooCommerce there is no checkout to post to.

- [ ] **Step 3: `edit.tsx`, `index.ts`, `view.ts`, icon, `ORDER`**

- `edit.tsx`: `useBlockProps( { className: 'yaycheckout-order-note' } )`. One `PanelBody` titled "Order note", holding, in Field's order and with Field's labels and help:
  - `TextControl` Label (`decodeEntities( label )`), Placeholder and Help text;
  - `RequiredIndicatorControls`, with no lock;
  - `DisclosureControls` with `fieldKey="order_comments"`, `label={ label || __( 'Order notes', 'yaycheckout' ) }` and `required`.
  The canvas is `DisclosurePreview` wrapping `FieldPreview` with `type="textarea"`, the label editable through `onLabelChange`, `labelPlaceholder={ __( 'Order notes', 'yaycheckout' ) }`, the placeholder or `__( 'Notes about your order, e.g. special notes for delivery.', 'yaycheckout' )`, and `inheritLabelFont`. The mode and text come from `disclosureMode( 'order_comments', required, disclosure )` and `defaultDisclosureText()`, as in `field/edit.tsx`.
- `index.ts`: `registerDynamicBlock( metadata, Edit )`.
- `view.ts`: the same two lines as `field/view.ts` (`initDisclosures()`).
- `block-icons.tsx`: add `'yaycheckout/order-note'` in the same 1.5-stroke family: a note sheet (a rounded `rect`) with three text lines and a folded corner.
- `BlockRegistry::ORDER`: insert `'order-note'` after `'extra-field'`.

- [ ] **Step 4: Build, lint and verify the storefront**

1. Run `pnpm build:blocks`. Expect `compiled successfully`, and `assets/dist/blocks/order-note/` holding `block.json`, `render.php`, `index.js` and `view.js`.
2. Run `composer lint -- packages/blocks/src/order-note/render.php includes/Blocks/BlockRegistry.php`. Expect no errors.
3. Add Order note under Billing in a layout, save, and open its preview link. Check each case after a reload:
   - A. Defaults: `#order_comments_field.notes` holds `textarea#order_comments[name="order_comments"]`, labelled "Order notes (optional)", with WooCommerce's placeholder.
   - B. Show field "Behind a checkbox": a checkbox labelled "Add order notes". Ticking it shows the textarea, and unticking hides it. "Behind a link" shows "+ Add order notes", and the link disappears after a click. "In a collapsible section" shows the header "Order notes".
   - C. Label `Gift message & wishes`, help text `Printed on the card`: the label reads `Gift message & wishes` (not `&amp;`), and `Printed on the card` sits under the field.
   - D. Required on, with Show field "Behind a checkbox" and required mark `(must)`: no checkbox, the textarea is visible, and the label ends with `(must)`.
4. The inserter finds "Order note" by "note", with the new icon. A second insert is refused. After a reload the console shows no block-validation warning.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/order-note/block.json`, `packages/blocks/src/order-note/index.ts`, `packages/blocks/src/order-note/edit.tsx`, `packages/blocks/src/order-note/view.ts`, `packages/blocks/src/order-note/render.php`, `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php`
Message: `feat(blocks): add an Order note block for WooCommerce's order notes`

### Task 2: Make WooCommerce follow the Order note's Required setting

**Files:**
- Modify: `includes/Services/LayoutMarkupReader.php`, `includes/Services/RequiredFieldOverlay.php`, `includes/Renderer/CheckoutRequiredFields.php`

**Interfaces:**
- Consumes: block `yaycheckout/order-note` and its attributes (Task 1).
- Produces:
  - `LayoutMarkupReader::order_note( string $markup ): ?array`: `null` when the layout has no Order note block. Otherwise the first block's entry, in the shape `fields()` returns: `fieldKey` `'order_comments'`, `type` `'textarea'`, `label` (plain text), `placeholder`, `description`, `required` (bool), `requiredMark` and `optionalText` (trimmed). Add `private const ORDER_NOTE = 'yaycheckout/order-note'`.
  - `RequiredFieldOverlay::apply_order_note( array $fields, ?array $note ): array`:
    - With `null`, `$fields` is returned unchanged.
    - Otherwise `$fields['order']['order_comments']['required']` becomes `! empty( $note['required'] )`, and `label` becomes the note's `label` when that is non-empty.
    - When `order_comments` is missing from `$fields['order']`, it is added back as `[ 'type' => 'textarea', 'class' => [ 'notes' ], 'label' => <note label or 'order_comments'>, 'required' => … ]`, for the same reason as `restore_missing_field()`.
  - `CheckoutRequiredFields::fields()` returns `array{address: array, all: array, order_note: ?array}`. When `order_note` isn't null, `all` also holds it under `'order_comments'`, so `RequiredIndicator::swap_in()` prints its mark or optional text.

- [ ] **Step 1: Add `LayoutMarkupReader::order_note()`**

Follow the shape and docblock style of `extra_fields()`. Use `plain_text()` for `label` and `text()` for the rest.

- [ ] **Step 2: Add `RequiredFieldOverlay::apply_order_note()`**

Its docblock says that the layout decides whether the order note is required, the same way `apply()` does for address fields. Leave `apply()` and `is_required()` untouched.

- [ ] **Step 3: Wire it into `CheckoutRequiredFields`**

- `fields()`: add `'order_note' => LayoutMarkupReader::order_note( $layout->content )`. When it isn't null, add it to `all` with `array_replace()`, keyed `'order_comments'`, as the existing comment there explains.
- `filter_fields()`: return `RequiredFieldOverlay::apply_order_note( RequiredFieldOverlay::apply( $fields, $layout_fields['address'] ), $layout_fields['order_note'] )`.
- Update the `@return` docblock of `fields()`.

- [ ] **Step 4: Lint and verify**

1. Run `composer lint -- includes/Services/LayoutMarkupReader.php includes/Services/RequiredFieldOverlay.php includes/Renderer/CheckoutRequiredFields.php`. Expect no errors.
2. Add this temporary mu-plugin, `wp-content/mu-plugins/yc-note-required.php`, and don't commit it:
   `add_filter( 'woocommerce_checkout_fields', function ( $f ) { $f['order']['order_comments']['required'] = true; return $f; } );`
   Then, on a layout with Order note:
   - A. Required on, note empty, Place order: WooCommerce shows "**Order notes** is a required field." (or the block's label in bold when it has one), and no order is created.
   - B. Required off, with the mu-plugin active: the label reads "Order notes (optional)", and an empty note places the order.
   - C. Required off, optional text `if any`: the label ends with `(if any)`.
   - D. Remove the Order note block and keep the mu-plugin. The overlay stands down, and WooCommerce's own flag holds: Place order fails with "**Order notes** is a required field.", which is unchanged from today.
3. Delete the mu-plugin.

- [ ] **Step 5: Commit**

Stage: `includes/Services/LayoutMarkupReader.php`, `includes/Services/RequiredFieldOverlay.php`, `includes/Renderer/CheckoutRequiredFields.php`
Message: `feat(checkout): make the order note required only when the Order note block says so`

### Task 3: The Additional fields block

**Files:**
- Create: `packages/blocks/src/additional-fields/block.json`, `index.ts`, `edit.tsx`, `render.php`
- Modify: `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php`

**Interfaces:**
- Consumes: `LayoutMarkupReader::order_note()` (Task 2), block name `yaycheckout/order-note` (Task 1).
- Produces: block `yaycheckout/additional-fields`, with no attributes, wrapper classes `yaycheckout-additional-fields woocommerce-additional-fields`, and `BlockRegistry::ORDER` entry `'additional-fields'` right after `'order-note'`.

- [ ] **Step 1: `block.json`**

Copy the shape of `create-account/block.json`:
- `"name": "yaycheckout/additional-fields"`, `"title": "Additional fields"`
- `"description": "WooCommerce's order fields, such as the order note and fields that plugins add, as WooCommerce defines them."`
- `"keywords": [ "note", "additional", "order", "fields" ]`
- `"ancestor": [ "yaycheckout/checkout" ]`
- no `attributes`, and no `example` attributes
- `"supports": { "html": false, "multiple": false, "reusable": false }`
- `editorScript` and `render` as `file:` entries. No `viewScript`.

- [ ] **Step 2: `render.php`**

Docblock: it mirrors the `woocommerce-additional-fields` part of WooCommerce's `checkout/form-shipping.php`, without the heading. Then:
1. Return without printing when `! function_exists( 'WC' )`.
2. `$yaycheckout_checkout = WC()->checkout()`. Find the served layout with `CheckoutPage::get_instance()->layout()`, as `field-value/render.php` does. Set `$yaycheckout_skip_note` to whether that layout exists and `LayoutMarkupReader::order_note( $layout->content )` isn't null.
3. Print the wrapper with `get_block_wrapper_attributes( [ 'class' => 'yaycheckout-additional-fields woocommerce-additional-fields' ] )`.
4. Fire `woocommerce_before_order_notes`, then print the field wrapper only while the `woocommerce_enable_order_notes_field` condition holds, then fire `woocommerce_after_order_notes`. Criterion 5 gives the exact calls. Skip `order_comments` when `$yaycheckout_skip_note`.

- [ ] **Step 3: `edit.tsx`, `index.ts`, icon, `ORDER`**

- `edit.tsx`: `useBlockProps( { className: 'yaycheckout-additional-fields' } )`. Read `hasOrderNote` with `useSelect( ( select ) => select( blockEditorStore ).getBlocksByName( 'yaycheckout/order-note' ).length > 0, [] )`. The canvas shows:
  - unless `hasOrderNote`, a `FieldPreview` with label `__( 'Order notes', 'yaycheckout' )`, `type="textarea"` and placeholder `__( 'Notes about your order, e.g. special notes for delivery.', 'yaycheckout' )`;
  - a non-dismissible info `Notice`: "Shows WooCommerce's order fields, including any a plugin adds, with the labels and required settings WooCommerce gives them."
  - with `hasOrderNote`, the notice adds: "The Order note block in this layout shows the order note, so this block leaves it out."
  There is no sidebar panel.
- `index.ts`: `registerDynamicBlock( metadata, Edit )`.
- `block-icons.tsx`: add `'yaycheckout/additional-fields'` in the same family: a note sheet like Order note's, with a small "+".
- `BlockRegistry::ORDER`: insert `'additional-fields'` after `'order-note'`.

- [ ] **Step 4: Build, lint and verify**

1. Run `pnpm build:blocks`. Expect `compiled successfully`, and `assets/dist/blocks/additional-fields/` holding `block.json`, `render.php` and `index.js`.
2. Run `composer lint -- packages/blocks/src/additional-fields/render.php includes/Blocks/BlockRegistry.php`. Expect no errors.
3. Add this temporary mu-plugin, `wp-content/mu-plugins/yc-order-fields.php`, and don't commit it:
   - a `woocommerce_checkout_fields` callback that adds `$f['order']['yc_gate_code'] = [ 'type' => 'text', 'label' => 'Gate code', 'required' => true ]`;
   - an echo of `<p id="yc-before">before</p>` on `woocommerce_before_order_notes`;
   - an echo of `<p id="yc-after">after</p>` on `woocommerce_after_order_notes`.
   Then, on a layout with Additional fields and no Order note:
   - A. The page shows `#yc-before`, then `textarea#order_comments` and `#yc_gate_code` labelled "Gate code *", then `#yc-after`, all inside `.yaycheckout-additional-fields`.
   - B. With an empty Gate code, Place order shows "**Gate code** is a required field." With it filled and a note typed, the order is created with that customer note.
   - C. Add Order note to the same layout and reload. `document.querySelectorAll( '[name="order_comments"]' ).length` is `1`, and that textarea sits in `.yaycheckout-order-note`. The editor preview of Additional fields shows the extra notice. A typed note saves on the order.
   - D. Add `add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );` to the mu-plugin. `#yc-before` and `#yc-after` still print, and neither `#order_comments` nor `#yc_gate_code` prints inside `.yaycheckout-additional-fields`.
4. Delete the mu-plugin. After a reload the console shows no block-validation warning, and a second Additional fields insert is refused.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/additional-fields/block.json`, `packages/blocks/src/additional-fields/index.ts`, `packages/blocks/src/additional-fields/edit.tsx`, `packages/blocks/src/additional-fields/render.php`, `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php`
Message: `feat(blocks): add an Additional fields block for WooCommerce's order fieldset`

### Task 4: Document it in architecture.md

**Files:**
- Modify: `docs/architecture.md` (Storefront, the **Fields** bullet)

- [ ] **Step 1: Update the Fields bullet**

The sentence that says `woocommerce_checkout_shipping` (account fields, order notes) never fires stays true. After the Create account sentences, add these two:
- The Order note block prints `order_comments` through `woocommerce_form_field()`, with Field's Show field and Required settings. `RequiredFieldOverlay::apply_order_note()` (run by `CheckoutRequiredFields`) makes WooCommerce require the note exactly when the block does, and WooCommerce saves the note as the order's customer note.
- The Additional fields block prints the `woocommerce-additional-fields` part of form-shipping.php without its heading: `get_checkout_fields( 'order' )` between `woocommerce_before_order_notes` and `woocommerce_after_order_notes`, while `woocommerce_enable_order_notes_field` allows it. It leaves out `order_comments` when the layout has an Order note block.

- [ ] **Step 2: Commit**

Stage: `docs/architecture.md`
Message: `docs(architecture): describe the Order note and Additional fields blocks`

### Final verification: the note reaches the order

Use a layout with Order note set to "Behind a checkbox", placed under Billing, saved and active. Work in a private window as a guest.

1. Tick "Add order notes" and type two lines: `Leave at the back door & ring` and `<b>twice</b>`. Place the order. In wp-admin, the order's "Customer provided note" reads both lines, with `&` and `<b>twice</b>` shown as text, not bold. The order-received page shows them under "Note:". The new-order admin email shows them too (WooCommerce > Status > Logs or a mail catcher).
2. Leave the checkbox unticked and place another order. The order has no customer note.
3. Swap the Order note block for Additional fields, then place an order with a note. The note saves the same way.
4. Run `pnpm build:blocks` and `composer lint`. Expect `compiled successfully` and no new lint errors.
