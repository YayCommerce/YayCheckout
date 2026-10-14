# Safe Mode Fallback Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-13
**Goal:** With safe mode on, a layout with a problem that can stop an order is replaced by WooCommerce's checkout and the merchant is told why; with it off, the layout renders and the merchant is still warned.
**Spec:** [docs/specs/2026-10-13-safe-mode-fallback-design.md](../specs/2026-10-13-safe-mode-fallback-design.md)
**Architecture:** `Renderer\CheckoutHealth` owns every rule and is asked by `CheckoutPage::resolve()` on every request, so render, AJAX and submit agree. Render failures are caught in `CheckoutPage::page_template()` and recorded in `yaycheckout_render_failure`, which `resolve()` reads as a circuit breaker. Precomputing a flag on admin events was rejected (spec § Alternatives).
**Touches:**
- (shared) `includes/Services/LayoutMarkupReader.php` `publish_error()`. Consumers: `LayoutRepository::publish()`/`activate()`, `LayoutCoreRestController` (editor Publish), `Pro\Services\LayoutImportReview` (reads only its first rule, unchanged), `tests/Integration/LayoutTemplateCatalogueTest.php`, and now `CheckoutHealth`.
- (shared) `includes/Support/CompatibilityCatalogue.php`, `includes/Helpers/GatewayCompatibilityHelper.php`, `includes/Helpers/StoreEnvironmentHelper.php`. Consumers: `CompatibilityRestController` (`GET /compatibility`), the SPA's Compatibility page and setup wizard scan (`ScanStep.tsx`), `StatusBadge.tsx`.
- `includes/Repositories/LayoutRepository.php`, `includes/Repositories/CompatibilityCatalogueRepository.php`, `includes/Renderer/CheckoutPage.php`, new `includes/Renderer/CheckoutHealth.php`, new `includes/Engine/Admin/SafeModeNotice.php`, `includes/Models/Settings.php`, `includes/YayCheckout.php`, `templates/checkout-page.php`.
- `apps/admin/src/lib/schema/compatibility.schema.ts`, `apps/admin/src/lib/api/compatibility.api.ts`, `apps/admin/src/components/shared/StatusBadge.tsx`, `apps/admin/src/pages/compatibility/components/StoreSection.tsx`, `apps/admin/src/pages/settings/components/GeneralTab.tsx`.
- `tests/Support/LayoutFactory.php` and the two Renderer tests whose fixtures lack Place order/Payment.
- `docs/architecture.md`, `.claude/rules/php-standards.md`.
**Tests:**
- `tests/Integration/Services/LayoutMarkupReaderPublishErrorTest.php` (Integration): the Place order and Payment publish rules and their messages.
- `tests/Integration/Renderer/CheckoutHealthTest.php` (Integration): plugin conflict with and without `fixed_in`, unsupported payment, the filter.
- `tests/Integration/Renderer/CheckoutPageSafeModeTest.php` (Integration): a throwing block falls back and trips the breaker, a content change resets it, safe mode off rethrows, a preview rethrows without recording.

## Global constraints

