# Different Address Block Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-11
**Goal:** a `yaycheckout/different-address` block that splits billing and shipping, with the main address taken from WooCommerce's `woocommerce_ship_to_destination` setting.
**Spec:** [docs/specs/2026-10-11-different-address-block-design.md](../specs/2026-10-11-different-address-block-design.md)
**Architecture:**
- Billing mode prints WooCommerce's own `ship_to_different_address` checkbox, and the Shipping section carries `shipping_address`, so WooCommerce's `checkout.js` and `WC_Checkout` do the work.
- Shipping mode keeps WooCommerce's checkbox checked and hidden, and adds a visible `yaycheckout_same_billing` box.
  - A view script hides Billing and mirrors shipping into it while the shopper types.
  - `Renderer\CheckoutDifferentAddress` copies shipping into billing on the server and makes billing optional for that request.
- This was chosen over an own checkbox, which would lose `checkout.js`'s `s_*` rate fields and break extensions that read WooCommerce's checkbox.

**Touches:**
- New: `packages/blocks/src/different-address/`, `includes/Renderer/CheckoutDifferentAddress.php`, `assets/js/checkout-different-address.js`.
- Changed:
  - `includes/Services/LayoutMarkupReader.php` (shared). Its consumers are the block `render.php` files, `CheckoutRequiredFields`, `CheckoutFragments` and `LayoutTemplateCatalogue`; this plan only adds methods.
  - `includes/Blocks/BlockRegistry.php`, `includes/YayCheckout.php`.
  - In `packages/blocks/src/`: `shipping/*`, `shared/address-section-edit.tsx` (shared by Billing and Shipping), `shared/block-icons.tsx`, `checkout/edit.tsx`.
  - `templates/layouts/classic-two-column/markup.php`, `assets/css/checkout-base.css`, `docs/architecture.md`.

**Tests:** `tests/Integration/Renderer/CheckoutDifferentAddressTest.php`, PHPUnit integration suite (`composer test`).
- It guards that in shipping mode, a POST with `yaycheckout_same_billing=1` gives `WC()->checkout()->get_posted_data()` billing address fields equal to the shipping ones, keeps `billing_email`, and leaves the billing address fields not required.
- It also guards that nothing is copied in billing mode.

## Global constraints

- PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- WordPress ≥ 6.5, WooCommerce ≥ 10.0.0.
- Free and Pro both ship the block. No code under `includes/Pro/`, and no `Plan::is_pro()` check.
- Keep WooCommerce's names exactly: `ship_to_different_address`, `#ship-to-different-address`, `#ship-to-different-address-checkbox`, `shipping_address` class, `woocommerce_ship_to_different_address_checked`.
- YayCheckout's own field is `yaycheckout_same_billing`. The editor global is `window.yayCheckoutDifferentAddress`.
- Read the mode on every render. Never cache or store it.
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/different-address/` and run `pnpm build:blocks`.
- Server filters act only when a YayCheckout layout is served for the request, and in shipping mode only when `yaycheckout_same_billing=1` is posted.
- Branch from `dev/edd` as `trace/feat/different-address-block`. Commit messages are Conventional Commits with the trailers CLAUDE.md names. Stage files by name.
- Base-branch state: `pnpm --filter @yaycheckout/admin-page lint` fails on `dev/edd` (docs/known-issues.md). This plan doesn't touch the SPA, so don't run it.

## Decisions made while planning

These go beyond the spec. Each one keeps the spec's behaviour.

1. **Hidden WooCommerce checkbox.** In shipping mode, the Shipping block prints WooCommerce's hidden checked checkbox. In billing mode, it prints it only when the layout has no Different address block. The Different address block in shipping mode prints only the visible `yaycheckout_same_billing` box. This gives the spec's result with one printer per mode.
2. **No Billing section in shipping mode.**
   - The Shipping block prints `<input type="hidden" name="yaycheckout_same_billing" value="1">`, so the server rule stays the single "posted" check (spec, Components).
   - The Different address block prints nothing in that case, since there is no Billing section to hide.
3. **Saved addresses differ** means the customer is logged in, both saved `address_1` values are non-empty, and any of `first_name`, `last_name`, `company`, `address_1`, `address_2`, `city`, `state`, `postcode`, `country` differ between `WC()->customer->get_billing()` and `->get_shipping()`.
4. **Editor label placeholders** use the `yaycheckout` text domain. WooCommerce's PHP strings aren't loaded into our editor bundle. The storefront fallbacks use the `woocommerce` domain, with `// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch` as in `includes/Renderer/CartItemQuantity.php:92`.

