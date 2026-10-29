# Layout shortcodes and form hooks: review fixes Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract. Work on the existing unpushed branch `trace/fix/layout-shortcodes-and-form-hooks`. It amends [2026-10-18-layout-shortcodes-and-form-hooks.md](2026-10-18-layout-shortcodes-and-form-hooks.md), whose work it builds on.

**Status:** done 2026-10-18
**Goal:** Fix what the code review of the branch found. The before/after-form hooks fire only through a Hook block, which every template ships. Nested forms are renamed only in matched pairs, on first paint and on every refresh. Shortcodes run once, before Visibility, and never fatal.
**Spec:** none, planned from the `/code-review` findings on commit b52ffc2a and the answers below.
**Architecture:**
- **Form hooks:** firing `woocommerce_before_checkout_form` on every layout fired it after the fields rendered, and let field plugins (Flexible Checkout Fields) override the layout's required flags with no opt-out. So the Checkout block stops firing it. Instead, every template and a newly inserted Checkout start with a WooCommerce Hook block for it, which renders first inside the form and which the merchant can move or delete.
- **Nested forms:** the rename walks form tags with `WP_HTML_Tag_Processor` and a stack. Matched pairs become `<div>…</div>`, and unmatched tags are dropped, as a browser drops them. A fragments filter applies the same rename to the HTML that `update_order_review` swaps in.
- **Shortcodes:** the pass moves to `render_block` priority 9, ahead of `BlockVisibility` at 10. It skips blocks with inner blocks, and wraps the Shortcode block's output in a `<div>` that Visibility can mark.

**Touches:**
- `includes/Renderer/WcHooks.php`
- `packages/blocks/src/checkout/render.php`
- `packages/blocks/src/checkout/edit.tsx`
- `assets/css/checkout-base.css` (shared: every layout, storefront and editor canvas)
- the 10 `templates/layouts/**/markup.php`
- `includes/Renderer/CheckoutNestedForms.php`
- `includes/YayCheckout.php`
- `includes/Renderer/LayoutShortcodes.php`
- `docs/architecture.md`
- `.claude/skills/build-checkout-template/references/checklist.md`

**Tests:**
- `tests/Integration/LayoutTemplateCatalogueTest.php` (`composer test`, extended): guards that every template's Checkout holds a Hook block for `woocommerce_before_checkout_form`.
- `tests/Integration/Renderer/CheckoutNestedFormsTest.php` (`composer test`, updated):
  - pairs become divs, while unmatched openers and stray closers are dropped;
  - template and script text are untouched;
  - the fragments filter rewrites the fragments.
- `tests/Integration/Renderer/LayoutShortcodesTest.php` (`composer test`, updated):
  - a nested list item runs a shortcode once;
  - a Hide rule marks the Shortcode block's wrapper;
  - a null from another filter doesn't fatal;
  - a shopper value in a Paragraph live value stays text;
  - the customer is reset after each test.
- `tests/Integration/Renderer/WcHooksTest.php`: the three `unplaced` tests are removed with the method.

## Requirements

