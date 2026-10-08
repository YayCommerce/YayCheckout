# Checkout Base CSS Implementation Plan

> **For agentic workers:** execute phases in order. Read this file, then the current phase file. Do not start a phase until the previous phase's exit criterion has been verified and `/yayplancheck` has passed on it.

**Status:** active
**Goal:** Trim the Design page to Colours, Typography, Inputs and Custom CSS. Then give the checkout and the layout editor one neutral base stylesheet that those tokens adjust, replacing WooCommerce's dequeued `woocommerce-general`.
**Spec:** [docs/specs/2026-10-08-checkout-base-css-design.md](../../specs/2026-10-08-checkout-base-css-design.md)
**Architecture:**
- **Phase 1:** removes the `buttons` and `spacing` token sections and all presets, end to end: model, REST, Pro and SPA. The Design wire gains a `defaults` key, so Reset no longer needs the Default preset.
- **Phase 2:** adds `assets/css/checkout-base.css` and per-block CSS. The base takes the theme's font and colours and reads the trimmed tokens as values with neutral fallbacks. One loader enqueues it on the storefront and in the editor canvas.
- **Why this order:** trimming first means the base CSS is written once, against the final token set.

**Touches:**
- PHP:
  - `includes/Models/Design.php`
  - `includes/Repositories/DesignRepository.php`
  - `includes/Controllers/DesignRestController.php`
  - `includes/Controllers/SetupRestController.php`
  - `includes/Renderer/DesignStyles.php`
  - `includes/Renderer/CheckoutPage.php`
  - `includes/Renderer/CheckoutColour.php` (deleted)
  - new `includes/Renderer/CheckoutBaseStyles.php`
  - `includes/Engine/Register/ScriptName.php`
  - `includes/Engine/Admin/LayoutEditorCanvas.php`
  - `includes/YayCheckout.php`
  - `includes/Pro/ProServiceProvider.php`
  - `includes/Pro/Controllers/DesignPresetRestController.php` (deleted)
- CSS: `assets/css/checkout-page.css` (deleted), new `assets/css/checkout-base.css`
- SPA: `apps/admin/src/lib/{schema,api,queries,helpers}/design.*`, `apps/admin/src/lib/api/setup.api.ts`, `apps/admin/src/pages/design/**`, `apps/admin/src/components/setup-wizard/{SetupWizard,BrandStep}.tsx`, `apps/admin/src/components/shared/PreviewPane.tsx`
- Blocks: `packages/blocks/src/{checkout,payment,shipping-methods,create-account,place-order,progress,steps,step-button,back-button,return-to-cart,email}/`, `packages/blocks/src/shared/{choice-rows.css,field-preview.*,section-preview.css}`
- Docs: `docs/architecture.md`, `docs/conventions.md`, new `docs/decisions/2026-10-08-checkout-owns-base-styles.md`
- Shared code:
  - **The Design wire (shared):** read by the Design page, the setup wizard (`window.yayCheckoutAdmin.design` through `LocalizeHelper`) and `PreviewPane`. All three change in Phase 1, Task 2.
  - **`--yaycheckout-primary` (shared):** read by six blocks' CSS. All six change in Phase 2, Task 2.

**Tests:** none. CSS and the design-schema trim decide nothing about whether a shopper can check out, and neither reproduces a fixed bug, so CLAUDE.md's test rule doesn't apply. The spec says the same. Each task has an exact manual check instead.

**Decided with the user while planning:**
- **Reset defaults:** the Design wire gains `defaults` (the token defaults from `Design::defaults()`), and Reset and the override count read it. This amends spec criterion 14: `GET /design` returns exactly `{ tokens, custom_css, updated_at, defaults }`.
- **Delivery:** two phases, one PR each.
- **Brand step:** the setup wizard's "Corner radius" slider set both the button and the input radius. It goes with the button controls, so the Brand step keeps Primary colour, Font and (Pro) Custom CSS.

## Global constraints

- **PHP 7.4:** product PHP must run on PHP 7.4. No enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types or `str_contains`.
- **Free names Pro only in `boot_pro()`.** Pro attaches only through `includes/Pro/ProServiceProvider.php`. `composer lint` runs `scripts/check-pro-boundary.sh`.
- **Never hand-edit `vendor/`, `vendor-prefixed/`, `node_modules/`, `assets/dist/` or `release/`.** Block CSS is edited in `packages/blocks/src/<block>/` and built with `pnpm build:blocks`. `assets/css/` is hand-written source.
- **Tooling:** run from the repo root on Node 22 (`nvm use`), with pnpm only.
- **Branches:** each phase branches from `dev/edd` as `Trace/<type>/<topic>` and lands through its own PR into `dev/edd` (squash merge).
- **Commits:** Conventional Commits, ending with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Stage files by name. Commit or push only when the user asks.
- **Wire casing:** snake_case on the wire and camelCase in the SPA, converted only in `apps/admin/src/lib/api/design.api.ts`.
- **TypeScript copies:** a copy of a PHP value says `Mirrors …` and names its owner, and both change in the same commit.
- **Specificity:**
  - Base selectors are `.yaycheckout-checkout` plus at least one class or element (≥ 0,2,0, or 0,1,1 plus a class). They must beat a theme's `input[type="text"]` (0,1,1).
  - Anything WooCommerce or select2 appends outside the form is scoped to `body.yaycheckout-page`.
  - Per-block selectors start with `.yaycheckout-checkout .<block-class>` (≥ 0,3,0).
  - No `@layer`. No `!important` except against WooCommerce's inline styles.
