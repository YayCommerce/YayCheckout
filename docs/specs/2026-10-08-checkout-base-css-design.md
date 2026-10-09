# Checkout Base CSS Design

**Status:** accepted 2026-10-08
**Related:** commit 0b7159e (dequeue WooCommerce style); reference prototype "WooCommerce Checkout Templates v1.1" (11 templates, shared by the designer as a local HTML bundle)

## Goal

The storefront checkout and the layout editor canvas look finished and alike on any theme, now that WooCommerce's `woocommerce-general` stylesheet is no longer loaded. YayCheckout ships one neutral base stylesheet that takes its font and colours from the theme. A few Design tokens adjust it, and block supports override it per block. The Design page drops its Buttons and Spacing sections and presets.

## Context

- `CheckoutPage::enqueue_scripts()` (`includes/Renderer/CheckoutPage.php:58`) dequeues `wc-blocks-style-checkout` and `woocommerce-general`, enqueues `assets/css/checkout-page.css` (handle `ScriptName::STYLE_CHECKOUT_PAGE`), then calls `DesignStyles::enqueue()`.
- `checkout-page.css` holds the theme-width resets, the matching widths for the Order Summary Items and Totals tables, and `.yaycheckout-hidden-when-empty`. `packages/blocks/src/checkout/style.css` holds rules that several blocks need: the Storefront float reset for `#order_review`, the reveal link, inline help text (`.description`) and the margin on address Columns.
- Blocks print WooCommerce markup:
  - Field and Email go through `woocommerce_form_field()` (`.form-row`, `.input-text`, `.description`, `abbr.required`).
  - Payment and Shipping methods print WooCommerce's gateway and rate HTML through `CheckoutFragments` (`ul.wc_payment_methods > li.wc_payment_method` with `.payment_box`, and `ul#shipping_method > li`).
  - Create account prints WooCommerce's account fields.
  - `wc-country-select` turns the country and state fields into selectWoo (select2) dropdowns.
- Since `woocommerce-general` was dequeued, nothing styles payment rows, `.payment_box`, notices, select2, invalid fields, the blockUI overlay, `abbr.required` or the password strength meter.
- `Models\Design::schema()` has five sections, each with an `enabled` flag that the SPA labels "Theme default": `colours`, `typography`, `spacing`, `buttons` and `inputs`.
  - `DesignStyles` prints `--yaycheckout-*` variables on `:root` for the sections that are on, followed by custom CSS. No stylesheet reads them yet.
  - `Renderer\CheckoutColour` prints a separate, ungated `--yaycheckout-primary`, which Place Order, Progress, Steps, Step Button, Back Button and Return to Cart read.
- Presets:
  - Built-in presets (Default, Dark, B2B) live in `Design::presets()`. Merchant presets are stored in `yaycheckout_design.presets` and written through Pro's `Pro\Controllers\DesignPresetRestController`.
  - `preset_id` travels on `GET/POST /design` and on `POST /setup`.
  - The SPA has `PresetsCard`, `SavePresetModal`, `activePresetIdFor()` (setup wizard), and Brand-step controls for button radius and style.
- The editor canvas is iframed. Field previews draw from `packages/blocks/src/shared/field-preview.css`, and Payment and Shipping previews from `section-preview.css`. Both use hard-coded values that differ from the storefront.
- `apps/admin/src/components/shared/PreviewPane.tsx` mirrors `DesignStyles` (`INPUT_HEIGHTS`, `FONT_FALLBACK`) and uses `NEUTRAL` stand-ins for sections that are off.
- The reference prototype's shared CSS is a wp-admin kit and isn't relevant here. Its checkout look lives in inline styles. What all 11 templates share:
  - inputs: 1px `#757575` border, 2px radius, 14–16px text, 40–48px height
  - labels: 13px, weight 500
  - primary button: full width, weight 600, `#3858e9`
  - payment and shipping options: bordered rows with 14px × 16px padding
  - headings: 20px, weight 400

  Where templates differ (boxed or underline inputs, 40 or 48px height, 0 or 2px radius), the difference matches the `inputs` tokens.

