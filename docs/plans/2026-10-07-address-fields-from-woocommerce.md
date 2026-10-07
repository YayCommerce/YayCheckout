# Address Fields From WooCommerce Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec: the requirements below are the brief, and the four backlog items they name hold the evidence.

**Status:** done 2026-10-07
**Goal:** address Fields render from WooCommerce's own definitions with the customer's value, the Shipping block posts a real shipping address, required flags respect WooCommerce's country locale, and the "optional" fallback has one PHP owner.
**Spec:** none, planned from the request
**Architecture:**
- A Field block takes its Key's entry from `WC()->checkout()->get_checkout_fields( $fieldset )` and lays the layout's label, placeholder and required flag over it, as the backlog item's Suggested fix says, then passes `WC()->checkout()->get_value( $key )`. WooCommerce owns classes, value and locale; the layout owns order, labels and the required flag. Chosen over restoring the hand-copied classes from before `d617a00`, which every WooCommerce change would have to be copied into again.
- The Shipping block gets back what `d617a00` removed: the checkbox copied from WooCommerce's `form-shipping.php`, `div.shipping_address`, `woocommerce-shipping-fields`, and the early return. The `shipToDifferent` attribute goes: WooCommerce's `woocommerce_ship_to_destination` option and filter decide whether the box starts ticked.
- `RequiredFieldOverlay::apply()` stays pure and takes each fieldset's country locale as an argument; a locale-hidden field is never required. The browser script skips a row address-i18n.js has hidden.

**Touches:**
- Blocks: `packages/blocks/src/field/render.php`, `packages/blocks/src/email/render.php`, `packages/blocks/src/shipping/{render.php,block.json,edit.tsx}`, `packages/blocks/src/shared/address-section-edit.tsx` (shared: Billing and Shipping edit), `packages/blocks/src/shared/editor-data.ts` (shared: every block that reads `editorData`), `packages/blocks/src/shared/field-preview.tsx` (shared: Field, Email, Extra field edit), `packages/blocks/src/shared/required-indicator-controls.tsx` (shared: Field, Email, Extra field inspector)
- PHP: `includes/Services/RequiredFieldOverlay.php`, `includes/Renderer/CheckoutRequiredFields.php`, `includes/Services/RequiredIndicator.php` (shared: `CheckoutRequiredFields`, field/email/extra-field `render.php`), `includes/Blocks/BlockRegistry.php`
- JS: `assets/js/checkout-field-requirements.js`
- Docs: `docs/architecture.md` (Fields and Validation bullets), `docs/conventions.md` (In code bullet)
- Backlog: tick boxes only, never delete files (the user closes them)

**Tests:**
- `tests/Integration/Services/RequiredFieldOverlayTest.php`, Integration suite: a locale-hidden field stays optional though the layout marks it required; a shown field follows the layout flag; `billing_email` is always required.
- `tests/Integration/Blocks/AddressRenderTest.php`, Integration suite: `billing_postcode` renders with `address-field` and `billing_country` with `update_totals_on_change` and the checkout value; the Shipping block prints `ship_to_different_address` and `div.shipping_address`, and prints nothing for a virtual-only cart.

## Requirements

- **Goal:** on a YayCheckout layout, a shopper sees their saved address, totals refresh as they type, a different shipping address is quoted and saved, and countries whose locale hides a field (UAE postcode, Singapore state) can order.
- **Out of scope:**
  - Checkout form hooks and account fields (PR 3, `docs/backlog/2026-10-05-account-fields-and-checkout-form-hooks-never-render.md`).
  - The Field block's width attribute and layout CSS. WooCommerce's classes, `form-row-first/last/wide` included, pass through unchanged.
  - Making a field the layout omits required again. Omitted fields stay optional; the merchant is told instead (Task 4).
  - The publish-message wording in the rule-copies item (settled by PR 1).
