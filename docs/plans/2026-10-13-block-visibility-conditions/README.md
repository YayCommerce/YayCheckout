# Block Visibility Conditions Implementation Plan

> **For agentic workers:** execute phases in order. Read this file, then the current phase file. Do not start a phase until the previous phase's exit criterion has been verified and `/yayplancheck` has passed on it.

**Status:** done 2026-10-13
**Goal:** Any block in a layout can be shown or hidden by the open step, the cart and login state, on load and live, with an editor panel and a preview.
**Spec:** [docs/specs/2026-10-13-block-visibility-conditions-design.md](../../specs/2026-10-13-block-visibility-conditions-design.md)
**Architecture:**
- PHP (`Renderer\BlockVisibility`) owns the rules. It decides visibility at render and prints the setting and the cart state for the browser.
- A built storefront script re-applies the same rules from `shared/visibility-rules.ts`, a TypeScript mirror, on step changes and after `update_order_review`.
- The editor panel and preview reuse that mirror.
- The storefront ships first, so phase 1 is usable by writing the attribute in the Code editor.

**Touches:**
- **Shared:** `includes/Renderer/LiveValues.php`. Its consumers are the Live value spans, the `live-value/view` script, and Place Order's `render.php` via `fill_html()`; only an internal helper is extracted.
- **Shared:** `includes/Blocks/BlockRegistry.php`. Its consumers are every block's editor script, which read `window.yayCheckoutBlocks`.
- **Shared:** `packages/blocks/src/shared/editor-data.ts`. Its consumers are every block's editor code and the SPA via `@blocks-shared`.
- **Shared:** `assets/css/checkout-base.css`. Its consumers are the storefront and the editor canvas.
- **New:** `includes/Renderer/BlockVisibility.php`, `packages/blocks/src/visibility/`, `packages/blocks/src/shared/visibility-rules.ts`.
- **Other:** `packages/blocks/webpack.config.js`, `includes/YayCheckout.php`, `docs/architecture.md`, `docs/decisions/`.

**Tests:** `tests/Integration/Renderer/BlockVisibilityTest.php` (Integration suite, `composer test`). It guards two things:
- `normalize()` and `is_visible()`: each malformed setting from spec criterion 8, step N and "last", no Steps block, subtotal `gte`/`lt` at 49.99 and 50.00, and all-must-match.
- The `render_block` filter: the data attribute and hiding class on Place Order set to "Show when last step"; standing down without a layout; an excluded Step left untouched.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit generated output (`assets/dist/`). Build with `pnpm build:blocks`.
- A TypeScript copy of PHP names its owner (`/** Mirrors BlockVisibility::… in PHP */`) and changes in the same commit as the PHP. PHP is the owner.
- Storefront hooks stand down when `CheckoutPage::get_instance()->layout()` is null, so stock WooCommerce checkout stays untouched.
- Every new string is translatable with the `yaycheckout` text domain.
- Free and Pro both ship it. Nothing may reference `YayCheckout\Pro`.
- Branch from `dev/edd` as `trace/feat/block-visibility`, Conventional Commits, a PR into `dev/edd`.
- Run on Node 22 (`nvm use`), and use pnpm only.

## Review focus

- **Place Order hidden when it shouldn't be:** "Show when last step" on a one-step layout, or with no step current, must show it. → Phase 1, Task 2 test, and Task 3 Step 4.
- **Edit back to an earlier step:** a Step Button set to "Hide when last step" must reappear when the shopper reopens step 2 from step 4. → Phase 1, Task 3 Step 4.
- **Coupon and subtotal change without a reload:** the state fragment must refresh on `update_order_review`. → Phase 1, Task 3 Step 5.
- **Layout with no conditional block:** no state element and no script. → Phase 1, Task 2 Step 5.
- **The editor drops the attribute on save:** a core block whose attribute isn't registered loses it when the layout is re-saved. → Phase 1, Task 3 Step 6.

## Phases