## Review focus

- **State select in shipping mode:** a shopper picks shipping country CA. The billing copy must set `billing_country` first, then `billing_state` after `country_to_state_changed`, or tax runs on the old state. Verified in Task 4, Step 3.
- **Tampered or no-JavaScript submit:** Billing is hidden or left empty and `yaycheckout_same_billing=1` is posted. The order must still get the shipping address as billing, with no "Billing … is a required field" error. Covered by the accepted test (Task 1) and Task 4, Step 4.
- **Billing mode, box unticked:** the shipping fields are empty and required in the layout. The order must place without shipping required errors, because WooCommerce skips the fieldset. Verified in Task 2, Step 4.
- **Rates in billing mode:** after ticking the box, typing a shipping postcode must refresh rates. This needs `div.shipping_address` on the Shipping wrapper (`checkout.js:660`). Verified in Task 3, Step 3.
- **A layout with no block (existing drafts):** shipping must save as typed and not be overwritten by billing. Verified in Task 3, Step 4.

---

### Task 1: Server copy in shipping mode

**Files:**
- Create: `includes/Renderer/CheckoutDifferentAddress.php`
- Modify: `includes/YayCheckout.php:34` (boot it after `CheckoutLogin`)
- Modify: `includes/Services/LayoutMarkupReader.php` (after `has_order_note()`, line 153)
- Test: `tests/Integration/Renderer/CheckoutDifferentAddressTest.php`

**Interfaces:**
- Produces, on `LayoutMarkupReader`. Each method is `has_block()` on a new private const, like `has_order_note()`:
  - `public static function has_billing( string $markup ): bool`
  - `public static function has_shipping( string $markup ): bool`
  - `public static function has_different_address( string $markup ): bool`
- Produces, on `YayCheckout\Renderer\CheckoutDifferentAddress` (SingletonTrait):
  - `public const SAME_BILLING_FIELD = 'yaycheckout_same_billing';`
  - `public const COPIED_KEYS = [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ];`
  - `public static function destination(): string`: returns `'billing'`, `'shipping'` or `'billing_only'`. It reads `get_option( 'woocommerce_ship_to_destination' )`, and `wc_ship_to_billing_address_only()` wins. Any other value gives `'billing'`.
  - `public static function needs_shipping_address(): bool`: `false` for `billing_only`, or when `WC()->cart` exists and `! WC()->cart->needs_shipping_address()`. Otherwise `true`.
  - `public static function saved_addresses_differ(): bool`: as in Decisions, item 3.
  - `public function filter_posted_data( $data )`, hooked to `woocommerce_checkout_posted_data`. Sets `billing_<key> = $data['shipping_<key>'] ?? ''` for each `COPIED_KEYS` entry. It never touches `billing_email`.
  - `public function filter_fields( $fields )`, hooked to `woocommerce_checkout_fields` at priority `10000` (after `CheckoutRequiredFields` at 9999). Sets `required => false` on `billing_<key>` for each `COPIED_KEYS` entry present.
  - `private function copies(): bool`: `true` when `CheckoutPage::get_instance()->layout()` is a `Layout`, `destination()` is `'shipping'`, and `'1' === wp_unslash( $_POST[ self::SAME_BILLING_FIELD ] ?? '' )`. Add the nonce phpcs ignore that WooCommerce uses; WooCommerce checks the nonce itself.

- [ ] **Step 1: Write the failing test**

