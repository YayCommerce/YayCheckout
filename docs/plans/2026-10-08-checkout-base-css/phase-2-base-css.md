# Phase 2: Base CSS

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** Phase 1 (`Design::schema()` has only `colours`, `typography` and `inputs`). Phase 1 is not merged yet, so `Trace/feat/checkout-base-css` is stacked on `Trace/refactor/design-token-trim`. Its PR targets that branch, or is rebased onto `dev/edd` once Phase 1 merges.
**Delivers:**
- The storefront checkout and the layout editor canvas share one neutral base that takes the theme's font and colour.
- Payment and shipping options are bordered choice rows, and the password meter is styled.
- The Colours, Typography and Inputs tokens adjust all of it.
- The primary colour falls back to the theme's primary, then `#3858e9`.

**Exit criterion** (spec criterion 17):
1. Run `./scripts/run.sh build`.
2. On a Storefront site, complete a checkout with the default layout.
3. Switch to Twenty Twenty-Five. Turn Colours on (primary `#ff0000`) and Inputs on (`underline`, `large`), then load the checkout and the layout editor.

Expected:
- On both themes, fields, the country select, payment and shipping rows and Place Order match spec criteria 2–10.
- The editor canvas matches the storefront's input and row styling.
- The order is placed.
- `composer lint` exits 0.

## Deviations found while implementing

- **`woocommerce-blocktheme`, `woocommerce-layout` and `woocommerce-smallscreen` are dequeued too** (the last two after yaytidy: layout's `.woocommerce-page form .form-row-first` float, at 0,2,1, beat the base's reset). On block themes it styles the same form markup as `woocommerce-general`, at 0,3,2 (`.woocommerce-page form .form-row input.input-text`), which beat the base. It assumes the general stylesheet we already dequeued.
- **The page rule carries more than the background.** DesignStyles' page rule also takes `color` (Colours on) and `font-family` / `font-size` (Typography on). `color-text`, `font-family` and `font-size-base` were printed but applied nowhere. An unset inherited property on `body` would reset the theme's body value, so the base can't read them there either. This widens the README's "page background only" exception into one page rule.
- **Inputs can be taller than 40px.** `min-height` is 40px, but the content decides when the theme's text is large: Twenty Twenty-Five's checkout text is 22px, so its inputs compute to 48.8px. Spec criterion 2's "40px" holds at a font size of about 16px.
- **Storefront's own payment rules needed answering.** `#payment .payment_methods > li:not(.woocommerce-notice)` gave the rows a grey background and padded labels, and `li:last-child:not(.woocommerce-notice)` dropped the last row's bottom edge. Both tie the shared row rule's specificity (1,2,1 / 1,3,1) and print later. The payment selectors now name WooCommerce's `div#payment`, the label padding is reset, the last row restates its bottom edge, and the editor preview prints the same `#payment` wrapper. Storefront's own radio icon stays.
- **Two contract changes from /yaytidy.** `DesignStyles::css()`'s `$with_custom_css` has no default, because its one caller always passes it. The primary colour expression is resolved once as `--yaycheckout-primary-color` (with `--yaycheckout-border-color` and `--yaycheckout-field-radius`) on `:root, .editor-styles-wrapper` in `checkout-base.css`, and block CSS reads `var(--yaycheckout-primary-color, #3858e9)`. The README contract is amended to match.
- **Spec criterion 2's 40px holds at a theme text size of about 16px.** Storefront computes 40.4px. Twenty Twenty-Five, whose checkout text is 22px, computes 48.8px, because 40px is the `min-height`.
- **Verified by simulation in the checkout page, not on a real layout or theme:**
  - **Password meter** (layout 61 has no Create account block): the built `create-account/style-index.css` was loaded with meter markup. Short and bad compute to `rgb(204, 24, 24)`, good to `rgb(240, 184, 73)`, strong to `rgb(74, 184, 102)`.
  - **Theme primary preset:** with `--wp--preset--color--primary` set on `:root` and Colours off, Place Order and the checked payment row both took the preset.
  - **Payment rows on Twenty Twenty-Five after the `#payment` change:** `14px 16px`, transparent background, the last row's 14px bottom padding and 1px border intact, and the checked row primary.
- **The Order Summary tables get 6px cell padding in the base.** That padding came from `woocommerce-blocktheme`.

### Task 1: Base stylesheet and its loader

**Files:**
- Create: `assets/css/checkout-base.css`
- Delete: `assets/css/checkout-page.css`
- Delete: `packages/blocks/src/checkout/style.css`
- Modify: `packages/blocks/src/checkout/block.json` (remove `"style"`)
- Create: `includes/Renderer/CheckoutBaseStyles.php`
- Modify: `includes/Renderer/DesignStyles.php`
- Modify: `includes/Renderer/CheckoutPage.php:58-71`
- Modify: `includes/Engine/Register/ScriptName.php:10`

