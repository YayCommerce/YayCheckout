# Address default structure Implementation Plan

**Status:** done 2026-10-07
**Goal:** New Billing and Shipping sections start with the screenshot's layout. First and last name sit side by side, then Company, Country, Address, the collapsed Apartment link, City, State and ZIP in one row, and Phone with help text.
**Spec:** none, planned from the request and a screenshot.
**Branch:** `trace/feat/address-default-structure` from `dev/edd`.
**Tests:** none. The change only seeds new blocks and layouts. CLAUDE.md's test rule names `RequiredFieldOverlay::apply()`, `LayoutMarkupReader::publish_error()`, `LayoutAssignment::pick()` and `LayoutImportReview`, and none of them changes. Each task ends in an exact manual check.

> On approval, step 0 copies this plan to `docs/plans/2026-10-07-address-default-structure.md` and commits it with Task 1.

## Context

Today a new Billing or Shipping section is either a flat list of the seven required fields (a new Checkout, and the new-layout seed) or empty (inserted alone). The merchant wants every new address section to start as the screenshot shows, a familiar Shopify-like form, without assembling it by hand.

## Requirements

- **Where it applies:**
  - A newly inserted Checkout block.
  - A Billing or Shipping block inserted alone.
  - `templates/default-layout.php`, the markup a new or reset layout starts with.
  - Saved layouts are untouched.
- **Structure,** the same for `billing_` and `shipping_` (labels are translatable):
  1. A two-column row: `First name` (required) | `Last name` (required).
  2. `Company` (optional).
  3. `Country/Region` (required, type `country`).
  4. `Address` (required, placeholder `House number and street name`).
  5. Apartment, `address_2`, labelled `Apartment, suite, unit, etc.` (optional). It shows as the `+ Add apartment, suite, etc.` link through the existing `RevealLink` default.
  6. A three-column row at 50% / 25% / 25%: `City` (required) | `State` (required, type `state`) | `ZIP Code` (required).
  7. `Phone` (optional, type `tel`), with help text `In case we need to contact you about your order`.
- **Decided with the user:** all three places get it. Shipping is identical, including Phone. Labels are the screenshot's. WooCommerce still relabels State and ZIP per country when the shopper changes country (`address-i18n.js`), which is accepted.

## Global constraints

- Never hand-edit generated output: `assets/dist/`. Rebuild with `pnpm build:blocks`.
- Product PHP must run on PHP 7.4.
- From the header of `templates/default-layout.php`:
  - "Keep the markup as the editor saves it: a hand edit that differs by one class or style makes the editor flag the block as invalid."
  - Translated text goes back in as PHP (`esc_html_e()`).
- `pnpm build:blocks` is the only check for the blocks' TypeScript. `shared/` is also the SPA's `@blocks-shared`, so a change there also needs `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`.

## Decisions

- **Rows use core `columns` / `column` blocks.** Billing and Shipping already allow core blocks (`useBlocksAllowedExcept( isCheckoutSection )`), and the seed already uses columns. The Field block's `width` attribute is not rendered anywhere, and a 50/25/25 row needs widths anyway. Columns stack on narrow screens by core's default.
- **One TS source** for both editor templates: new `packages/blocks/src/shared/address-template.ts`.
  - `export function addressTemplate( prefix: 'billing' | 'shipping' ): TemplateArray` returns the tree above. A Field is `[ fieldMeta.name, { fieldKey, type, label, required, placeholder?, description? } ]`. A row is `[ 'core/columns', {}, [ [ 'core/column', { width? }, [ field ] ], … ] ]`, with widths `'50%'`, `'25%'`, `'25%'` on the City / State / ZIP row and none on the name row.
  - `TemplateArray` is a local type, `( string | object | TemplateArray )[]`.
  - Its comment says that `templates/default-layout.php` mirrors it, and to change both together.
- **Consumers:**
  - `checkout/edit.tsx`: `defaultTemplate()` uses `[ billingMeta.name, {}, addressTemplate( 'billing' ) ]` and the same for shipping. Delete `addressSection()` and the `address-fields` import.
  - `shared/address-section-edit.tsx`: pass `template: addressTemplate( section )` to `useInnerBlocksProps`. A template only fills an empty block, so saved sections are untouched. As with Checkout, a section the merchant empties is refilled on the next load.
- **Shipping Phone persists.** WooCommerce has no `shipping_phone` checkout field by default. `RequiredFieldOverlay::apply()` already adds any layout field WooCommerce lacks (`restore_missing_field()`), so it is posted and saved like the others. Verified on the storefront in Task 2.
- **Docs that describe the old pairing:**
  - `shared/address-fields.ts` header says `default-layout.php` "seeds new layouts with a subset of it". It becomes: keys come from this catalog, and the structure lives in `address-template.ts`, mirrored by `default-layout.php`.
  - The same "one pair runs the other way" sentence in `docs/conventions.md`.
  - The "A new Checkout starts from `defaultTemplate()`" bullet in `.claude/rules/blocks.md`.

---

### Task 0: Branch and save the plan

