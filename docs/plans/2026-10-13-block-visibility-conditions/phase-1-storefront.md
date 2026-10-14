# Phase 1: Storefront

Plan status, constraints and the interface contract are in [README.md](README.md).

**Depends on:** nothing. Branch `trace/feat/block-visibility` from `dev/edd`.

**Exit criterion:** with the attribute written by hand in the layout's Code editor, spec criteria 1–9 pass on the storefront (Task 3, Steps 4–6). `composer test -- --filter BlockVisibilityTest` passes.

---

### Task 1: Expose the cart's money amounts

**Files:**
- Modify: `includes/Renderer/LiveValues.php` (`values()`, ~:110-150)

**Interfaces:**
- Produces: `LiveValues::money(): array<string, float>` (README contract).

- [ ] **Step 1: Extract `public static function money(): array` from `values()`**

Move the `$money` array (and the `$tax` it needs) into `money()`, returning `[]` without a cart. `values()` formats `self::money()` exactly as today. Docblock: "The cart's money amounts as the cart displays them (with tax when WooCommerce shows prices with tax); also read by BlockVisibility."

- [ ] **Step 2: Verify the live values are unchanged**

Open a layout preview whose markup holds a live value, for example template 01's Place Order on `trace/fix/template-01-buttons`, or any paragraph with a `cart_subtotal` live value. Set it up with `PreviewToken::mint( <id>, 60 )`, then go to `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>` with a product in the cart.
Expected: the live values print the same text as on `dev/edd`, and `composer lint -- includes/Renderer/LiveValues.php` passes.

- [ ] **Step 3: Commit**

Stage: `includes/Renderer/LiveValues.php`
Message: `refactor(live-values): expose the cart's money amounts`

### Task 2: BlockVisibility on the server

**Files:**
- Create: `includes/Renderer/BlockVisibility.php`
- Modify: `includes/YayCheckout.php:33-35`: add `\YayCheckout\Renderer\BlockVisibility::get_instance();` after `HideWhenEmpty`.
- Modify: `assets/css/checkout-base.css`: add `.yaycheckout-hidden-by-visibility { display: none !important; }` beside the existing hidden rule (~:279), with a one-line comment naming `BlockVisibility::HIDDEN_CLASS`.
- Test: `tests/Integration/Renderer/BlockVisibilityTest.php`

**Interfaces:**
- Consumes: `LiveValues::money()` (Task 1), `CheckoutStepsProgress::layout_titles()`, `CheckoutPage::get_instance()->layout()`.
- Produces: everything under "PHP: BlockVisibility" in the README contract.

- [ ] **Step 1: Write the failing tests**