**Interfaces:**
- Consumes: Phase 1's trimmed `Design::schema()`, and the README's "Primary colour expression" (written here first, and reused by Task 2).
- Produces: the README contract's "`DesignStyles` CSS" and "Base loader".

Base content follows the spec's Components list, items 1–19, in that order. Specificity follows the README's Global constraints.

Verification must cover:
- spec criteria 1–6 and 9–12;
- the select2 dropdown styled on the checkout and absent elsewhere (Review focus);
- the custom CSS printing after `checkout-base.css`.

Found while expanding:
- `select2-css`, `woocommerce-layout`, `woocommerce-smallscreen` and `wc-blocks-style` are still enqueued on the storefront. `wc-blocks-style` already draws `.wc-block-components-notice-banner`, so the base only sets that banner's border colours, and it must not restyle the banner's layout.
- Field and Email wrappers are `div.form-row` holding WooCommerce's `p.form-row`. The 12px row gap goes on the block wrapper only. The inner `.form-row .form-row` gets `margin: 0`.

- [ ] **Step 1: Create `assets/css/checkout-base.css`.**
  - **Order:** spec Components items 1–19, in the spec's order.
  - **Moved rules:** copy items 1, 2 and 18 verbatim from `checkout-page.css`. Copy items 3, 7, 8 and 17 verbatim from `checkout/style.css`.
  - **Inputs:** the input rule covers `.input-text`, `select`, `textarea`, `.yaycheckout-coupon-form__code` and `.select2-selection--single`. It reads `input-height`, `input-radius`, `input-border-width`, `input-padding-inline`, `color-border` and `color-background`, each with the fallback from the README contract.
  - **Buttons:** the button rule is `.yaycheckout-checkout :is(.button, button[type=submit])`. It never sets `display`, because Place Order's align classes set it.
  - **Notices:** they use a private `--yaycheckout-notice` colour set per type.
  - **Animation:** the blockUI spinner animation is `yaycheckout-spin`, and stops under `prefers-reduced-motion: reduce`.
- [ ] **Step 2: Add `ScriptName::STYLE_CHECKOUT_BASE = 'yaycheckout/style/checkout-base'`** in place of `STYLE_CHECKOUT_PAGE`.
- [ ] **Step 3: Update `DesignStyles`.**
  - `css( Design $design, bool $with_custom_css ): string` (no default since /yaytidy; see Deviations).
  - `vars()` adds `input-border-width` and `input-padding-inline`, and prints `input-radius: 0px` when the style is underline.
  - While Colours is on, `css()` prints the README's page background rule after `:root`.
  - Remove `enqueue()`.
- [ ] **Step 4: Create `Renderer\CheckoutBaseStyles`** with `enqueue( bool $with_custom_css ): void`.
  - Enqueue `checkout-base.css`.
  - Register `ScriptName::STYLE_CHECKOUT` with no source and depending on the base.
  - Add `DesignStyles::css()` to it inline.
- [ ] **Step 5: Switch the storefront to the new loader.**
  - `CheckoutPage::enqueue_scripts()` calls `( new CheckoutBaseStyles() )->enqueue( true )` in place of the `checkout-page.css` enqueue and `DesignStyles::enqueue()`.
  - Delete `assets/css/checkout-page.css` and `packages/blocks/src/checkout/style.css`.
  - In the Checkout block, remove `"style"` from `block.json` and the `./style.css` import from `index.ts`.
- [ ] **Step 6: Verify.**
  - Run `composer lint` and `pnpm build:blocks`. Both exit 0.
  - On the storefront checkout (Twenty Twenty-Five, every Design section off):
    - `link#yaycheckout/style/checkout-base-css` is present, and `checkout-page-css` and `woocommerce-general-css` are absent.
    - A billing input computes `border-top-width: 1px`, a 2px radius and height 40px, and takes the theme's `font-family`.
    - The country select2 selection is 40px high.
    - Submitting with First name empty gives its input a `rgb(204, 24, 24)` border.
  - Turn Inputs on (large, underline): inputs are 48px with `border-width: 0 0 1px`.
  - Turn Colours on (background `#0a0a0a`): `body` background is `rgb(10, 10, 10)`. Turn it off again: the theme background returns.
  - Open the select2 dropdown: its options list is bordered and the highlighted option uses the primary colour.
  - Restore the developer's `yaycheckout_design` from a backup afterwards.
- [ ] **Step 7: Commit.**
  - Stage the files above.
  - Message: `feat(checkout): add the base stylesheet and its loader`

### Task 2: One gated primary colour

