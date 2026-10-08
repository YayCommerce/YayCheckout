# Group "Hide When Empty" Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-08
**Goal:** A core Group in a checkout layout hides while every YayCheckout block the merchant ticked inside it is empty, on first paint and after each `updated_checkout`.
**Spec:** [docs/specs/2026-10-08-group-hide-when-empty-design.md](../specs/2026-10-08-group-hide-when-empty-design.md)
**Architecture:** Watchable blocks print `data-yaycheckout-block-id` and, when empty, `data-yaycheckout-empty="1"` on their wrapper; Shipping methods and Payment also mark the fragment node `CheckoutFragments` swaps. A `render_block_core/group` filter (`Renderer\HideWhenEmpty`) decides first paint from those markers, and a jQuery script re-reads them after `updated_checkout`, so PHP alone defines "empty". Chosen over re-rendering the Group as a fragment, which would wipe typed input.
**Touches:** new `includes/Renderer/HideWhenEmpty.php`; `includes/YayCheckout.php`; `includes/Renderer/CheckoutFragments.php`; `includes/Blocks/BlockRegistry.php`; `packages/blocks/src/{shipping-methods,payment,express-pay,slot,coupon-form,login,create-account}/{block.json,render.php}`; new `packages/blocks/src/shared/hide-when-empty.tsx`; `packages/blocks/src/checkout/index.ts`; new `assets/js/checkout-hide-when-empty.js`; `assets/css/checkout-page.css`; `docs/architecture.md`. No shared code across plugins.
**Tests:** `tests/Integration/Renderer/HideWhenEmptyTest.php`, Integration suite (`composer test`): guards that a Group hides only when every watched descendant is empty, ignores stale ids, and leaves an unset Group untouched (spec AC 10). Accepted with the spec.

## Global constraints

- PHP 7.4 syntax only: no enums, `match`, `readonly`, promoted properties, `?->`, named arguments or union types. WordPress ≥ 6.5, so `WP_HTML_Tag_Processor` is available.
- Free feature. No `Plan::is_pro()` gating, and nothing under `includes/Pro/`.
- Edit block inputs in `packages/blocks/src/`, never `assets/dist/`. Rebuild with `pnpm build:blocks`.
- New attributes are read with defaults in `render.php`. No block deprecations.
- The TypeScript copy of the hide rule carries `/** Mirrors HideWhenEmpty::hidden() in PHP. */`.
- Storage keys and class names are class constants on `HideWhenEmpty`.
- Branch from `dev/edd` as `trace/feat/group-hide-when-empty`. Conventional Commits; each commit ends with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Stage files by name.
- `composer test` held no tests before this plan; `composer lint` runs the Pro boundary check then phpcs. Lint only changed files with `composer lint -- <files>`.

## Review focus

- **Required field inside a hidden Group:** a shopper is blocked by an error for a field they can't see. Only the editor warns (spec AC 8). Task 4 Step 4 checks the warning.
- **Payment hidden while payment is due:** the worst failure. If the `#payment` marker isn't refreshed by the fragment, a Group stays hidden after a coupon is removed. Task 3 Step 3 removes the coupon and checks the Group returns.
- **Copied block shares a `blockId`:** two blocks with one id make the Group follow whichever is non-empty. Task 4 Step 5 duplicates a watched block and checks the copy gets a new id.
- **Nested Groups:** an inner Group's hide must not hide the outer Group's other content. Task 2's test covers only one level; Task 5 Step 2 checks a nested pair by hand.

---

### Task 1: Watchable blocks print emptiness markers

**Files:**
- Create: `includes/Renderer/HideWhenEmpty.php` (constants and `wrapper_attributes()` only; the filter comes in Task 2)
- Modify: `includes/Renderer/CheckoutFragments.php` (`shipping_rates_html()`, `payment_methods_html()`, new helpers)
- Modify: `packages/blocks/src/{shipping-methods,payment,express-pay,slot,coupon-form,login,create-account}/block.json` and `render.php`