```php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Tests\Support\LayoutFactory;

/** Shipping mode copies the shipping address into billing on submit; billing mode copies nothing. */
class CheckoutDifferentAddressTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        add_filter( 'woocommerce_cart_needs_shipping_address', '__return_true' );
        LayoutFactory::serve( '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/billing /--><!-- wp:yaycheckout/different-address /--><!-- wp:yaycheckout/shipping /--><!-- /wp:yaycheckout/checkout -->' );
        $_POST = [
            'woocommerce-process-checkout-nonce' => 'x',
            'ship_to_different_address'          => '1',
            'billing_email'                      => 'shopper@example.com',
            'shipping_first_name'                => 'Ada',
            'shipping_last_name'                 => 'Lovelace',
            'shipping_address_1'                 => '1 Main St',
            'shipping_city'                      => 'Toronto',
            'shipping_state'                     => 'ON',
            'shipping_postcode'                  => 'M5V 2T6',
            'shipping_country'                   => 'CA',
        ];
    }

    public function tear_down(): void {
        $_POST = [];
        parent::tear_down();
    }

    public function test_shipping_mode_copies_shipping_into_billing(): void {
        update_option( 'woocommerce_ship_to_destination', 'shipping' );
        $_POST['yaycheckout_same_billing'] = '1';

        $checkout = new \WC_Checkout();
        $data     = $checkout->get_posted_data();

        foreach ( [ 'first_name', 'last_name', 'address_1', 'city', 'state', 'postcode', 'country' ] as $key ) {
            $this->assertSame( $data[ 'shipping_' . $key ], $data[ 'billing_' . $key ], $key );
        }
        $this->assertSame( 'shopper@example.com', $data['billing_email'] );
        $this->assertFalse( $checkout->get_checkout_fields( 'billing' )['billing_address_1']['required'] );
    }

    public function test_billing_mode_copies_nothing(): void {
        update_option( 'woocommerce_ship_to_destination', 'billing' );
        $_POST['yaycheckout_same_billing'] = '1';

        $data = ( new \WC_Checkout() )->get_posted_data();

        $this->assertSame( '', $data['billing_address_1'] );
        $this->assertSame( '1 Main St', $data['shipping_address_1'] );
    }
}
```

`new \WC_Checkout()` avoids the fields WooCommerce's singleton has already cached. If the constructor isn't public, reset `WC()->checkout()`'s `fields` property to `null` through `ReflectionProperty`, as `LayoutFactory::forget_served_layout()` does.

- [ ] **Step 2: Run the test to confirm it fails**

Run: `composer test -- --filter CheckoutDifferentAddressTest`
Expected: `test_shipping_mode_copies_shipping_into_billing` FAILS (billing values are empty). `test_billing_mode_copies_nothing` passes. The database setup is in docs/known-issues.md, "`composer test` needs its own database".

- [ ] **Step 3: Implement the `LayoutMarkupReader` methods and `CheckoutDifferentAddress` as in Interfaces, then boot it in `includes/YayCheckout.php` after `CheckoutLogin::get_instance()`**

The class docblock says it stands down when no layout is served, in the same words as `CheckoutRequiredFields`.

- [ ] **Step 4: Run the test to confirm it passes**

Run: `composer test -- --filter CheckoutDifferentAddressTest`
Expected: 2 tests, both PASS. Then run `composer test` and expect no failures.

- [ ] **Step 5: Lint**

Run: `composer lint -- includes/Renderer/CheckoutDifferentAddress.php includes/Services/LayoutMarkupReader.php includes/YayCheckout.php tests/Integration/Renderer/CheckoutDifferentAddressTest.php`
Expected: the boundary check passes and phpcs reports no errors.

- [ ] **Step 6: Commit**

Stage: `includes/Renderer/CheckoutDifferentAddress.php`, `includes/Services/LayoutMarkupReader.php`, `includes/YayCheckout.php`, `tests/Integration/Renderer/CheckoutDifferentAddressTest.php`
Message: `feat(checkout): copy shipping into billing when billing is the same`

### Task 2: The block in billing mode

**Files:**
- Create in `packages/blocks/src/different-address/`:
  - `block.json`
  - `render.php`
  - `edit.tsx` (canvas preview and RichText label only; Task 5 adds the notices)
  - `editor-data.ts`
  - `index.ts`
- Modify: `includes/Blocks/BlockRegistry.php`. Add `'different-address'` to `ORDER` after `'shipping'`. Add the editor global after the create-account one, in `register_blocks()`.
- Modify: `packages/blocks/src/shared/block-icons.tsx` (an entry `'yaycheckout/different-address'` after `'yaycheckout/shipping'`)
- Modify: `assets/css/checkout-base.css` (checkbox row for `#ship-to-different-address` and `.yaycheckout-different-address__same`, scoped under `.yaycheckout-checkout`, matching the Create account checkbox row)