- Product PHP runs on PHP 7.4 (no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types; also none of `mixed`, `static`, `never` or intersection types, a `catch` without a variable, `throw` as an expression, a trailing comma after the last declared parameter, `new` in a default value, first-class callables, `$object::class`, attributes).
- Free and Pro alike; nothing in `includes/Pro/`, no `Plan::is_pro()` check. Free names Pro nowhere new.
- No remote request on the storefront: the storefront reads the catalogue only through `CompatibilityCatalogueRepository::cached()`.
- Every check in `resolve()` reads store configuration only (options, active plugins, enabled gateways, the catalogue transient), never the cart, the shopper's input or gateway availability.
- `yaycheckout_render_failure` is written only when its `layout_id`, `hash` or `reason` changes.
- Smallest change: no refactor of code the feature does not need.
- Wire stays snake_case; a strict zod schema changes in the same commit as its PHP shape.
- Edit `packages/blocks/src/`, never `assets/dist/blocks/`.
- New strings use the `yaycheckout` text domain.
- Lint changed PHP with `composer lint -- <files>`; type-check the SPA with `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. `pnpm --filter @yaycheckout/admin-page lint` already fails on `dev/edd` (docs/known-issues.md): expect no new findings in changed files.
- Commits follow Conventional Commits and end with the `Co-Authored-By` trailer (CLAUDE.md § Git). Branch: `alan/feat/safe-mode`. Commit only when the user asks.
- Never test edits on an existing draft layout: autosave writes them. Use a new or duplicated layout.

## Review focus

- A shopper submits the order after the render request fell back: the submit must also see WooCommerce's checkout, not validate against the layout (Task 4, breaker test `test_next_request_after_failure_falls_back`).
- Existing test fixtures and the layout templates lacking Place order or Payment become unpublishable: every fixture passed to `LayoutFactory::serve()` and every template must still activate (Task 1, Step 4).
- A storefront request when the catalogue transient has expired must not call the remote URL (Task 2, Step 4).
- An exception mid-render leaves an open output buffer that leaks half a layout into WooCommerce's page (Task 4, `test_throwing_block_falls_back_and_records`, asserting no layout markup is echoed).
- The breaker never clears because the hash was taken from a different string than `resolve()` compares (Task 4, `test_content_change_resets_breaker`).

---

### Task 1: Place order and Payment publish rules

**Files:**
- Modify: `includes/Services/LayoutMarkupReader.php` (constants at lines 13–20, `publish_error()` at 225)
- Modify: `tests/Support/LayoutFactory.php` (`checkout_markup()`)
- Modify: `tests/Integration/Renderer/CheckoutDifferentAddressTest.php:14`, `tests/Integration/Renderer/CheckoutRequiredFieldsHiddenTest.php:15`
- Test: `tests/Integration/Services/LayoutMarkupReaderPublishErrorTest.php`

**Interfaces:**
- Produces: `LayoutMarkupReader::publish_error( string $markup ): string` with two more rules, used by Task 3.

- [ ] **Step 1: Write the failing test**

```php
namespace YayCheckout\Tests\Integration\Services;

class LayoutMarkupReaderPublishErrorTest extends \WP_UnitTestCase {
    private const PAY   = '<!-- wp:yaycheckout/payment /-->';
    private const ORDER = '<!-- wp:yaycheckout/place-order /-->';

    private static function checkout( string $inner ): string {
        return '<!-- wp:yaycheckout/checkout -->' . $inner . '<!-- /wp:yaycheckout/checkout -->';
    }

    public function test_missing_place_order_is_refused(): void {
        $this->assertSame( 'Add a Place order block to this layout before activating it.', LayoutMarkupReader::publish_error( self::checkout( self::PAY ) ) );
    }

    public function test_missing_payment_is_refused(): void {
        $this->assertSame( 'Add a Payment block to this layout before activating it.', LayoutMarkupReader::publish_error( self::checkout( self::ORDER ) ) );
    }

    public function test_checkout_rule_still_comes_first(): void {
        $this->assertSame( 'Add a Checkout block to this layout before activating it.', LayoutMarkupReader::publish_error( self::PAY . self::ORDER ) );
    }

    public function test_complete_layout_passes(): void {
        $this->assertSame( '', LayoutMarkupReader::publish_error( self::checkout( self::PAY . self::ORDER ) ) );
    }
}
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `composer test -- --filter LayoutMarkupReaderPublishErrorTest`
Expected: FAIL on the two "missing" tests (empty string returned).

- [ ] **Step 3: Implement the rules**

Add `private const PLACE_ORDER = 'yaycheckout/place-order';` and `private const PAYMENT = 'yaycheckout/payment';`. In `publish_error()`, right after the Checkout rule, return the Place order message when `! has_block( self::PLACE_ORDER, $markup )`, then the Payment message when `! has_block( self::PAYMENT, $markup )`. Messages are exactly the test's strings, wrapped in `__( …, 'yaycheckout' )`.

- [ ] **Step 4: Update fixtures**

`LayoutFactory::checkout_markup()` returns `'<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/payment /--><!-- wp:yaycheckout/place-order /--><!-- /wp:yaycheckout/checkout -->'` and its docblock says "the root block with the two blocks publish_error() requires". In the two Renderer tests, insert `<!-- wp:yaycheckout/payment /--><!-- wp:yaycheckout/place-order /-->` before `<!-- /wp:yaycheckout/checkout -->` in the `serve()` markup.