```php
class BlockVisibilityTest extends WP_UnitTestCase {

    private const STATE = [ 'needs_shipping' => true, 'has_coupon' => false, 'subtotal' => 50.0, 'logged_in' => false ];

    public function test_normalize_rejects_malformed_settings(): void {
        foreach ( [
            null,
            [],
            [ 'action' => 'show', 'rules' => [] ],
            [ 'action' => 'maybe', 'rules' => [ [ 'type' => 'logged_in' ] ] ],
            [ 'action' => 'show', 'rules' => [ [ 'type' => 'unknown' ] ] ],
            [ 'action' => 'show', 'rules' => [ [ 'type' => 'step', 'step' => 0 ] ] ],
            [ 'action' => 'show', 'rules' => [ [ 'type' => 'subtotal', 'op' => 'gte', 'amount' => -1 ] ] ],
            [ 'action' => 'show', 'rules' => [ [ 'type' => 'subtotal', 'op' => 'eq', 'amount' => 5 ] ] ],
            [ 'action' => 'show', 'rules' => [ [ 'type' => 'has_coupon' ], [ 'type' => 'has_coupon' ] ] ],
        ] as $value ) {
            $this->assertNull( BlockVisibility::normalize( $value ), wp_json_encode( $value ) );
        }
    }

    public function test_step_rules(): void {
        $last = BlockVisibility::normalize( [ 'action' => 'show', 'rules' => [ [ 'type' => 'step', 'step' => 'last' ] ] ] );
        $two  = BlockVisibility::normalize( [ 'action' => 'hide', 'rules' => [ [ 'type' => 'step', 'step' => 2 ] ] ] );

        $this->assertFalse( BlockVisibility::is_visible( $last, self::STATE, 1, 4 ) );
        $this->assertTrue( BlockVisibility::is_visible( $last, self::STATE, 4, 4 ) );
        $this->assertTrue( BlockVisibility::is_visible( $last, self::STATE, 1, 1 ) );  // One step: it is the last.
        $this->assertFalse( BlockVisibility::is_visible( $last, self::STATE, 1, 0 ) ); // No Steps block: never matches.
        $this->assertFalse( BlockVisibility::is_visible( $two, self::STATE, 2, 4 ) );
        $this->assertTrue( BlockVisibility::is_visible( $two, self::STATE, 3, 4 ) );
    }

    public function test_subtotal_boundary(): void {
        $gte = BlockVisibility::normalize( [ 'action' => 'show', 'rules' => [ [ 'type' => 'subtotal', 'op' => 'gte', 'amount' => 50 ] ] ] );
        $lt  = BlockVisibility::normalize( [ 'action' => 'show', 'rules' => [ [ 'type' => 'subtotal', 'op' => 'lt', 'amount' => 50 ] ] ] );

        $this->assertFalse( BlockVisibility::is_visible( $gte, [ 'subtotal' => 49.99 ] + self::STATE, 1, 0 ) );
        $this->assertTrue( BlockVisibility::is_visible( $gte, [ 'subtotal' => 50.0 ] + self::STATE, 1, 0 ) );
        $this->assertTrue( BlockVisibility::is_visible( $lt, [ 'subtotal' => 49.99 ] + self::STATE, 1, 0 ) );
        $this->assertFalse( BlockVisibility::is_visible( $lt, [ 'subtotal' => 50.0 ] + self::STATE, 1, 0 ) );
    }

    public function test_every_rule_must_match(): void {
        $both = BlockVisibility::normalize( [ 'action' => 'show', 'rules' => [ [ 'type' => 'logged_in' ], [ 'type' => 'has_coupon' ] ] ] );

        $this->assertFalse( BlockVisibility::is_visible( $both, [ 'logged_in' => true ] + self::STATE, 1, 0 ) );
        $this->assertTrue( BlockVisibility::is_visible( $both, [ 'logged_in' => true, 'has_coupon' => true ] + self::STATE, 1, 0 ) );
    }

    public function test_render_filter_marks_place_order_hidden_until_last_step(): void {
        $setting = '{"yaycheckoutVisibility":{"action":"show","rules":[{"type":"step","step":"last"}]}}';
        $steps   = '<!-- wp:yaycheckout/steps --><!-- wp:yaycheckout/step {"title":"A"} /--><!-- wp:yaycheckout/step {"title":"B"} /--><!-- /wp:yaycheckout/steps -->';
        LayoutFactory::serve( '<!-- wp:yaycheckout/checkout -->' . $steps . '<!-- wp:yaycheckout/place-order ' . $setting . ' /--><!-- /wp:yaycheckout/checkout -->' );

        $html = do_blocks( '<!-- wp:yaycheckout/place-order ' . $setting . ' /-->' );

        $this->assertStringContainsString( 'data-yaycheckout-visibility=', $html );
        $this->assertStringContainsString( 'yaycheckout-hidden-by-visibility', $html );
    }

    public function test_render_filter_stands_down_without_layout_and_on_excluded_blocks(): void {
        $setting = '{"yaycheckoutVisibility":{"action":"hide","rules":[{"type":"logged_in"}]}}';
        LayoutFactory::forget_served_layout();
        $this->assertStringNotContainsString( 'data-yaycheckout-visibility', do_blocks( '<!-- wp:paragraph ' . $setting . ' --><p>x</p><!-- /wp:paragraph -->' ) );

        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        $this->assertStringNotContainsString( 'data-yaycheckout-visibility', do_blocks( '<!-- wp:yaycheckout/step ' . $setting . ' /-->' ) );
    }
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `composer test -- --filter BlockVisibilityTest` (Local needs the per-site socket, `docs/known-issues.md`)
Expected: FAIL, class `BlockVisibility` not found.

- [ ] **Step 3: Implement `BlockVisibility`**

Implement the README contract in `includes/Renderer/BlockVisibility.php`:
- **Class docblock:** "Shows or hides any block in a layout by the open step, the cart and login (the block attribute yaycheckoutVisibility). PHP owns the rules; packages/blocks/src/shared/visibility-rules.ts mirrors normalize() and is_visible() for the storefront script and the editor preview."
- **`normalize()`:** returns `null` unless:
  - `action` is `show` or `hide`;
  - `rules` is a non-empty list in which each `type` is in `RULE_TYPES` and appears once;
  - `step` is an int ≥ 1 or `'last'`;
  - `subtotal` has `op` in `gte`/`lt` and a numeric `amount` ≥ 0.

  The output keeps only the known keys per rule, with `amount` cast to float and `step` to int unless `'last'`.
- **`state()`:**
  - `needs_shipping`: `WC()->cart->needs_shipping()`.
  - `has_coupon`: `! empty( WC()->cart->get_applied_coupons() )`.
  - `subtotal`: `round( LiveValues::money()['cart_subtotal'] ?? 0.0, wc_get_price_decimals() )`.
  - `logged_in`: `is_user_logged_in()`.
  - Without a cart, all false and 0.0.
- **`filter_block()`:**
  - Returns the content untouched when there is no layout, when the block name is in `EXCLUDED`, when `normalize( $block['attrs'][ self::ATTRIBUTE ] ?? null )` is null, or when the content is `''`.
  - Otherwise it sets `DATA_ATTR` to `wp_json_encode( $setting )` on the first tag (`WP_HTML_Tag_Processor`), and adds `HIDDEN_CLASS` when `! is_visible( $setting, self::state(), 1, $step_count )`.
  - `$step_count` is `count( CheckoutStepsProgress::layout_titles() )`, computed once per request.
  - It also enqueues `SCRIPT` and sets a `$rendered` flag.
- **`print_state()`:** echoes the state element only when `$rendered` is set.
- **`fragments()`:** adds `'#' . STATE_ID` only when the served layout's content contains `ATTRIBUTE`.
- **The state element:** `<script type="application/json" id="yaycheckout-visibility-state">` holding `wp_json_encode( state() + [ 'step_count' => $count ], JSON_HEX_TAG | JSON_HEX_AMP )`, as `LiveValues::values_element()` does.

- [ ] **Step 4: Run the tests to confirm they pass**

Run: `composer test -- --filter BlockVisibilityTest`
Expected: PASS, 6 tests. Then `composer lint -- includes/Renderer/BlockVisibility.php includes/YayCheckout.php` passes.

- [ ] **Step 5: Verify nothing prints for a layout without the setting**

Preview template 01's layout (no conditional block) on the storefront.
Expected: the page source has no `yaycheckout-visibility-state` and no `yaycheckout-visibility` script handle.

- [ ] **Step 6: Commit**

Stage: `includes/Renderer/BlockVisibility.php`, `includes/YayCheckout.php`, `assets/css/checkout-base.css`, `tests/Integration/Renderer/BlockVisibilityTest.php`
Message: `feat(visibility): show or hide blocks by step, cart and login on the server`

### Task 3: Live storefront script and attribute registration

**Files:**
- Create: `packages/blocks/src/shared/visibility-rules.ts`
- Create: `packages/blocks/src/visibility/view.ts`
- Create: `packages/blocks/src/visibility/index.tsx`: in this phase it only registers the attribute.
- Modify: `packages/blocks/webpack.config.js`: add the entries `'visibility/index': './src/visibility/index.tsx'` and `'visibility/view': './src/visibility/view.ts'`, and update the header comment.
- Modify: `includes/Blocks/BlockRegistry.php`:
  - In `register_view_scripts()`, register `BlockVisibility::SCRIPT` from `built_asset( 'visibility/view', true )`, with dependencies `+ [ 'jquery' ]`. Comment: "Enqueued by BlockVisibility::filter_block() for a conditional block. Skipped without a build."
  - Add `enqueue_visibility_editor()` on `enqueue_block_editor_assets`. It enqueues `built_asset( 'visibility/index' )` as handle `yaycheckout-visibility-editor` after the Checkout editor script, with translations. Unlike `enqueue_live_value_editor()`, it runs in every editor, not only the layout editor, so markup stays valid anywhere.

**Interfaces:**
- Consumes: the README storefront DOM contract and the `BlockVisibility` constants.
- Produces: everything under "TypeScript" in the README contract. Phase 2 imports `normalize`, `isVisible`, `ATTRIBUTE` and `EXCLUDED` from `shared/visibility-rules.ts`.

- [ ] **Step 1: Write `shared/visibility-rules.ts`**

Implement `normalize()` and `isVisible()` and the constants per the README contract. Each export carries `/** Mirrors BlockVisibility::<name> in PHP */`.

- [ ] **Step 2: Write `visibility/view.ts`**

- On `DOMContentLoaded`, read `#yaycheckout-visibility-state`.
- `apply()` runs on every `[data-yaycheckout-visibility]`. It takes `normalize( JSON.parse( attr ) )`, skips nulls, and toggles `HIDDEN_CLASS` by `isVisible( setting, state, openStep(), state.step_count )`.
- `openStep()` follows the README storefront DOM rule.
- Call `apply()` on load, on `document` `yaycheckout:step-change`, and on `jQuery( document.body ).on( 'updated_checkout' )` after re-reading the state element. Guard on `window.jQuery`.