**Interfaces:**
- Consumes: Task 1's `CheckoutDifferentAddress::destination()`, `needs_shipping_address()`, `saved_addresses_differ()` and `SAME_BILLING_FIELD`, and `LayoutMarkupReader::has_billing()` and `has_shipping()`.
- Produces:
  - `block.json`:
    - `name` `yaycheckout/different-address`, `title` "Different address", `ancestor` `["yaycheckout/checkout"]`, `supports.multiple: false`.
    - Colour, border and spacing (margin, padding) supports, copied from `return-to-cart/block.json`, without typography.
    - Attributes `shipLabel` and `sameLabel`, both `{ "type": "string", "default": "" }`.
    - `viewScript` `yaycheckout-different-address`.
  - `window.yayCheckoutDifferentAddress: { destination: 'billing' | 'shipping' | 'billing_only'; settingsUrl: string }`. `settingsUrl` is `admin_url( 'admin.php?page=wc-settings&tab=shipping&section=options' )`. It is printed on `generate_block_asset_handle( 'yaycheckout/different-address', 'editorScript' )`. `editor-data.ts` exports `differentAddressEditorData`, falling back to `{ destination: 'billing', settingsUrl: '' }`, as `login/editor-data.ts` does.
  - Storefront markup in billing mode, from WooCommerce's `templates/checkout/form-shipping.php`:
    ```html
    <div class="wp-block-yaycheckout-different-address yaycheckout-different-address …">
      <div id="ship-to-different-address">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
          <input id="ship-to-different-address-checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" type="checkbox" name="ship_to_different_address" value="1" [checked]> <span>{label}</span>
        </label>
      </div>
    </div>
    ```
    `checked` comes from `apply_filters( 'woocommerce_ship_to_different_address_checked', 'shipping' === get_option( 'woocommerce_ship_to_destination' ) ? 1 : 0 )`, as WooCommerce prints it.
  - Storefront markup in shipping mode: the same wrapper, holding `<label class="yaycheckout-different-address__same …checkbox"><input type="checkbox" name="yaycheckout_same_billing" value="1" [checked]> <span>{label}</span></label>`. It is checked unless `saved_addresses_differ()`. Task 4 adds its script.
  - `render.php` prints nothing (returns early) when:
    - `CheckoutPage` has no layout, or
    - `! needs_shipping_address()`, or
    - `! has_shipping( $layout->content )`, or
    - shipping mode and `! has_billing( $layout->content )`.
  - Labels:
    - `shipLabel`: `LayoutMarkupReader::plain_text( $attributes, 'shipLabel', __( 'Ship to a different address?', 'woocommerce' ) )`.
    - `sameLabel`: the same, with `__( 'Use same address for billing', 'woocommerce' )`.

- [ ] **Step 1: Write `block.json`, `index.ts` (`registerDynamicBlock( metadata, Edit )`), `editor-data.ts` and the `BlockRegistry` changes**

Leave `viewScript` out of `block.json` until Task 4, which registers the handle.

- [ ] **Step 2: Write `render.php` as in Interfaces, and `edit.tsx`**

`edit.tsx` shows a disabled checkbox and a `RichText` label (`allowedFormats={ [] }`, `withoutInteractiveFormatting`, as `shared/step-nav-button-edit.tsx` does). It edits `shipLabel` when `destination` is `'billing'`, and `sameLabel` otherwise. The placeholder is "Ship to a different address?" or "Use same address for billing", in the `yaycheckout` domain.

- [ ] **Step 3: Build and lint**

Run: `pnpm build:blocks`, then `composer lint -- includes/Blocks/BlockRegistry.php packages/blocks/src/different-address/render.php`
Expected: the build succeeds, `assets/dist/blocks/different-address/` exists, and phpcs reports no errors.

- [ ] **Step 4: Verify the editor, and the billing-mode order that has the box unticked**