| Phase | Delivers (user-visible) | Exit criterion (exact check) | Status |
|---|---|---|---|
| [1. Storefront](phase-1-storefront.md) | A setting written in the Code editor shows and hides blocks on load, on step changes and after cart updates | Spec criteria 1–9 pass on the storefront with the attribute set by hand | done |
| [2. Editor](phase-2-editor.md) | The Visibility panel, the Conditional marker, the preview sidebar, docs | Spec criteria 10–13, including the end-to-end walk | done |

## Interface contract

**PHP: `YayCheckout\Renderer\BlockVisibility`** (singleton, `SingletonTrait`, built in `YayCheckout.php` beside `HideWhenEmpty`)
- **Constants:**
  - `ATTRIBUTE = 'yaycheckoutVisibility'`
  - `DATA_ATTR = 'data-yaycheckout-visibility'`
  - `HIDDEN_CLASS = 'yaycheckout-hidden-by-visibility'`
  - `STATE_ID = 'yaycheckout-visibility-state'`
  - `SCRIPT = 'yaycheckout-visibility'`
  - `EXCLUDED = [ 'yaycheckout/checkout', 'yaycheckout/steps', 'yaycheckout/step' ]`
  - `RULE_TYPES = [ 'step', 'needs_shipping', 'has_coupon', 'subtotal', 'logged_in' ]`
- `public static function normalize( $value ): ?array`: `[ 'action' => 'show'|'hide', 'rules' => list<array> ]`, or `null` for an absent, empty or malformed setting.
- `public static function state(): array{needs_shipping: bool, has_coupon: bool, subtotal: float, logged_in: bool}`
- `public static function is_visible( array $setting, array $state, int $step, int $step_count ): bool`. `$step` is the open step (1-based); `$step_count` is 0 when the layout has no Steps block.
- `public function filter_block( $block_content, $block ): string`: on `render_block`, priority 10.
- `public function print_state(): void`: on `wp_footer`.
- `public function fragments( $fragments )`: on `woocommerce_update_order_review_fragments`, priority 20.

**PHP: `YayCheckout\Renderer\LiveValues`**
- `public static function money(): array<string, float>`: the five money amounts `values()` formats today (`cart_total`, `cart_subtotal`, `cart_shipping_total`, `cart_discount_total`, `cart_tax_total`), or `[]` without a cart. `values()` calls it.

**TypeScript: `packages/blocks/src/shared/visibility-rules.ts`**
- Types:
  - `VisibilityRule`
  - `VisibilitySetting = { action: 'show' | 'hide'; rules: VisibilityRule[] }`
  - `VisibilityState = { needs_shipping: boolean; has_coupon: boolean; subtotal: number; logged_in: boolean }`
- `export const ATTRIBUTE = 'yaycheckoutVisibility'`, `export const EXCLUDED: string[]`, `export const HIDDEN_CLASS`, `export const STATE_ID`, `export const DATA_ATTR`. Each one is marked as mirroring its PHP constant.
- `export function normalize( value: unknown ): VisibilitySetting | null`, mirroring `BlockVisibility::normalize()`.
- `export function isVisible( setting: VisibilitySetting, state: VisibilityState, step: number, stepCount: number ): boolean`, mirroring `BlockVisibility::is_visible()`.

**Rule semantics (both sides)**
- `step` matches when `step_count > 0`, and either `rule.step === step`, or `rule.step === 'last'` and `step === step_count`.
- `needs_shipping`, `has_coupon` and `logged_in` match their state boolean.
- `subtotal` with `gte` matches `round(subtotal, decimals) >= amount`; with `lt` it matches `<`. PHP uses `wc_get_price_decimals()`, and the state carries the subtotal already rounded so TypeScript compares as is.
- Visible = (`action === 'show'`) === (every rule matches).

**Storefront DOM**
- A conditional block's first tag carries `data-yaycheckout-visibility` (JSON of the normalized setting), plus `yaycheckout-hidden-by-visibility` while hidden.
- `<script type="application/json" id="yaycheckout-visibility-state">` holds `state()` plus `step_count`.
- The open step is the first `.yaycheckout-step[data-state="current"]` in the first `.yaycheckout-steps`, read from `data-step`. With none current and `step_count > 0`, the open step is `step_count`.
