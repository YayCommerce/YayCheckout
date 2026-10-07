# Field help text

**Status:** active
**Branch:** `onyx/feat/field-help-text` from `dev/edd`
**Tests:** none. CLAUDE.md's test rule covers publish rules, layout assignment, required fields and import review; help text is display-only, so every task ends in an exact manual check instead.

> On approval, step 0 copies this file to `docs/plans/2026-10-07-field-help-text.md` (yayplan's location) and commits it with Task 1.

## Context

Merchants can't explain a checkout field to shoppers ("We only use this to send your receipt"). WooCommerce already supports this through `woocommerce_form_field()`'s `description` argument. The Field block even has an unused `description` attribute that `LayoutMarkupReader::fields()` reads, but no control sets it and `render.php` never prints it.

## Requirements

1. The Field, Extra field and Email address blocks each get a **Help text** setting.
2. The merchant types it in the block sidebar (a `TextControl` under Placeholder). The canvas preview shows it read-only under the input.
3. The storefront shows it the way WooCommerce does: passed as `woocommerce_form_field()`'s `description`, which prints `<span class="description" id="{key}-description">` and adds `aria-describedby` to the input. WooCommerce's own CSS and JS decide how it looks, as a popup while the input is focused.
4. An empty help text prints nothing. Saved layouts without the attribute render exactly as before.

Constraints, verbatim from CLAUDE.md:
- Never hand-edit generated output: `assets/dist/` is rebuilt with `pnpm build:blocks`. A block's `render.php` is edited in `packages/blocks/src/<block>/`.
- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- From `.claude/rules/blocks.md`: an added attribute needs a default in `render.php`, because stored layouts keep the old markup. Render WooCommerce's own markup, because WooCommerce's checkout scripts and themes target it.

## Decisions

- **Attribute:** `description` (string, default `""`) on all three blocks. Field already declares it. The name matches WooCommerce's argument and `LayoutMarkupReader::fields()`. No change to `LayoutMarkupReader`: each `render.php` reads its own attributes.
- **PHP read:** `LayoutMarkupReader::plain_text( $attributes, 'description' )` (`includes/Services/LayoutMarkupReader.php:244`) strips tags, decodes entities and trims. Its result goes to WooCommerce as `esc_html( $help )`, because WooCommerce only runs `wp_kses_post()` on it. In the no-WooCommerce fallback markup it is printed with `esc_html()`.
- **Fallback markup:** wherever a `render.php` prints its own control, add `aria-describedby="{key}-description"` to the control when help text is set, and print `<span class="description" id="{key}-description">…</span>` as the wrapper's last child. That covers the no-WooCommerce branch of all three blocks, and Extra field's checkbox-choices branch, which prints its own markup even with WooCommerce loaded.
- **Hidden fields:** Field hides the Help text control when `type === 'hidden'` (nothing for a shopper to read). `render.php` still passes whatever is stored. WooCommerce's hidden type ignores the description.
- **Editor preview:** a new optional `description` prop on the shared `FieldPreview`, rendered as `<p className="yaycheckout-field-preview__description">` after the control. It is always visible in the editor, since the focus popup can't be previewed.

## Tasks

### Task 0: Branch and save the plan

1. `git switch -c onyx/feat/field-help-text dev/edd`. Expect a clean `git status`.
2. Copy this plan to `docs/plans/2026-10-07-field-help-text.md`. It is staged with Task 1's commit.

### Task 1: Shared preview, then Field

**Interfaces**
- `packages/blocks/src/shared/field-preview.tsx`: `FieldPreviewProps` gains `description?: string`. When it is non-empty, render `<p className="yaycheckout-field-preview__description">{ description }</p>` as the last child of `.yaycheckout-field-preview`.
- `packages/blocks/src/shared/field-preview.css`: `.editor-styles-wrapper .yaycheckout-field-preview__description`: `margin: 4px 0 0; color: #646970; font-size: 12px;` (the same grey as `__optional`).

Steps
1. Add the prop and the CSS above. `shared/` is also the SPA's `@blocks-shared` (blocks rule), so this task's verification includes the SPA type-check.
2. `packages/blocks/src/field/edit.tsx`: add `description` to the destructure. Under the Placeholder control, when `type !== 'hidden'`, add `TextControl` label `__( 'Help text', 'yaycheckout' )`, help `__( 'Shown under the field while the shopper fills it in.', 'yaycheckout' )`, value `description`, `onChange` → `setAttributes( { description: value } )`. Pass `description` to `FieldPreview`.
3. `packages/blocks/src/field/render.php`: `$yaycheckout_description = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'description' );`. Add `'description' => esc_html( $yaycheckout_description ),` to the `woocommerce_form_field()` args. In the fallback, apply the fallback-markup decision to the textarea, the checkbox and the input.
4. Verify: `pnpm build:blocks` → `compiled successfully`. `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` → no errors. `composer lint -- packages/blocks/src/field/render.php` → no errors.
5. Verify in the editor: open a layout, select the Billing "Town / City" field, set Help text to `As on your ID`. Expect the grey text under the preview input. Save, open the storefront checkout and focus Town / City. Expect WooCommerce's description popup with `As on your ID`. In the page source, expect `<span class="description" id="billing_city-description"` and `aria-describedby="billing_city-description"` on the input. Then set Help text to `<b>x</b>` and save. The storefront must show no bold (`x` once `plain_text()` strips the tags).
6. Commit `packages/blocks/src/shared/field-preview.tsx`, `packages/blocks/src/shared/field-preview.css`, `packages/blocks/src/field/edit.tsx`, `packages/blocks/src/field/render.php`, `docs/plans/2026-10-07-field-help-text.md` as `feat(blocks): add help text to the Field block`.

### Task 2: Extra field

1. `packages/blocks/src/extra-field/block.json`: add `"description": { "type": "string", "default": "" }`.
2. `packages/blocks/src/extra-field/edit.tsx`: add `description` to its attributes interface and destructure. Add the same Help text `TextControl` after the Placeholder control (shown for every type), and pass `description` to `FieldPreview`.
3. `packages/blocks/src/extra-field/render.php`: read `$yaycheckout_description` as in Task 1. Add it to `$yaycheckout_args` as `esc_html()`. Apply the fallback-markup decision to the select, the radios (`aria-describedby` on each radio), the checkbox-choices list (on each checkbox), the single checkbox and the input.
4. Verify: `pnpm build:blocks` and `composer lint -- packages/blocks/src/extra-field/render.php` pass. In the editor, add an Extra field with Key `gift_note`, type Text, Help text `Printed on the gift card`. Expect the preview text. On the storefront, expect the focus popup. Then switch it to Checkbox with two options and save. Expect `<span class="description" id="gift_note-description">` after the choices, and `aria-describedby` on both checkboxes.
5. Commit the three files as `feat(blocks): add help text to the Extra field block`.

### Task 3: Email address

1. `packages/blocks/src/email/block.json`: add the same `description` attribute.
2. `packages/blocks/src/email/edit.tsx`: add `description` to `EmailAttributes` and the destructure, add the Help text `TextControl` after Placeholder, and pass `description` to `FieldPreview`.
3. `packages/blocks/src/email/render.php`: read `$yaycheckout_description`, add `'description' => esc_html( $yaycheckout_description )` to the args, and apply the fallback-markup decision to the input.
4. Verify: `pnpm build:blocks` and `composer lint -- packages/blocks/src/email/render.php` pass. Set Help text to `We send your receipt here` and save. On the storefront, focusing Email address shows the popup, and the source has `id="billing_email-description"`.
5. Commit the three files as `feat(blocks): add help text to the Email address block`.

### Final verification

Open a layout saved before this change and change nothing. Its storefront checkout source has no `class="description"` span in any YayCheckout field. Clear every help text set above and save: the spans are gone again. Then run `/yayplancheck docs/plans/2026-10-07-field-help-text.md`.

## Review focus

- **Escaping:** WooCommerce runs `wp_kses_post()`, not `esc_html()`, on the description. Passing the raw attribute would let a merchant's `<a>` or `<strong>` through. Every path must pass `esc_html( plain_text(…) )`. Task 1 step 5 checks this with `<b>x</b>`. Note that `plain_text()` strips tags first, so the storefront may show just `x`. Either result passes, as long as no bold is rendered.
- **Popup depends on WooCommerce's assets:** the focus popup comes from WooCommerce's frontend CSS and `checkout.js`. If the theme doesn't load WooCommerce's stylesheet, the text shows as a plain line under the input. That is acceptable, but note it if the step-5 checks show a plain line.
- **Extra field checkbox-choices** prints its own markup even with WooCommerce loaded. It is the one path where a missing fallback span would show up on a normal store.