Setup: WooCommerce → Settings → Shipping → Shipping options, "Shipping destination" = "Default to customer billing address". Use a physical product in the cart, and the Test layout (id 376) with Different address inserted between Billing and Shipping. Pick a revision as the restore point first.

- Editor:
  - The inserter lists "Different address" after "Shipping".
  - A second one can't be inserted.
  - The canvas shows "Ship to a different address?".
  - Typing "Ship elsewhere" in the label changes it in place.
- Storefront:
  - `#ship-to-different-address-checkbox` is unticked and labelled "Ship elsewhere".
  - Shipping is still always visible, because Task 3 adds `shipping_address`.
  - Clear the label in the canvas and save: the storefront prints "Ship to a different address?".
  - Leave the box unticked and place the order with Billing filled. The order is placed with no "Shipping … is a required field" error, and the admin order's shipping address equals its billing address.

- [ ] **Step 5: Commit**

Stage: the five files in `packages/blocks/src/different-address/`, `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/shared/block-icons.tsx`, `assets/css/checkout-base.css`
Message: `feat(blocks): add Different address block`

### Task 3: Shipping section follows the mode

**Files:**
- Modify: `packages/blocks/src/shipping/render.php`
- Modify: `packages/blocks/src/shipping/block.json` (remove `shipToDifferent`, lines 38-41)
- Modify: `packages/blocks/src/shipping/edit.tsx` (remove `shipToDifferent` from `ShippingAttributes`)
- Modify: `packages/blocks/src/shared/address-section-edit.tsx` (remove `shipToDifferent` from `AddressSectionAttributes` and the toggle at lines 52-58, plus its destructure)

**Interfaces:**
- Consumes: Task 1's `destination()`, `needs_shipping_address()`, `SAME_BILLING_FIELD`, `has_billing()` and `has_different_address()`.
- Produces, in `shipping/render.php`:
  - It prints nothing when `! needs_shipping_address()`.
  - The wrapper class gains `shipping_address`, giving `'yaycheckout-shipping shipping_address'`. If the block-spacing branch has merged, keep its gap class too.
  - When a layout is served and (`destination()` is `'shipping'` or `! has_different_address()`), it prints first inside the wrapper:
    `<div id="ship-to-different-address" hidden><input id="ship-to-different-address-checkbox" type="checkbox" name="ship_to_different_address" value="1" checked></div>`
  - When `destination()` is `'shipping'` and `! has_billing()`, it also prints `<input type="hidden" name="yaycheckout_same_billing" value="1">`.

`checkout.js` (`ship_to_different_address()`, line 502) hides `div.shipping_address`, then shows it when the box is checked, so a checked hidden box keeps Shipping visible.

- [ ] **Step 1: Make the `render.php` changes in Interfaces, and remove `shipToDifferent` from the three editor files**

- [ ] **Step 2: Build and lint**

Run: `pnpm build:blocks`, then `composer lint -- packages/blocks/src/shipping/render.php`
Expected: the build succeeds, phpcs reports no errors, and `git grep -n shipToDifferent -- packages` prints nothing.

- [ ] **Step 3: Verify billing mode with the block**

Setup as in Task 2, Step 4.

- Shipping is hidden on load.
- Ticking the box slides it down.
- Typing a shipping postcode in another zone refreshes "Shipping options". The network tab's `update_order_review` request carries `s_postcode` = the typed value.
- Place the order. The admin order shows two different addresses.
- The Shipping sidebar has no "Ship to a different address" toggle.

- [ ] **Step 4: Verify a layout without the block, and `billing_only`**

- Remove Different address from the Test layout and save.
  - The storefront shows Shipping, with no visible checkbox.
  - Place an order with different addresses. The admin order keeps both as typed.
- Set "Shipping destination" to "Force shipping to the customer billing address".
  - Neither Shipping nor the box prints.
  - The order's shipping address equals its billing address.
- Restore the billing default, then empty the cart and add only a virtual product.
  - Neither Shipping nor the box prints.
  - The order places with Billing only.
- Put the block back and the physical product in the cart.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/shipping/render.php`, `packages/blocks/src/shipping/block.json`, `packages/blocks/src/shipping/edit.tsx`, `packages/blocks/src/shared/address-section-edit.tsx`
Message: `feat(blocks): let Shipping follow WooCommerce's shipping destination`