**Interfaces:**
- Produces, on `YayCheckout\Renderer\HideWhenEmpty` (uses `SingletonTrait`):
  - `ATTRIBUTE = 'yaycheckoutHideWhenEmpty'`, `HIDDEN_CLASS = 'yaycheckout-hidden-when-empty'`, `SUPPORT = 'yaycheckoutWatchable'`, `BLOCK_ID_PATTERN = '/^[a-z0-9]{8}$/'`, `SCRIPT = 'yaycheckout-hide-when-empty'`
  - `BLOCK_ID_ATTR = 'data-yaycheckout-block-id'`, `EMPTY_ATTR = 'data-yaycheckout-empty'`, `LIVE_ATTR = 'data-yaycheckout-live'`, `GROUP_ATTR = 'data-yaycheckout-hide-when-empty'`
  - `public static function wrapper_attributes( array $attributes, bool $empty ): array`: returns `[ BLOCK_ID_ATTR => blockId ]` only when `$attributes['blockId']` is a string matching `BLOCK_ID_PATTERN`, plus `[ EMPTY_ATTR => '1' ]` when `$empty`; `[]` otherwise. Callers merge it into `get_block_wrapper_attributes()`'s extras.
- Produces, on `CheckoutFragments`: `public static function shipping_empty(): bool` (true when there is no cart or `! ( needs_shipping() && show_shipping() )`), `public static function payment_empty(): bool` (true when there is no cart or `! needs_payment()`).
- Produces: every watchable `block.json` has attribute `"blockId": { "type": "string", "default": "" }` and `"supports": { …, "yaycheckoutWatchable": true }`.

- [x] **Step 1: Create `HideWhenEmpty` with the constants and `wrapper_attributes()` above**

Docblock says it owns what "empty" means for a Group's watched blocks; the per-block rules live in each `render.php` and `CheckoutFragments`.

- [x] **Step 2: Add `shipping_empty()` and `payment_empty()` to `CheckoutFragments`, and mark the fragment roots**

`shipping_rates_html()`: the `SHIPPING_RATES_CLASS` div always carries `LIVE_ATTR="1"`, and `EMPTY_ATTR="1"` when `shipping_empty()`; its table condition becomes `! self::shipping_empty()`. `payment_methods_html()`: the `#payment` div gets the same two attributes from `payment_empty()`. Fragments carry the markers, so the live script never needs PHP's rule.

- [x] **Step 3: Add `blockId` and `yaycheckoutWatchable` to the seven `block.json` files**

- [x] **Step 4: Pass `HideWhenEmpty::wrapper_attributes()` into each `render.php` wrapper**

- Shipping methods: `$empty = CheckoutFragments::shipping_empty()`. Payment: `payment_empty()`.
- Express pay: buffer `wc_get_pay_buttons()` before the wrapper; `$empty` is no cart or `'' === trim( $buffer )`; echo the buffer inside the wrapper.
- Slot: buffer `do_action( 'yaycheckout_slot_' . $name )` the same way; `$empty` when the name is `''` or the trimmed buffer is `''`.
- Coupon form, Login, Create account: `$empty = false` (they print nothing when empty, and a missing wrapper counts as empty). Login keeps its `array()` style.

- [x] **Step 5: Rebuild and lint**

Run: `pnpm build:blocks` then `composer lint -- includes/Renderer/HideWhenEmpty.php includes/Renderer/CheckoutFragments.php packages/blocks/src/*/render.php`
Expected: build succeeds; lint reports no errors.

- [x] **Step 6: Verify the markup**

In a layout's code editor add `"blockId":"abcd1234"` to the Payment block and `"blockId":"slot0001"` to a Slot with an unhooked name; activate; load checkout with a paid product.
Expected: page source has `data-yaycheckout-block-id="abcd1234"` without `data-yaycheckout-empty` on `.yaycheckout-payment`, `data-yaycheckout-live="1"` on `#payment`, and `data-yaycheckout-block-id="slot0001" data-yaycheckout-empty="1"` on the slot. A Payment block without `blockId` prints neither block-id nor empty.

- [x] **Step 7: Commit**

Stage: `includes/Renderer/HideWhenEmpty.php`, `includes/Renderer/CheckoutFragments.php`, the 14 `block.json` / `render.php` files above.
Message: `feat(blocks): mark watchable blocks as empty`

### Task 2: Group hides on first paint

**Files:**
- Modify: `includes/Renderer/HideWhenEmpty.php`
- Modify: `includes/YayCheckout.php` (bootstrap after `CheckoutFragments::get_instance()`)
- Modify: `includes/Blocks/BlockRegistry.php` (`register_view_scripts()`)
- Modify: `assets/css/checkout-page.css`
- Create: `assets/js/checkout-hide-when-empty.js` (empty `jQuery(function ($) {});` shell with its docblock; filled in Task 3)
- Test: `tests/Integration/Renderer/HideWhenEmptyTest.php`