- [ ] **Step 5: Run the suite**

Run: `composer test`
Expected: all pass, including `LayoutTemplateCatalogueTest` (every template already has both blocks).

- [ ] **Step 6: Lint**

Run: `composer lint -- includes/Services/LayoutMarkupReader.php`
Expected: no errors.

- [ ] **Step 7: Commit**

Stage: `includes/Services/LayoutMarkupReader.php`, `tests/Support/LayoutFactory.php`, `tests/Integration/Renderer/CheckoutDifferentAddressTest.php`, `tests/Integration/Renderer/CheckoutRequiredFieldsHiddenTest.php`, `tests/Integration/Services/LayoutMarkupReaderPublishErrorTest.php`
Message: `feat(layouts): require Place order and Payment blocks to publish`

### Task 2: Catalogue `conflict` status and storefront-safe read

**Files:**
- Modify: `includes/Support/CompatibilityCatalogue.php`
- Modify: `includes/Repositories/CompatibilityCatalogueRepository.php`
- Modify: `includes/Helpers/GatewayCompatibilityHelper.php` (`block_registered_ids()` visibility)
- Modify: `includes/Helpers/StoreEnvironmentHelper.php` (`store_type()` visibility)
- Modify: `apps/admin/src/lib/schema/compatibility.schema.ts`, `apps/admin/src/components/shared/StatusBadge.tsx`

**Interfaces:**
- Produces: `CompatibilityCatalogue::STATUS_CONFLICT = 'conflict'`; entry key `fixed_in` (`?string`); `CompatibilityCatalogueRepository::cached(): array` (same shape as `get()`); `GatewayCompatibilityHelper::block_registered_ids(): string[]` public; `StoreEnvironmentHelper::store_type(): string` public.

- [ ] **Step 1: Catalogue**

Add `STATUS_CONFLICT`, append it to `GATEWAY_STATUSES` and `PLUGIN_STATUSES`, add `'fixed_in' => null` to `entry_defaults()` (and its `@return`). `normalize_entries()` needs no change. `CONTRACT_VERSION` stays `1`.

`fixed_in` stays out of the wire: `GatewayCompatibilityHelper::row()` and `CheckoutHookScanHelper` rows do not copy it, so the SPA gets no new field. Only the status enums change on the SPA side.

- [ ] **Step 2: `cached()`**

`public function cached(): array`: the transient when it is an array, else `CompatibilityCatalogue::bundled() + [ 'source' => SOURCE_BUNDLED, 'fetched_at' => null ]`. It never calls `fetch_remote()` and never writes the transient. Docblock: "For the storefront: never fetches."

- [ ] **Step 3: Visibility and SPA**

Make `block_registered_ids()` and `store_type()` public, no other change. In `compatibility.schema.ts` add `'conflict'` to `gatewayStatusWireSchema` and `pluginStatusWireSchema`. In `StatusBadge.tsx` add `conflict: { label: __('Conflict', 'yaycheckout'), variant: 'destructive-soft' }`.

- [ ] **Step 4: Verify**

Run: `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` → no errors.
Run: `composer lint -- includes/Support/CompatibilityCatalogue.php includes/Repositories/CompatibilityCatalogueRepository.php includes/Helpers/GatewayCompatibilityHelper.php includes/Helpers/StoreEnvironmentHelper.php` → no errors.
Run: `wp eval 'delete_transient( "yaycheckout_compatibility_catalogue" ); var_dump( ( new YayCheckout\Repositories\CompatibilityCatalogueRepository() )->cached()["source"], get_transient( "yaycheckout_compatibility_catalogue" ) );'` → `string(7) "bundled"` and `bool(false)` (no fetch, nothing cached).
Open `/wp-admin/admin.php?page=yaycheckout#/compatibility` → page loads, gateway badges unchanged.

- [ ] **Step 5: Commit**

Stage: the six files above.
Message: `feat(compatibility): add conflict status and a storefront-safe catalogue read`

### Task 3: `CheckoutHealth` and the safe-mode check in `resolve()`

