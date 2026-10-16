# WooCommerce Hook block Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. On approval, copy this plan to `docs/plans/2026-10-16-woocommerce-hook-block.md` in the worktree `../yaycheckout-wc-hooks` and rename its branch first: `git -C ../yaycheckout-wc-hooks branch -m trace/feat/wc-hook-block` (no commits on it yet). All work happens in that worktree.

**Status:** done 2026-10-16
**Goal:** A Free `yaycheckout/wc-hook` block fires one chosen WooCommerce classic-checkout action wherever the merchant places it, so plugin output lands in its own block instead of inside (and breaking) another block's markup.
**Spec:** none, planned from the request and the hook scout in this session.
**Architecture:** One block with a hook picker, backed by one PHP catalogue `Renderer\WcHooks` (allowed hooks, labels, args, Woo core callbacks to skip, which hooks refresh on AJAX). Blocks that fire a hook in place today keep doing so unless the served layout has a Hook block for that hook (fallback), so no layout loses or doubles plugin output. Labels reach the editor as inline data from PHP, so there is no TypeScript copy of the catalogue.
**Touches:** new `packages/blocks/src/wc-hook/`, new `includes/Renderer/WcHooks.php`; `includes/Services/LayoutMarkupReader.php`, `includes/Renderer/CheckoutFragments.php`, `includes/Blocks/BlockRegistry.php`, block renders of express-pay, create-account, additional-fields, place-order, terms; `packages/blocks/src/shared/block-icons.tsx`, `packages/blocks/src/shared/allowed-blocks.ts` (shared: also the SPA's `@blocks-shared`, so type-check the SPA); `docs/architecture.md`.
**Tests:**
- `tests/Integration/Renderer/WcHooksTest.php` (`composer test`): an in-place hook is skipped when a Hook block for it is in the served layout and fired otherwise; the Hook block never prints Woo's core callbacks (login/coupon form, billing form, review table) and restores them afterwards.
- `tests/Integration/Blocks/WcHookRenderTest.php` (`composer test`): render prints nothing for an unknown or unhooked hook, wraps output otherwise, always prints the live root for a refresh hook.

## Requirements

- **Goal:** the merchant inserts "WooCommerce Hook", picks a hook, and whatever plugins print on that hook appears there, in a block of its own that hides when empty.
- **Out of scope:** the 3 login hooks (they add inputs to the login dialog's own `<form>`, so moving them breaks the plugin), `woocommerce_after_shipping_rate` (fires per rate row), `woocommerce_checkout_terms_and_conditions` (its core callbacks are the Terms block's content); Woo's AJAX `woocommerce_checkout_payment()` re-firing submit/terms hooks in discarded markup (existing, separate issue); adding Hook blocks to bundled templates; WooCommerce notices (`woocommerce_output_all_notices` stays unprinted, as today).
- **Acceptance criteria:**
  1. The picker offers exactly the 30 hooks in Task 1's catalogue, grouped Page / Customer details / Order review / Payment.
  2. A Hook block prints the hook's third-party output inside `div.wp-block-yaycheckout-wc-hook`; with no output it prints nothing (non-refresh hooks) or a hidden `[data-yaycheckout-empty]` wrapper (refresh hooks).
  3. Placing a Hook block for a hook that another block fires today (e.g. `woocommerce_review_order_before_submit`) moves the output: Place Order no longer prints it, the Hook block does, once.
  4. A Hook block for `woocommerce_before_checkout_form`, `woocommerce_checkout_billing`, `woocommerce_checkout_shipping` or `woocommerce_checkout_order_review` prints no login form, coupon form, notices wrapper, billing/shipping form, review table or payment section.
  5. A Hook block for a `review_order_*` cart/shipping/total hook re-renders on every `update_order_review`.
  6. End to end: with the probe mu-plugin (Task 6), a fresh layout with a Hook block per group shows each probe once, at the Hook block, on `http://yaydevs.local/?page_id=9`, and still after changing the shipping country.
- **Decided with the user:** one block with a hook picker; in-place hooks fall back (fire in place unless a Hook block for them is placed); all positional hooks, Woo core callbacks skipped; ships in Free; both tests above.

## Global constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types (CLAUDE.md rule 4).
- Never hand-edit `assets/dist/`, `vendor/`, `templates/layouts/*/preview.html`; edit `packages/blocks/src/wc-hook/render.php`, then `pnpm build:blocks`.
- Free code only: nothing under `includes/Pro/`, no `YayCheckout\Pro` reference (`composer lint` runs `scripts/check-pro-boundary.sh`).
- Read `docs/conventions.md` before adding the PHP file and the editor data global; follow its file-name and comment rules.
- `pnpm --filter @yaycheckout/admin-page lint` fails on `dev/edd` today: expect no new failures, not a pass.
- Commits: Conventional Commits, trailer `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`; stage files by name; commit only when the user asks.

## Review focus

- A Hook block hidden by a Visibility rule still counts as placed, so the in-place copy stays off and the output disappears while hidden. Expected and documented (Task 6), not worked around.
- Two Hook blocks with the same hook fire it twice; a plugin adding inputs there posts duplicates. The editor warns (Task 2 Step 3).
- Detaching core callbacks must restore them even if a callback throws, or Woo's own AJAX later prints no review table: `try`/`finally` (Task 1, covered by WcHooksTest).
- `WC()->checkout()->checkout_form_billing` is an object callback: remove it with the same `WC()->checkout()` instance, else `remove_action` silently misses and the billing form prints twice (WcHooksTest).
- Express Pay: placing `woocommerce_checkout_before_customer_details` moves wallet buttons (`wc_get_pay_buttons`, Stripe) into the Hook block and leaves Express Pay empty. Documented (Task 6).

---

### Task 1: Hook catalogue and firing (`Renderer\WcHooks`)

**Files:**
- Create: `includes/Renderer/WcHooks.php`
- Modify: `includes/Services/LayoutMarkupReader.php` (add `wc_hooks()` next to `slots()`, :88)
- Test: `tests/Integration/Renderer/WcHooksTest.php`

**Interfaces:**
- Produces (all `public static`, class `YayCheckout\Renderer\WcHooks`):
  - `const BLOCK = 'yaycheckout/wc-hook'`
  - `const HOOKS` — `hook => [ 'group' => 'page'|'customer'|'review'|'payment', 'checkout_arg' => bool, 'refresh' => bool ]`, exactly these 30, in this order:
    - page: `woocommerce_before_checkout_form`*, `woocommerce_after_checkout_form`*
    - customer: `woocommerce_checkout_before_customer_details`, `woocommerce_checkout_after_customer_details`, `woocommerce_checkout_billing`, `woocommerce_checkout_shipping`, `woocommerce_before_checkout_billing_form`*, `woocommerce_after_checkout_billing_form`*, `woocommerce_before_checkout_shipping_form`*, `woocommerce_after_checkout_shipping_form`*, `woocommerce_before_checkout_registration_form`*, `woocommerce_after_checkout_registration_form`*, `woocommerce_before_order_notes`*, `woocommerce_after_order_notes`*
    - review: `woocommerce_checkout_before_order_review_heading`, `woocommerce_checkout_before_order_review`, `woocommerce_checkout_order_review`, `woocommerce_checkout_after_order_review`, `woocommerce_review_order_before_cart_contents`†, `woocommerce_review_order_after_cart_contents`†, `woocommerce_review_order_before_shipping`†, `woocommerce_review_order_after_shipping`†, `woocommerce_review_order_before_order_total`†, `woocommerce_review_order_after_order_total`†
    - payment: `woocommerce_review_order_before_payment`, `woocommerce_review_order_after_payment`, `woocommerce_review_order_before_submit`, `woocommerce_review_order_after_submit`, `woocommerce_checkout_before_terms_and_conditions`, `woocommerce_checkout_after_terms_and_conditions`
    - `*` = `checkout_arg` true (passes `WC()->checkout()`, as Woo's templates do); `†` = `refresh` true.
  - `labels(): array<string,string>` — hook => translated label (e.g. "Before order notes"), `yaycheckout` text domain. Kept in a method, not the const, because `__()` can't run in a const.
  - `is_allowed( string $hook ): bool`
  - `core_callbacks( string $hook ): array` — callbacks to detach: `woocommerce_before_checkout_form` → `woocommerce_checkout_login_form`, `woocommerce_checkout_coupon_form`, `woocommerce_output_all_notices`; `woocommerce_checkout_billing` → `[ WC()->checkout(), 'checkout_form_billing' ]`; `woocommerce_checkout_shipping` → `[ WC()->checkout(), 'checkout_form_shipping' ]`; `woocommerce_checkout_order_review` → `woocommerce_order_review`, `woocommerce_checkout_payment`; every other hook → `[]`.
  - `fire( string $hook ): string` — `''` unless `is_allowed()`; detaches `core_callbacks()` (priority from `has_action()`), buffers `do_action()` with the checkout arg when set, restores the callbacks in `finally`.
  - `has_callbacks( string $hook ): bool` — any callback left after discounting `core_callbacks()` (the counting idea of `ExpressPay::has_buttons()`, `includes/Renderer/ExpressPay.php:24-42`).
  - `placed(): string[]` — allowed hooks of Hook blocks in `CheckoutPage::get_instance()->layout()`'s content, `[]` with no layout; memoised per layout id.
  - `is_placed( string $hook ): bool`
  - `in_place( string $hook, ...$args ): void` — `do_action( $hook, ...$args )` unless `is_placed( $hook )`.
  - `without_placed( array $hooks, callable $render ): void` — runs `$render` with the callbacks of each placed hook in `$hooks` detached, restored in `finally` (for templates that fire hooks we can't wrap: `checkout/terms.php`).
- Produces: `LayoutMarkupReader::wc_hooks( string $markup ): string[]` — unique `hook` attrs of `yaycheckout/wc-hook` blocks, via `flatten()` (:362).

- [ ] **Step 1: Write the failing tests** (use `LayoutFactory::serve()` / `forget_served_layout()` from `tests/Support/LayoutFactory.php`, as `BlockVisibilityTest` does)

```php
public function test_in_place_fires_when_no_hook_block_is_placed(): void {
    LayoutFactory::serve( LayoutFactory::checkout_markup() );
    add_action( 'woocommerce_review_order_before_submit', static function () { echo 'PROBE'; } );
    ob_start();
    WcHooks::in_place( 'woocommerce_review_order_before_submit' );
    $this->assertSame( 'PROBE', ob_get_clean() );
}

public function test_in_place_is_skipped_when_a_hook_block_is_placed(): void {
    LayoutFactory::serve( '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_review_order_before_submit"} /--><!-- /wp:yaycheckout/checkout -->' );
    add_action( 'woocommerce_review_order_before_submit', static function () { echo 'PROBE'; } );
    ob_start();
    WcHooks::in_place( 'woocommerce_review_order_before_submit' );
    $this->assertSame( '', ob_get_clean() );
    $this->assertSame( 'PROBE', WcHooks::fire( 'woocommerce_review_order_before_submit' ) );
}

public function test_fire_skips_core_callbacks_and_restores_them(): void {
    wc_load_cart();
    add_action( 'woocommerce_checkout_billing', static function () { echo 'PROBE'; } );
    $html = WcHooks::fire( 'woocommerce_checkout_billing' );
    $this->assertSame( 'PROBE', trim( $html ) );
    $this->assertNotFalse( has_action( 'woocommerce_checkout_billing', [ WC()->checkout(), 'checkout_form_billing' ] ) );

    $html = WcHooks::fire( 'woocommerce_before_checkout_form' );
    $this->assertStringNotContainsString( 'woocommerce-form-login', $html );
    $this->assertStringNotContainsString( 'checkout_coupon', $html );
    $this->assertStringNotContainsString( 'woocommerce-notices-wrapper', $html );
    $this->assertNotFalse( has_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form' ) );
}

public function test_fire_ignores_hooks_outside_the_catalogue(): void {
    add_action( 'woocommerce_login_form', static function () { echo 'PROBE'; } );
    $this->assertSame( '', WcHooks::fire( 'woocommerce_login_form' ) );
    $this->assertSame( '', WcHooks::fire( 'init' ) );
}
```

- [ ] **Step 2: Run to confirm they fail**

Run: `composer test -- --filter WcHooksTest`
Expected: FAIL, class `YayCheckout\Renderer\WcHooks` not found.

- [ ] **Step 3: Implement `LayoutMarkupReader::wc_hooks()` and `WcHooks` per Interfaces.**

- [ ] **Step 4: Run to confirm they pass**

Run: `composer test -- --filter WcHooksTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Lint**

Run: `composer lint -- includes/Renderer/WcHooks.php includes/Services/LayoutMarkupReader.php tests/Integration/Renderer/WcHooksTest.php`
Expected: no errors. Dynamic `do_action()` lines carry the same `DynamicHooknameFound` ignore comment as `CheckoutFragments.php:1036`.

- [ ] **Step 6: Commit**

Stage: `includes/Renderer/WcHooks.php`, `includes/Services/LayoutMarkupReader.php`, `tests/Integration/Renderer/WcHooksTest.php`
Message: `feat(renderer): add WooCommerce hook catalogue with in-place fallback`

### Task 2: The `yaycheckout/wc-hook` block

**Files:**
- Create: `packages/blocks/src/wc-hook/{block.json,index.ts,edit.tsx,editor.css,editor-data.ts,render.php,style.css}`
- Modify: `includes/Renderer/CheckoutFragments.php` (extract `wrap_extension_html()`), `includes/Renderer/WcHooks.php` (add `content_html()`), `includes/Blocks/BlockRegistry.php` (`ORDER` :50-86 after slot; editor data next to `editor_express_pay_data()` :236-247), `packages/blocks/src/shared/block-icons.tsx` (`blockIcons`), `packages/blocks/src/shared/allowed-blocks.ts` (`ALLOWED_IN_ADDRESS_SECTIONS`, so billing/shipping-form hooks can sit inside Billing/Shipping)
- Test: `tests/Integration/Blocks/WcHookRenderTest.php`

**Interfaces:**
- Consumes: `WcHooks::fire()`, `is_allowed()`, `HOOKS`, `labels()`, `has_callbacks()` (Task 1).
- Produces: block `yaycheckout/wc-hook`, attributes `hook` (string, default `""`) and `blockId` (string, default `""`); root class `yaycheckout-wc-hook`; refresh-hook content root `div.yaycheckout-wc-hook__content.yaycheckout-wc-hook__content--{hook}` carrying `BlockEmptiness::LIVE_ATTR` (and `EMPTY_ATTR` when empty), used by Task 4.
- Produces: `CheckoutFragments::wrap_extension_html( string $html, string $class ): string` — `<tr`-starting output in `table.shop_table.{class} > tbody`, other output in `div.{class}`, `''` for blank.
- Produces: `WcHooks::content_html( string $hook ): string` — the refresh content root with the wrapped `fire()` output inside.
- Produces: `window.yayCheckoutWcHooks = { hooks: Array<{ hook: string; label: string; group: 'page'|'customer'|'review'|'payment'; hasCallbacks: boolean }> }`, typed and read in `editor-data.ts` (pattern: `express-pay/editor-data.ts`).

- [ ] **Step 1: Write the failing render test**

```php
public function test_unknown_or_unhooked_hook_prints_nothing(): void {
    $this->assertSame( '', trim( do_blocks( '<!-- wp:yaycheckout/wc-hook {"hook":"init"} /-->' ) ) );
    $this->assertSame( '', trim( do_blocks( '<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_after_checkout_form"} /-->' ) ) );
}

public function test_output_is_wrapped_in_the_block(): void {
    add_action( 'woocommerce_after_checkout_form', static function () { echo '<p>PROBE</p>'; } );
    $html = do_blocks( '<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_after_checkout_form"} /-->' );
    $this->assertMatchesRegularExpression( '#<div[^>]*class="[^"]*yaycheckout-wc-hook[^"]*"[^>]*>\s*<p>PROBE</p>#', $html );
}

public function test_refresh_hook_prints_an_empty_live_root(): void {
    wc_load_cart();
    $html = do_blocks( '<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_review_order_before_order_total"} /-->' );
    $this->assertStringContainsString( 'yaycheckout-wc-hook__content--woocommerce_review_order_before_order_total', $html );
    $this->assertStringContainsString( 'data-yaycheckout-live="1"', $html );
    $this->assertStringContainsString( 'data-yaycheckout-empty="1"', $html );
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `pnpm build:blocks && composer test -- --filter WcHookRenderTest`
Expected: FAIL (block not registered, output empty / assertions miss).

- [ ] **Step 3: Write the block**
  - `block.json`: model on `slot/block.json` (apiVersion 3, category `yaycheckout`, `ancestor: ["yaycheckout/checkout"]`, supports `html:false`, `yaycheckoutWatchable:true`, `reusable:false`, color, border, spacing; `multiple` allowed), plus `style: file:./style-index.css`. Title "WooCommerce Hook", keywords `hook`, `plugin`, `action`.
  - `render.php`: `$html = WcHooks::fire( $hook )`. Non-refresh hook: return when `trim( $html ) === ''`, else wrap with `BlockEmptiness::wrapper( [ 'class' => 'yaycheckout-wc-hook', 'data-hook' => $hook ], $attributes )`. Refresh hook: always print the wrapper (with `EMPTY_ATTR` when empty) around `WcHooks::content_html( $hook )`. Non-refresh output also goes through `CheckoutFragments::wrap_extension_html( $html, 'yaycheckout-wc-hook__rows' )`, so plugin `<tr>` rows get a table.
  - `CheckoutFragments`: extract the table wrapping in `print_shipping_extension_rows()` (:656-676) into `wrap_extension_html()` and call it there too (shipping output must stay byte-identical).
  - `WcHooks::content_html()`: the content root around `wrap_extension_html( fire( $hook ), … )`.
  - `style.css`: `.yaycheckout-wc-hook[data-yaycheckout-empty]{display:none}`.
  - `edit.tsx`: dashed placeholder like `slot/edit.tsx` reading "WooCommerce hook: {label}" or "Choose a hook"; `InspectorControls > PanelBody "Settings" > SelectControl` (pattern `login/edit.tsx:105-115`) with an `<optgroup>` per group; a `Notice status="info"` "Nothing on this store prints here yet." when `hasCallbacks` is false; a `Notice status="warning"` "This hook is placed N times; it prints in each." when another `yaycheckout/wc-hook` in the editor's block tree has the same `hook` (`useSelect` on `core/block-editor`).
  - `BlockRegistry`: add to `ORDER`, add `editor_wc_hooks_data()` emitting `window.yayCheckoutWcHooks` via `inline_data()` on the block's `editorScript` handle.

- [ ] **Step 4: Build and run the test**

Run: `pnpm build:blocks && composer test -- --filter WcHookRenderTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Static checks**

Run: `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` → no errors. `composer lint -- includes/Blocks/BlockRegistry.php packages/blocks/src/wc-hook/render.php tests/Integration/Blocks/WcHookRenderTest.php` → no errors.

- [ ] **Step 6: Verify in the editor** (fresh throwaway layout, never an existing draft: autosave writes drafts)

Open: wp-admin → YayCheckout → new blank layout. Action: insert "WooCommerce Hook" inside Checkout and inside Billing; open the picker.
Expected: 4 groups, 30 options; placeholder shows the label; the "Nothing … prints here" notice on a hook with no plugin; inserting a second block with the same hook shows the warning on both.

- [ ] **Step 7: Commit**

Stage: the 7 files in `packages/blocks/src/wc-hook/`, `includes/Renderer/CheckoutFragments.php`, `includes/Renderer/WcHooks.php`, `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/shared/block-icons.tsx`, `packages/blocks/src/shared/allowed-blocks.ts`, `tests/Integration/Blocks/WcHookRenderTest.php`
Message: `feat(blocks): add WooCommerce Hook block`

### Task 3: In-place hooks fall back to the Hook block

**Files:**
- Modify: `packages/blocks/src/express-pay/render.php:21-22`, `packages/blocks/src/create-account/render.php:38,54`, `packages/blocks/src/additional-fields/render.php:27,49`, `packages/blocks/src/place-order/render.php:47,57`, `packages/blocks/src/terms/render.php:20`, `includes/Renderer/CheckoutFragments.php` (`print_shipping_extension_rows()` :656, `payment_methods_html()` :697/:736, `print_order_items_hook()` :1034, `order_breakdown_html()` :1175-1176)

**Interfaces:**
- Consumes: `WcHooks::in_place()`, `WcHooks::is_placed()`, `WcHooks::without_placed()` (Task 1).

- [ ] **Step 1: Replace each `do_action( <hook>, ...$args )` above with `WcHooks::in_place( <hook>, ...$args )`.** In the two buffered helpers (`print_shipping_extension_rows`, `print_order_items_hook`) return early when `WcHooks::is_placed( $hook )`. Terms: wrap the `wc_get_template( 'checkout/terms.php' )` call in `WcHooks::without_placed( [ 'woocommerce_checkout_before_terms_and_conditions', 'woocommerce_checkout_after_terms_and_conditions' ], … )`. Login and `woocommerce_after_shipping_rate` stay as they are.

- [ ] **Step 2: Run the suite**

Run: `composer test`
Expected: all pass, including `PlaceOrderRenderTest` and `WcHooksTest` (no served layout → every in-place hook still fires).

- [ ] **Step 3: Verify on the storefront** with the probe mu-plugin from Task 6 Step 1

Open: `http://yaydevs.local/?page_id=9` (a product in the cart: `?add-to-cart=29`). Action: with the active layout unchanged, view source; then add a Hook block for `woocommerce_review_order_before_submit` to a fresh layout, preview it (`PreviewToken::mint()`).
Expected: unchanged layout prints `PROBE:woocommerce_review_order_before_submit` inside `.form-row.place-order`; the preview prints it once, inside `.yaycheckout-wc-hook`, and not in `.form-row.place-order`.

- [ ] **Step 4: Lint and commit**

Run: `composer lint -- <the 6 modified files>` → no errors.
Stage: the 6 modified files. Message: `feat(blocks): let a Hook block take over hooks other blocks fire`

### Task 4: Refresh review-order Hook blocks on `update_order_review`

**Files:**
- Modify: `includes/Renderer/CheckoutFragments.php` (`fragments()` :143, `skip_review_order_table()` :128), `includes/Renderer/WcHooks.php`

**Interfaces:**
- Consumes: `WcHooks::content_html()`, `WcHooks::placed()` (Tasks 1–2).
- Produces: `WcHooks::fragments(): array<string,string>` — selector `.yaycheckout-wc-hook__content--{hook}` => `content_html( $hook )` for each placed hook with `refresh` true.

- [ ] **Step 1: Merge `WcHooks::fragments()` into the array `CheckoutFragments::fragments()` returns** (before its `array_filter`; the root is never empty so it survives). In `skip_review_order_table()`, also swap the template when any placed hook has `refresh` true, so Woo's discarded review table doesn't fire those hooks a second time.

- [ ] **Step 2: Verify the refresh**

Probe mu-plugin prints `PROBE:<hook>:<WC()->cart->get_total('edit')>` on `woocommerce_review_order_before_order_total`. Open the preview of a layout with a Hook block for that hook, no Order Summary. Action: change shipping country / apply coupon `ttt`.
Expected: the Hook block's number changes after each `update_order_review` (Network tab: response `fragments` has key `.yaycheckout-wc-hook__content--woocommerce_review_order_before_order_total`); the probe appears once per refresh in the PHP log (no second firing from Woo's table).

- [ ] **Step 3: Run tests, lint, commit**

Run: `composer test` → all pass. `composer lint -- includes/Renderer/CheckoutFragments.php includes/Renderer/WcHooks.php` → no errors.
Stage: both files. Message: `feat(renderer): refresh review-order Hook blocks with checkout fragments`

### Task 5: Docs

**Files:**
- Modify: `docs/architecture.md` — new **WooCommerce hooks** bullet near Express Pay/Slot (:75): the catalogue, fallback rule, core callbacks skipped, refresh hooks, the hidden-by-visibility and Express Pay caveats; update :68 (Terms), :72 (review-order hooks: "unless a Hook block takes it"), :126 (scan's never-fired hooks are now placeable).

- [ ] **Step 1: Edit, then commit**

Stage: `docs/architecture.md`, `docs/plans/2026-10-16-woocommerce-hook-block.md`. Message: `docs(architecture): describe the WooCommerce Hook block`

### Task 6: End-to-end check

- [ ] **Step 1: Probe mu-plugin** (outside the repo; delete after)

Create `/Users/shrief/Local-Sites/yaydevs/app/public/wp-content/mu-plugins/yaycheckout-hook-probe.php` that, on `init`, adds for each key of `\YayCheckout\Renderer\WcHooks::HOOKS` a callback echoing `<p class="probe">PROBE:{hook}</p>` (a `<tr><td>` row for the `review_order_*` cart/shipping/total hooks).

- [ ] **Step 2: Build and run**

Run: `./scripts/run.sh build` → succeeds.
Open: fresh layout from Classic two-column, add one Hook block per group (`woocommerce_before_checkout_form`, `woocommerce_checkout_billing`, `woocommerce_review_order_before_shipping`, `woocommerce_review_order_after_submit`), preview at `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>` with a product in the cart.
Expected: each of those four probes appears exactly once, inside its Hook block; no extra login form, coupon form, billing form or review table appears; probes for unplaced hooks still appear where their blocks put them today (e.g. `woocommerce_before_order_notes` in Additional Fields); after changing the shipping country, the shipping probe is still there once. Place an order: it succeeds.

- [ ] **Step 3: Clean up**

Delete the probe mu-plugin and, with the user's OK, the throwaway layout. Run `/yayplancheck docs/plans/2026-10-16-woocommerce-hook-block.md`.
