
# Create Account Block Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec: the requirements below are the brief. On approval, save this plan as `docs/plans/2026-10-07-create-account-block.md` before Task 1.

**Status:** active
**Goal:** a merchant adds a "Create account" block to a layout, and the storefront shows WooCommerce's account-creation UI exactly when and how WooCommerce's settings say.
**Spec:** none, planned from the request
**Architecture:**
- The block only renders markup, the same markup as the account part of `../woocommerce/templates/checkout/form-billing.php`. `WC_Checkout::get_posted_data()`, `validate_posted_data()` and `process_customer()` already run on the YayCheckout form, so they create the account. The block never calls `wc_create_new_customer()`.
- Which inputs appear comes from WooCommerce at render time: `is_registration_enabled()`, `is_registration_required()` and `get_checkout_fields( 'account' )`. The block stores none of it, so a settings change takes effect with no layout edit.
- WooCommerce's `checkout.js` already toggles `div.create-account` from `#createaccount`, so the block ships no view script.

**Touches:**
- Create: `packages/blocks/src/create-account/{block.json,index.ts,edit.tsx,editor.css,render.php}`
- Modify:
  - `packages/blocks/src/shared/block-icons.tsx`
  - `packages/blocks/src/shared/allowed-blocks.ts` (shared; consumed by `shared/address-section-edit.tsx` for Billing and Shipping, and by `step/edit.tsx`)
  - `includes/Blocks/BlockRegistry.php` (`ORDER`)
  - `docs/architecture.md` (Storefront, Fields bullet)

**Tests:** none. CLAUDE.md's test rule names `publish_error()`, `LayoutAssignment::pick()`, `RequiredFieldOverlay::apply()` and `LayoutImportReview`. None of them changes. The new logic is three WooCommerce calls inside `render.php`, so every task gets a manual check instead.

## Requirements

- **Goal:** with WooCommerce > Settings > Accounts & Privacy > Account creation "During checkout" on, a guest on a layout with the block can create an account at checkout, and sees exactly the inputs WooCommerce's settings call for.
- **Out of scope:**
  - Adding the block to `templates/default-layout.php`.
  - Ticking or editing `docs/backlog/2026-10-05-account-fields-and-checkout-form-hooks-never-render.md`. Its boxes name the default layout, which this plan doesn't change.
  - "After checkout" (`woocommerce_enable_delayed_account_creation`): it lives on the order-received page, which stays WooCommerce's.
  - The checkout login form (`woocommerce_enable_checkout_login_reminder`), and order notes.
  - Any change to `RequiredFieldOverlay`. It only touches `billing` and `shipping`.
- **Storefront behaviour matrix.** "Guest" is guest checkout, and "Signup" is "During checkout":

| Guest | Signup | Visitor | Storefront |
|---|---|---|---|
| any | any | logged in | nothing |
| any | off | guest | nothing |
| on | on | guest | `#createaccount` checkbox; `div.create-account` with the account fields, hidden until it is ticked |
| off | on | guest | no checkbox; account fields always visible and required |

  Account fields come from `get_checkout_fields( 'account' )`:
  - `account_username` exists only when `woocommerce_registration_generate_username` = `no`.
  - `account_password` exists only when `woocommerce_registration_generate_password` = `no`.
  - Fields that plugins add to the `account` fieldset render too.
- **Acceptance criteria:**
  1. The block is registered as `yaycheckout/create-account`, titled "Create account". It is limited to one per layout, may be placed only under Checkout, and may also go inside Billing and Shipping.
  2. The storefront follows the matrix above, with these names and ids: checkbox `name="createaccount"` `id="createaccount"` `value="1"`, and fields named `account_username` and `account_password`, printed through `woocommerce_form_field()`.
  3. The checkbox label is the block's `checkboxLabel`. When it is empty, the label is WooCommerce's translated "Create an account?". `&` prints as `&`.
  4. When the block prints anything, it fires `woocommerce_before_checkout_registration_form` and `woocommerce_after_checkout_registration_form` once each, with `WC()->checkout()`.
  5. The editor always shows a static preview: the checkbox with its label editable on the canvas, plus "Account username" and "Create account password" previews. The sidebar has a notice saying the storefront follows WooCommerce's settings.
  6. End to end: with guest checkout on, signup on and both generate options off, a guest who ticks the box and fills username and password places an order. Afterwards a user exists with that username, the order's customer is that user, and the shopper is logged in.