**Interfaces:**
- Consumes: Task 1's constants and markers.
- Produces:
  - `protected function __construct()` adds filter `render_block_core/group` → `filter_group( $block_content, $block )`, priority 10, 2 args.
  - `public function filter_group( $block_content, $block ): string`
  - `public static function hidden( string $html, array $ids ): bool`: true when `$ids` isn't empty and, for every id, no element in `$html` carries `BLOCK_ID_ATTR` = id without also carrying `EMPTY_ATTR`. A missing id counts as empty.
  - Script handle `HideWhenEmpty::SCRIPT` → `assets/js/checkout-hide-when-empty.js`, deps `[ 'jquery', 'wc-checkout' ]`, `YAYCHECKOUT_VERSION`, in footer.
  - CSS: `.yaycheckout-hidden-when-empty { display: none !important; }`

`filter_group()` algorithm: read `$block['attrs'][ ATTRIBUTE ]`; keep unique strings matching `BLOCK_ID_PATTERN`. Keep only ids that a descendant in `LayoutMarkupReader::flatten( $block['innerBlocks'] )` carries as `attrs['blockId']`, where that descendant's registered type has `block_has_support( $type, [ self::SUPPORT ] )`. When none remain, return `$block_content` unchanged. Otherwise, on the first tag via `WP_HTML_Tag_Processor`, set `GROUP_ATTR` to the ids joined by a space, `add_class( HIDDEN_CLASS )` when `hidden()`, then `wp_enqueue_script( self::SCRIPT )` and return the updated HTML.

- [x] **Step 1: Write the failing tests**

```php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Renderer\HideWhenEmpty;

/** A Group hides only when every block it watches is empty; anything else leaves it as it was. */
class HideWhenEmptyTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        // An earlier test's Group enqueued it; the unset-Group test must start clean.
        wp_dequeue_script( HideWhenEmpty::SCRIPT );
    }

    private function group( array $watch, string $inner ): string {
        $attrs = [] === $watch ? '' : ' ' . wp_json_encode( [ HideWhenEmpty::ATTRIBUTE => $watch ] );

        return do_blocks( '<!-- wp:group' . $attrs . ' --><div class="wp-block-group">' . $inner . '</div><!-- /wp:group -->' );
    }

    private function slot( string $id, string $name ): string {
        return '<!-- wp:yaycheckout/slot {"slotName":"' . $name . '","blockId":"' . $id . '"} /-->';
    }

    public function test_hides_when_every_watched_block_is_empty(): void {
        $html = $this->group( [ 'aaaa0001', 'bbbb0002' ], $this->slot( 'aaaa0001', 'one' ) . $this->slot( 'bbbb0002', 'two' ) );

        $this->assertStringContainsString( HideWhenEmpty::HIDDEN_CLASS, $html );
        $this->assertStringContainsString( 'data-yaycheckout-hide-when-empty="aaaa0001 bbbb0002"', $html );
    }

    public function test_shows_when_one_watched_block_has_content(): void {
        add_action( 'yaycheckout_slot_two', static function () { echo '<p>Filled</p>'; } );

        $html = $this->group( [ 'aaaa0001', 'bbbb0002' ], $this->slot( 'aaaa0001', 'one' ) . $this->slot( 'bbbb0002', 'two' ) );

        $this->assertStringNotContainsString( HideWhenEmpty::HIDDEN_CLASS, $html );
        $this->assertStringContainsString( 'data-yaycheckout-hide-when-empty="aaaa0001 bbbb0002"', $html );
    }

    public function test_ignores_ids_that_are_not_descendants(): void {
        $html = $this->group( [ 'zzzz9999' ], $this->slot( 'aaaa0001', 'one' ) );

        $this->assertStringNotContainsString( HideWhenEmpty::HIDDEN_CLASS, $html );
        $this->assertStringNotContainsString( 'data-yaycheckout-hide-when-empty', $html );
    }

    public function test_group_without_setting_is_unchanged(): void {
        $html = $this->group( [], $this->slot( 'aaaa0001', 'one' ) );

        $this->assertStringNotContainsString( HideWhenEmpty::HIDDEN_CLASS, $html );
        $this->assertStringNotContainsString( 'data-yaycheckout-hide-when-empty', $html );
        $this->assertFalse( wp_script_is( HideWhenEmpty::SCRIPT, 'enqueued' ) );
    }
}
```

- [x] **Step 2: Run the tests to confirm they fail**

Run: `composer test -- --filter HideWhenEmptyTest`
Expected: FAIL, the hide and group-attribute assertions fail because nothing filters `core/group` yet.

- [x] **Step 3: Implement `filter_group()` and `hidden()`, bootstrap the class, register the script, add the CSS rule**

