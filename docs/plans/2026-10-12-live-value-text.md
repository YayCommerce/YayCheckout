# Live Value Text Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** active
**Goal:** Merchants insert live cart totals, cart counts and shopper fields as chips inside Paragraph and Heading text in the layout editor. The storefront fills them and keeps them current.
**Spec:** [docs/specs/2026-10-12-live-value-text-design.md](../specs/2026-10-12-live-value-text-design.md)
**Architecture:** A non-editable RichText object format (`contentEditable: false`, inserted with `insertObject()` as core's footnote format does) saves a tagged span. `Renderer\LiveValues` fills each span in the allow-listed blocks' rendered HTML. A JSON element, printed in the footer and swapped as an `update_order_review` fragment, carries cart values to `live-value/view.ts`; field values are read from the form as Field Value does. Field Value's field logic moves into shared helpers so both features use one copy.
**Touches:**
- (shared) `packages/blocks/src/shared/form-text.ts`, `packages/blocks/src/shared/editor-data.ts`, new `packages/blocks/src/shared/showable-fields.ts` and `packages/blocks/src/shared/canvas-style.ts`. Consumers: Field Value, Steps' summary (`controlsText()`), Layout design sidebar (`checkout/layout-design.tsx`), every block reading `editorData`, and the admin SPA through `@blocks-shared`.
- (shared) new `includes/Renderer/ShowableFields.php`. Consumers: `packages/blocks/src/field-value/render.php`, `LiveValues`.
- `includes/Blocks/BlockRegistry.php`, `includes/YayCheckout.php`, new `includes/Renderer/LiveValues.php`.
- `packages/blocks/src/field-value/{edit.tsx,view.ts,render.php}`, `packages/blocks/src/checkout/layout-design.tsx`, new `packages/blocks/src/live-value/`, new `packages/blocks/webpack.config.js`.
- `docs/architecture.md`.
**Tests:** none (display only, below CLAUDE.md's test bar; the spec's criterion 13 is the final check).

## Global constraints

- Product PHP runs on PHP 7.4 (no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types; also none of `mixed`, `static`, `never` or intersection types, a `catch` without a variable, `throw` as an expression, a trailing comma after the last declared parameter, `new` in a default value, first-class callables, `$object::class`, attributes).
- Plugin floor WordPress 6.5, WooCommerce 10.0.0.
- Free and Pro; no code in `includes/Pro/`, no `Plan::is_pro()` check.
- Edit `packages/blocks/src/`, never `assets/dist/blocks/`; rebuild with `pnpm build:blocks`.
- Every value and fallback is escaped on output (`esc_html`, `esc_attr`). Values are plain text, never HTML.
- `LiveValues::BLOCKS` and `LIVE_VALUE_BLOCKS` must name the same blocks, each with a `Mirrors` comment pointing at the other (`docs/conventions.md`).
- No new tests.
- `packages/blocks/src/shared/` is also the SPA's `@blocks-shared`: after changing it, build the blocks and type-check the SPA.
- Nothing type-checks the blocks' TypeScript; `pnpm build:blocks` is the check, then open a layout in the editor.
- Never test edits on an existing draft layout: autosave writes them. Duplicate the layout or use a new one.
- Commits follow Conventional Commits and end with the `Co-Authored-By` trailer (CLAUDE.md § Git). Branch: `trace/feat/live-value-text`.

## Review focus

- Field Value must render byte-for-byte the same after its logic moves to `ShowableFields` / `showable-fields.ts` / `fieldText()` (Task 1, Step 5).
- A Paragraph or Heading with no live value must come back unchanged, with no script or style enqueued: `fill()` returns early on the substring check (Task 3, Step 4).
- `</script>` or `<` in a value or label inside the footer JSON must not break the page: encode with `JSON_HEX_TAG | JSON_HEX_AMP` (Task 3, Step 4).
- A chip whose field leaves the layout must keep its place and never print its label on the storefront (Task 2, Step 6; Task 3, Step 4).
- Undo after inserting a chip must remove the whole chip, not half of it (Task 2, Step 6).
- `update_order_review` must not add the fragment for a layout without live values (Task 4, Step 3).
- The webpack override must keep every `block.json` entry: the build output must still list every block directory (Task 2, Step 5).

---

### Task 1: Move Field Value's field logic into shared helpers

A pure refactor; Field Value's output is unchanged.

**Files:**
- Create: `includes/Renderer/ShowableFields.php`
- Create: `packages/blocks/src/shared/showable-fields.ts`
- Modify: `packages/blocks/src/field-value/render.php`, `packages/blocks/src/field-value/edit.tsx`, `packages/blocks/src/field-value/view.ts`, `packages/blocks/src/shared/form-text.ts`

**Interfaces:**
- Produces:
  - `ShowableFields::labels( string $markup ): array` (key => label, in layout order). It holds the showable-field rules now in `field-value/render.php`: `billing_email` and Field blocks inside Billing or Shipping, minus types `checkbox`, `password`, `hidden`; then Extra fields not already keyed, minus a single checkbox; empty labels fall back as today.
  - `ShowableFields::value( string $key, string $markup ): string` (`$yaycheckout_value_of` today: Extra field only when `remember`, choices by option label, `hidden_for_country()` reads empty, country and state by name). `''` for a key `labels()` doesn't hold.
  - Both memoise their parse per `md5( $markup )` in a private static array.
  - TS: `export interface ShowableField { key: string; label: string; section: 'billing' | 'shipping' | 'extra' }`, `export function showableFields( editor: BlockEditorSelectors ): ShowableField[]` (today's `layoutFields()`), `export function fieldNames( fields: ShowableField[] ): Map< string, string >` (today's `tokenNames()`), `export const SECTION_LABELS` (today's `SECTIONS`).
  - `export function fieldText( form: Element | null, key: string ): string` in `shared/form-text.ts` (today's `text()` in `field-value/view.ts`).

- [ ] **Step 1: Create `includes/Renderer/ShowableFields.php`**

`namespace YayCheckout\Renderer;`, a final class with static methods only (no singleton, no hooks). Move the logic, with its comments, from `field-value/render.php`.

- [ ] **Step 2: Make `field-value/render.php` call `ShowableFields::labels()` and `::value()`**

Keep the key filtering, the separator join, the placeholder and the markup exactly as they are.

- [ ] **Step 3: Create `shared/showable-fields.ts` and move `fieldText()` into `shared/form-text.ts`**

`field-value/edit.tsx` imports `showableFields`, `fieldNames`, `ShowableField` and `SECTION_LABELS`; `field-value/view.ts` imports `fieldText`. The `UNSHOWABLE_TYPES` comment now mirrors `ShowableFields.php`.

- [ ] **Step 4: Build and type-check**

Run: `pnpm build:blocks`, expect exit 0. Run `composer lint -- includes/Renderer/ShowableFields.php packages/blocks/src/field-value/render.php`, expect no errors. Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`, expect exit 0.

- [ ] **Step 5: Verify Field Value is unchanged**

Before Step 1, save the checkout HTML of a layout holding Field Value blocks on `billing_email`, `billing_country` + `billing_state` (separator `, `) and one Extra field with Remember value on, with a filled session: `curl -s -b <shopper cookies> https://yaycheckout.test/checkout/ > /tmp/fv-before.html`. Repeat after Step 4 into `fv-after.html`. Run `diff <(grep -o '<div[^>]*yaycheckout-field-value.*' /tmp/fv-before.html) <(grep -o '<div[^>]*yaycheckout-field-value.*' /tmp/fv-after.html)`; expected: no output. In the browser, typing into Email still updates the block on each keystroke. In the editor, the Field Value picker lists the same fields with the same names.

- [ ] **Step 6: Commit**

Stage: the six files above.
Message: `refactor(blocks): share Field Value's showable fields and form reader`

### Task 2: Editor format and chip

**Files:**
- Create: `packages/blocks/webpack.config.js`
- Create: `packages/blocks/src/live-value/constants.ts`, `format.tsx`, `popover.tsx`, `index.ts`, `editor.css`, `missing-fields.tsx`, `view.ts` (empty until Task 4)
- Create: `packages/blocks/src/shared/canvas-style.ts`
- Create: `includes/Renderer/LiveValues.php` (constants and `cart_values()` only; Task 3 fills the rest)
- Modify: `packages/blocks/src/checkout/layout-design.tsx`, `packages/blocks/src/shared/editor-data.ts`, `includes/Blocks/BlockRegistry.php`, `includes/YayCheckout.php`

**Interfaces:**
- Consumes: `showableFields()`, `fieldNames()`, `SECTION_LABELS` (Task 1).
- Produces:
  - `LiveValues::BLOCKS = [ 'core/paragraph', 'core/heading' ]`.
  - `LiveValues::CLASS_NAME = 'yaycheckout-live-value'`, `LiveValues::KEY_ATTR = 'data-yaycheckout-value'`, `LiveValues::FALLBACK_ATTR = 'data-fallback'`, `LiveValues::FIELD_PREFIX = 'field:'`.
  - `LiveValues::cart_values(): array`, which returns, in this order:
    `[ 'cart_total' => __( 'Order total' ), 'cart_subtotal' => __( 'Subtotal' ), 'cart_shipping_total' => __( 'Shipping' ), 'cart_discount_total' => __( 'Discount' ), 'cart_tax_total' => __( 'Tax' ), 'cart_item_count' => __( 'Item count' ), 'cart_line_count' => __( 'Product count' ) ]`
    (text domain `yaycheckout`). The keys ending `_count` are the Counts group.
  - Handles `LiveValues::EDITOR_SCRIPT = 'yaycheckout-live-value-editor'` and `LiveValues::EDITOR_STYLE = 'yaycheckout-live-value-editor'`.
  - `window.yayCheckoutBlocks.liveValues: Record< string, string >`, from `cart_values()`.
  - TS `constants.ts`: `LIVE_VALUE_FORMAT = 'yaycheckout/live-value'`, `LIVE_VALUE_CLASS`, `KEY_ATTR`, `FALLBACK_ATTR`, `FIELD_PREFIX`, `LIVE_VALUE_BLOCKS = [ 'core/paragraph', 'core/heading' ]`, each mirroring its `LiveValues` constant.
  - `export function useCanvasStyle( css: string | null, styleId: string ): void` in `shared/canvas-style.ts`.

- [ ] **Step 1: Add `packages/blocks/webpack.config.js`**

Spread `@wordpress/scripts/config/webpack.config`. Set `entry: () => ( { ...defaultConfig.entry(), 'live-value/index': './src/live-value/index.ts', 'live-value/view': './src/live-value/view.ts' } )`. Create an empty `src/live-value/view.ts` now; Task 4 fills it.

- [ ] **Step 2: Move `canvasFrames()` and `useCanvasDesign()` from `checkout/layout-design.tsx` into `shared/canvas-style.ts` as `useCanvasStyle()`**

`layout-design.tsx` imports it, with no change in behaviour.

- [ ] **Step 3: Write the format: `constants.ts`, `format.tsx`, `popover.tsx`**

- `registerFormatType( LIVE_VALUE_FORMAT, { title: __( 'Live value' ), tagName: 'span', className: LIVE_VALUE_CLASS, attributes: { value: KEY_ATTR, fallback: FALLBACK_ATTR }, contentEditable: false, interactive: true, edit } )`.
- `edit` renders a `RichTextToolbarButton` (icon `database`, title "Live value") only when the selected block's name (`getSelectedBlock()?.name` from `blockEditorStore`) is in `LIVE_VALUE_BLOCKS`. The button opens `popover.tsx`, anchored with `useAnchor( { editableContentElement: contentRef.current, settings } )`.
- The popover has:
  - a `SelectControl` with optgroups Cart totals / Counts (from `editorData.liveValues`) and Fields (`showableFields()` named by `fieldNames()`, value `field:<key>`);
  - a `TextControl` "Fallback when empty";
  - Insert/Update, and Remove when editing.
- Insert uses `insertObject( value, { type: LIVE_VALUE_FORMAT, attributes: { value: key, fallback }, innerHTML: escapeHTML( label ) } )`. Edit reads the chip with `getActiveObject( value )`, then replaces it at the same index. Remove uses `remove( value, start, start + 1 )`.
- `editor-data.ts`: add `liveValues: Record< string, string >` to `EditorData`, default `{}`.

- [ ] **Step 4: Missing-field style and chip CSS: `missing-fields.tsx`, `editor.css`, `index.ts`**

- `missing-fields.tsx` registers a plugin (`registerPlugin( 'yaycheckout-live-value-missing', { render } )`). Its render calls `useCanvasStyle( css, 'yaycheckout-live-value-missing' )`, where `css` marks `.yaycheckout-live-value[data-yaycheckout-value^="field:"]` with the warning style and `::after { content: " – Missing field" }` (translated, CSS-string-escaped). It then resets the warning for each current key: `[data-yaycheckout-value="field:<CSS.escape(key)>"]`. It reads `showableFields()` through `useSelect`, as a JSON string, as Field Value does.
- `editor.css`: the chip style (inline-block, `border-radius: 4px`, `padding: 0 .3em`, tinted background `color-mix( in srgb, currentColor 12%, transparent )`) and the warning colour `#cc1818`.
- `index.ts` imports `./format`, `./missing-fields` and `./editor.css`.

- [ ] **Step 5: PHP: register and enqueue the editor assets**

- `LiveValues.php`: `namespace YayCheckout\Renderer`, `SingletonTrait`, the constants and `cart_values()`. The constructor is empty until Task 3.
- `YayCheckout.php`: add `\YayCheckout\Renderer\LiveValues::get_instance();` after `HideWhenEmpty`.
- `BlockRegistry`:
  - On `enqueue_block_editor_assets`, when `'layout' === $this->editor( null )`, enqueue `LiveValues::EDITOR_SCRIPT` from `assets/dist/blocks/live-value/index.js`. Its deps and version come from `index.asset.php`, plus `generate_block_asset_handle( 'yaycheckout/checkout', 'editorScript' )`.
  - In `editor_design_data()`, add `window.yayCheckoutBlocks.liveValues` with `inline_data()`.
  - On `enqueue_block_assets`, when `is_admin()` and `'layout' === $this->editor( null )`, enqueue `LiveValues::EDITOR_STYLE` from `live-value/index.css`, so the canvas iframe gets it.
  - Skip both when the built files are missing.

- [ ] **Step 6: Build and verify in the editor**

Run: `pnpm build:blocks`, expect exit 0. `assets/dist/blocks/live-value/` holds `index.js`, `index.asset.php`, `index.css`, `view.js`, `view.asset.php`, and `ls assets/dist/blocks` still lists every block directory it listed before. Run `composer lint -- includes/Renderer/LiveValues.php includes/Blocks/BlockRegistry.php includes/YayCheckout.php`, expect no errors. Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`, expect exit 0.

In a new layout (not an existing draft):
1. Select a Paragraph. The toolbar's More menu has "Live value". Select a List or a YayCheckout block: it doesn't.
2. Insert Order total with fallback `Free`: a tinted, non-editable chip "Order total". Code editor shows `<span class="yaycheckout-live-value" data-yaycheckout-value="cart_total" data-fallback="Free">Order total</span>`.
3. Click the chip, change it to Item count, Update: the chip reads "Item count".
4. Undo: the chip reads "Order total" again. Undo once more: the chip is gone entirely.
5. Insert "First name (Billing)", then delete Billing's First name Field block: the chip shows "First name – Missing field" in red and stays. Undo the delete: the warning goes.
6. The Layout design sidebar still restyles the canvas (moved hook).
7. Open a regular page in the block editor: no "Live value" button, and no `yaycheckout-live-value-editor` script in the page source.

- [ ] **Step 7: Commit**

Stage: every file listed in this task's Files.
Message: `feat(blocks): add the live value format to Paragraph and Heading`

### Task 3: Fill live values on the storefront

**Files:**
- Modify: `includes/Renderer/LiveValues.php`, `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/live-value/view.ts` (one import)
- Create: `packages/blocks/src/live-value/view.css` (imported by `view.ts`)

**Interfaces:**
- Consumes: Task 2's constants; `ShowableFields::labels()` and `::value()` (Task 1).
- Produces:
  - `LiveValues::values(): array`: cart key => `[ 'text' => string, 'empty' => bool ]`, `[]` without `WC()->cart`. Values as the spec's Integration points (display-adjusted by `WC()->cart->display_prices_including_tax()`, money via `html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' )`, counts via `number_format_i18n()`, empty at 0).
  - `LiveValues::fill( $block_content, $block ): string` on `render_block_{name}` for each of `BLOCKS`.
  - `LiveValues::values_element(): string`, which returns `<script type="application/json" id="yaycheckout-live-values">…</script>` with `wp_json_encode( self::values(), JSON_HEX_TAG | JSON_HEX_AMP )`.
  - `LiveValues::VALUES_ID = 'yaycheckout-live-values'`.
  - Handles `LiveValues::SCRIPT = 'yaycheckout-live-value'` and `LiveValues::STYLE = 'yaycheckout-live-value'`.

- [ ] **Step 1: `values()` and a private `resolve( string $key, string $markup ): array`**

`resolve()` returns `[ 'text', 'empty', 'known' ]`:
- a cart key reads from `values()`, memoised per request;
- `field:<key>` with `<key>` in `ShowableFields::labels( $markup )` reads `ShowableFields::value()`, empty when `''`;
- anything else is `known => false`.

- [ ] **Step 2: `fill()`**

- Return `$block_content` untouched unless `CheckoutPage::get_instance()->takes_over()` and the content contains `CLASS_NAME`.
- `preg_replace_callback( '#<span\b[^>]*>(.*?)</span>#s', … )`. In the callback, read the opening tag with `WP_HTML_Tag_Processor`, and act only when `has_class( CLASS_NAME )`. Otherwise return the match unchanged.
- Printed text, from `resolve()`:
  - unknown key, or inner HTML holding `<`: the fallback, or `''`;
  - empty value: the fallback when it isn't `''`, otherwise the value's own text (`$0.00`, `0`, `''`);
  - otherwise: the value text.
- Add class `is-empty` when the value is empty or unknown, and print the text with `esc_html()`. Build the opening tag with `get_updated_html()`, so the data attributes stay.
- Set `$this->rendered = true`, then enqueue `SCRIPT` and `STYLE`. The layout renders before `wp_head()`, so the style prints in the head.

- [ ] **Step 3: Footer element**

`add_action( 'wp_footer', [ $this, 'print_values' ] )`: echo `values_element()` only when `$this->rendered`.

- [ ] **Step 4: Register the handles; verify server output**

- `view.css`: `.yaycheckout-live-value.is-empty[data-yaycheckout-value^="field:"] { opacity: .5; }`, imported by `view.ts`. Confirm `pnpm build:blocks` emits `assets/dist/blocks/live-value/view.css`.
- `BlockRegistry::register_view_scripts()`: register `LiveValues::SCRIPT` from `live-value/view.js` with deps `view.asset.php` deps + `jquery`, footer `true`, and `LiveValues::STYLE` from `live-value/view.css`. Skip both when the files are missing.

Run: `composer lint -- includes/Renderer/LiveValues.php includes/Blocks/BlockRegistry.php`, expect no errors.

In a duplicate of the active layout, activate it with:
- Paragraph "Your cart is [Order total] to pay ([Item count] items)";
- Heading "Hi [First name (Billing)]", fallback `there`;
- a Paragraph without chips.

Cart: one $30.00 product, no tax, no shipping; guest with an empty session. View the checkout source:
- "Your cart is $30.00 to pay (1 items)";
- `Hi <span class="yaycheckout-live-value is-empty" … data-fallback="there">there</span>`;
- the plain Paragraph is identical to its saved markup;
- `<script type="application/json" id="yaycheckout-live-values">` in the footer, holding `"cart_total":{"text":"$30.00","empty":false}`.

Then:
- Set WooCommerce tax display (cart) to incl. with a 10% tax: Subtotal chip = the Totals block's Subtotal row text.
- Edit the code to change one chip's key to `cart_bogus`: it prints its fallback, never its label.
- A layout without chips: page source has no `yaycheckout-live-value` script or style.

- [ ] **Step 5: Commit**

Stage: `includes/Renderer/LiveValues.php`, `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/live-value/view.css`, `packages/blocks/src/live-value/view.ts`.
Message: `feat(storefront): fill live values in Paragraph and Heading`

### Task 4: Keep live values current

**Files:**
- Modify: `includes/Renderer/LiveValues.php`, `packages/blocks/src/live-value/view.ts`

**Interfaces:**
- Consumes: `values_element()`, `VALUES_ID` (Task 3); `fieldText()` (Task 1); `onDomReady`, `jquery` (`shared/`).
- Produces: fragment key `#yaycheckout-live-values` on `woocommerce_update_order_review_fragments`.

- [ ] **Step 1: Fragment**

Add `add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'fragments' ], 20 )`. `fragments( $fragments )` adds `'#' . VALUES_ID => self::values_element()` only when `CheckoutPage::get_instance()->layout()` is not null and its `content` contains `CLASS_NAME`. Return non-arrays untouched.

- [ ] **Step 2: `view.ts`**

- `syncCart()`: parse `#yaycheckout-live-values` (on a parse error, return and leave the spans). For each `.yaycheckout-live-value` whose key has no `field:` prefix and is in the map, set the text and `is-empty` by the same rule as `fill()`.
- `syncFields()`: for each `field:` span, `fieldText( document.querySelector( 'form.checkout' ), key )` with the same rule.
- Leave spans with unknown keys alone. Set `textContent` only when it changes.
- `onDomReady`: run both, then `jquery( document.body )?.on( 'updated_checkout', syncCart )` and `.on( 'input change country_to_state_changed updated_checkout', syncFields )`.

- [ ] **Step 3: Build and verify live updates**

Run: `pnpm build:blocks` (exit 0) and `composer lint -- includes/Renderer/LiveValues.php` (no errors). On the Task 3 layout:
- Increase the quantity with the Items stepper: "$60.00" and "2", no reload.
- Apply a 10% coupon: Discount reads `$6.00`.
- Type `Ana` into First name: "Hi Ana" on each keystroke, unmuted. Clear it: muted "there".
- Pick Vietnam with a chip on `field:billing_state`: it reads empty (fallback).
- Reload after typing: "Ana" is in the page source.
- In the Network tab, an `update_order_review` response of a layout without chips has no `#yaycheckout-live-values` key.

- [ ] **Step 4: Commit**

Stage: `includes/Renderer/LiveValues.php`, `packages/blocks/src/live-value/view.ts`.
Message: `feat(storefront): keep live values in step with the cart and form`

### Task 5: Document and verify end to end

**Files:**
- Modify: `docs/architecture.md`; `docs/plans/2026-10-12-live-value-text.md` (Status)

- [ ] **Step 1: Update `docs/architecture.md`**

- Storefront: add a **Live values** bullet after **Hide when empty**. It covers `LiveValues::BLOCKS`, `fill()`, the footer element and fragment `#yaycheckout-live-values`, field values via `fieldText()`, and `ShowableFields` shared with Field Value.
- Blocks and the embedded editor: add a sentence that `packages/blocks/webpack.config.js` adds the `live-value/index` and `live-value/view` entries beside the `block.json` ones, and that `BlockRegistry` enqueues the editor entry only in the layout editor.

- [ ] **Step 2: End-to-end verification (spec criterion 13)**

On yaycheckout.test, in the active layout (duplicate first if it's a draft):
1. Add a Paragraph "Your cart is [Order total] to pay ([Item count] items)".
2. Add a Heading "Hi [First name (Billing)]" with fallback `there`.
3. Publish.
4. Open the checkout with one product in the cart.

Expected:
- It shows the cart's total, `1`, and "Hi there" muted.
- Typing a first name: the heading follows.
- Increasing the quantity with the stepper: total and count update without a reload.
- Applying a coupon: the total drops.
- Reloading: all values are in the page source.
- A Field Value block on the same layout still works as before.

- [ ] **Step 3: Set this plan's Status to `done <the date this step runs, YYYY-MM-DD>` and commit**

Stage: `docs/architecture.md`, `docs/plans/2026-10-12-live-value-text.md`.
Message: `docs: document live values`