- **Backlog items closed:** `2026-10-05-address-fields-never-refresh-totals-or-prefill.md`, `2026-10-05-the-shipping-block-discards-the-shipping-address.md`, `2026-10-05-required-fields-ignore-woocommerces-country-rules.md`, and the "optional" box of `2026-10-05-rule-copies-between-php-and-javascript-name-no-owner.md`. Their `## Done when` boxes are the acceptance criteria below.
- **Acceptance criteria:**
  1. A `billing_postcode` Field renders with `address-field` among its `.form-row` classes; a country Field with `update_totals_on_change`.
  2. Field and Email blocks pass `WC()->checkout()->get_value()` for their Key; a logged-in customer with a saved billing address sees it filled in on the default layout.
  3. As a guest on the default layout, the first `update_order_review` request posts a non-empty `country`, and changing the billing postcode to another shipping zone updates the rates without a reload.
  4. The Shipping block prints `#ship-to-different-address` with an input named `ship_to_different_address`, wraps its inner blocks in `div.shipping_address`, carries `woocommerce-shipping-fields`, and prints nothing when `WC()->cart->needs_shipping_address()` is false or `wc_ship_to_billing_address_only()` is true. `shipToDifferent` and its toggle are gone.
  5. A guest who ticks "Ship to a different address?" and enters an address in another zone sees that zone's rates; the placed order shows that shipping address in wp-admin; changing the shipping country to United States fills `#shipping_state`.
  6. `RequiredFieldOverlay::apply()` leaves `required` false on a field the locale marks `hidden`; the browser script adds no `validate-required` to a row address-i18n.js hid. A guest picking United Arab Emirates orders without a postcode; Singapore without a state.
  7. With Phone set to Required in Customizer → WooCommerce → Checkout, the layout editor names Phone when the layout has no `billing_phone` Field.
  8. `RequiredIndicator::span()`, the browser script, `field-preview.tsx` and the inspector placeholder show the same translated "optional", taken from `RequiredIndicator::optional_text()`.
  9. End to end: on the default layout, a guest in the United Arab Emirates ticks "Ship to a different address?", enters a United States shipping address, sees US rates, and places the order without a billing postcode; the order in wp-admin shows the US shipping address.
- **Decided with the user (2026-10-07):**
  - Field render: lay the layout over WooCommerce's definition (not just restore the classes).
  - `shipToDifferent`: delete the attribute and its toggle.
  - The Phone notice is in this PR.
  - Tests: both listed above.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit `assets/dist/`; edit `packages/blocks/src/<block>/render.php` and run `pnpm build:blocks`.
- Free names Pro only in `boot_pro()`; nothing here touches `includes/Pro/`.
- Branch `alan/fix/address-fields-from-woocommerce` (created from `origin/dev/edd` 583bb21, no upstream). Commit messages: Conventional Commits, ending in `Co-Authored-By: Claude <model> <noreply@anthropic.com>`. Commit only when the user asks; stage files by name. `composer.json` carries an unrelated local change from `run.sh dev`: never stage it.
- Run Node 22 (`nvm use`) before pnpm.

## Base branch state