- **Decided with the user (2026-10-07):**
  - Not added to the default layout.
  - The editor preview is static, with no live WooCommerce settings.
  - The backlog item is left untouched.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/create-account/` and run `pnpm build:blocks`.
- Run commands on Node 22 (`nvm use`). Use pnpm only.
- Branch from `dev/edd` as `<name>/feat/create-account-block`. Commit messages follow Conventional Commits and end with the `Co-Authored-By` trailer CLAUDE.md names. Stage files by name.
- Base checks: `pnpm build:blocks` and `composer lint -- <files>` pass on `dev/edd`. `pnpm --filter @yaycheckout/admin-page lint` already fails there, and this plan doesn't touch the SPA.

## Review focus

- **Guest checkout off, signup on, generate password off.** The password field must render and be required. An empty password fails with "Create account password is a required field." (Task 1, Step 6, case D).
- **An email that already has an account.** WooCommerce stops with "An account is already registered with your email address. Please log in." and creates no order (Final verification, step 3).
- **The merchant checks the storefront while logged in.** The block renders nothing, which is correct. Every storefront check uses a private window as a guest, through the layout's preview link (Task 1, Step 6).
- **Box left unticked with the fields hidden.** `maybe_skip_fieldset()` skips `account`, so an empty username or password must not block the order (Final verification, step 2).
- **Block placed inside Billing.** It must still post `createaccount` and the account fields inside `form.checkout` (Task 2, Step 3).

---

### Task 1: The Create account block

**Files:**
- Create: `packages/blocks/src/create-account/block.json`, `index.ts`, `edit.tsx`, `editor.css`, `render.php`
- Modify: `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php`

**Interfaces:**
- Produces:
  - block `yaycheckout/create-account`, wrapper classes `yaycheckout-create-account woocommerce-account-fields`
  - attribute `checkboxLabel: string`, default `""`
  - `BlockRegistry::ORDER` entry `'create-account'`, right after `'email'`

- [ ] **Step 1: `block.json`**

Copy the shape of `email/block.json`:
- `"name": "yaycheckout/create-account"`, `"title": "Create account"`, `"category": "yaycheckout"`
- `"description": "Lets a guest create an account at checkout, following WooCommerce's account settings."`
- `"keywords": [ "account", "register", "signup", "password" ]`
- `"ancestor": [ "yaycheckout/checkout" ]`
- `"attributes": { "checkboxLabel": { "type": "string", "default": "" } }`
- `"supports": { "html": false, "multiple": false, "reusable": false }`
- `editorScript`, `editorStyle`, and `render` as `file:` entries. No `viewScript`, and no `style`.

- [ ] **Step 2: `render.php`**

Docblock: say that it mirrors the account section of WooCommerce's form-billing.php, and that `WC_Checkout::process_customer()` creates the account. Then, in order:
1. Return early, printing nothing, when `! function_exists( 'WC' )`, `is_user_logged_in()`, or `! WC()->checkout()->is_registration_enabled()`.
2. Let `$yaycheckout_checkout = WC()->checkout()`. Read `$yaycheckout_label` with `LayoutMarkupReader::plain_text( $attributes, 'checkboxLabel', __( 'Create an account?', 'woocommerce' ) )`. Disable `WordPress.WP.I18n.TextDomainMismatch` around that line, with the same reason as `checkout/render.php`.
3. Print a `<div>` with `get_block_wrapper_attributes( [ 'class' => 'yaycheckout-create-account woocommerce-account-fields' ] )`.
4. When `! $yaycheckout_checkout->is_registration_required()`, print WooCommerce's markup verbatim: `p.form-row.form-row-wide.create-account` holding the `label.woocommerce-form__label.woocommerce-form__label-for-checkbox.checkbox`, and the `input#createaccount` with classes `woocommerce-form__input woocommerce-form__input-checkbox input-checkbox`, `type="checkbox"`, `name="createaccount"`, `value="1"`. It is `checked()` when `true === $checkout->get_value( 'createaccount' )` or when `true === apply_filters( 'woocommerce_create_account_default_checked', false )`. The label text is `esc_html( $yaycheckout_label )`, inside a `<span>`.
5. `do_action( 'woocommerce_before_checkout_registration_form', $yaycheckout_checkout )`.
6. When `$yaycheckout_checkout->get_checkout_fields( 'account' )` is non-empty, print `<div class="create-account">`. For each `$key => $field`, call `woocommerce_form_field( $key, $field, $yaycheckout_checkout->get_value( $key ) )`. Then print `<div class="clear"></div>` and close the div.
7. `do_action( 'woocommerce_after_checkout_registration_form', $yaycheckout_checkout )`, then close the wrapper.