1. Run `git switch -c trace/feat/address-default-structure dev/edd`. Expect a clean tree.
2. Copy this plan to `docs/plans/2026-10-07-address-default-structure.md`.

### Task 1: Editor templates

**Files:** new `packages/blocks/src/shared/address-template.ts`, `packages/blocks/src/checkout/edit.tsx`, `packages/blocks/src/shared/address-section-edit.tsx`, `packages/blocks/src/shared/address-fields.ts` (comment), `.claude/rules/blocks.md`, `docs/conventions.md`

1. Create `address-template.ts` and wire up both consumers as in Decisions. Update the three docs.
2. Verify the build: `pnpm build:blocks` → `compiled successfully`. `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` → no errors.
3. Verify in the editor without saving. In any layout, insert a Checkout block into an empty spot, then a lone Billing block. Undo both, so the layout stays clean, before leaving.
   - **Both:** Billing shows First/Last side by side, then Company, Country/Region, Address with its placeholder, the faded Apartment with its `+ Add apartment, suite, etc.` link, City / State / ZIP in one row at 50/25/25, and Phone with `In case we need to contact you about your order` under it.
   - **Checkout:** Shipping shows the same with `shipping_` keys. Check with `wp.data.select('core/block-editor')` that every Field's `fieldKey` has the right prefix.
   - **Billing alone:** it is no longer empty.
   - The Field sidebar for City still shows `Town / City (billing_city)` in the WooCommerce field picker, proving the key resolves through the catalog at depth.
4. Commit `address-template.ts`, `checkout/edit.tsx`, `address-section-edit.tsx`, `address-fields.ts`, `.claude/rules/blocks.md`, `docs/conventions.md` and the plan as `feat(blocks): start address sections with the full address structure`.

**Added during implementation:** `packages/blocks/src/checkout/style.css` sets `margin-block: 0` on columns inside Billing and Shipping. Core's 1.75em Columns margin put an extra ~30px under the name row and the City row. With the rule, every field is the same 82–83px apart on the storefront.

### Task 2: New-layout seed

**Files:** `templates/default-layout.php`

1. Replace the Billing and Shipping inner markup with the same structure. Write it the way the editor saves core columns:
   - `<!-- wp:columns -->` + `<div class="wp-block-columns">`
   - each `<!-- wp:column {"width":"50%"} -->` + `<div class="wp-block-column" style="flex-basis:50%">`, or the bare `<!-- wp:column -->` + `<div class="wp-block-column">`
   - each Field as a self-closing comment, with `label`, `placeholder` and `description` through `esc_html_e()`.
   - Update the header's last line about which file it mirrors.
2. Verify the build: `composer lint -- templates/default-layout.php` → no errors.
3. Verify the markup is valid. In the layout editor's console, run `wp.blocks.parse( markup )`, where `markup` is the output of `LayoutRepository::default_markup()`. Get it with `wp eval 'echo YayCheckout\Repositories\LayoutRepository::default_markup();'`, using the Local socket `--exec` define from the earlier sessions, then paste it in. Walk the result: every block, at every depth, has `isValid === true`, and the Field keys and labels match Task 1.
4. Verify on the storefront. From Layouts → Add layout, create one draft layout, which starts from the seed. On its preview link (`POST /yaycheckout/v1/layouts/{id}/preview-link`):
   - **Order:** both sections render in the screenshot's order.
   - **Rows:** at desktop width, First/Last share a row and City/State/ZIP share one row (`offsetTop` equal within each row).
   - **Apartment:** it shows as the link.
   - **Phone:** it shows its help text inline.
   - **Shipping Phone saves:** place a test order (preview cart, Cash on delivery) with Ship to a different address and a shipping phone. The order edit screen shows that phone under Shipping.

   Trash the test layout and the test order afterwards.
5. Commit `templates/default-layout.php` as `feat(layouts): seed new layouts with the full address structure`.

### Final check

Run `/yayplancheck docs/plans/2026-10-07-address-default-structure.md`.

## Found during verification (not fixed here)

- WooCommerce's `address-i18n.js` rewrites every address field's label from its locale table when the country is set, not only State and ZIP. Once a country is chosen, the storefront reads "Country / Region", "Street address" and "Town / City". This already applies to any label a merchant sets on these fields today. Keeping layout labels would need a filter on WooCommerce's locale data, which is a separate change.
- Shipping Phone: this WooCommerce version already defines `shipping_phone`, and `RequiredFieldOverlay::apply()` keeps it optional. I checked this with WP-CLI instead of a test order, because the preview cart totals 0 and shows no "Ship to a different address" toggle.

## Review focus

- **Seed markup validity:** one stray attribute or style in `default-layout.php` makes the editor flag a block as invalid on every new layout. Task 2 step 3 checks every block at every depth.
- **The two copies drift apart:** `address-template.ts` and `default-layout.php` now hold the same structure. Both name each other in their header comments, and Task 2 step 3 compares keys and labels.
- **Refill on empty:** a merchant who deletes every field in a section gets the structure back on reload. Core templates work this way, and Checkout already does the same.
- **Concurrent edits on the shared draft:** someone else edits layout 32 on this site, so verification never saves it. It uses a separate test layout, which is then trashed.
