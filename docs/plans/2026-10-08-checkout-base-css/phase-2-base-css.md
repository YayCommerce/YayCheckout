# Phase 2: Base CSS

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** Phase 1 merged into `dev/edd`. `Design::schema()` has only `colours`, `typography` and `inputs`. Branch `Trace/feat/checkout-base-css` from `dev/edd`.
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

Steps: expand after Phase 1 lands.

### Task 2: One gated primary colour

**Files:**
- Delete: `includes/Renderer/CheckoutColour.php`
- Modify: `includes/YayCheckout.php:28`
- Modify: `packages/blocks/src/{place-order,progress,steps,step-button,back-button,return-to-cart}/style.css`. Every `var(--yaycheckout-primary)` becomes the README's primary colour expression.

**Interfaces:**
- Consumes: the "Base loader" from Task 1 (the tokens now print through it).
- Produces: nothing new. `--yaycheckout-primary` no longer exists; `git grep -- '--yaycheckout-primary'` returns nothing.

Verification: spec criterion 5, all three cases.

Steps: expand after Phase 1 lands.

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

Steps: expand after Phase 1 lands.

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

Steps: expand after Phase 1 lands.

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

Steps: expand after Phase 1 lands.