**Files:**
- Create: `includes/Renderer/CheckoutHealth.php`
- Modify: `includes/Renderer/CheckoutPage.php` (`resolve()`, class docblock)
- Modify: `includes/Repositories/LayoutRepository.php` (`active()` at line 51)
- Modify: `includes/Models/Settings.php` (`safe_mode` default)
- Test: `tests/Integration/Renderer/CheckoutHealthTest.php`

**Interfaces:**
- Consumes: Task 1 `publish_error()`; Task 2 `cached()`, `STATUS_CONFLICT`, `block_registered_ids()`, `store_type()`.
- Produces:
  - `CheckoutHealth::problem( Layout $layout ): string` — `publish_error( $layout->content )`, else `store_problem()`.
  - `CheckoutHealth::store_problem(): string` — first non-empty of `conflicting_plugin()`, `unsupported_payment()`, passed through `apply_filters( 'yaycheckout_safe_mode_problem', $problem )`.
  - `CheckoutHealth::reported_problem(): string` — added in Task 5.
  - `LayoutRepository::active_layouts(): Layout[]` — the active layouts, newest activation first.
  - Filter `yaycheckout_safe_mode_problem` ( string $problem ): string.

Static methods, no singleton: it registers no hooks.

- [ ] **Step 1: Write the failing tests**

`set_up()`: `update_option( 'active_plugins', [ 'woocommerce/woocommerce.php', 'fake-conflict/fake-conflict.php' ] )`, `set_transient( CompatibilityCatalogue::TRANSIENT, <catalogue>, HOUR_IN_SECONDS )` where `<catalogue>` is `CompatibilityCatalogue::bundled() + [ 'source' => 'bundled', 'fetched_at' => null ]` with the entries each test adds. Gateways come from `tests/Support/FakeClassicGateway.php` / `FakeBlockPaymentMethod.php`, registered through `woocommerce_payment_gateways` and the Blocks `PaymentMethodRegistry`, then `WC()->payment_gateways()->init()`.

```php
public function test_active_conflicting_plugin_is_a_problem(): void {
    $this->catalogue_plugin( 'fake-conflict', [ 'status' => 'conflict', 'title' => 'Fake Conflict' ] );
    $this->assertSame( 'Fake Conflict conflicts with YayCheckout.', CheckoutHealth::store_problem() );
}

public function test_fixed_in_at_or_below_installed_version_is_not_a_problem(): void {
    $this->catalogue_plugin( 'woocommerce', [ 'status' => 'conflict', 'fixed_in' => '1.0.0' ] );
    $this->assertSame( '', CheckoutHealth::store_problem() );
}

public function test_fixed_in_above_installed_version_is_a_problem(): void {
    $this->catalogue_plugin( 'woocommerce', [ 'status' => 'conflict', 'fixed_in' => '999.0.0' ] );
    $this->assertStringEndsWith( 'conflicts with YayCheckout.', CheckoutHealth::store_problem() );
}

public function test_only_gateway_marked_conflict_is_a_problem(): void {
    $this->enable_classic_gateway();                    // fake_classic
    $this->catalogue_gateway( 'fake_classic', [ 'status' => 'conflict' ] );
    $this->assertSame( 'No enabled payment method works in the YayCheckout checkout.', CheckoutHealth::store_problem() );
}

public function test_only_block_payment_on_block_checkout_is_a_problem(): void {
    $this->use_block_checkout_page();                   // checkout page content: <!-- wp:woocommerce/checkout /-->
    $this->register_block_payment_method();             // FakeBlockPaymentMethod, no classic twin
    $this->assertSame( 'No enabled payment method works in the YayCheckout checkout.', CheckoutHealth::store_problem() );
}

public function test_no_payment_method_at_all_is_not_a_problem(): void {
    $this->assertSame( '', CheckoutHealth::store_problem() );
}

public function test_filter_adds_a_problem(): void {
    add_filter( 'yaycheckout_safe_mode_problem', static fn() => 'Custom reason.' );
    $this->assertSame( 'Custom reason.', CheckoutHealth::store_problem() );
}

public function test_layout_markup_problem_comes_first(): void {
    $layout = ( new LayoutRepository() )->find( LayoutFactory::create_draft( [ 'post_content' => '<!-- wp:yaycheckout/checkout /-->' ] ) );
    $this->assertSame( 'Add a Place order block to this layout before activating it.', CheckoutHealth::problem( $layout ) );
}

public function test_safe_mode_on_falls_back_on_a_problem(): void {
    LayoutFactory::serve( LayoutFactory::checkout_markup() );
    add_filter( 'yaycheckout_safe_mode_problem', static fn() => 'Custom reason.' );
    $this->assertNull( CheckoutPage::get_instance()->layout() );
}

public function test_safe_mode_off_keeps_the_layout(): void {
    update_option( 'yaycheckout_settings', [ 'general' => [ 'safe_mode' => false ] ] );
    LayoutFactory::serve( LayoutFactory::checkout_markup() );
    add_filter( 'yaycheckout_safe_mode_problem', static fn() => 'Custom reason.' );
    $this->assertNotNull( CheckoutPage::get_instance()->layout() );
}
```