### Task 4: Shipping mode in the browser

**Files:**
- Create: `assets/js/checkout-different-address.js`
- Modify: `includes/Blocks/BlockRegistry.php`:
  - add `public const DIFFERENT_ADDRESS_SCRIPT = 'yaycheckout-different-address';`;
  - register it in `register_view_scripts()` with deps `[ 'jquery', 'wc-checkout', 'wc-address-i18n' ]`.
- Modify: `packages/blocks/src/different-address/block.json` (add `"viewScript": "yaycheckout-different-address"`)

**Interfaces:**
- Consumes: the shipping-mode markup from Task 2 (`input[name="yaycheckout_same_billing"]` inside `.yaycheckout-different-address`).
- Produces: no PHP or JavaScript API. The script's behaviour:
  - It does nothing unless `input[name="yaycheckout_same_billing"][type="checkbox"]` exists.
  - On load and on change, it hides `.yaycheckout-checkout .yaycheckout-billing` while the box is ticked, and shows it while unticked.
  - While ticked, on `input change` of any `#shipping_<key>` (key in `CheckoutDifferentAddress::COPIED_KEYS`), it sets `#billing_<key>` to the same value and triggers `change` on it.
  - For `country`, it sets `#billing_country`, triggers `change`, and then sets `#billing_state` on the next `country_to_state_changed` event.
  - On ticking, it copies every key once, country first.
  - It never touches `#billing_email`.

- [ ] **Step 1: Write the script and the registration as in Interfaces**

- [ ] **Step 2: Build**

Run: `pnpm build:blocks`
Expected: success, and `assets/dist/blocks/different-address/block.json` names the `viewScript` handle.

- [ ] **Step 3: Verify shipping mode and the state copy**

Setup: "Shipping destination" = "Default to customer shipping address". Use a guest session and a cart with a taxable physical product.

- The storefront shows "Use same address for billing" ticked, and Billing hidden.
- Set shipping country Canada and state Ontario. In devtools, `#billing_country` is `CA` and `#billing_state` is `ON`, and the order review's tax line reflects Ontario.
- Untick the box: Billing shows with the copied values. Tick it again: Billing hides.
- Log in as a customer whose saved billing and shipping addresses differ: the box starts unticked and Billing shows. Log in as one whose saved addresses match: it starts ticked.

- [ ] **Step 4: Verify the order and the no-JavaScript fallback**

- Place the order with the box ticked and only shipping and email filled. The admin order's billing address equals its shipping address, and the billing email is the one typed.
- Repeat with JavaScript disabled in devtools: Billing shows, still ticked. Leave Billing empty and place the order. There's no billing required-field error, and the billing address equals the shipping address.
- Untick the box, fill a different Billing, and place the order. The order has two different addresses.
- Remove Billing from the Test layout and save.
  - The storefront prints no "Use same address for billing" box.
  - Shipping's wrapper holds `input[type=hidden][name=yaycheckout_same_billing]`.
  - The order's billing address equals its shipping address.
- Undo the removal.

- [ ] **Step 5: Commit**

Stage: `assets/js/checkout-different-address.js`, `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/different-address/block.json`
Message: `feat(checkout): hide and mirror Billing when it matches shipping`

### Task 5: Editor notices and warnings

**Files:**
- Modify: `packages/blocks/src/different-address/edit.tsx`

**Interfaces:**
- Consumes:
  - `differentAddressEditorData` (Task 2).
  - `select( blockEditorStore )`: `getBlocksByName`, `getClientIdsOfDescendants`, `getBlockName`, `getBlockAttributes`, typed as in `field-value/edit.tsx` (`BlockEditorSelectors`).
- Produces: UI only.
  - `InspectorControls` → `PanelBody` "Main address":
    - Text: "Billing is the main address." or "Shipping is the main address." In `billing_only`: "Shipping is forced to the billing address."
    - An `ExternalLink` to `settingsUrl`, reading "Change in WooCommerce settings".
  - Canvas `Notice` (`status="warning"`, `isDismissible={ false }`):
    - In `billing_only`: "WooCommerce ships to the billing address only, so this block prints nothing."
    - Otherwise, when `getBlocksByName( 'yaycheckout/shipping' )` is empty: "This layout has no Shipping section, so this block prints nothing."
  - Sidebar `Notice` in shipping mode, one line per Field block inside Billing:
    - When its key is in `COPIED_KEYS` and no `shipping_<key>` Field exists in Shipping: "%s will be saved empty when billing is the same as shipping."
    - When its key isn't an address key (e.g. `billing_email`): "%s is hidden when billing is the same as shipping; move it out of Billing."
    - `%s` is the Field's label, or its key when the label is empty.
  - The editor never hides Billing or Shipping.

