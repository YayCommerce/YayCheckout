# Layout shortcodes and checkout-form safety Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract. On approval, copy this plan to `docs/plans/2026-10-18-layout-shortcodes-and-form-hooks.md` on branch `trace/fix/layout-shortcodes-and-form-hooks` (from `dev/edd`).

**Status:** done 2026-10-18
**Goal:** Shortcodes run in checkout layouts. A `<form>` printed inside the Checkout block can no longer cut the checkout form short. `woocommerce_before/after_checkout_form` fire by default, as on stock checkout, so YayCurrency's notices appear. The Hook block's editor hint stops claiming that nothing prints.
**Spec:** none, planned from the YayCurrency/shortcode test session of 2026-10-18.
**Architecture:**
- **Shortcodes:** a render-scoped `render_block` filter runs `do_shortcode()` on an allowlist of core text blocks while the layout renders. Running it over the whole page would also run shortcode-like text the shopper typed into fields.
- **Nested forms:** the Checkout block renames nested `<form>` tags in its inner content to `<div>`, skipping `<template>` (the Login dialog).
- **Default form hooks:** the Checkout block fires the two page hooks through the existing `WcHooks::fire()` (core callbacks trimmed) unless a Hook block places them.

**Touches:**
- `templates/checkout-page.php`
- `packages/blocks/src/checkout/render.php`
- new `includes/Renderer/LayoutShortcodes.php`
- new `includes/Renderer/CheckoutNestedForms.php`
- `includes/Renderer/WcHooks.php`
- `assets/css/checkout-base.css` (shared: every layout, storefront and editor canvas)
- `packages/blocks/src/wc-hook/edit.tsx`
- `docs/architecture.md`