Helpers (`catalogue_plugin()`, `catalogue_gateway()`, `enable_classic_gateway()`, `use_block_checkout_page()`, `register_block_payment_method()`) are private in the test class.

- [ ] **Step 2: Run them to confirm they fail**

Run: `composer test -- --filter CheckoutHealthTest`
Expected: FAIL, class `CheckoutHealth` not found.

- [ ] **Step 3: `LayoutRepository::active_layouts()`**

Move the first line of `active()` into `public function active_layouts(): array`; `active()` calls it. No other change.

- [ ] **Step 4: Implement `CheckoutHealth`**

`includes/Renderer/CheckoutHealth.php`, class docblock: what makes a layout unsafe to serve, read from store configuration only so every request of one form agrees.

- `private static function conflicting_plugin( array $catalogue ): string`: slugs from `get_option( 'active_plugins', [] )` and, when `is_multisite()`, `array_keys( get_site_option( 'active_sitewide_plugins', [] ) )`; slug = `dirname( $file )`, or `basename( $file, '.php' )` when `dirname()` is `'.'`. For each `$catalogue['plugins']` entry with status `conflict` whose slug is active: when `fixed_in` is set, read the version with `PluginHeaderHelper::describe( 'plugin', $slug, WP_PLUGIN_DIR . '/' . $slug )` and skip the entry unless `version_compare( $version, $fixed_in, '<' )`. Name = entry `title`, else the header name. Return `sprintf( __( '%s conflicts with YayCheckout.', 'yaycheckout' ), $name )` for the first match.
- `private static function unsupported_payment( array $catalogue ): string`: per the spec's *usable here* / *usable natively* definitions, from `WC()->payment_gateways()->payment_gateways()` (`'yes' === $gateway->enabled`), `$catalogue['gateways'][ $id ]['status']`, `GatewayCompatibilityHelper::block_registered_ids()` and `'block' === StoreEnvironmentHelper::store_type()`. Return `__( 'No enabled payment method works in the YayCheckout checkout.', 'yaycheckout' )` or `''`.
- `store_problem()` reads `( new CompatibilityCatalogueRepository() )->cached()` once and passes it to both. A new rule is one private method and one line here.

- [ ] **Step 5: Wire into `resolve()` and flip the default**

In `CheckoutPage::resolve()`, after `active()`: when the layout is not null, safe mode is on and `'' !== CheckoutHealth::problem( $layout )`, return null. Update the class docblock's "Stands down … when safe mode is on" sentence to "when safe mode is on and the layout has a problem (CheckoutHealth) or last failed to render". In `Settings::schema()`, `safe_mode` default `true` and its comment: "Read by Renderer\CheckoutPage: on, a layout with a problem gives way to WooCommerce's checkout."

- [ ] **Step 6: Run tests and lint**

Run: `composer test` → all pass.
Run: `composer lint -- includes/Renderer/CheckoutHealth.php includes/Renderer/CheckoutPage.php includes/Repositories/LayoutRepository.php includes/Models/Settings.php` → no errors.

- [ ] **Step 7: Commit**

Stage: the five files above.
Message: `feat(checkout): fall back to WooCommerce checkout when safe mode finds a problem`

### Task 4: Render failure circuit breaker

**Files:**
- Modify: `includes/Renderer/CheckoutPage.php`
- Modify: `templates/checkout-page.php`
- Test: `tests/Integration/Renderer/CheckoutPageSafeModeTest.php`