- **Goal:** the branch's four fixes hold in the general case, without the regressions the review found.
- **Out of scope:**
  - a renamed form's class or id matching plugin scripts, whose `submit` can bubble to `form.checkout` (accepted with the rename choice);
  - `wc_add_notice()` calls on a Hook-block hook staying unprinted (the Hook block's existing rule: notices stay unprinted);
  - shortcodes in blocks outside the allowlist;
  - firing the customer-details and billing/shipping hooks by default.
- **Acceptance criteria:**
  1. The Checkout block's `render.php` fires no WooCommerce hook. `WcHooks::unplaced()` is gone, and `WcHooks::in_place()` is back to its `dev/edd` body.
  2. Every `templates/layouts/**/markup.php` and `defaultTemplate()` in `packages/blocks/src/checkout/edit.tsx` start the Checkout's inner blocks with `<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_before_checkout_form"} /-->`.
  3. `neutralize()` gives these results:
     - `<form a>x</form>` → `<div a>x</div>`;
     - an opener with no closer is removed;
     - a closer with no opener is removed;
     - nested pairs both become divs;
     - forms inside `<template>` and `</form>` text inside `<script>`, comments or attributes are untouched.
  4. On `update_order_review`, every fragment value goes through `neutralize()` while a layout is served.
  5. A Shortcode block holding `[yaycurrency-switcher]` with a "Hide when logged in" rule prints the hidden class on the element that contains the switcher.
  6. A nested list item's shortcode runs once (`[[zz_probe]]` prints `[zz_probe]`).
  7. A `render_block` filter returning null for a Paragraph doesn't throw.
  8. End to end, on a fresh layout from Classic two-column with YayCurrency USD (no checkout) and EUR, and "checkout in different currency" on:
     - the YayCurrency notice shows inside the template's Hook block;
     - with a switcher shortcode inside the form, every billing field is in `form.checkout` after the first `updated_checkout`;
     - a COD order succeeds.
- **Decided with the user:**
  - Form hooks: Hook block only, shipped in every template.
  - Nested forms: pair-aware rename to `<div>`, on first paint and fragments.
  - Shortcodes: keep the allowlist and fix it.
  - Tests: all three proposed.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types (also not `mixed` or `static` types, or a `catch` without a variable).
- The WordPress floor is 6.5. `WP_HTML_Tag_Processor`'s protected `bookmarks` hold `WP_HTML_Span( start, length )` from 6.5.
- Never hand-edit `assets/dist/`. Rebuild with `pnpm build:blocks`, on Node 22 (`source ~/.nvm/nvm.sh && nvm use`).
- A template's markup must stay exactly as the editor saves it. `templates/layouts/**/preview.html` is generated: don't edit it (an empty Hook block prints nothing, so previews don't change).
- Commit messages: `type(scope): summary`. End each with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`, as CLAUDE.md requires, then the `Claude-Session:` line.
- No stored layouts exist yet, so no migration step.

## Review focus

- **An unclosed plugin `<form>` inside a column** must not swallow the column's `</div>`. Task 2's test covers this ("an opener with no closer is removed").
- **A gateway that prints a `<form>` in `payment_fields()`** is swapped in by `update_order_review` after load. Task 2's fragments test and the Task 4 end-to-end check cover this.
- **A shopper value in a live value** must never run as a shortcode. That holds only because `render_block` runs before `render_block_core/paragraph`, where `LiveValues` fills. Task 3's test covers this.
- **Steps templates** (minimal-steps, soft-steps, multi-step-wizard, material-stepper, accordion-one-page): the Hook block sits outside the Steps block, so it shows on every step. Task 1's manual check covers one of them.

---

### Task 1: Form hooks fire only through a Hook block, which templates ship

**Files:**
- Modify: `includes/Renderer/WcHooks.php` (remove `unplaced()`; restore `in_place()` from `dev/edd`)
- Modify: `packages/blocks/src/checkout/render.php` (remove the two `unplaced()` echoes and their comments)
- Modify: `assets/css/checkout-base.css` (remove the two `.yaycheckout-checkout-hook--…` rules)
- Modify: `packages/blocks/src/checkout/edit.tsx` (`defaultTemplate()`)
- Modify: all 10 `templates/layouts/**/markup.php` (`git ls-files 'templates/layouts/*markup.php'`)
- Modify: `docs/architecture.md`, `.claude/skills/build-checkout-template/references/checklist.md`
- Test: `tests/Integration/LayoutTemplateCatalogueTest.php`, `tests/Integration/Renderer/WcHooksTest.php`

**Interfaces:**
- Produces: the template convention. The Checkout's first inner block is `<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_before_checkout_form"} /-->`.

- [ ] **Step 1: Write the failing test** in `LayoutTemplateCatalogueTest`.
  ```php
  public function test_every_template_places_the_before_checkout_form_hook(): void {
      $available = array_filter( LayoutTemplateCatalogue::templates( true ), static fn( $t ) => $t['available'] );

      foreach ( array_column( $available, 'slug' ) as $slug ) {
          $this->assertContains( 'woocommerce_before_checkout_form', LayoutMarkupReader::wc_hooks( LayoutTemplateCatalogue::markup( $slug ) ), $slug );
      }
  }
  ```
  Run `composer test -- --filter test_every_template_places_the_before_checkout_form_hook`. Expect: it fails on the first slug.
- [ ] **Step 2: Add the Hook block to the templates.** In each template, insert the Hook block line from Interfaces, plus a blank line, directly after the line holding `<!-- wp:yaycheckout/checkout -->`. In `defaultTemplate()`, add `[ wcHookMeta.name, { hook: 'woocommerce_before_checkout_form' } ]` as the first entry, with `import wcHookMeta from '../wc-hook/block.json';` placed among the other meta imports in alphabetical order.
- [ ] **Step 3: Revert the default firing.**
  - Delete `WcHooks::unplaced()`.
  - Restore `in_place()`'s docblock and its unplaced branch (a plain `do_action( $hook, ...$args )`) to `git show origin/dev/edd:includes/Renderer/WcHooks.php`.
  - Remove the two `unplaced()` echoes from `checkout/render.php`, and the two CSS rules with their comment.
  - Remove from `WcHooksTest` the three `test_unplaced_*` tests and the `BEFORE` const.
- [ ] **Step 4: Update the docs.**
  - In `docs/architecture.md`, change the Fields sentence. It should say that `woocommerce_before_checkout_form` fires only where a Hook block places it, and that every template and a newly inserted Checkout place one first inside the form, so plugin notices such as YayCurrency's show.
  - Drop "Checkout, for the two around its form, through `WcHooks::unplaced()`;" and "and `in_place()` both" from the WooCommerce hooks paragraph.
  - Put `woocommerce_before_checkout_form` back as the example in the compatibility-scan line.
  - In `checklist.md`, add a check: "Checkout's first inner block is a WooCommerce Hook block for `woocommerce_before_checkout_form`."
- [ ] **Step 5: Run the checks.**
  - `composer test`. Expect: OK, no failures.
  - `composer lint -- includes/Renderer/WcHooks.php packages/blocks/src/checkout/render.php $(git ls-files 'templates/layouts/*markup.php')`. Expect: no new errors; the one existing PrecisionAlignment warning in `editorial-fashion/markup.php:123` is allowed.
  - `pnpm build:blocks`. Expect: compiled successfully.
- [ ] **Step 6: Check in the browser.**
  - Create a fresh layout from Minimal steps (Pro), not an existing draft.
  - Open it in the editor. Expected: no "block contains unexpected content" warning, and the Hook block is the first block in Checkout.
  - Preview it with YayCurrency set up as acceptance criterion 8 describes, and switch to USD. Expected: the YayCurrency notice shows above the steps and stays on step 2.
- [ ] **Step 7: Commit.** Stage the files above by name. Message: `fix(checkout): fire the before-form hook through a Hook block every template ships`.

### Task 2: Pair-aware nested form rename, on first paint and refresh

**Files:**
- Modify: `includes/Renderer/CheckoutNestedForms.php`, `includes/YayCheckout.php`
- Test: `tests/Integration/Renderer/CheckoutNestedFormsTest.php`

**Interfaces:**
- Produces:
  - `CheckoutNestedForms::neutralize( string $html ): string` (same signature).
    - Fast path: return `$html` unchanged unless `preg_match( '#</?form\b#i', $html )`.
    - Walk tags with the processor (closers visited), tracking `<template>` depth. Outside a template, push each FORM opener's span.
    - On a FORM closer: if the stack is non-empty, pop it, replace the opener's `<form` with `<div` and the closer with `</div>`. Otherwise replace the closer with `''`.
    - After the walk, replace each opener still on the stack with `''`.
    - Apply the edits from the end. `MARKER` is removed (nothing reads it).
  - `CheckoutNestedForms` uses `SingletonTrait`. Its constructor adds `woocommerce_update_order_review_fragments` at priority 30, after `CheckoutFragments` and `BlockVisibility` at 20.
  - `CheckoutNestedForms::fragments( $fragments )`: returns `$fragments` unchanged unless it is an array and `CheckoutPage::get_instance()->layout()` is not null. Otherwise it maps each string value through `neutralize()`.
  - Built in `includes/YayCheckout.php` next to `CheckoutFragments::get_instance()`.

- [ ] **Step 1: Update the tests.**
  - Replace `test_nested_form_becomes_div` and `test_closers_are_found_as_tags_not_text`, and keep `test_template_forms_are_untouched` and `test_html_without_forms_is_unchanged`.
    ```php
    public function test_matched_pairs_become_divs_and_unmatched_tags_are_dropped(): void {
        $this->assertSame( '<div method="post" class="sw"><select name="currency"></select></div><input name="b">', CheckoutNestedForms::neutralize( '<FORM method="post" class="sw"><select name="currency"></select></Form ><input name="b">' ) );
        $this->assertSame( '<div class="col"><select></select></div><div class="col2"></div>', CheckoutNestedForms::neutralize( '<div class="col"><form class="x"><select></select></div><div class="col2"></div>' ) );
        $this->assertSame( '<div>a</div><input name="b">', CheckoutNestedForms::neutralize( '<div>a</form></div><input name="b">' ) );
        $this->assertSame( '<div id="o"><div id="i"></div></div>', CheckoutNestedForms::neutralize( '<form id="o"><form id="i"></form></form>' ) );
    }
    public function test_template_and_text_are_untouched(): void {
        $template = '<template><dialog><form method="post"><input name="username"></form></dialog></template>';
        $this->assertSame( '<select></select>' . $template, CheckoutNestedForms::neutralize( '<form class="sw"><select></select>' . $template ) );
        $this->assertSame( '<div class="sw"><script>var s="</form>";</script><!-- </form> --></div>', CheckoutNestedForms::neutralize( '<form class="sw"><script>var s="</form>";</script><!-- </form> --></form>' ) );
        $this->assertSame( '<div class="form-row"></div>', CheckoutNestedForms::neutralize( '<div class="form-row"></div>' ) );
    }
    public function test_fragments_are_neutralized_while_a_layout_is_served(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        $out = CheckoutNestedForms::get_instance()->fragments( [ '.woocommerce-checkout-payment' => '<div><form><input name="x"></form></div>' ] );
        $this->assertSame( '<div><div><input name="x"></div></div>', $out['.woocommerce-checkout-payment'] );
        LayoutFactory::forget_served_layout();
    }
    ```
    The fragments test adds `tear_down()` calling `LayoutFactory::forget_served_layout()` and `use YayCheckout\Tests\Support\LayoutFactory;`. If `serve()` leaves `layout()` null outside the checkout page, set it up as `WcHooksTest` does.
  - Run `composer test -- --filter CheckoutNestedFormsTest`. Expect: the new tests fail.
- [ ] **Step 2: Implement** the interfaces above, keeping the anonymous `WP_HTML_Tag_Processor` subclass that reads the bookmark span. Update the class docblock: matched pairs become divs; unmatched tags are dropped, as browsers drop a nested opener; it runs on first paint and on refreshed fragments.
- [ ] **Step 3: Run the checks.**
  - `composer test`. Expect: OK.
  - `composer lint -- includes/Renderer/CheckoutNestedForms.php includes/YayCheckout.php tests/Integration/Renderer/CheckoutNestedFormsTest.php`. Expect: no errors.
- [ ] **Step 4: Check in the browser.**
  - Add a temporary mu-plugin that echoes `<form class="zz"><input name="zz_probe" value="1"></form>` on `woocommerce_review_order_before_payment`.
  - Preview a fresh layout and wait for `updated_checkout`.
  - Expected: `document.querySelector('[name=zz_probe]').form.name === 'checkout'` and `document.querySelectorAll('form form').length === 0`.
  - Delete the mu-plugin.
- [ ] **Step 5: Commit.** Stage the three files. Message: `fix(checkout): rename nested forms in pairs, on refresh too`.

### Task 3: Shortcode pass runs once, before Visibility, null-safe

**Files:**
- Modify: `includes/Renderer/LayoutShortcodes.php`
- Test: `tests/Integration/Renderer/LayoutShortcodesTest.php`

**Interfaces:**
- Produces:
  - `render()` adds `run_in_block` at `render_block` priority 9 (ahead of `BlockVisibility::filter_block` at 10) and removes it at 9.
  - `run_in_block( $block_content, $block ): string`: untyped parameters, `(string)` cast. It returns the content unchanged when:
    - the content has no `[`;
    - `blockName` is not in `BLOCKS`;
    - `$block['innerBlocks']` is not empty (its inner blocks ran already).
  - For `core/shortcode`, it returns `'<div class="wp-block-shortcode">' . do_shortcode( shortcode_unautop( $content ) ) . '</div>'`.
  - Otherwise it returns `do_shortcode( shortcode_unautop( $content ) )`.
  - The `BLOCKS` docblock states the inner-blocks rule instead of "each is a leaf".

- [ ] **Step 1: Update the tests.**
  - Add `use YayCheckout\Renderer\BlockVisibility;` and `use YayCheckout\Tests\Support\LayoutFactory;`.
  - Add `tear_down` resetting `WC()->customer->set_billing_first_name( '' )` when `WC()->customer` is set, and calling `LayoutFactory::forget_served_layout()`.
  - Add these tests:
    ```php
    public function test_nested_list_item_runs_once(): void {
        $html = LayoutShortcodes::render( '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>a<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>[[zz_probe]]</li><!-- /wp:list-item --></ul><!-- /wp:list --></li><!-- /wp:list-item --></ul><!-- /wp:list -->' );
        $this->assertStringContainsString( '[zz_probe]', $html );
        $this->assertStringNotContainsString( 'SC-OK', $html );
    }
    public function test_hide_rule_marks_the_shortcode_wrapper(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        wp_set_current_user( self::factory()->user->create() );
        add_shortcode( 'zz_block', static fn() => '<div class="zz-out">x</div>' );
        $html = LayoutShortcodes::render( '<!-- wp:shortcode {"yaycheckoutVisibility":{"action":"hide","rules":[{"type":"logged_in"}]}} -->' . "\n[zz_block]\n" . '<!-- /wp:shortcode -->' );
        remove_shortcode( 'zz_block' );
        // The processor writes new attributes right after the tag name, so match them in any order.
        $this->assertMatchesRegularExpression( '#<div [^>]*\bwp-block-shortcode\b[^>]*>\s*<div class="zz-out">#', $html );
        $this->assertMatchesRegularExpression( '#<div [^>]*\b' . BlockVisibility::HIDDEN_CLASS . '\b[^>]*>\s*<div class="zz-out">#', $html );
    }
    public function test_a_null_from_another_filter_does_not_fatal(): void {
        $null = static fn() => null;
        add_filter( 'render_block', $null, 8 );
        $html = LayoutShortcodes::render( '<!-- wp:paragraph --><p>[zz_probe]</p><!-- /wp:paragraph -->' );
        remove_filter( 'render_block', $null, 8 );
        $this->assertSame( '', trim( $html ) );
    }
    public function test_a_live_value_of_a_shopper_field_stays_text(): void {
        // The checkout page, a served layout that shows billing_first_name, and a Paragraph with its live value.
        ...
        WC()->customer->set_billing_first_name( '[zz_probe]' );
        $html = LayoutShortcodes::render( $markup );
        $this->assertStringContainsString( '[zz_probe]', $html );
        $this->assertStringNotContainsString( 'SC-OK', $html );
    }
    ```
  - Set up the live-value test so `CheckoutPage::takes_over()` is true:
    - create a page;
    - `update_option( 'woocommerce_checkout_page_id', $page )`;
    - `LayoutFactory::serve( $markup )` with `$markup` holding a `yaycheckout/field` for `billing_first_name` and a Paragraph holding `<span class="yaycheckout-live-value" data-yaycheckout-value="field:billing_first_name"></span>`;
    - `$this->go_to( get_permalink( $page ) )`.
    - If the visibility rule in the hide-rule test doesn't apply under `serve()`, mirror `BlockVisibilityTest::test_render_filter_stands_down_without_layout_and_on_excluded_blocks`.
  - Run `composer test -- --filter LayoutShortcodesTest`. Expect: the nested-list, hide-rule and null tests fail; the live-value test may already pass.
- [ ] **Step 2: Implement** the interfaces above.
- [ ] **Step 3: Run the checks.**
  - `composer test`. Expect: OK.
  - `composer lint -- includes/Renderer/LayoutShortcodes.php tests/Integration/Renderer/LayoutShortcodesTest.php`. Expect: no errors.
- [ ] **Step 4: Check in the browser.**
  - Give layout 2202's in-form Shortcode block (`[yaycurrency-switcher]`) a "Hide when logged in" rule, and preview it as admin.
  - Expected: the in-form switcher is hidden, the header switcher shows, and the Paragraph still shows `PARA-SC: VND`.
- [ ] **Step 5: Commit.** Stage both files. Message: `fix(renderer): run layout shortcodes once, before visibility, null-safe`.

### Task 4: End-to-end check and the PR commit

- [ ] **Step 1: Run the end-to-end check** (acceptance criterion 8).
  - Set up YayCurrency with USD (no checkout) and EUR, and "checkout in different currency" on.
  - Create a fresh layout from Classic two-column (it carries the Hook block) with `[yaycurrency-switcher]` in a Shortcode block above Contact.
  - Expected:
    - switching to USD shows the notice inside `.wp-block-yaycheckout-wc-hook`;
    - after the first `updated_checkout`, no `billing_*` field is outside `form.checkout`;
    - with the layout activated, a COD order in EUR succeeds and saves the typed billing name.
  - Then restore the active layout (`yaycheckout_active_layouts` as it was), and remove the test currencies and the YayCurrency options.
- [ ] **Step 2: Run the full checks.**
  - `composer test`. Expect: OK.
  - `composer lint`. Expect: only the existing `editorial-fashion/markup.php:123` warning.
  - `pnpm build:blocks`. Expect: compiled successfully.
- [ ] **Step 3: Mark this plan done.** Set its Status to `done YYYY-MM-DD`, then squash the branch into one commit on `origin/dev/edd`. The branch is unpushed. Use the message from b52ffc2a, updated for this plan's changes, with the `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>` and `Claude-Session:` trailers.