Name every global `$yaycheckout_*`. The two `do_action` lines need the same `PrefixAllGlobals.NonPrefixedHooknameFound` disable that `checkout/render.php` uses.

- [ ] **Step 3: `edit.tsx`, `index.ts`, `editor.css`, icon**

- `edit.tsx`: `useBlockProps( { className: 'yaycheckout-create-account' } )`. The canvas shows:
  - A row with a disabled checkbox and a `RichText` (`tagName="span"`) bound to `checkboxLabel`, with placeholder `__( 'Create an account?', 'yaycheckout' )`, `allowedFormats={ [] }` and `withoutInteractiveFormatting`.
  - `FieldPreview` with label `__( 'Account username', 'yaycheckout' )`, type `text`, `required`.
  - `FieldPreview` with label `__( 'Create account password', 'yaycheckout' )`, type `password`, `required`.
- `edit.tsx` sidebar: a `PanelBody` titled "Create account", holding:
  - a `TextControl` "Checkbox label" showing `decodeEntities( checkboxLabel )`;
  - a non-dismissible info `Notice`: "Shown only to guests, and only when WooCommerce > Settings > Accounts & Privacy allows account creation during checkout. The checkbox appears only while guest checkout is on. Username and password appear only when WooCommerce doesn't generate them."
- `index.ts`: `registerDynamicBlock( metadata, Edit )`, importing `./editor.css` from `edit.tsx` like `coupon-form` does.
- `editor.css`: lay out the checkbox row (inline-flex, 8px gap, 12px bottom margin), scoped under `.editor-styles-wrapper .yaycheckout-create-account`.
- `block-icons.tsx`: add `'yaycheckout/create-account'` in the same 1.5-stroke family: a person outline (head `circle` plus shoulders `path`) with a small "+".

- [ ] **Step 4: Register in `BlockRegistry::ORDER`**

Insert `'create-account'` right after `'email'`.

- [ ] **Step 5: Build and lint**

1. Run `pnpm build:blocks`. Expect `compiled successfully`, and `assets/dist/blocks/create-account/` holding `block.json`, `render.php` and `index.js`.
2. Run `composer lint -- packages/blocks/src/create-account/render.php includes/Blocks/BlockRegistry.php`. Expect no errors.

- [ ] **Step 6: Verify the matrix on the storefront**

Setup: add Create account under Email address in a layout, save, and open its preview link in a private window as a guest, with a product in the cart. Change settings in WooCommerce > Settings > Accounts & Privacy. For each case, reload and inspect the form.
- A. Signup "During checkout" off: no `.yaycheckout-create-account` in the DOM.
- B. Guest on, signup on, both "Send password setup link" and "Generate account login" on: `#createaccount` is unticked, and there is no `div.create-account`.
- C. Guest on, signup on, both generate options off: ticking `#createaccount` slides down `div.create-account` holding `#account_username` and `#account_password`, and unticking hides it again.
- D. Guest off, signup on, generate password off: there is no checkbox, and `#account_password` is visible and marked required. Submitting with it empty shows "Create account password is a required field." and creates no order.
- E. Log in as any user and open the same checkout: no `.yaycheckout-create-account`.