**Interfaces:**
- Consumes: Task 3 `resolve()` check.
- Produces:
  - `CheckoutPage::OPTION_RENDER_FAILURE = 'yaycheckout_render_failure'`, value `array{layout_id: int, hash: string, reason: string, time: int}`, autoloaded.
  - `CheckoutPage::page_template( string $template ): string` (signature gains `$template`).
  - `CheckoutPage::html(): string` — the rendered layout, for the template.
  - `CheckoutPage::render_failure(): string` — the reason of a recorded failure whose layout is still active (`LayoutRepository::is_active()`) and whose content still hashes to `hash`, else `''`. Consumed by Task 5.
  - `CheckoutPage::record_fatal( Layout $layout ): void` — public only because `register_shutdown_function` calls it.

- [ ] **Step 1: Write the failing tests**

`set_up()`: `wc_load_cart()`; register a block type `yaycheckout-test/boom` whose `render_callback` throws `new \RuntimeException( 'boom' )` (unregister in `tear_down()`); `delete_option( CheckoutPage::OPTION_RENDER_FAILURE )`. `$boom` is `LayoutFactory::checkout_markup()` with `<!-- wp:yaycheckout-test/boom /-->` inserted before the closing Checkout comment.

```php
public function test_throwing_block_falls_back_and_records(): void {
    $id    = LayoutFactory::serve( $boom );
    $level = ob_get_level();

    $this->assertSame( '/theme/page.php', CheckoutPage::get_instance()->page_template( '/theme/page.php' ) );
    $this->assertSame( $level, ob_get_level() );
    $this->assertNull( CheckoutPage::get_instance()->layout() );

    $failure = get_option( CheckoutPage::OPTION_RENDER_FAILURE );
    $this->assertSame( $id, $failure['layout_id'] );
    $this->assertSame( 'boom', $failure['reason'] );
    $this->assertSame( md5( get_post( $id )->post_content ), $failure['hash'] );
}

public function test_next_request_after_failure_falls_back(): void {
    LayoutFactory::serve( $boom );
    CheckoutPage::get_instance()->page_template( '/theme/page.php' );
    LayoutFactory::forget_served_layout();
    $this->assertNull( CheckoutPage::get_instance()->layout() );
}

public function test_content_change_resets_breaker(): void {
    $id = LayoutFactory::serve( $boom );
    CheckoutPage::get_instance()->page_template( '/theme/page.php' );
    wp_update_post( [ 'ID' => $id, 'post_content' => LayoutFactory::checkout_markup() ] );
    LayoutFactory::forget_served_layout();
    $this->assertNotNull( CheckoutPage::get_instance()->layout() );
}

public function test_render_without_place_order_button_falls_back(): void {
    add_filter( 'render_block_yaycheckout/place-order', '__return_empty_string' );
    LayoutFactory::serve( LayoutFactory::checkout_markup() );
    $this->assertSame( '/theme/page.php', CheckoutPage::get_instance()->page_template( '/theme/page.php' ) );
    $this->assertSame( 'The layout rendered without a Place order button.', get_option( CheckoutPage::OPTION_RENDER_FAILURE )['reason'] );
}

public function test_safe_mode_off_rethrows_and_records(): void {
    update_option( 'yaycheckout_settings', [ 'general' => [ 'safe_mode' => false ] ] );
    LayoutFactory::serve( $boom );
    try {
        CheckoutPage::get_instance()->page_template( '/theme/page.php' );
        $this->fail( 'Expected the exception to propagate.' );
    } catch ( \RuntimeException $e ) {
        $this->assertSame( 'boom', get_option( CheckoutPage::OPTION_RENDER_FAILURE )['reason'] );
    }
}

public function test_render_failure_reports_only_while_it_applies(): void {
    $id = LayoutFactory::serve( $boom );
    CheckoutPage::get_instance()->page_template( '/theme/page.php' );
    $this->assertSame( 'boom', CheckoutPage::render_failure() );
    ( new LayoutRepository() )->deactivate( $id );
    $this->assertSame( '', CheckoutPage::render_failure() );
}
```

A preview rethrows without recording: cover it with the manual check in Step 5, since a preview needs a signed token request.