- Before Task 1, run `composer lint`, `composer test` and `pnpm build:blocks` once and note the result: a check already failing there stays out of scope, and no task may add a failure.
- `composer test` today runs only `tests/Integration/Blocks/PlaceOrderRenderTest.php` (PHP 8.4 via `php@8.4`, Local's per-site socket for MySQL, see `docs/known-issues.md`).

## Review focus

- WooCommerce's `form-row-first`/`form-row-last` now reach the Field's `<p>` (first/last name, and per-country locales): inside the block's own `div.form-row` they may float at half width. Task 1 Step 6 checks the default layout's widths.
- A Field whose Key WooCommerce doesn't define (custom key, or one the Customizer hid and the overlay restored without classes): renders as today, with the layout's args only. Task 1 Step 6.
- The editor preview and REST render (no cart): Field and Shipping render plainly, no checkbox, no `get_value()`. Task 1 Step 6, Task 2 Step 5.
- A shipping country whose locale hides state while the shipping section is collapsed: ticking the box later must still show asterisks on visible fields. The browser script checks the row's own inline `display`, not visibility. Task 3 Step 7.
- A logged-in customer whose saved billing country is the UAE: the page loads with postcode hidden and not required. Task 3 Step 7.

---

### Task 1: Field and Email render from WooCommerce's definitions

**Files:**
- Modify: `packages/blocks/src/field/render.php`
- Modify: `packages/blocks/src/email/render.php`
- Test: `tests/Integration/Blocks/AddressRenderTest.php`

**Interfaces:**
- Produces: nothing other tasks call. Task 2 adds Shipping cases to the same test file.

- [ ] **Step 1: Write the failing tests**

```php
namespace YayCheckout\Tests\Integration\Blocks;

/** Address blocks render WooCommerce's own fields and shipping contract. */
class AddressRenderTest extends \WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
    }

    public function test_postcode_field_carries_woocommerces_classes_and_value(): void {
        add_filter( 'woocommerce_checkout_get_value', static fn( $v, $k ) => 'billing_postcode' === $k ? '10115' : $v, 10, 2 );
        $html = do_blocks( '<!-- wp:yaycheckout/field {"fieldKey":"billing_postcode","label":"Zip","required":true} /-->' );

        $this->assertMatchesRegularExpression( '#<p class="[^"]*\baddress-field\b[^"]*" id="billing_postcode_field"#', $html );
        $this->assertStringContainsString( 'value="10115"', $html );
        $this->assertStringContainsString( 'Zip', $html );
    }

    public function test_country_field_refreshes_totals(): void {
        $html = do_blocks( '<!-- wp:yaycheckout/field {"fieldKey":"billing_country","type":"country","label":"Country"} /-->' );

        $this->assertMatchesRegularExpression( '#<p class="[^"]*\bupdate_totals_on_change\b[^"]*" id="billing_country_field"#', $html );
    }

    public function test_email_block_passes_the_checkout_value(): void {
        add_filter( 'woocommerce_checkout_get_value', static fn( $v, $k ) => 'billing_email' === $k ? 'a@example.com' : $v, 10, 2 );
        $html = do_blocks( '<!-- wp:yaycheckout/email /-->' );

        $this->assertStringContainsString( 'value="a@example.com"', $html );
    }
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `composer test -- --filter AddressRenderTest`
Expected: FAIL on `address-field`, `update_totals_on_change` and both `value=` assertions.

- [ ] **Step 3: Field render from WooCommerce's definition**

In `packages/blocks/src/field/render.php`, inside the `woocommerce_form_field` branch, when `WC()->cart instanceof WC_Cart` (storefront only, as in Shipping's render) and the Key matches `/^(billing|shipping)_/`:
- `$definition = WC()->checkout()->get_checkout_fields( $fieldset )[ $key ] ?? []`, `$fieldset` being the prefix without `_`.
- args = `$definition`, then the layout's `label`, `required`, `return => false`, and `placeholder` only when non-empty. Everything else, `class` and `type` included, stays WooCommerce's.
- value = `WC()->checkout()->get_value( $key )`, as the third argument.

Without a cart or a definition, call `woocommerce_form_field()` with today's args and no value. Update the docblock: WooCommerce owns classes, value and locale; the layout owns label, placeholder and required.

- [ ] **Step 4: Email passes its value**

In `packages/blocks/src/email/render.php`, pass `WC()->checkout()->get_value( 'billing_email' )` as the third argument when `WC()->cart instanceof WC_Cart`, else `null`.

- [ ] **Step 5: Run the tests to confirm they pass**

Run: `pnpm build:blocks && composer test -- --filter AddressRenderTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Verify on the storefront and in the editor**

Run: `composer lint -- packages/blocks/src/field/render.php packages/blocks/src/email/render.php` → no errors.
Open `https://yaycheckout.test/checkout/` as a guest with a physical product in the cart, on the default layout:
- DevTools network: the first `?wc-ajax=update_order_review` request's `country` is non-empty.
- Change billing postcode to one in another shipping zone: Order review rates change without a reload.
Log in as a customer with a saved billing address: the fields are filled in.
First and last name render at the same widths as before the change (compare with a screenshot from `dev/edd`).
Open the default layout in the YayCheckout editor: Field previews still render, no PHP notice in `wp-content/debug.log`.

- [ ] **Step 7: Commit**

Stage: `packages/blocks/src/field/render.php`, `packages/blocks/src/email/render.php`, `tests/Integration/Blocks/AddressRenderTest.php`
Message: `fix(blocks): render address fields from WooCommerce's definitions`

### Task 2: Shipping block posts a real shipping address

**Files:**
- Modify: `packages/blocks/src/shipping/render.php`, `packages/blocks/src/shipping/block.json`, `packages/blocks/src/shipping/edit.tsx`
- Modify: `packages/blocks/src/shared/address-section-edit.tsx` (Shipping toggle only)
- Test: `tests/Integration/Blocks/AddressRenderTest.php`

**Interfaces:**
- Consumes: the Task 1 test class.
- Produces: `div.woocommerce-shipping-fields > #ship-to-different-address + div.shipping_address`, the contract `checkout.js`, `country-select.js` and `address-i18n.js` read.

- [ ] **Step 1: Write the failing tests**

Add to `AddressRenderTest`. A helper `add_product_to_cart( bool $virtual ): void` creates and saves a `WC_Product_Simple` (price 10, `set_virtual( $virtual )`) and adds it to `WC()->cart`. `needs_shipping_address()` needs shipping enabled with at least one method: in the helper's first call, set `woocommerce_ship_to_countries` to `''` and create a `WC_Shipping_Zone` with a `flat_rate` method.

```php
public function test_shipping_prints_woocommerces_ship_to_different_address_contract(): void {
    $this->add_product_to_cart( false );
    $html = do_blocks( '<!-- wp:yaycheckout/shipping --><!-- wp:yaycheckout/field {"fieldKey":"shipping_city","label":"City"} /--><!-- /wp:yaycheckout/shipping -->' );

    $this->assertMatchesRegularExpression( '#class="[^"]*\bwoocommerce-shipping-fields\b#', $html );
    $this->assertMatchesRegularExpression( '#id="ship-to-different-address".*name="ship_to_different_address"#s', $html );
    $this->assertMatchesRegularExpression( '#<div class="shipping_address">.*id="shipping_city_field"#s', $html );
}

public function test_shipping_prints_nothing_for_a_virtual_cart(): void {
    $this->add_product_to_cart( true );
    $this->assertSame( '', trim( do_blocks( '<!-- wp:yaycheckout/shipping /-->' ) ) );
}

public function test_shipping_prints_nothing_when_shipping_goes_to_billing_only(): void {
    update_option( 'woocommerce_ship_to_destination', 'billing_only' );
    $this->add_product_to_cart( false );
    $this->assertSame( '', trim( do_blocks( '<!-- wp:yaycheckout/shipping /-->' ) ) );
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `composer test -- --filter AddressRenderTest`
Expected: the three new tests FAIL; Task 1's pass.

- [ ] **Step 3: Restore the shipping render**

In `packages/blocks/src/shipping/render.php`, restore what `d617a00` removed (`git show d617a00 -- packages/blocks/src/shipping/render.php`): `$yaycheckout_has_cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart`; return early when it has a cart and `! WC()->cart->needs_shipping_address() || wc_ship_to_billing_address_only()`; wrapper class `yaycheckout-shipping woocommerce-shipping-fields`; with a cart, the `h3#ship-to-different-address` checkbox copied from WooCommerce's `templates/checkout/form-shipping.php` (filter `woocommerce_ship_to_different_address_checked`, string `Ship to a different address?` in the `woocommerce` domain, with the phpcs ignore from `d617a00`); then the title and `$content` inside `div.shipping_address`. Fix the docblock to describe this.

- [ ] **Step 4: Delete `shipToDifferent`**

Remove the attribute from `packages/blocks/src/shipping/block.json`, the field from `ShippingAttributes` in `packages/blocks/src/shipping/edit.tsx`, and from `AddressSectionAttributes` plus the `section === 'shipping'` toggle in `packages/blocks/src/shared/address-section-edit.tsx`. Saved layouts that carry the attribute keep validating: it is a dynamic block and WordPress ignores unknown attributes.

- [ ] **Step 5: Run the tests and verify**

Run: `pnpm build:blocks && composer test -- --filter AddressRenderTest`
Expected: PASS, 6 tests.
Run: `composer lint -- packages/blocks/src/shipping/render.php` → no errors; `git grep -n shipToDifferent -- packages` → no output.
Open the default layout in the editor: the Shipping block shows its fields (no cart, plain render) and its inspector has no "Ship to a different address" toggle.
On `https://yaycheckout.test/checkout/` as a guest with a physical product, default layout:
- The shipping fields are collapsed under "Ship to a different address?". Tick it, set shipping country United States: `#shipping_state` lists US states.
- Enter a shipping address in another zone: Order review shows that zone's rates.
- Place the order: wp-admin → WooCommerce → Orders shows the shipping address typed, not the billing one.
With only a virtual product in the cart, the Shipping section is absent.

- [ ] **Step 6: Commit**

Stage: `packages/blocks/src/shipping/render.php`, `packages/blocks/src/shipping/block.json`, `packages/blocks/src/shipping/edit.tsx`, `packages/blocks/src/shared/address-section-edit.tsx`, `tests/Integration/Blocks/AddressRenderTest.php`
Message: `fix(blocks): restore the shipping block's ship-to-different-address contract`

### Task 3: Required flags respect WooCommerce's country locale

**Files:**
- Modify: `includes/Services/RequiredFieldOverlay.php`
- Modify: `includes/Renderer/CheckoutRequiredFields.php`
- Modify: `assets/js/checkout-field-requirements.js`
- Test: `tests/Integration/Services/RequiredFieldOverlayTest.php`

**Interfaces:**
- Produces: `RequiredFieldOverlay::apply( array $fields, array $layout_fields, array $locales = [] ): array`, where `$locales` is `[ 'billing' => array, 'shipping' => array ]`, each the fieldset country's entry from `WC_Countries::get_country_locale()`, keyed by unprefixed field name (`'postcode' => [ 'required' => false, 'hidden' => true ]`).

- [ ] **Step 1: Write the failing tests**

```php
namespace YayCheckout\Tests\Integration\Services;

use YayCheckout\Services\RequiredFieldOverlay;

/** The layout decides required flags, except where WooCommerce's locale hides the field. */
class RequiredFieldOverlayTest extends \WP_UnitTestCase {

    private function fields(): array {
        return [
            'billing'  => [
                'billing_postcode' => [ 'required' => false ],
                'billing_state'    => [ 'required' => false ],
                'billing_city'     => [ 'required' => true ],
                'billing_email'    => [ 'required' => false, 'type' => 'email' ],
            ],
            'shipping' => [ 'shipping_state' => [ 'required' => false ] ],
        ];
    }

    private function layout(): array {
        return [
            'billing_postcode' => [ 'required' => true ],
            'billing_state'    => [ 'required' => true ],
            'billing_city'     => [ 'required' => false ],
            'shipping_state'   => [ 'required' => true ],
        ];
    }

    public function test_a_locale_hidden_field_stays_optional(): void {
        $locales = WC()->countries->get_country_locale();
        $fields  = RequiredFieldOverlay::apply( $this->fields(), $this->layout(), [ 'billing' => $locales['AE'], 'shipping' => $locales['SG'] ] );

        $this->assertFalse( $fields['billing']['billing_postcode']['required'] );
        $this->assertFalse( $fields['shipping']['shipping_state']['required'] );
    }

    public function test_a_shown_field_follows_the_layout(): void {
        $fields = RequiredFieldOverlay::apply( $this->fields(), $this->layout(), [ 'billing' => WC()->countries->get_country_locale()['AE'] ] );

        $this->assertTrue( $fields['billing']['billing_state']['required'] );
        $this->assertFalse( $fields['billing']['billing_city']['required'] );
    }

    public function test_billing_email_is_always_required(): void {
        $fields = RequiredFieldOverlay::apply( $this->fields(), [] );

        $this->assertTrue( $fields['billing']['billing_email']['required'] );
    }
}
```

`$locales['AE']['state']` is `required => false` without `hidden`, so `billing_state` must follow the layout (true): only `hidden` overrides.

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `composer test -- --filter RequiredFieldOverlayTest`
Expected: `test_a_locale_hidden_field_stays_optional` FAILS (required is true); the other two PASS (they guard today's behaviour).

- [ ] **Step 3: Implement the `$locales` argument**

In `RequiredFieldOverlay::apply()`, a field whose unprefixed name has `! empty( $locales[ $fieldset ][ $name ]['hidden'] )` gets `required => false`; every other field keeps `is_required()`. Restored missing fields follow the same rule. Update the docblock: the locale's `hidden` wins over the layout; omitted fields stay optional.

- [ ] **Step 4: Pass the locales**

In `CheckoutRequiredFields::filter_fields()`, build `$locales` from `WC()->countries->get_country_locale()` and `WC()->checkout()->get_value( 'billing_country' )` / `( 'shipping_country' )`; a missing or empty country gives `[]` for that fieldset. Pass it to `apply()`.

- [ ] **Step 5: Skip hidden rows in the browser script**

In `assets/js/checkout-field-requirements.js` `applyRequired()`, skip a field row whose own inline style is `display: none` (`field[0].style.display === 'none'`, set by address-i18n.js's `field.hide()`). Do not test `:hidden`: rows inside a collapsed `div.shipping_address` are hidden too and must still get their marks.

- [ ] **Step 6: Run the tests**

Run: `composer test -- --filter RequiredFieldOverlayTest` → PASS, 3 tests.
Run: `composer lint -- includes/Services/RequiredFieldOverlay.php includes/Renderer/CheckoutRequiredFields.php` → no errors.

- [ ] **Step 7: Verify on the storefront**

On `https://yaycheckout.test/checkout/` as a guest, default layout:
- Billing country United Arab Emirates: postcode row hidden, no `validate-required` on `#billing_postcode_field`; place the order without a postcode → order received.
- Billing country Singapore: state hidden; place the order without a state → order received.
- Billing country United States: postcode and state show `*` and are required.
- Tick "Ship to a different address?" after picking UAE billing: the visible shipping fields carry their `*`.
Log in as a customer whose saved billing country is AE: on load, postcode hidden and not required.

- [ ] **Step 8: Commit**

Stage: `includes/Services/RequiredFieldOverlay.php`, `includes/Renderer/CheckoutRequiredFields.php`, `assets/js/checkout-field-requirements.js`, `tests/Integration/Services/RequiredFieldOverlayTest.php`
Message: `fix(checkout): never require a field WooCommerce's locale hides`

### Task 4: The editor names WooCommerce-required billing fields the layout leaves out

**Files:**
- Modify: `includes/Blocks/BlockRegistry.php`
- Modify: `packages/blocks/src/shared/editor-data.ts`
- Modify: `packages/blocks/src/shared/address-section-edit.tsx`

**Interfaces:**
- Produces: `editorData.requiredByWooCommerce: Array< { key: string; label: string } >`, from `BlockRegistry::print_editor_data()` on `enqueue_block_editor_assets` (moved out of `register_blocks()` on `init`, with the existing keys, so the WooCommerce lookup runs only where a block editor loads). Task 5 adds a second key to the same object.

- [ ] **Step 1: PHP supplies the list**

Move the `$editor_data` inline script from `BlockRegistry::register_blocks()` into a new `public function print_editor_data(): void` hooked on `enqueue_block_editor_assets`, and add `'requiredByWooCommerce'` to `$editor_data`: each entry of `WC()->countries->get_address_fields( WC()->countries->get_base_country(), 'billing_' )` with `! empty( $field['required'] )`, as `[ 'key' => $key, 'label' => $field['label'] ]`. `[]` when `WC()` is not loaded. These are WooCommerce's flags before `RequiredFieldOverlay` (which filters `woocommerce_checkout_fields`, not `woocommerce_billing_fields`), so the Customizer's Phone setting shows through. Add the field to `EditorData` in `editor-data.ts`, default `[]`.

- [ ] **Step 2: Billing shows the notice**

In `AddressSectionEdit`, for `section === 'billing'`, collect the layout's keys with `useSelect` over `core/block-editor`: every `yaycheckout/field` block's `fieldKey` at any depth, plus `billing_email` when a `yaycheckout/email` block exists. Walk the document flat with `getClientIdsWithDescendants()`, as `extraFields()` in `shared/extra-field-keys.ts` does, mapping the Email address block to `billing_email` with a comment naming `LayoutMarkupReader::fields()` as owner. `field/edit.tsx` keeps its own `collectFieldKeys()`. When some `requiredByWooCommerce` keys are missing, render on the canvas, above `children`, a non-dismissible `<Notice status="warning">`: `WooCommerce requires %s, but this layout leaves it out, so checkout won't ask for it.` with the labels joined by `, ` (`sprintf` + `__`, domain `yaycheckout`). Billing's `render.php` is untouched, so the storefront never shows it.

- [ ] **Step 3: Verify**

Run: `pnpm build:blocks` → success; `composer lint -- includes/Blocks/BlockRegistry.php` → no errors.
In wp-admin → Appearance → Customize → WooCommerce → Checkout, set Phone field to Required. Open a layout in the editor and delete its `billing_phone` Field: the Billing block shows "WooCommerce requires Phone, but this layout leaves it out…". Undo the delete: the notice goes. Set Phone to Optional: no notice either way.

- [ ] **Step 4: Commit**

Stage: `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/shared/editor-data.ts`, `packages/blocks/src/shared/address-section-edit.tsx`
Message: `feat(blocks): name WooCommerce-required billing fields the layout leaves out`

### Task 5: One owner for the "optional" fallback, and docs

**Files:**
- Modify: `includes/Services/RequiredIndicator.php`
- Modify: `assets/js/checkout-field-requirements.js`
- Modify: `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/shared/editor-data.ts`
- Modify: `packages/blocks/src/shared/field-preview.tsx`, `packages/blocks/src/shared/required-indicator-controls.tsx`
- Modify: `docs/architecture.md`, `docs/conventions.md`
- Modify: backlog files (tick boxes only)

**Interfaces:**
- Consumes: `editorData` from Task 4.
- Produces: `RequiredIndicator::optional_text(): string`; `editorData.optionalText: string`; `script_payload()` entries whose `optionalText` is never empty.

- [ ] **Step 1: PHP owns the text**

Add `public static function optional_text(): string` to `RequiredIndicator`, returning `__( 'optional', 'woocommerce' )`: the string WooCommerce's own fields and `wc_address_i18n_params.i18n_optional_text` use, so the storefront reads the same in every language (phpcs ignore `WordPress.WP.I18n.TextDomainMismatch`, with that reason). `span()` uses it as its fallback. `script_payload()` fills each entry's `optionalText` with it when the layout's is empty.

- [ ] **Step 2: Copies read the owner**

- `assets/js/checkout-field-requirements.js`: delete `fallbackOptional` and the `wc_address_i18n_params` read; use `config.optionalText`. Its header keeps naming `RequiredIndicator::span()` for the markup and adds `RequiredIndicator::optional_text()` for the text.
- `BlockRegistry::print_editor_data()`: `'optionalText' => RequiredIndicator::optional_text()` in `$editor_data`; `EditorData` gains `optionalText: string`, default `'optional'`.
- `field-preview.tsx`: `optionalText || editorData.optionalText`. `required-indicator-controls.tsx`: `placeholder={ editorData.optionalText }`. Each names `RequiredIndicator::optional_text()` as owner in a one-line comment.

- [ ] **Step 3: Docs**

- `docs/architecture.md` Fields bullet: Field blocks render WooCommerce's definition for their Key with the checkout value; Shipping prints the ship-to-different-address checkbox. Keep the form-hooks sentence (PR 3).
- `docs/architecture.md` Validation bullet: replace "over WooCommerce's country rules too" with: except a field the shopper's country locale hides, which is never required; `checkout-field-requirements.js` skips rows address-i18n.js hid. Add that the editor names WooCommerce-required billing fields the layout leaves out.
- `docs/conventions.md` In code bullet: the script now reads the optional text from the payload; it repeats only `span()`'s required mark and markup.

- [ ] **Step 4: Verify**

Run: `pnpm build:blocks` → success; `composer lint` → no errors; `composer test` → PASS, all tests (PlaceOrder, AddressRender, RequiredFieldOverlay).
Switch the site language to Tiếng Việt (Settings → General), with WooCommerce's translations installed: an optional Field without its own text shows the same translated word on the storefront on load, after a country change, in the editor preview and as the inspector placeholder. Switch back to English.

- [ ] **Step 5: Tick the backlog**

Tick every box under `## Done when` in `2026-10-05-address-fields-never-refresh-totals-or-prefill.md`, `2026-10-05-the-shipping-block-discards-the-shipping-address.md`, `2026-10-05-required-fields-ignore-woocommerces-country-rules.md`, and the "optional" box in `2026-10-05-rule-copies-between-php-and-javascript-name-no-owner.md`, each with the file or check that proves it. Do not delete the files.

- [ ] **Step 6: End-to-end check**

On `https://yaycheckout.test/checkout/` as a guest, default layout, physical product in cart: billing country United Arab Emirates (no postcode), tick "Ship to a different address?", enter a United States shipping address. Expected: Order review shows US rates; the order places; wp-admin shows the US shipping address and an AE billing address without postcode.

- [ ] **Step 7: Commit**

Stage: `includes/Services/RequiredIndicator.php`, `assets/js/checkout-field-requirements.js`, `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/shared/editor-data.ts`, `packages/blocks/src/shared/field-preview.tsx`, `packages/blocks/src/shared/required-indicator-controls.tsx`, `docs/architecture.md`, `docs/conventions.md`, the four backlog files
Message: `fix(checkout): give the optional label one translated owner`