**Files:**
- Delete: `includes/Renderer/CheckoutColour.php`
- Modify: `includes/YayCheckout.php:28`
- Modify: `packages/blocks/src/{place-order,progress,steps,step-button,back-button,return-to-cart}/style.css`. Every `var(--yaycheckout-primary)` becomes the README's primary colour expression (since /yaytidy, `var(--yaycheckout-primary-color, #3858e9)`).

**Interfaces:**
- Consumes: the "Base loader" from Task 1 (the tokens now print through it).
- Produces: nothing new. The ungated `--yaycheckout-primary` no longer exists in code (the grep in Step 2).

Verification: spec criterion 5, all three cases.

- [ ] **Step 1:** delete `includes/Renderer/CheckoutColour.php` and its boot line. Replace every `var(--yaycheckout-primary)` in the six files with the README's primary colour expression.
- [ ] **Step 2: Verify.**
  - Run `git grep -nE -- '--yaycheckout-primary([^-]|$)' -- ':!docs'`. Expected: no output. (`--yaycheckout-primary-color` is the base's alias; docs keep the old name as records.)
  - Run `pnpm build:blocks` and `composer lint`. Both exit 0.
  - With Colours off on Twenty Twenty-Five, Place Order's background equals `getComputedStyle(document.body).getPropertyValue('--wp--preset--color--primary')`, or `#3858e9` when that is empty.
  - With Colours on and primary `#ff0000`, it is `rgb(255, 0, 0)`.
- [ ] **Step 3: Commit.** Message: `refactor(blocks): read the primary colour from the gated Design token`

### Task 3: Per-block choice rows and password meter

**Files:**
- Create: `packages/blocks/src/shared/choice-rows.css`
- Create: `packages/blocks/src/payment/style.css`, `packages/blocks/src/shipping-methods/style.css` (each imports `../shared/choice-rows.css`)
- Create: `packages/blocks/src/create-account/style.css`
- Modify: `packages/blocks/src/{payment,shipping-methods,create-account}/block.json`. Add `"style": "file:./style-index.css"`.
- Modify: `packages/blocks/src/{payment,shipping-methods,create-account}/index.ts`. Add `import './style.css';`, as `place-order/index.ts` does.

**Interfaces:**
- Consumes: the "Primary colour expression" and the `--yaycheckout-color-border` and `--yaycheckout-input-radius` fallbacks from the README contract.
- Produces: the README contract's "Choice-row partial".

Verification must cover:
- spec criteria 7 and 8;
- the password meter colours (`#cc1818`, `#f0b849`, `#4ab866`);
- a one-gateway cart, where the row shows the checked border (Review focus);
- a per-block rule beating the base rule it refines, even though block CSS prints first (Review focus).

Found while expanding: shipping rates print inside `table.shop_table > tr.woocommerce-shipping-totals > th + td > ul#shipping_method`. A single rate prints `input[type=hidden].shipping_method`, not a checked radio. So a row counts as checked when it matches `:is(:has(input:checked), :only-child)`.

- [ ] **Step 1: Create `shared/choice-rows.css`.**
  - Each `li` has a 1px border, rows overlap by `margin-top: -1px`, and the first and last rows take the group's corner radius.
  - A checked row has a primary-coloured border, and sits above its neighbours (`position: relative; z-index: 1`).
  - The label takes the remaining width with weight 500.
  - `.payment_box` spans the full row width, with 8px top padding and no background.
- [ ] **Step 2: Create `payment/style.css` and `shipping-methods/style.css`.** Each `@import`s the partial. The shipping one also flattens the rates table to blocks, and visually hides the package `th` when there is only one package row.
- [ ] **Step 3: Create `create-account/style.css`** for the password meter (`short`/`bad` `#cc1818`, `good` `#f0b849`, `strong` `#4ab866`) and the password hint.
- [ ] **Step 4: Register the new styles.** In all three blocks, add `"style": "file:./style-index.css"` to `block.json` and `import './style.css';` to `index.ts`.
- [ ] **Step 5: Verify.**
  - `pnpm build:blocks` exits 0, and `assets/dist/blocks/{payment,shipping-methods,create-account}/style-index.css` exist.
  - On the storefront: payment rows are bordered with `14px 16px` padding, the checked row's border is the primary colour, and only the checked gateway's `.payment_box` shows.
  - Shipping shows the same rows, and with one rate that row still has the primary border.
  - In `document.styleSheets`, the payment block sheet precedes `checkout-base`, yet the row padding is `14px 16px`, not overridden by the base.
- [ ] **Step 6: Commit.** Message: `feat(blocks): style payment and shipping choices and the password meter`

### Task 4: Editor parity

**Files:**
- Modify: `includes/Engine/Admin/LayoutEditorCanvas.php`. Hook `enqueue_block_assets` to call `( new CheckoutBaseStyles() )->enqueue( false )` only when `is_admin()` and the current screen is the block editor for `Layout::POST_TYPE`.
- Modify: `packages/blocks/src/shared/field-preview.tsx` and `field-preview.css`. Previews print `form-row`, `input-text`, `description` and `required`; the CSS keeps only editor-only rules.
- Modify: `packages/blocks/src/email/edit.tsx` (same classes).
- Modify: `packages/blocks/src/payment/edit.tsx` and `packages/blocks/src/shipping-methods/edit.tsx`. They print `ul.wc_payment_methods > li.wc_payment_method` and `ul#shipping_method > li`, each with a radio and a label.
- Modify: `packages/blocks/src/shared/section-preview.css` (heading rule only).

**Interfaces:**
- Consumes: the "Base loader" from Task 1, and the choice-row partial from Task 3.
- Produces: nothing new.

Verification must cover:
- spec criterion 13;
- a post or page editor loads neither `yaycheckout/style/checkout-base` nor the tokens (Review focus);
- custom CSS `body{outline:5px solid red}` shows on the storefront but not in the layout canvas.

- [ ] **Step 1: Load the base in the editor canvas.** In `LayoutEditorCanvas::__construct()`, add `add_action( 'enqueue_block_assets', [ $this, 'canvas_base_styles' ] )`. Add `public function canvas_base_styles(): void`, which returns unless `is_admin()` and the current screen is the block editor for `Layout::POST_TYPE`, then calls `( new CheckoutBaseStyles() )->enqueue( false )`.
- [ ] **Step 2: Print storefront classes in the field preview.** In `FieldPreview`:
  - the root also gets `form-row`
  - the `input`, `select` and `textarea` get `input-text`
  - the required mark gets `required`
  - the description gets `description`

  Then trim `field-preview.css` to the rules no base rule covers: the `--inherit` label, `__optional`, `__hidden`, and `__choices` spacing.
- [ ] **Step 3: Print WooCommerce's list markup in the Payment and Shipping previews.** Payment prints `ul.wc_payment_methods > li.wc_payment_method` and Shipping prints `ul#shipping_method > li`, each `li` holding an `input[type=radio]` and a `label`. Then trim `section-preview.css` to the heading rule.
- [ ] **Step 4: Verify.**
  - Run `pnpm build:blocks`, `composer lint` and `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. All exit 0.
  - In layout 61's editor, a Field input computes the same border width, radius and height as on the storefront, and the Payment preview shows bordered rows.
  - A Post editor (`post-new.php`) has no `yaycheckout/style/checkout-base` stylesheet.
  - With custom CSS `body{outline:5px solid red}` saved, the storefront shows the outline and the layout canvas does not. Restore the design afterwards.
- [ ] **Step 5: Commit.** Message: `feat(editor): match the layout canvas to the storefront base`

### Task 5: Preview mirror, docs and decision record

**Files:**
- Modify: `apps/admin/src/components/shared/PreviewPane.tsx`. `NEUTRAL.radius` becomes `2`, and its comment says it mirrors `checkout-base.css`'s fallbacks and `DesignStyles`.
- Modify: `docs/architecture.md:62`. The Rendering paragraph names `checkout-base.css` and `CheckoutBaseStyles`, and drops "no stylesheet reads the properties yet". Add one sentence on the base / per-block / token placement rule.
- Modify: `docs/conventions.md:23`. The `PreviewPane.tsx` mirror list adds `checkout-base.css` fallbacks.
- Create: `docs/decisions/2026-10-08-checkout-owns-base-styles.md`, in the format of `docs/decisions/2026-10-05-decision-record-practice.md`. It records that WooCommerce's general stylesheet is replaced by YayCheckout's base, that tokens are values with neutral fallbacks, and the placement rule. It also records the rejected options from the spec's Alternatives table.
- Modify: `docs/plans/2026-10-08-checkout-base-css/README.md`. Set Status to `done YYYY-MM-DD` and both phases to `done`.

**Interfaces:**
- Consumes: everything above.
- Produces: nothing.

Verification:
- `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` exits 0.
- With every Design group off, the Design page's Preview shows inputs and buttons with a 2px radius.

- [ ] **Step 1:** make the `PreviewPane.tsx`, `architecture.md` and `conventions.md` edits listed above.
- [ ] **Step 2:** write the decision record.
- [ ] **Step 3: Verify.** `tsc --noEmit` exits 0, and with every Design group off, the Design page's Preview shows inputs and buttons with a 2px radius.
- [ ] **Step 4: Commit.** Message: `docs: record the checkout base styles decision`. The plan Status flips in the PR that finishes the plan.