- [ ] **Step 2: Run them to confirm they fail**

Run: `composer test -- --filter CheckoutPageSafeModeTest`
Expected: FAIL (`page_template()` returns the plugin template; option missing).

- [ ] **Step 3: Implement in `CheckoutPage`**

- Properties `private $html = ''` and `private $rendering = false`.
- `private function render( Layout $layout ): string`: the block of `templates/checkout-page.php` lines 20–35 moved verbatim (preview banner, `WC()->cart->calculate_totals()`, `do_blocks()`), into its own `ob_start()`/`ob_get_clean()`.
- `page_template( string $template )`: as the spec's Components line describes. Record `ob_get_level()` before rendering and `ob_end_clean()` back to it in the `catch`. Order inside `catch`: clean buffers; if `PreviewRequest::get_instance()->active()` rethrow; `record_failure()`; if safe mode off rethrow; else `$this->layout = null`, `remove_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], self::SCRIPTS_PRIORITY )`, return `$template`. The `id="place_order"` check throws `\RuntimeException` with `__( 'The layout rendered without a Place order button.', 'yaycheckout' )` inside the `try`. `register_shutdown_function( [ $this, 'record_fatal' ], $layout )` before rendering.
- `record_fatal()`: only when `$this->rendering` is still true and `error_get_last()['type']` is one of `E_ERROR`, `E_CORE_ERROR`, `E_COMPILE_ERROR`.
- `private function record_failure( Layout $layout, string $reason ): void`: write only when the stored `layout_id`, `hash` or `reason` differ (Global constraints).
- `private static function has_failed( Layout $layout ): bool` and the `resolve()` check: with safe mode on, return null when `has_failed()`. Place it beside Task 3's check, after the preview branch.
- `render_failure()` per Interfaces.

- [ ] **Step 4: Template**

`templates/checkout-page.php`: drop lines 11–35 (`$yaycheckout_layout`, `$yaycheckout_preview`, the buffered block) and print `\YayCheckout\Renderer\CheckoutPage::get_instance()->html()` where `$yaycheckout_main` was printed, with the same phpcs ignore comment. Move the "Rendered before wp_head()" comment to `render()`'s docblock.

- [ ] **Step 5: Run tests, lint, and check a preview by hand**

Run: `composer test` → all pass.
Run: `composer lint -- includes/Renderer/CheckoutPage.php templates/checkout-page.php` → no errors.
Manual: add `throw new \Exception( 'boom' );` at the top of `packages/blocks/src/order-summary/render.php`, `pnpm build:blocks`, open the Preview link of a new layout made from the default template (it has an Order summary) → the PHP error shows (WP_DEBUG) and `wp option get yaycheckout_render_failure` → `Error: Could not get 'yaycheckout_render_failure' option.` Revert the throw and rebuild.

- [ ] **Step 6: Commit**

Stage: `includes/Renderer/CheckoutPage.php`, `templates/checkout-page.php`, `tests/Integration/Renderer/CheckoutPageSafeModeTest.php`
Message: `feat(checkout): fall back and trip a breaker when the layout fails to render`

### Task 5: Report the problem to the merchant

**Files:**
- Modify: `includes/Renderer/CheckoutHealth.php` (add `reported_problem()`)
- Modify: `includes/Helpers/StoreEnvironmentHelper.php` (`describe()`)
- Create: `includes/Engine/Admin/SafeModeNotice.php`
- Modify: `includes/YayCheckout.php` (boot it after `Engine\Admin\Settings`)
- Modify: `apps/admin/src/lib/schema/compatibility.schema.ts`, `apps/admin/src/lib/api/compatibility.api.ts`, `apps/admin/src/pages/compatibility/components/StoreSection.tsx`, `apps/admin/src/pages/settings/components/GeneralTab.tsx`

**Interfaces:**
- Consumes: Task 3 `store_problem()`, `active_layouts()`; Task 4 `render_failure()`.
- Produces: `CheckoutHealth::reported_problem(): string`; wire `store.checkout_problem: string` → app `store.checkoutProblem: string`.

- [ ] **Step 1: `reported_problem()`**

First non-empty of: `CheckoutPage::render_failure()`; `publish_error()` of each `( new LayoutRepository() )->active_layouts()` item's content; `store_problem()`. Docblock: "What the admin reports: the same rules the storefront applies, safe mode on or off."