- [x] **Step 4: Run the tests to confirm they pass**

Run: `composer test -- --filter HideWhenEmptyTest`
Expected: PASS, 4 tests.

- [x] **Step 5: Lint**

Run: `composer lint -- includes/Renderer/HideWhenEmpty.php includes/YayCheckout.php includes/Blocks/BlockRegistry.php tests/Integration/Renderer/HideWhenEmptyTest.php`
Expected: no errors.

- [x] **Step 6: Verify first paint on the storefront (spec AC 2, 3)**

In a layout's code editor wrap a heading and Shipping methods (`"blockId":"ship0001"`) in `<!-- wp:group {"yaycheckoutHideWhenEmpty":["ship0001"]} -->`. Load checkout with only a virtual product.
Expected: the Group has class `yaycheckout-hidden-when-empty` and isn't visible. With a physical product and no address: the Group is visible.

- [x] **Step 7: Commit**

Stage: `includes/Renderer/HideWhenEmpty.php`, `includes/YayCheckout.php`, `includes/Blocks/BlockRegistry.php`, `assets/css/checkout-page.css`, `assets/js/checkout-hide-when-empty.js`, `tests/Integration/Renderer/HideWhenEmptyTest.php`
Message: `feat(checkout): hide a group when its watched blocks are empty`

### Task 3: Live re-check after `updated_checkout`

**Files:**
- Modify: `assets/js/checkout-hide-when-empty.js`

**Interfaces:**
- Consumes: Task 1's attribute names; Task 2's `GROUP_ATTR`, `HIDDEN_CLASS` and script handle.
- Produces: a handler on `$( document.body ).on( 'updated_checkout' )`.

- [x] **Step 1: Implement the handler**

1. For each `[data-yaycheckout-live]` node, set or remove `data-yaycheckout-empty` on its closest `[data-yaycheckout-block-id]` to match the node.
2. For each `[data-yaycheckout-hide-when-empty]` Group, split the ids on spaces and toggle `yaycheckout-hidden-when-empty`. The Group is hidden when every id has no `[data-yaycheckout-block-id="<id>"]` inside the Group without `data-yaycheckout-empty`.

Put `/** Mirrors HideWhenEmpty::hidden() in PHP. */` above the step-2 function, and name the attribute constants in the file docblock.

- [x] **Step 2: Lint**

Run: `npx --no-install prettier --check ../../assets/js/checkout-hide-when-empty.js` from `apps/admin`.
Expected: no findings.

- [x] **Step 3: Verify live toggling (spec AC 4)**

Create a 100% percentage coupon `FREE100`. In a layout, wrap a heading and Payment (`"blockId":"pay00001"`) in a Group watching `pay00001`, and add a Coupon form outside it. Load checkout with a paid product.
Expected: the Group is visible. Apply `FREE100`: after the update, the Group gets `yaycheckout-hidden-when-empty`. Remove the coupon from the totals: the Group shows again. There's no reload either way.

- [x] **Step 4: Commit**

Stage: `assets/js/checkout-hide-when-empty.js`
Message: `feat(checkout): re-check hidden groups after checkout updates`

### Task 4: Editor panel on core/group

**Files:**
- Create: `packages/blocks/src/shared/hide-when-empty.tsx`
- Modify: `packages/blocks/src/checkout/index.ts` (add `import '../shared/hide-when-empty';`)

**Interfaces:**
- Consumes: the `blockId` attribute and `yaycheckoutWatchable` support from Task 1; `editorData.postType` from `packages/blocks/src/shared/editor-data.ts`.
- Produces:
  - `addFilter( 'blocks.registerBlockType', 'yaycheckout/hide-when-empty/attribute', … )`, which adds `yaycheckoutHideWhenEmpty: { type: 'array', items: { type: 'string' }, default: [] }` to `core/group` only.
  - `addFilter( 'editor.BlockEdit', 'yaycheckout/hide-when-empty/panel', … )`, which wraps `BlockEdit` and renders `<InspectorControls><PanelBody title={ __( 'Hide when empty', 'yaycheckout' ) }>` for `core/group`, only when `select( editorStore ).getCurrentPostType() === editorData.postType`.
  - `function newBlockId(): string`, which returns 8 characters from `[a-z0-9]` and matches `HideWhenEmpty::BLOCK_ID_PATTERN` (comment: `Mirrors HideWhenEmpty::BLOCK_ID_PATTERN in PHP.`).