## Scope

**In:**
- **New stylesheet:** `assets/css/checkout-base.css` absorbs `assets/css/checkout-page.css` (deleted) and the rules in `packages/blocks/src/checkout/style.css` (deleted, with the `"style"` key in its `block.json`). It adds the base listed under Approach.
- **New stylesheets for Payment and Shipping methods:** each block gets a `style.css` that imports a shared `packages/blocks/src/shared/choice-rows.css`.
- **Create account:** a `style.css` for the password strength meter.
- **Order Summary:** its table rules stay in the base, because two blocks (Items and Totals) print them.
- **Loading:** a new `Renderer\CheckoutBaseStyles` enqueues the base and the tokens on the storefront (with custom CSS) and in the layout editor canvas (without custom CSS).
- **Primary colour:** `CheckoutColour` is removed. Every block reads `--yaycheckout-color-primary` with the fallback chain below.
- **New token variables** in `DesignStyles`: `input-border-width` and `input-padding-inline`. `input-radius` is printed as `0px` for underline inputs. A body background rule is printed while Colours is on.
- **Editor previews:** Field and Email previews print storefront classes (`form-row`, `input-text`, `description`, `required`). Payment and Shipping previews print WooCommerce's list markup. `field-preview.css` and `section-preview.css` keep only editor-only rules.
- **Design schema:** remove the `buttons` and `spacing` sections and all presets (built-in, merchant and `preset_id`) from `Models\Design`, the Design and Setup REST bodies, the zod schemas and wire converters, the Design page, the setup wizard, and Pro (`DesignPresetRestController` and its registration in `ProServiceProvider`).
- **`PreviewPane.tsx`:** fixed button look and gap; `NEUTRAL.radius` becomes `2`.
- **Docs:** update `docs/architecture.md` (Design schema and presets line; the Rendering paragraph about `checkout-page.css` and "no stylesheet reads the properties yet") and `docs/conventions.md` (the `SPACING_SCALES` mirror example).

