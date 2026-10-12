# Required overlay ignores locale-hidden fields Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the backlog item before starting.

**Status:** done 2026-10-11
**Goal:** A field that WooCommerce's country locale hides is never required at checkout, even when the layout marks it required. Every visible field keeps the layout's required setting.
**Spec:** none, planned from `docs/backlog/2026-10-07-required-overlay-hidden-locale-field.md`
**Architecture:** The server overlay `RequiredFieldOverlay::apply()` gets a list of locale-hidden keys and gives those keys `required => false`. It also does this for any field whose WooCommerce definition has `hidden === true`. `CheckoutRequiredFields::filter_fields()` builds the list from the country WooCommerce built the fields for. On the client, `applyRequired()` skips a row that `address-i18n.js` hid. That is the same row-display test `checkout-hide-when-empty.js` already uses.
**Touches:** `includes/Services/RequiredFieldOverlay.php`, `includes/Renderer/CheckoutFieldDefaults.php` (shared: also called by `packages/blocks/src/field/render.php` and `packages/blocks/src/field-value/render.php`; the new parameter is optional, so they don't change), `includes/Renderer/CheckoutRequiredFields.php`, `assets/js/checkout-field-requirements.js`
**Tests:** `tests/Integration/Renderer/CheckoutRequiredFieldsHiddenTest.php`, suite Integration. It guards that a locale-hidden field the layout marks required is not required for VN, and stays required for US.

## Requirements

- **Goal:** a shopper in a country whose locale hides a field can check out, whatever the layout says about that field.
- **Out of scope:**
  - `RequiredIndicator::script_payload()` stays country-agnostic.
  - `apply_order_note()` doesn't change.
  - No change to how a visible field's `required` is decided.
- **Acceptance criteria:**
  1. `RequiredFieldOverlay::apply()` returns `required => false` for a field whose WooCommerce definition has `hidden => true`, or whose key is in `$hidden_keys`, even when the layout marks it required.
  2. `applyRequired()` adds no `validate-required` class to a row that `address-i18n.js` hid.
  3. End to end: on a layout with a required State block, checking out with billing country VN creates the order with no State error, and with country US a blank State is still refused.
- **Decided with the user:**
  - Tests: the hidden-field test in `**Tests:**` is accepted.
  - The layout keeps deciding `required` for every visible field. A field is exempt only when the locale hides it. Hidden is detected from WooCommerce's `hidden` flag or a locale lookup, so the fix works whether or not the installed WooCommerce keeps the flag in `$fields`.

## Global constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- WooCommerce requires at least 10.0.0, so don't rely on WooCommerce keeping `hidden` in `$fields`.
- Never hand-edit `vendor/`, `vendor-prefixed/`, `assets/dist/` or `release/`. `assets/js/*.js` is a tracked source file.
- `RequiredFieldOverlay` stays pure: no hooks and no WooCommerce calls.
- Branch from `dev/edd` as `<name>/fix/required-hidden-locale-field`.

## Review focus

- **Switching country:** VN → US must make State required again, both on the client (applyRequired re-runs on `country_to_state_changing`) and on the server (the hidden list is rebuilt per request). Covered by Task 2 Step 2 and Task 3.
- **A row hidden by its container**, such as a collapsed step or a closed shipping section, must keep `validate-required`. Read the row's own `css('display')`, not `:hidden`. Covered by Task 2 Step 2.
- **Shipping fieldset:** shipping hidden keys use `shipping_country`, not `billing_country`. Covered by the Task 1 test.

---

### Task 1: Server overlay ignores locale-hidden fields

**Files:**
- Modify: `includes/Services/RequiredFieldOverlay.php` (`apply()`)
- Modify: `includes/Renderer/CheckoutFieldDefaults.php` (`hidden_for_country()`)
- Modify: `includes/Renderer/CheckoutRequiredFields.php` (`filter_fields()`)
- Test: `tests/Integration/Renderer/CheckoutRequiredFieldsHiddenTest.php`

**Interfaces:**
- Produces: `RequiredFieldOverlay::apply( array $fields, array $layout_fields, array $hidden_keys = [] ): array`. `$hidden_keys` is a list of full WooCommerce keys, such as `billing_state`.
- Produces: `CheckoutFieldDefaults::hidden_for_country( string $key, string $country = '' ): bool`. When `$country` is `''`, it reads the customer's country as it does today.

- [ ] **Step 1: Write the failing test** (pattern from `tests/Integration/Renderer/CheckoutDifferentAddressTest.php`)

```php
class CheckoutRequiredFieldsHiddenTest extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        LayoutFactory::serve( '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/billing --><!-- wp:yaycheckout/field {"fieldKey":"billing_state","label":"State","required":true} /--><!-- /wp:yaycheckout/billing --><!-- wp:yaycheckout/shipping --><!-- wp:yaycheckout/field {"fieldKey":"shipping_state","label":"State","required":true} /--><!-- /wp:yaycheckout/shipping --><!-- /wp:yaycheckout/checkout -->' );
    }
    public function tear_down(): void { $_POST = []; parent::tear_down(); }

    public function test_locale_hidden_state_is_not_required(): void {
        $_POST = [ 'billing_country' => 'VN', 'shipping_country' => 'VN' ];
        $fields = ( new \WC_Checkout() )->get_checkout_fields();
        $this->assertFalse( $fields['billing']['billing_state']['required'] );
        $this->assertFalse( $fields['shipping']['shipping_state']['required'] );
    }

    public function test_visible_state_keeps_layout_required(): void {
        $_POST = [ 'billing_country' => 'US', 'shipping_country' => 'VN' ];
        $fields = ( new \WC_Checkout() )->get_checkout_fields();
        $this->assertTrue( $fields['billing']['billing_state']['required'] );
        $this->assertFalse( $fields['shipping']['shipping_state']['required'] );
    }
}
```
VN and US must be among the store's selling and shipping countries. If the test DB limits them, set `woocommerce_allowed_countries` to `all` and `woocommerce_ship_to_countries` to `''` in `set_up()`.

- [ ] **Step 2: Run it to confirm it fails**

Run: `composer test -- --filter CheckoutRequiredFieldsHiddenTest`
Expected: FAIL. `test_locale_hidden_state_is_not_required` asserts false but gets true.

- [ ] **Step 3: Add the optional `$country` parameter to `CheckoutFieldDefaults::hidden_for_country( string $key, string $country = '' ): bool`**

A non-empty `$country` replaces the `self::value( '<fieldset>_country' )` lookup. Everything else stays the same, including the fallback to `default`. Update the docblock.

- [ ] **Step 4: Implement `RequiredFieldOverlay::apply( array $fields, array $layout_fields, array $hidden_keys = [] ): array`**

After `is_required()`, a field gets `required = false` when its key is in `$hidden_keys` (strict `in_array`) or `true === ( $field['hidden'] ?? false )`. This also applies to fields that `restore_missing_field()` adds back. Leave `is_required()` unchanged. Add one docblock line on why: the shopper can't fill a field the locale hides.

- [ ] **Step 5: Pass the hidden keys from `CheckoutRequiredFields::filter_fields()`**

For `billing` and `shipping`, take the country as WooCommerce does in `WC_Checkout::initialize_checkout_fields()`: `WC()->checkout()->get_value( "{$fieldset}_country" )`, or `WC()->countries->get_base_country()` when it is empty. Collect each key in `$fields[ $fieldset ]` for which `CheckoutFieldDefaults::hidden_for_country( $key, $country )` is true. Pass the list as `apply()`'s third argument.

- [ ] **Step 6: Run the test to confirm it passes, then lint**

Run: `composer test -- --filter CheckoutRequiredFieldsHiddenTest`. Expected: PASS, 2 tests.
Run: `composer lint -- includes/Services/RequiredFieldOverlay.php includes/Renderer/CheckoutFieldDefaults.php includes/Renderer/CheckoutRequiredFields.php tests/Integration/Renderer/CheckoutRequiredFieldsHiddenTest.php`. Expected: no errors.

- [ ] **Step 7: Commit**

Stage the four files above.
Message: `fix(checkout): don't require a field the country locale hides`

### Task 2: Client script skips rows address-i18n hid

**Files:**
- Modify: `assets/js/checkout-field-requirements.js` (`applyRequired()`)

**Interfaces:** none new.

- [ ] **Step 1: In `applyRequired()`, skip a required field whose row `field.css('display') === 'none'`**

Return before adding `validate-required` or the mark. `address-i18n.js` has already removed both from that row. Add a comment matching the one in `checkout-hide-when-empty.js`: read the row's own display, not `:hidden`, because a hidden container would make every row `:hidden`. The optional branch doesn't change.

- [ ] **Step 2: Verify in the storefront**

Open: checkout with a layout whose Billing has a State Field block marked required.
Action: pick billing country Vietnam, then United States, then Vietnam again. With a multi-step layout, also check a step that isn't shown yet.

Expected:
- With VN, `#billing_state_field` has no `validate-required` class and no required mark.
- With US, it has `validate-required` and the mark.
- A required field in a step that isn't shown keeps `validate-required`.

- [ ] **Step 3: Commit**

Stage: `assets/js/checkout-field-requirements.js`
Message: `fix(checkout): skip locale-hidden rows when applying required marks`

### Task 3: End-to-end check and close the backlog item

- [ ] **Step 1: Place an order**

Use a layout with a required State block (e.g. layout 61).

Action:
1. Check out as a guest with billing country VN, every other required field filled, and Cash on delivery.
2. Repeat with country US and State left blank.

Expected:
1. VN: the order is created and the page redirects to order-received, with no "Billing State / County is a required field."
2. US: the order is refused with "Billing State is a required field."

- [ ] **Step 2: Close the backlog item**

Run `/yaybacklog check required overlay`, tick the three boxes it reports `met`, and remove the item. Stage the removal of `docs/backlog/2026-10-07-required-overlay-hidden-locale-field.md` and set this plan's Status to `done YYYY-MM-DD` in the same PR.
Message: `docs(backlog): close required overlay hidden-field item`