- **Tokens are values only:** CSS reads `var(--yaycheckout-<name>, <fallback>)`. `DesignStyles` prints one selector rule only: the page background while Colours is on.
- **RTL:** use logical properties (`margin-inline`, `padding-inline`, `inset-inline`, `text-align: start/end`).
- **Known failing checks on `dev/edd`:** `pnpm --filter @yaycheckout/admin-page lint` exits 1 there (4 eslint warnings, and prettier fails on `lib/utils.ts`, `styles/custom-header.css` and `tsconfig.node.json`). Expect no new failures beyond those.

## Review focus

- **A stored `yaycheckout_design` that still holds `preset_id`, `presets`, `tokens.buttons` and `tokens.spacing`:** reads without error, and the next save stores none of them. Checked in Phase 1, Task 1, Step 6.
- **A stale SPA bundle or old client that still posts `preset_id`:** the save succeeds and `preset_id` is ignored. Checked in Phase 1, Task 1, Step 6.
- **A setup-wizard re-run from Settings after the trim:** it still creates a draft and saves the design. Checked in Phase 1, Task 2, Step 8.
- **Block styles print before `checkout-base.css` on the storefront:** a per-block rule must still beat the base rule it refines. Checked in Phase 2, Task 3.
- **Only one gateway or one rate:** WooCommerce hides its radio, but the row still shows the checked border. Checked in Phase 2, Task 3.
- **The select2 dropdown is appended to `body`:** it is styled on the checkout page and nowhere else. Checked in Phase 2, Task 1.
- **The editor canvas:** the base and tokens load only on a layout's editor screen, never on a post or page editor, and the merchant's custom CSS never loads there. Checked in Phase 2, Task 4.

## Phases

| Phase | Delivers (user-visible) | Exit criterion (exact check) | Status |
|---|---|---|---|
| 1 | [Design trim](phase-1-design-trim.md): the Design page shows Colours, Typography, Inputs and Custom CSS with no presets. The wizard's Brand step has no radius or button controls. Reset still works. | `GET /yaycheckout/v1/design` returns exactly `tokens, custom_css, updated_at, defaults` with tokens `colours, typography, inputs`. The Design page saves, resets and shows "N overrides". A wizard re-run completes. `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` exits 0. `composer lint` exits 0. | pending |
| 2 | [Base CSS](phase-2-base-css.md): the checkout and the editor canvas share one neutral base, per-block payment, shipping and password styles, and the tokens adjust them. | Spec criterion 17 (end-to-end on Storefront and Twenty Twenty-Five), as written in the Phase 2 exit criterion. | pending |

## Interface contract

- **Design wire.** Produced by Phase 1, Task 1. Consumed by Phase 1, Task 2 and by `LocalizeHelper` (unchanged code).
  - **Shape:** `Design::to_array(): array` returns `[ 'tokens' => array, 'custom_css' => string, 'updated_at' => ?string, 'defaults' => array ]`. `defaults` is `Design::defaults()`.
  - **Sections:** `Design::schema()` has exactly `colours`, `typography` and `inputs`.
  - **Stored option:** `Design::to_option(): array` returns `[ 'tokens', 'custom_css', 'updated_at' ]`.
- **REST bodies.** Produced by Phase 1, Task 1. Consumed by Phase 1, Task 2.
  - `POST /yaycheckout/v1/design` takes `{ tokens, custom_css }`, both required. Other keys are ignored.
  - `POST /yaycheckout/v1/setup` takes `{ template_slug, design: { tokens, custom_css }, rerun? }`.
- **SPA design types.** Produced by Phase 1, Task 2.
  - `DesignResponse = { tokens: DesignTokens; customCss: string; updatedAt: string | null; defaultTokens: DesignTokens }`
  - `designToWire( form: DesignForm ): { tokens: DesignTokensWire; custom_css: string }`
  - `postDesign( form: DesignForm ): Promise<DesignResponse>`
- **`DesignStyles` CSS.** Produced by Phase 2, Task 1.
  - **Signature:** `DesignStyles::css( Design $design, bool $with_custom_css = true ): string`.
  - **Variables on `:root`:** `color-primary`, `color-text`, `color-background` and `color-border` while Colours is on; `font-family` and `font-size-base` while Typography is on; `input-radius`, `input-height`, `input-border-width` and `input-padding-inline` while Inputs is on. Each name is prefixed `--yaycheckout-`.
  - **Underline inputs:** `input-radius: 0px`, `input-border-width: 0 0 1px`, `input-padding-inline: 0`.
  - **Boxed inputs:** `input-border-width: 1px`, `input-padding-inline: 12px`.
  - **Page background:** while Colours is on, it also prints `body.yaycheckout-page, .editor-styles-wrapper { background-color: var(--yaycheckout-color-background); }`.
  - `DesignStyles::enqueue()` is removed.
- **Base loader.** Produced by Phase 2, Task 1. Consumed by Phase 2, Task 4.
  - `Renderer\CheckoutBaseStyles::enqueue( bool $with_custom_css ): void` enqueues `assets/css/checkout-base.css` under `ScriptName::STYLE_CHECKOUT_BASE = 'yaycheckout/style/checkout-base'`, then the `DesignStyles` CSS inline under `ScriptName::STYLE_CHECKOUT`, after it.
- **Primary colour expression.** Produced by Phase 2, Task 2. Consumed by Phase 2, Tasks 1 and 3: `var(--yaycheckout-color-primary, var(--wp--preset--color--primary, #3858e9))`.
- **Choice-row partial.** Produced by Phase 2, Task 3. `packages/blocks/src/shared/choice-rows.css` is imported by `payment/style.css` and `shipping-methods/style.css`.