- [ ] **Step 7: Verify the editor**

1. The inserter finds "Create account" by "account" and "register", with the new icon. A second insert is refused.
2. Type `Join & save` into the canvas label. The sidebar shows `Join & save`. Save and preview (setup from case C): the storefront label reads `Join & save`, not `&amp;`.
3. Clear the label, save and preview. The storefront reads "Create an account?".
4. The browser console shows no block-validation warning after a reload.

- [ ] **Step 8: Commit**

Stage: `packages/blocks/src/create-account/block.json`, `packages/blocks/src/create-account/index.ts`, `packages/blocks/src/create-account/edit.tsx`, `packages/blocks/src/create-account/editor.css`, `packages/blocks/src/create-account/render.php`, `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php`
Message: `feat(blocks): add a Create account block that follows WooCommerce's account settings`

### Task 2: Allow the block inside Billing and Shipping

**Files:**
- Modify: `packages/blocks/src/shared/allowed-blocks.ts`

**Interfaces:**
- Consumes: block `yaycheckout/create-account` (Task 1).
- Produces: `isCheckoutSection( type )` returns `false` for `yaycheckout/create-account`, so `address-section-edit.tsx` lets it into Billing and Shipping. `step/edit.tsx` gets the same answer, which only matters if it uses the helper for exclusion.

- [ ] **Step 1: Add the block to `ALLOWED_IN_ADDRESS_SECTIONS`**

Import `createAccountMeta` from `../create-account/block.json` and add `createAccountMeta.name` to the set. Rewrite the comment above it so it also says that Create account posts its fields from anywhere in the form, the way WooCommerce prints it under billing.

- [ ] **Step 2: Build**

Run `pnpm build:blocks`. Expect `compiled successfully`.

- [ ] **Step 3: Verify placement**

1. Drag Create account into Billing, after the last field. It drops there. Drag it into Shipping and it drops there too.
2. Check that a Step's allowed blocks didn't change: insert a block into a Step in a Steps layout, and its inserter offers the same blocks as before.
3. With the block inside Billing, repeat case C from Task 1, Step 6, and place the order with username `ycguest1`. The order is created, and Users lists `ycguest1`.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/shared/allowed-blocks.ts`
Message: `feat(blocks): allow the Create account block inside address sections`

### Task 3: Document it in architecture.md

**Files:**
- Modify: `docs/architecture.md` (Storefront, the **Fields** bullet)

- [ ] **Step 1: Update the Fields bullet**

After "...never do.", add: "The Create account block prints the account part of form-billing.php when WooCommerce allows registration at checkout and the visitor is a guest: the `createaccount` checkbox while guest checkout is on, then `get_checkout_fields( 'account' )` through `woocommerce_form_field()`, between `woocommerce_before_checkout_registration_form` and `woocommerce_after_checkout_registration_form`. `WC_Checkout::process_customer()` creates the account, and WooCommerce's `checkout.js` toggles the fields. A layout without the block still has WooCommerce require the account fields when guest checkout is off."

- [ ] **Step 2: Commit**

Stage: `docs/architecture.md`
Message: `docs(architecture): describe the Create account block`

### Final verification: an account is created with the order

Use the case C settings (guest on, signup on, both generate options off), with the block under Email address. Work in a private window, through the active layout or its preview link.

1. Tick the box, enter username `ycguest2` and a strong password, fill the rest, and place the order. The order is created, Users lists `ycguest2` with role Customer, the order's Customer in wp-admin is `ycguest2`, and the order-received page shows the shopper logged in.
2. As a new guest, leave the box unticked and place an order with another email. The order is created as a guest order, and no new user appears.
3. Tick the box with the email used in step 1. The checkout shows "An account is already registered with your email address. Please log in." and creates no order.
4. Switch to both generate options on, and repeat step 1 with a new email, ticking only the box. The order is created, a user is created with a generated login, and WooCommerce's "new account" email (WooCommerce > Status > Logs, or a mail catcher) contains a set-password link.