**Tests:**
- `tests/Integration/Renderer/LayoutShortcodesTest.php` (`composer test`): guards that shortcodes run in Shortcode and text blocks, and that they never run in yaycheckout/* output.
- `tests/Integration/Renderer/CheckoutNestedFormsTest.php` (`composer test`): guards that a nested `<form>` becomes a `<div>` while `<template>` forms are untouched.
- `tests/Integration/Renderer/WcHooksTest.php` (`composer test`, extended): guards that `woocommerce_before_checkout_form` prints once outside the form when unplaced, not at all when a Hook block places it, and without WooCommerce's login or coupon forms.

## Requirements

- **Goal:** a merchant can put `[yaycurrency-switcher]` (or any shortcode) in a layout and it works, anywhere, without breaking the checkout. YayCurrency's checkout notices show without extra setup.
- **Out of scope:**
  - firing the customer-details, `woocommerce_checkout_billing` and `woocommerce_checkout_shipping` hooks by default;
  - making a nested plugin form submit on its own (inside the checkout it can't, and its submit button submits the checkout);
  - the compatibility scan (it runs in REST, where YayCurrency does attach);
  - editor-canvas rendering of shortcodes (core shows the text box, as on any page).
- **Acceptance criteria:**
  1. `[yaycurrency-switcher]` in a Shortcode block renders the switcher. `[yaycurrency-currency]` in a Paragraph, Heading, Custom HTML or List item block renders the currency code.
  2. A billing field whose saved value is `[yaycurrency-currency]` prints that literal text after reload.
  3. With a switcher shortcode inside the Checkout block, every billing field after it is still inside the checkout form (`input.form` is `form.checkout`).
  4. The Login block's dialog (`<form>` inside `<template>`) still logs in.
  5. With no Hook block, `woocommerce_before_checkout_form` output prints once, above `form.checkout` and outside it. `woocommerce_after_checkout_form` prints once, below it. Neither prints WooCommerce's login form, coupon form or notices wrapper.
  6. With a Hook block for `woocommerce_before_checkout_form`, its output prints only in that block.
  7. Hook block editor: for a hook with no wp-admin callbacks, the hint reads "Nothing hooks this in the editor. Some plugins add output only on the storefront, so preview to check.", and the "Nothing prints here" badge is gone.
  8. End to end: with YayCurrency configured (USD, EUR, checkout in different currency on, USD set to no checkout), the YayCurrency notice shows on a layout with no Hook block. The in-form switcher changes currency. A COD order placed through the active layout succeeds with the billing name saved.
- **Decided with the user:**
  - Shortcode blocks: Shortcode, plus text blocks (Paragraph, Heading, Custom HTML, List item); never `yaycheckout/*`.
  - Default hooks: before and after the form only.
  - Editor hint: soften the copy; no stored value.
  - Tests: all three proposed.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit `assets/dist/`. A block's `render.php` is edited in `packages/blocks/src/<block>/`, then `pnpm build:blocks`.
- Free names Pro nowhere. All of this is Free code.
- Commit messages: `type(scope): summary` plus the trailer CLAUDE.md requires. Stage files by name.

## Review focus

- **Shopper-typed text that looks like a shortcode** (`[foo]` in an address) must never run. Task 1's test covers this.
- **The Login dialog's `<form>` lives in a `<template>`.** Renaming it would break login. Task 2's test and its manual check cover this.
- **`</FORM>` in upper case, or a `<form` attribute spread across lines.** The rename must be case-insensitive and tag-aware. Task 2's test covers this.
- **The logged-out view and must-log-in branches of the Checkout block.** Stock fires `before_checkout_form` in both, but `after_checkout_form` only with the form. Task 3's manual check covers this.
- **The before-form output sits outside `form.checkout`.** YayCurrency's JS finds its wrapper by class, so its location doesn't matter. Task 3's end-to-end check confirms this.

---

### Task 1: Shortcodes run in layout text blocks

**Files:**
- Create: `includes/Renderer/LayoutShortcodes.php`
- Modify: `templates/checkout-page.php:34`
- Test: `tests/Integration/Renderer/LayoutShortcodesTest.php`

**Interfaces:**
- Produces:
  - `LayoutShortcodes::BLOCKS`: `[ 'core/shortcode', 'core/paragraph', 'core/heading', 'core/html', 'core/list-item' ]`.
  - `LayoutShortcodes::render( string $content ): string`: adds a `render_block` filter, returns `do_blocks( $content )`, and removes the filter in a `finally`.
  - `LayoutShortcodes::run_in_block( string $block_content, array $block ): string`: for a `blockName` in `BLOCKS`, returns `do_shortcode( shortcode_unautop( $block_content ) )`; otherwise returns `$block_content` unchanged.

- [ ] **Step 1: Write the failing test.** Register `zz_probe` → `'SC-OK'` in `set_up`, remove it in `tear_down`.
  ```php
  public function test_shortcodes_run_in_shortcode_and_text_blocks(): void {
      $html = LayoutShortcodes::render( "<!-- wp:shortcode -->\n[zz_probe]\n<!-- /wp:shortcode -->\n<!-- wp:paragraph --><p>P [zz_probe]</p><!-- /wp:paragraph -->" );
      $this->assertSame( 2, substr_count( $html, 'SC-OK' ) );
      $this->assertStringNotContainsString( '[zz_probe]', $html );
  }
  public function test_shortcodes_never_run_in_yaycheckout_blocks(): void {
      // A shopper-typed value that looks like a shortcode.
      WC()->customer->set_billing_first_name( '[zz_probe]' );
      LayoutFactory::serve( LayoutFactory::checkout_markup() );
      $html = LayoutShortcodes::render( LayoutFactory::checkout_markup() );
      $this->assertStringContainsString( '[zz_probe]', $html );
      $this->assertStringNotContainsString( 'SC-OK', $html );
  }
  public function test_filter_is_removed_after_render(): void {
      LayoutShortcodes::render( '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' );
      $this->assertFalse( has_filter( 'render_block', [ LayoutShortcodes::class, 'run_in_block' ] ) );
  }
  ```
  Run `composer test -- --filter LayoutShortcodesTest`. Expect: it fails because the class doesn't exist yet. If `checkout_markup()` has no billing first-name field, add a `yaycheckout/field` for `billing_first_name` to the markup used.
- [ ] **Step 2: Implement `LayoutShortcodes`** with the interfaces above. In `templates/checkout-page.php`, replace `do_blocks( $yaycheckout_layout->content )` with `\YayCheckout\Renderer\LayoutShortcodes::render( $yaycheckout_layout->content )`, keeping the `BlockVisibility::finalize()` wrap.
- [ ] **Step 3: Run the tests.** `composer test -- --filter LayoutShortcodesTest`. Expect: OK (3 tests).
- [ ] **Step 4: Lint.** `composer lint -- includes/Renderer/LayoutShortcodes.php templates/checkout-page.php`. Expect: no errors.
- [ ] **Step 5: Commit.** Stage the three files. Message: `fix(renderer): run shortcodes in layout shortcode and text blocks`.

### Task 2: Nested forms can't end the checkout form

**Files:**
- Create: `includes/Renderer/CheckoutNestedForms.php`
- Modify: `packages/blocks/src/checkout/render.php`, only the form branch's `echo $content`
- Test: `tests/Integration/Renderer/CheckoutNestedFormsTest.php`

**Interfaces:**
- Produces: `CheckoutNestedForms::neutralize( string $html ): string`.
  - Outside `<template>…</template>`, every `<form` opening tag becomes `<div data-yaycheckout-nested-form` with its attributes kept, and every `</form>` becomes `</div>`.
  - Matching is case-insensitive. Template contents are left byte-identical.
  - Use `WP_HTML_Tag_Processor` to find the FORM and TEMPLATE tags (tag closers visited), and rewrite by offsets or by splitting on template boundaries. Don't use a bare regex over the whole string.

- [ ] **Step 1: Write the failing test.**
  ```php
  public function test_nested_form_becomes_div(): void {
      $out = CheckoutNestedForms::neutralize( '<div><FORM method="post" class="yay-currency-form-switcher"><select name="currency"></select></Form></div><input name="billing_first_name">' );
      $this->assertStringNotContainsStringIgnoringCase( '<form', $out );
      $this->assertStringNotContainsStringIgnoringCase( '</form>', $out );
      $this->assertStringContainsString( '<div data-yaycheckout-nested-form method="post" class="yay-currency-form-switcher">', $out );
  }
  public function test_template_forms_are_untouched(): void {
      $in = '<template class="yaycheckout-login__template"><dialog><form method="post"><input name="username"></form></dialog></template>';
      $this->assertSame( $in, CheckoutNestedForms::neutralize( $in ) );
  }
  public function test_html_without_forms_is_unchanged(): void {
      $in = '<div class="a"><input name="b"></div>';
      $this->assertSame( $in, CheckoutNestedForms::neutralize( $in ) );
  }
  ```
  Run `composer test -- --filter CheckoutNestedFormsTest`. Expect: it fails.
- [ ] **Step 2: Implement it,** and in the Checkout block's form branch print `\YayCheckout\Renderer\CheckoutNestedForms::neutralize( $content )` instead of `$content`. The no-form branch, used for the logged-out view, has no outer form and stays as is.
- [ ] **Step 3: Run the tests.** `composer test -- --filter CheckoutNestedFormsTest`. Expect: OK (3 tests).
- [ ] **Step 4: Build, then check in the browser.**
  - Run `pnpm build:blocks`.
  - Put a Shortcode block with `[yaycurrency-switcher]` above the billing fields in a fresh throwaway layout (never an existing draft, which autosave would change). Preview it at `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>`.
  - In the console, `document.querySelector('#billing_first_name').form?.name` should return `"checkout"`, and the switcher should still change currency.
  - Log out and open the login dialog. Expected: logging in works.
- [ ] **Step 5: Lint.** `composer lint -- includes/Renderer/CheckoutNestedForms.php packages/blocks/src/checkout/render.php`. Expect: no errors.
- [ ] **Step 6: Commit.** Stage the three files. Message: `fix(checkout): keep nested forms from closing the checkout form`.

### Task 3: Before/after form hooks fire by default

**Files:**
- Modify: `includes/Renderer/WcHooks.php` (new method)
- Modify: `packages/blocks/src/checkout/render.php`
- Modify: `assets/css/checkout-base.css`
- Modify: `docs/architecture.md:68`, `:129`
- Test: `tests/Integration/Renderer/WcHooksTest.php` (extend)

**Interfaces:**
- Consumes: `WcHooks::is_placed()`, `WcHooks::fire()`, `CheckoutFragments::wrap_extension_html()`, `WcHooks::ROWS_CLASS`, all existing.
- Produces: `WcHooks::unplaced( string $hook ): string`.
  - Returns what `in_place( $hook, ...HOOKS args )` prints, in `wrap_extension_html( $output, ROWS_CLASS . ' yaycheckout-checkout-hook--' . $hook )`; `''` when nothing prints (so also when a Hook block places it and nothing is anchored).
  - `in_place()`'s unplaced branch fires through `with_hook_trimmed( $hook, core_callbacks( $hook ), … )`, a no-op for every hook whose `core_callbacks()` is empty (changed by yaytidy finding 3).
  - Callers use it only for `woocommerce_before_checkout_form` and `woocommerce_after_checkout_form`.

- [ ] **Step 1: Write the failing tests** in `WcHooksTest`. Use `const BEFORE = 'woocommerce_before_checkout_form'`.
  ```php
  public function test_unplaced_prints_form_hook_without_core_forms(): void {
      wc_load_cart();
      LayoutFactory::serve( LayoutFactory::checkout_markup() );
      add_action( self::BEFORE, [ $this, 'print_probe' ] );
      $html = WcHooks::unplaced( self::BEFORE );
      $this->assertSame( 1, substr_count( $html, 'PROBE' ) );
      $this->assertStringContainsString( 'yaycheckout-checkout-hook--' . self::BEFORE, $html );
      $this->assertStringNotContainsString( 'checkout_coupon', $html );
      $this->assertStringNotContainsString( 'woocommerce-form-login', $html );
  }
  public function test_unplaced_is_empty_when_a_hook_block_places_it(): void {
      LayoutFactory::serve( '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/wc-hook {"hook":"' . self::BEFORE . '"} /--><!-- /wp:yaycheckout/checkout -->' );
      add_action( self::BEFORE, [ $this, 'print_probe' ] );
      $this->assertSame( '', WcHooks::unplaced( self::BEFORE ) );
  }
  public function test_unplaced_is_empty_when_nothing_prints(): void {
      LayoutFactory::serve( LayoutFactory::checkout_markup() );
      $this->assertSame( '', WcHooks::unplaced( 'woocommerce_after_checkout_form' ) );
  }
  ```
  Run `composer test -- --filter WcHooksTest`. Expect: the new tests fail and the old ones pass.
- [ ] **Step 2: Implement `unplaced()`.**
- [ ] **Step 3: Call it in the Checkout block's `render.php`, mirroring WooCommerce's `form-checkout.php`.**
  - Echo `unplaced( 'woocommerce_before_checkout_form' )` once, right after the `function_exists( 'WC' )` check passes and before the logged-out-view, must-log-in and form branches.
  - Echo `unplaced( 'woocommerce_after_checkout_form' )` only after `</form>`.
  - The no-WooCommerce branch prints neither.
- [ ] **Step 4: Add the spacing CSS.** In `checkout-base.css`, add `.yaycheckout-checkout-hook--woocommerce_before_checkout_form { margin-block-end: 24px; }` and `.yaycheckout-checkout-hook--woocommerce_after_checkout_form { margin-block-start: 24px; }`.
- [ ] **Step 5: Update the docs.** In `docs/architecture.md:68`, change the sentence so it says that `woocommerce_before_checkout_form` and `woocommerce_after_checkout_form` fire above and below the form unless a Hook block places them, while the customer-details pair, `woocommerce_checkout_billing` and `woocommerce_checkout_shipping` still never do. In `:129`, drop `woocommerce_before_checkout_form` from the "fire only where a Hook block places them" example. In the same file, note that `LayoutShortcodes` runs shortcodes in the allowlisted core blocks and that `CheckoutNestedForms` neutralizes nested forms.
- [ ] **Step 6: Run the checks.**
  - `composer test`. Expect: no new failures.
  - `composer lint -- includes/Renderer/WcHooks.php packages/blocks/src/checkout/render.php`. Expect: no errors.
  - `pnpm build:blocks`. Expect: it succeeds.
- [ ] **Step 7: Check in the browser** with a throwaway layout that has no Hook block.
  - Add `add_action( 'woocommerce_before_checkout_form', fn() => print 'PROBE-B' )` and the same for `after` in a temporary mu-plugin.
  - Expected: `PROBE-B` shows above the form once and `PROBE-A` below it once. `document.querySelector('form.checkout').contains(<probe element>)` is false for both. There is no coupon or login form at the top.
  - Then add a Hook block for `before`. Expected: `PROBE-B` prints only inside it.
  - Delete the mu-plugin afterwards.
- [ ] **Step 8: Commit.** Stage `includes/Renderer/WcHooks.php`, `packages/blocks/src/checkout/render.php`, `assets/css/checkout-base.css`, `docs/architecture.md` and `tests/Integration/Renderer/WcHooksTest.php`. Message: `fix(checkout): fire before and after checkout form hooks by default`.

### Task 4: Hook block hint stops claiming nothing prints

**Files:**
- Modify: `packages/blocks/src/wc-hook/edit.tsx:86-92`, `:118-120`

**Interfaces:** none. `hasCallbacks` keeps its meaning: callbacks visible in wp-admin.

- [ ] **Step 1:** When `! option.hasCallbacks`, change the inspector `Notice` text to `'Nothing hooks this in the editor. Some plugins add output only on the storefront, so preview to check.'` (domain `yaycheckout`) and remove the `yaycheckout-wc-hook__badge` span. If the badge's CSS in `packages/blocks/src/wc-hook/style.css` or `editor.css` is now unused, remove it too.
- [ ] **Step 2: Build and check.**
  - Run `pnpm build:blocks`.
  - Insert a Hook block in a throwaway layout and pick "Before checkout form" with YayCurrency active.
  - Expected: the new hint shows in the sidebar and the canvas has no "Nothing prints here" badge.
- [ ] **Step 3: Commit.** Stage `packages/blocks/src/wc-hook/edit.tsx` and any CSS file changed. Message: `fix(wc-hook): stop saying nothing prints for storefront-only hooks`.

### Task 5: End-to-end check with YayCurrency (no code)

- [ ] **Step 1: Set up.**
  - Add USD and EUR currencies in YayCurrency's settings (not by script).
  - Turn on "Checkout in different currency", and turn off checkout for USD.
  - Clear the `yay_currencies_transient` transient.
- [ ] **Step 2: Check the storefront.** Use a throwaway layout copied from Classic two-column with no Hook block, a Shortcode block with `[yaycurrency-switcher]` in the header, another inside the Checkout block above Contact, and a Paragraph with `[yaycurrency-currency]`.
  - Expected: both switchers render and the paragraph shows the code.
  - Switch to USD. Expected: the YayCurrency notice ("…recorded in VND") shows above the form.
  - Switch to EUR with the in-form switcher. Expected: the summary shows €.
- [ ] **Step 3: Place an order.**
  - Activate the throwaway layout, or set it as the active layout for the admin role.
  - Place a Cash on delivery order. Expected: the order-received page loads, and in admin the order has the billing name typed and the currency EUR.
  - Restore the previous active layout.
  - Delete the throwaway layouts and the test currencies, with the user's OK.