- [ ] **Step 3: Write `visibility/index.tsx` (attribute only)**

Add a `blocks.registerBlockType` filter (namespace `yaycheckout/visibility`) that adds `[ ATTRIBUTE ]: { type: 'object' }` to every block name not in `EXCLUDED`. Then run `pnpm build:blocks`.
Expected: the build succeeds, and `assets/dist/blocks/visibility/view.js` and `index.js` exist.

- [ ] **Step 4: Verify steps on the storefront (spec criteria 1, 2, Review focus)**

Setup:
1. Create a throwaway layout from template 02 (`LayoutRepository::create()`).
2. In its Code editor, add `"yaycheckoutVisibility":{"action":"hide","rules":[{"type":"step","step":"last"}]}` to a new Step Button placed after the Steps block, and `"yaycheckoutVisibility":{"action":"show","rules":[{"type":"step","step":"last"}]}` to its Place Order.
3. Preview it with a product in the cart.

Expected:
- On load, the page source has Place Order with `yaycheckout-hidden-by-visibility`.
- On step 3, the Step Button is hidden and Place Order shows.
- Clicking Change back to step 1 shows the Step Button again and hides Place Order.

- [ ] **Step 5: Verify cart rules live (spec criteria 3–7)**

On the same preview, set a paragraph to each of the following in turn: *Show when* [has_coupon]; *Show when* [subtotal gte 50] (with the cart subtotal set near 50); *Hide when* [needs_shipping]; *Hide when* [logged_in].
Expected:
- Applying and removing a coupon toggles the first without a reload.
- Changing the quantity across 50 toggles the second after the totals refresh.
- The needs-shipping paragraph is hidden with a physical product.
- The logged-in paragraph is hidden while logged in, and shown in a private window as a guest.

- [ ] **Step 6: Verify the editor keeps the attribute (Review focus)**

Open the throwaway layout in the editor, change any text, and save.
Expected: the Code editor still shows every `yaycheckoutVisibility` object, and the storefront behaves as in Step 4.

- [ ] **Step 7: Commit**

Stage: `packages/blocks/src/shared/visibility-rules.ts`, `packages/blocks/src/visibility/view.ts`, `packages/blocks/src/visibility/index.tsx`, `packages/blocks/webpack.config.js`, `includes/Blocks/BlockRegistry.php`
Message: `feat(visibility): re-apply block visibility live on step and cart changes`