**Out:**
- Block supports (colour, border, spacing) for Payment, Shipping methods, Email, Extra field or Create account.
- New tokens, for example status colours, label weight or heading sizes.
- Rebuilding the reference templates as presets or layouts.
- Loading web fonts (`DesignStyles`' `TODO(fonts)` stays).
- Styling the inserter previews beyond what the fallbacks give.
- A migration that strips old keys from `yaycheckout_design`.
- Restyling the admin SPA.

## Acceptance criteria

1. **Assets on the storefront:** the checkout page loads `checkout-base.css` (handle `yaycheckout/style/checkout-base`). It loads neither `checkout-page.css` nor `woocommerce-general`, and the merchant's custom CSS prints after `checkout-base.css`.
2. **Fields with every Design section off:** on Twenty Twenty-Five and on Storefront, a text input in a Field block has the theme's font family and text colour, a 1px solid border, a 2px radius and a computed height of 40px. Field rows are 12px apart.
3. **Inputs section on:** with size `large`, radius `8` and style `boxed`, inputs are 48px high with an 8px radius. Switching the style to `underline` gives `border-width: 0 0 1px`, a 0px radius and 0 inline padding.
4. **Colours section on:** with border `#e4e4e7` and background `#0a0a0a`, input borders compute to `rgb(228, 228, 231)` and `body.yaycheckout-page` has background `rgb(10, 10, 10)`. With Colours off, the theme's body background is unchanged.
5. **Primary fallback:**
   - Colours off on a theme that defines `--wp--preset--color--primary`: Place Order's background equals that preset.
   - Colours off on a theme without it: `#3858e9`.
   - Colours on with primary `#ff0000`: `#ff0000`.
6. **Button base:** with no block styles set, Place Order has a primary fill, `#fff` text, a 2px radius and font weight 600. A merchant border radius of 20px set in the editor's Place Order block wins.
7. **Payment:** with two gateways, each `li.wc_payment_method` is a bordered row with 14px × 16px padding. The checked row's border is the primary colour. The checked gateway's `.payment_box` sits inside its row, and other gateways' boxes stay hidden.
8. **Shipping methods:** with two rates, the rows look like the payment rows, including the checked state.
9. **Invalid field:** submitting with Billing first name empty gives that field's input a `#cc1818` border. The notice group at the top of the form is a bordered block with a `#cc1818` left border. Notices printed in block-theme banner markup (`.wc-block-components-notice-banner.is-error`) look the same.
10. **Country dropdown:** the select2 field matches a text input in height, border and radius. Its open dropdown, which select2 appends to `body`, shows a bordered list with the highlighted option in the primary colour.
11. **Loading overlay:** while totals update, the review area shows WooCommerce's blockUI overlay with a spinner.
12. **Touch screens:** at `(pointer: coarse)` with a typography base size of 14px, inputs compute to at least 16px.
13. **Editor parity:** in the layout editor, a Field preview's input has the same border, radius and height as on the storefront under the same Design. The Payment preview shows bordered rows. The merchant's custom CSS does not apply in the editor.
14. **Design REST:**
   - `GET /yaycheckout/v1/design` returns exactly `{ tokens, custom_css, updated_at }`, and `tokens` has exactly `colours`, `typography` and `inputs`.
   - `POST /design` with `{ tokens, custom_css }` saves.
   - A body that also has `preset_id` saves and ignores it.
   - A stored option that still holds `buttons`, `spacing`, `presets` and `preset_id` reads without error, and the next save stores none of them.
15. **Pro routes:** the Pro build registers no design-preset routes. `composer lint` passes `check-pro-boundary.sh`.
16. **Admin UI:** the Design page shows Colours, Typography, Inputs and Custom CSS only, with no presets card. The setup wizard's Brand step shows colour and font only. `POST /setup` succeeds with `design: { tokens, custom_css }`.
17. **End-to-end:**
   1. Run `./scripts/run.sh build`.
   2. On a Storefront site, complete a checkout with the default layout.
   3. Then on Twenty Twenty-Five, with Colours on (primary `#ff0000`) and Inputs on (underline, large), load the checkout and the layout editor.

   Expected result:
   - The fields, the country select, the payment and shipping rows and Place Order match criteria 2–10 on both themes.
   - The editor canvas matches the storefront's input and row styling.
   - The order is placed.

## Constraints

- **PHP 7.4 in product PHP:** no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- **Free/Pro boundary:** Free names Pro only in `boot_pro()`. Removing `DesignPresetRestController` also removes its registration in `includes/Pro/ProServiceProvider.php`.
- **Never hand-edit `assets/dist/`:** change block CSS in `packages/blocks/src/<block>/` and rebuild with `pnpm build:blocks`. `assets/css/checkout-base.css` is hand-written source.
- **Specificity budget:**
  - Base selectors are `.yaycheckout-checkout` plus at least one class or element (≥ 0,2,0 or 0,1,1 with a class), so they beat a theme's `input[type="text"]` (0,1,1).
  - Things that WooCommerce or select2 append outside the form are scoped to `body.yaycheckout-page`.
  - Per-block selectors start with `.yaycheckout-checkout .<block-class>` (≥ 0,3,0), because block styles print before the base on the storefront.
  - No `!important` except where WooCommerce writes inline styles (already the case for `.description`).
  - No `@layer`: unlayered theme CSS would beat it.
- **Tokens are values only:** base and block CSS read `var(--yaycheckout-<name>, <fallback>)`. `DesignStyles` prints selectors in one place only: the page background rule while Colours is on.
- **Logical properties** (`margin-inline`, `padding-inline`, `text-align: start/end`), so RTL works.
- **`PreviewPane.tsx` mirror:** its comment names `DesignStyles` and `checkout-base.css`, and every number it copies changes in the same commit as its PHP or CSS source (conventions: TypeScript copies).
- **Tests:** none planned. The project test policy covers checkout logic, not CSS. Verify with the manual checks in Acceptance criteria.

## Approach

**Chosen:** a neutral base with value-only tokens and per-block CSS for markup only one block prints.

**Architecture:** one stylesheet, `checkout-base.css`, styles everything that more than one block prints, or that WooCommerce prints outside any block. It inherits the theme's font and colour and uses the reference's shapes as fallbacks. Markup printed by a single block is styled in that block's `style.css`, which WordPress loads only when the block renders, on the storefront and in the editor canvas. Design tokens are CSS variables that both read, and an off section leaves its variables unset so the fallbacks apply. Block supports (inline styles and `has-*` classes) override per instance.

Placement rule for any future rule:
- If two or more blocks print the markup, or WooCommerce prints it outside a block, it goes in the base.
- If one block owns it, it goes in that block's `style.css`.
- Tokens supply values only, never selectors.

**Components:**
- `assets/css/checkout-base.css`: base styles, in this order:
  1. page and theme-width resets (from `checkout-page.css`)
  2. `.yaycheckout-hidden-when-empty`
  3. the Storefront `#order_review` float reset
  4. `.form-row` (12px gap between rows, logical margins)
  5. labels (weight 500)
  6. `abbr.required` (`#cc1818`, no underline)
  7. `.description` help text (from `checkout/style.css`)
  8. the reveal link
  9. `.input-text`, `select`, `textarea`
  10. checkbox and radio (`accent-color: var(--yaycheckout-color-primary, …)`)
  11. focus ring (2px outline in the primary colour, offset 1px)
  12. `.woocommerce-invalid` inputs (`#cc1818` border)
  13. select2 (`.select2-container .select2-selection` styled like an input; `body.yaycheckout-page .select2-dropdown`)
  14. notices (`.woocommerce-error`, `-message`, `-info`, `.woocommerce-NoticeGroup`, `.wc-block-components-notice-banner.is-error`, `.is-success` and `.is-info`), with a 4px left border in error `#cc1818`, success `#4ab866` or info in the primary colour
  15. blockUI (`.blockUI.blockOverlay::before` spinner)
  16. `.button` and `button[type=submit]` inside the form (primary fill, `#fff` text, radius `2px`, weight 600, `min-height: var(--yaycheckout-input-height, 40px)`)
  17. address Columns margin
  18. Order Summary table widths
  19. `@media (pointer: coarse)` minimum input font size of 16px
- `packages/blocks/src/shared/choice-rows.css`: rows for `ul.wc_payment_methods` and `ul#shipping_method`:
  - no list bullets
  - each `li` has a 1px border in `var(--yaycheckout-color-border, color-mix(in srgb, currentColor 45%, transparent))` and 14px × 16px padding
  - adjoining rows share borders, and the group's outer corners use `var(--yaycheckout-input-radius, 2px)`
  - the checked row's border is the primary colour, selected through `:has(input:checked)`
  - `.payment_box` has no background or arrow and 8px top padding
- `packages/blocks/src/payment/style.css` and `packages/blocks/src/shipping-methods/style.css`: each imports `../shared/choice-rows.css` and holds that block's extras. Each `block.json` gains `"style": "file:./style-index.css"`.
- `packages/blocks/src/create-account/style.css`: `.woocommerce-password-strength` (short/bad `#cc1818`, good `#f0b849`, strong `#4ab866`) and `.woocommerce-password-hint`, with `"style"` added to `block.json`.
- `includes/Renderer/CheckoutBaseStyles.php`:
  - `enqueue( bool $with_custom_css ): void` enqueues `checkout-base.css` under `ScriptName::STYLE_CHECKOUT_BASE`, then the `DesignStyles` CSS.
  - The editor screen is the storefront's only peer.
- `includes/Renderer/DesignStyles.php`:
  - `css( Design $design, bool $with_custom_css = true )`.
  - `vars()` drops the spacing and button sections and adds `input-border-width` and `input-padding-inline`. Underline forces `input-radius: 0px`.
  - When Colours is on, it prints `body.yaycheckout-page, .editor-styles-wrapper { background-color: var(--yaycheckout-color-background); }`.
- `includes/Renderer/CheckoutPage.php`: replaces its `checkout-page.css` enqueue and `DesignStyles` call with `( new CheckoutBaseStyles() )->enqueue( true )`. It keeps both dequeues.
- `includes/Engine/Admin/LayoutEditorCanvas.php`: on `enqueue_block_assets`, when `is_admin()` and the current screen is the block editor for `Layout::POST_TYPE`, calls `( new CheckoutBaseStyles() )->enqueue( false )`.
- `includes/Engine/Register/ScriptName.php`: `STYLE_CHECKOUT_PAGE` becomes `STYLE_CHECKOUT_BASE = 'yaycheckout/style/checkout-base'`.
- `includes/Renderer/CheckoutColour.php`: deleted, along with its boot line in `includes/YayCheckout.php:28`. These files replace `var(--yaycheckout-primary)` with `var(--yaycheckout-color-primary, var(--wp--preset--color--primary, #3858e9))`:
  - `place-order/style.css`
  - `progress/style.css`
  - `steps/style.css`
  - `step-button/style.css`
  - `back-button/style.css`
  - `return-to-cart/style.css`
- `includes/Models/Design.php`:
  - drops the `spacing` and `buttons` sections, `SPACING_SCALES`, `PRESET_NAME_MAX_CHARS`, `$presets`, `$preset_id`, `presets()`, `user_presets()`, `has_preset()`, `user_preset_index()` and `sanitize_preset()`
  - `sanitize_input()` requires `tokens` and `custom_css` and ignores other keys
  - `from_option()` reads only `tokens`, `custom_css` and `updated_at`
  - `to_array()` (or the equivalent) emits `{ tokens, custom_css, updated_at }`
- Related PHP:
  - `includes/Repositories/DesignRepository.php` loses its preset methods.
  - `includes/Controllers/DesignRestController.php` uses the body and response shape above.
  - `includes/Controllers/SetupRestController.php` takes `design: { tokens, custom_css }`.
  - `includes/Pro/Controllers/DesignPresetRestController.php` is deleted and unregistered.
- SPA:
  - `apps/admin/src/lib/schema/design.schema.ts`, `lib/api/design.api.ts`, `lib/api/setup.api.ts`, `lib/queries/design.queries.ts` and `lib/helpers/design.helper.ts` drop the preset, spacing and button parts.
  - The Design page loses `SpacingGroup.tsx`, the Buttons group in `TokenGroups.tsx`, `PresetsCard.tsx` and `SavePresetModal.tsx`. `ResetConfirmModal.tsx` reads "Colours, typography and inputs go back to their defaults and save immediately. Custom CSS is kept." `DesignPage.tsx`'s description reads "Customize checkout colors and typography."
  - The setup wizard loses `BrandStep.tsx`'s radius and button-style controls and `SetupWizard.tsx`'s `activePresetIdFor()`.
  - `PreviewPane.tsx`: the button look and the 10px gap become fixed, and `NEUTRAL.radius` becomes `2`.
- Block editor previews:
  - `packages/blocks/src/shared/field-preview.tsx` and `.css`, `email/edit.tsx`: print the storefront classes.
  - `payment/edit.tsx` and `shipping-methods/edit.tsx`: print `ul.wc_payment_methods > li.wc_payment_method` and `ul#shipping_method > li` with radio and label.
  - `shared/section-preview.css`: keeps the heading rule only.

**Data and storage:**
- `yaycheckout_design` keeps its key. Its stored `tokens.buttons`, `tokens.spacing`, `presets` and `preset_id` are ignored on read and dropped on the next save.
- No migration. Uninstall is unchanged.

**Integration points:**
- WordPress hooks:
  - `wp_enqueue_scripts`, through the existing `CheckoutPage` hook.
  - `enqueue_block_assets`, from `LayoutEditorCanvas`, gated to layout editor screens.
- REST routes:
  - `GET/POST yaycheckout/v1/design`: shape changed.
  - `POST yaycheckout/v1/setup`: body changed.
  - Pro's design-preset routes: removed.
- WooCommerce markup targeted: `woocommerce_form_field()` output, the gateway and rate HTML, the selectWoo containers, notice templates (classic and block-theme banner), blockUI and the password strength meter.
- No sibling plugin reads `yaycheckout_design`.

**UI:**
- Design page: Colours, Typography, Inputs and Custom CSS.
- Setup wizard Brand step: colour and font.
- No new controls.

**Edge cases and failure modes:**
- **No `color-mix()` support** (Safari < 16.2, Chrome < 111): the border variable fails at computed-value time. `border-color` then takes its initial value, `currentColor`, so inputs still show a solid border in the text colour.
- **A theme with heavy input selectors** (for example `body .entry-content input`): it may outrank the base. The merchant's custom CSS, which prints last, remains the escape hatch. No `!important` arms race.
- **Gateways that print their own fields** (Stripe UPE, PayPal buttons): the base styles only the row and `.payment_box`, and leaves gateway iframes alone.
- **select2 dropdown:** it is appended to `body` and scoped to `body.yaycheckout-page`, so it never leaks to other pages. It never appears in the editor.
- **One gateway or one rate:** WooCommerce hides the radio. The row still has its border, and the `:has(input:checked)` checked state applies because WooCommerce still renders a checked hidden input.
- **Dark themes with Colours off:** borders come from `currentColor`, and inputs and rows have transparent backgrounds, so they adapt.
- **Inserter previews:** these render a single block without `.yaycheckout-checkout`. They get block CSS and fallbacks only, which is accepted.
- **Merchants who used Outline buttons, button radius, spacing or presets:** after upgrading, their checkout shows the base buttons and fixed spacing, and their saved presets are gone. This goes in the release note.

**Decision record:** `docs/decisions/2026-10-08-checkout-owns-base-styles.md`. It records that YayCheckout replaces WooCommerce's general stylesheet with its own base on the checkout, that Design tokens are CSS values with neutral fallbacks (never selectors), and the base / per-block / token placement rule.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Full reference look as the base (fixed `#3858e9`, `#757575` borders, system font) | Ignores the theme. The "Theme default" label would be wrong. |
| Structure-only base (no input or button look) | Themes that don't style inputs (most block themes) would show browser defaults. |
| Base as the Checkout block's `style.css` | User chose a plugin asset file. It also keeps page-level resets out of block CSS. |
| Payment and shipping rows in the base | Only one block prints each list. A shared partial keeps one source and loads only when the block renders. |
| Base selectors at `.yaycheckout-checkout :where(…)` (0,1,0) | Loses to a theme's `input[type=text]` (0,1,1). |
| `@layer` for the base | Unlayered theme CSS beats every layer. |
| Keep primary on the ungated `--yaycheckout-primary` | Colours off would still recolour blocks. Two names for one value. |
| Buttons follow the Inputs radius | User chose a fixed base plus block supports. |
| Delete old preset, button and spacing keys in an upgrade routine | Adds migration code for keys that `from_option()` already ignores. |
| Swap the wizard's button radius for an input radius | User chose to drop the controls. |
| Ship presets that reproduce reference templates | Out of scope. User removed presets. |

## Rollout

- Ships in both Free and Pro with no flag.
- Release note:
  - The checkout now uses YayCheckout's own base styles instead of WooCommerce's.
  - The Design page's Buttons and Spacing sections and design presets were removed; style buttons per block in the layout editor.
  - Saved presets are not kept.
- Existing installs keep their Colours, Typography, Inputs and custom CSS values.

## Open questions

None.