- [ ] **Step 1: Add the panel, notices and warnings as in Interfaces**

Mirror PHP's `COPIED_KEYS` as a `const` with a comment naming `CheckoutDifferentAddress::COPIED_KEYS`, per docs/conventions.md on TypeScript copies of PHP.

- [ ] **Step 2: Build**

Run: `pnpm build:blocks`
Expected: success.

- [ ] **Step 3: Verify each notice in the editor**

On the Test layout:
- Shipping mode, with `shipping_phone` removed from Shipping: the sidebar warns "Phone will be saved empty when billing is the same as shipping."
- Add a Field `billing_email` inside Billing: a second warning names it.
- Undo both.
- Set `billing_only`: the canvas notice shows.
- Billing mode, with Shipping removed: the "no Shipping section" notice shows. Undo.
- Each mode's panel text matches, and the link opens Shipping options.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/different-address/edit.tsx`
Message: `feat(blocks): explain the main address and warn about missing shipping fields`

### Task 6: Default template, docs and end-to-end check

**Files:**
- Modify: `templates/layouts/classic-two-column/markup.php` (insert `<!-- wp:yaycheckout/different-address /-->` and a blank line between line 113 `<!-- /wp:yaycheckout/billing -->` and line 115 `<!-- wp:yaycheckout/shipping … -->`)
- Modify: `packages/blocks/src/checkout/edit.tsx` (`defaultTemplate()`: import `../different-address/block.json` as `differentAddressMeta`, and add `[ differentAddressMeta.name ]` between Billing and Shipping)
- Modify: `docs/architecture.md:63`. Replace "The Shipping block prints no `ship_to_different_address` checkbox, so WooCommerce copies the billing address into shipping." with a short account of:
  - the mode taken from `woocommerce_ship_to_destination`;
  - WooCommerce's checkbox (visible in billing mode, hidden and checked otherwise);
  - `yaycheckout_same_billing` and `Renderer\CheckoutDifferentAddress`;
  - the no-block and `billing_only` cases.

**Interfaces:**
- Consumes: the block from Task 2.
- Produces: nothing new.

- [ ] **Step 1: Make the three edits**

- [ ] **Step 2: Build, lint and run the suite**

Run: `pnpm build:blocks`, `composer lint -- templates/layouts/classic-two-column/markup.php`, `composer test`
Expected:
- the build succeeds;
- phpcs reports no errors, or only those `dev/edd` already reports for that file;
- all tests pass, including `LayoutTemplateCatalogueTest`.

- [ ] **Step 3: End-to-end verification (spec criterion 16)**

On Twenty Twenty-Five, with a physical product in the cart, create a new layout from the default template. It shows Different address between Billing and Shipping. Activate it, then:

- **`billing`:** the box is unticked and Shipping is hidden. Tick it, enter a different shipping postcode, and see rates refresh. Place the order: the admin order shows two addresses.
- **`shipping`:** the box is ticked and Billing is hidden. Fill in shipping only and place the order: the billing address equals the shipping address, and the billing email is the one typed. Untick it, fill Billing, and place a second order: the addresses differ.
- **`billing_only`:** neither the box nor Shipping shows, and the order's addresses match.

Repeat the `shipping` run on Storefront (classic theme). Then:
- switch back to Twenty Twenty-Five;
- reset "Shipping destination" to the billing default;
- restore the Test layout from the chosen revision;
- re-activate the previously active layout.

Expected: every check above holds on both themes.

- [ ] **Step 4: Commit**

Stage: `templates/layouts/classic-two-column/markup.php`, `packages/blocks/src/checkout/edit.tsx`, `docs/architecture.md`
Message: `feat(templates): add Different address to the default layout`