- [ ] **Step 2: Wire**

`StoreEnvironmentHelper::describe()` adds `'checkout_problem' => CheckoutHealth::reported_problem()` and its `@return`. Zod: `checkout_problem: z.string()` on `store`; `StoreEnvironment` gains `checkoutProblem: string`; `reportFromWire()` maps it.

- [ ] **Step 3: Rendering row and setting text**

`StoreSection.tsx` Rendering value, per the spec's UI table: `checkoutProblem` empty → `YayCheckout layout`; otherwise `sprintf( safeMode ? __('Native checkout — %s') : __('YayCheckout layout — %s'), checkoutProblem )`, with translator comments. `GeneralTab.tsx` description: the spec's exact sentence.

- [ ] **Step 4: `SafeModeNotice`**

`Engine\Admin\SafeModeNotice`, `SingletonTrait`, hooks `admin_notices`. Prints nothing unless `current_user_can( LayoutPostType::CAPABILITY )` and `reported_problem()` is non-empty. Otherwise `<div class="notice notice-warning"><p>…</p></div>` with `esc_html`, text: safe mode on → *"Shoppers are getting the WooCommerce checkout: %s"*, off → *"Your checkout layout may not work: %s"*, followed by a link *"See Compatibility"* to `admin_url( 'admin.php?page=' . AdminUrlHelper::MENU_SLUG ) . '#/compatibility'` (`esc_url`).

- [ ] **Step 5: Verify**

Run: `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` → no errors.
Run: `composer lint -- includes/Renderer/CheckoutHealth.php includes/Helpers/StoreEnvironmentHelper.php includes/Engine/Admin/SafeModeNotice.php includes/YayCheckout.php` → no errors.
Manual (safe mode on): create `wp-content/mu-plugins/safe-mode-check.php` containing `<?php add_filter( 'yaycheckout_safe_mode_problem', function () { return 'Test reason.'; } );`. Open `/wp-admin/` → warning *"Shoppers are getting the WooCommerce checkout: Test reason."*; Compatibility › Rendering → `Native checkout — Test reason.`; `/checkout/` → WooCommerce's checkout. Turn safe mode off in Settings › General → notice *"Your checkout layout may not work: Test reason."*, Rendering → `YayCheckout layout — Test reason.`, `/checkout/` → the layout. Delete the mu-plugin → no notice, Rendering → `YayCheckout layout`. Turn safe mode back on.

- [ ] **Step 6: Commit**

Stage: the nine files above.
Message: `feat(admin): report safe mode problems on Compatibility and in an admin notice`

### Task 6: Docs and end-to-end check

**Files:**
- Modify: `docs/architecture.md` (§ Storefront bullet on `Renderer\CheckoutPage`, § Compatibility scan)
- Modify: `.claude/rules/php-standards.md` (Storefront bullet)

- [ ] **Step 1: Docs**

`architecture.md` § Storefront: `CheckoutPage` takes over when a layout resolves and, with safe mode on, `CheckoutHealth::problem()` is empty and the layout has no recorded render failure; a render failure falls back in the same request and trips `yaycheckout_render_failure` until the layout's content changes. § Compatibility scan: `conflict` status, `fixed_in`, `cached()` for the storefront, `store.checkout_problem`. `php-standards.md`: "three exceptions" → four, adding "`CheckoutPage` records a render failure in `yaycheckout_render_failure`".

- [ ] **Step 2: End-to-end verification (spec criterion 15)**

On yaycheckout.test with safe mode on, add `throw new \Exception( 'boom' );` at the top of `packages/blocks/src/order-summary/render.php`, run `pnpm build:blocks`, open `/checkout/` as a guest: WooCommerce's checkout shows; place an order with Cash on delivery: it succeeds; wp-admin shows the notice with *boom*; Compatibility › Rendering reads `Native checkout — boom`. Remove the throw, rebuild, save a content change to the active layout: `/checkout/` shows the layout again and the notice is gone.

- [ ] **Step 3: Commit**

Stage: `docs/architecture.md`, `.claude/rules/php-standards.md`
Message: `docs(architecture): document safe mode fallback`