Panel behaviour:
- List descendants from `getClientIdsOfDescendants( [ clientId ] )` whose block type has `hasBlockSupport( name, 'yaycheckoutWatchable' )`.
- Show one `CheckboxControl` each, labelled with the block type's `title`, plus ` · <slotName>` for Slot.
- With none, show `__( 'Add a Shipping methods, Payment, Express pay, Slot, Coupon form, Login or Create account block to this group.', 'yaycheckout' )`.
- Ticking a block whose `blockId` is `''` or used by another block anywhere in the editor (`getClientIdsWithDescendants()`) first calls `updateBlockAttributes( child, { blockId: newBlockId() } )`, then adds the id.
- On render, prune attribute ids that no listed descendant carries.
- When the attribute is non-empty and a descendant `yaycheckout/field` or `yaycheckout/email` has `required: true`, show `<Notice status="warning" isDismissible={ false }>` with `__( "This group contains required fields. If it hides, shoppers can't complete checkout.", 'yaycheckout' )`.

- [x] **Step 1: Implement the attribute filter, the panel and `newBlockId()`**

- [x] **Step 2: Build and type-check**

Run: `pnpm build:blocks`
Expected: the build succeeds with no errors. The blocks build transpiles TypeScript without type-checking it, so Steps 3 to 5 are the real check.

- [x] **Step 3: Verify picking (spec AC 1)**

Open a layout in the editor. Add a Group containing a heading, Payment and a Slot named `promo`, and select the Group.
Expected: a "Hide when empty" panel lists "Payment" and "Slot · promo", and not the heading. Tick Payment and save. The code editor then shows `"yaycheckoutHideWhenEmpty":["<8 chars>"]` on the Group and the same `"blockId"` on Payment. In a regular post's editor, a Group shows no such panel.

- [x] **Step 4: Verify the required-field warning (spec AC 8)**

Move a required Field into that Group.
Expected: the warning notice shows. Untick every block: the notice is gone.

- [x] **Step 5: Verify duplicate ids (spec AC 7)**

Duplicate the watched Payment inside the Group (⌘⇧D), then tick the copy.
Expected: the copy's `blockId` in the code editor differs from the original's, and both ids are in the Group's attribute.

- [x] **Step 6: Commit**

Stage: `packages/blocks/src/shared/hide-when-empty.tsx`, `packages/blocks/src/checkout/index.ts`
Message: `feat(blocks): add hide-when-empty panel to group`

### Task 5: Document and verify end to end

**Files:**
- Modify: `docs/architecture.md` (one bullet under "Storefront", after "Extra fields and fragments")

**Interfaces:**
- Consumes: everything above.

- [x] **Step 1: Add the architecture bullet**

Add a **Hide when empty** bullet. It covers which blocks are watchable (the `yaycheckoutWatchable` support), what empty means for each, the `blockId` / `yaycheckoutHideWhenEmpty` attributes, the `render_block_core/group` filter, the `data-yaycheckout-live` fragment markers, and the `updated_checkout` script. It names `HideWhenEmpty::hidden()` as the owner.

- [x] **Step 2: Verify nested Groups and the unset case (spec AC 6, 9; Review focus)**

In one layout, put an inner Group watching an unhooked Slot inside an outer Group that watches Payment. Add a third Group with no setting.
Expected: with a paid product, the inner Group is hidden and the outer Group is visible. The third Group's markup has no `data-yaycheckout-hide-when-empty`. On a layout with no watched Group, the page source doesn't load `checkout-hide-when-empty.js`.

- [x] **Step 3: Verify coupon and express pay (spec AC 5)**

Watch a Coupon form and an Express pay in one Group, with WooCommerce coupons disabled and no express gateway.
Expected: the Group is hidden. Enable coupons and reload: the Group is visible.

- [x] **Step 4: End-to-end (spec AC 11)**

Use a layout with a Group around a heading and Payment, watching Payment, plus a Coupon form. Load checkout with a paid product.
Expected:
- The Group is visible.
- Apply `FREE100`: the Group disappears.
- Remove the coupon: it reappears.
- Apply `FREE100` again and reload: the page source already has `yaycheckout-hidden-when-empty` on the Group, with no flash.
- Place the order: it succeeds and the order-received page shows.

- [x] **Step 5: Run all checks**

Run: `composer lint` and `composer test`
Expected: both exit 0. `composer test` reports the 4 `HideWhenEmptyTest` tests passing.

- [x] **Step 6: Commit**

Stage: `docs/architecture.md`, `docs/plans/2026-10-08-group-hide-when-empty.md` (Status → `done YYYY-MM-DD`)
Message: `docs(architecture): describe group hide-when-empty`
