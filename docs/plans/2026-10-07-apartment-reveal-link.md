# Apartment field behind a link Implementation Plan

**Status:** done 2026-10-07
**Goal:** The Apartment field (`billing_address_2` / `shipping_address_2`) starts collapsed behind a "+ Add apartment, suite, etc." link, and opens when the shopper clicks it.
**Spec:** none, planned from the request and a screenshot of the target look.
**Branch:** `onyx/feat/apartment-reveal-link` from `dev/edd`. `field/render.php` also changes on the unmerged `onyx/feat/field-help-text`. Whichever PR merges second rebases and resolves that file.
**Tests:** none. CLAUDE.md's test rule names `RequiredFieldOverlay::apply()` among others, and this plan doesn't touch it. The behaviour is display and interaction, so each task ends in an exact manual check.

> On approval, step 0 copies this plan to `docs/plans/2026-10-07-apartment-reveal-link.md` and commits it with Task 1.

## Context

Most shoppers have no apartment or suite. A permanently visible Apartment field makes the checkout look longer than it is. Shopify-style checkouts show a link instead, which opens the field when needed. The merchant should be able to keep the old behaviour.

## Requirements

- **Out of scope:** collapsing any other field, and passing saved values into Field blocks (`field/render.php` doesn't pass a value today; that gap is unchanged).
- **Acceptance criteria:**
  1. A Field block whose key is `billing_address_2` or `shipping_address_2` gets two sidebar settings: **Hide behind a link** (on by default) and, while that is on, **Link text** (empty means `+ Add apartment, suite, etc.`, translated).
  2. With the setting on and the field not required, the storefront shows only the link. Clicking it shows the field, hides the link and moves focus into the input.
  3. A required Apartment field is never collapsed: the setting is disabled, with help `A required field is always shown.`, and `render.php` ignores it.
  4. The field opens by itself when its input gets a value (the login modal fills fields and triggers `change`), or when WooCommerce's country locale makes it required. When the locale hides the field, the link hides too.
  5. Canvas: the link shows, with the field below it faded, still editable.
  6. End to end: on a draft layout with both Apartment fields, the preview shows two links. Clicking the billing link opens Billing Apartment only, and turning the setting off shows the field with no link.
- **Decided with the user:** a toggle on the Field block, only for address_2, on by default. Editable link text with a default. The canvas shows the link plus the faded field.

## Global constraints

- Never hand-edit generated output: `vendor/`, `vendor-prefixed/`, `node_modules/`, `assets/dist/`, `release/`. Change the input and rerun the command that builds it. A block's `render.php` is edited in `packages/blocks/src/<block>/`, never in `assets/dist/blocks/`.
- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- From `.claude/rules/blocks.md`:
  - An added attribute needs a default in `render.php`, because stored layouts keep the old markup.
  - Render WooCommerce's own markup.
  - Editor-only styles go in the block's `editor.css`, imported by `edit.tsx` and scoped under `.editor-styles-wrapper`.
  - `pnpm build:blocks` is the only check for the blocks' TypeScript.

## Decisions

- **Attributes** (`field/block.json`): `"collapseBehindLink": { "type": "boolean", "default": true }` and `"revealText": { "type": "string", "default": "" }`. `render.php` treats a missing `collapseBehindLink` as `true`.
- **Which keys:** `billing_address_2` and `shipping_address_2`. PHP checks them with `in_array`. TS checks with `fieldKey.endsWith( '_address_2' )` and a `Mirrors` comment naming `field/render.php` (conventions.md rule for TS copies of PHP).
- **Markup when collapsed** (inside the existing `.yaycheckout-field` wrapper):
  - `<button type="button" class="yaycheckout-field__reveal" aria-expanded="false" aria-controls="{key}-collapsible">{text}</button>`
  - followed by `<div class="yaycheckout-field__collapsible" id="{key}-collapsible" hidden>…woocommerce_form_field() output…</div>`.
  - The container exists because WooCommerce's `address-i18n.js` calls `.show()` and `.hide()` on the inner `#{key}_field` row on every country change. Hiding our container, not that row, means the locale can't reopen a collapsed field.
- **Server renders collapsed.** WooCommerce's checkout already depends on JS (`update_order_review`), so a no-JS shopper losing the Apartment field is accepted. That avoids a flash of the field on load.
- **Storefront script:** a new `field/view.ts`, registered as `"viewScript": "file:./view.js"`, so it loads only where a Field renders. It binds per `.yaycheckout-field__reveal`:
  - On click, remove `hidden`, set `aria-expanded="true"`, hide the button and focus the `:input`.
  - `change` / `input` on the hidden input with a non-empty value opens it the same way, without moving focus.
  - On jQuery `document.body` event `country_to_state_changed`: if the inner row has the class `validate-required`, open it. If the row's inline `display` is `none` (locale hidden), hide the button. Otherwise show the button while still collapsed.
- **Link style** goes in `packages/blocks/src/checkout/style.css`, next to the help text rule (the Checkout front style loads wherever a field renders): `background: none; border: 0; padding: 0; margin-top: 0.25em; font: inherit; color: var(--wp--style--color--link, currentColor); cursor: pointer;`. This matches `button.yaycheckout-login__trigger`, without the underline, as in the screenshot.

---

### Task 0: Branch and save the plan

1. Run `git switch -c onyx/feat/apartment-reveal-link dev/edd`. Expect a clean tree.
2. Copy this plan to `docs/plans/2026-10-07-apartment-reveal-link.md`.

### Task 1: Storefront behaviour

**Files:** `packages/blocks/src/field/block.json`, `packages/blocks/src/field/render.php`, new `packages/blocks/src/field/view.ts`, `packages/blocks/src/checkout/style.css`

1. Add the two attributes and `viewScript` to `field/block.json`.
2. `field/render.php`:
   - Compute `$yaycheckout_collapsed`. It is true when the key is one of the two and the field is not required (`$yaycheckout_required`), and `collapseBehindLink` is missing or truthy.
   - Compute `$yaycheckout_reveal_text`: `LayoutMarkupReader::plain_text( $attributes, 'revealText', __( '+ Add apartment, suite, etc.', 'yaycheckout' ) )`.
   - When collapsed, print the button and open the container before the field (in both the WooCommerce branch and the fallback), then close it after. Escape the text with `esc_html()`, and the ids with `esc_attr()`.
3. Create `field/view.ts` with the behaviour in Decisions. Use `jQuery` only for the `country_to_state_changed` binding, through the same optional-jQuery guard pattern as `login/view.ts`.
4. Add the link CSS.
5. Verify the build: `pnpm build:blocks` → `compiled successfully`. `composer lint -- packages/blocks/src/field/render.php` → no errors.
6. Verify on the storefront. Add a Billing Apartment field to the draft layout (Field block, key `billing_address_2`) and save. On its preview link (`POST /yaycheckout/v1/layouts/{id}/preview-link`):
   - The page shows `+ Add apartment, suite, etc.` in link colour, and `#billing_address_2` is not visible.
   - Click the link: the field appears, the link is gone, `document.activeElement.id === 'billing_address_2'`, and the button has `aria-expanded="true"`.
   - Reload, then run `jQuery('#billing_address_2').val('Apt 4').trigger('change')`: the field opens.
   - Reload, and change the billing country to one whose WooCommerce locale hides address_2. If none applies on this store, check the hidden-row branch by running `jQuery('#billing_address_2_field').hide(); jQuery(document.body).trigger('country_to_state_changed')`. The link disappears.
   - Mark the field Required (with the toggle still on) and save: the field shows with no link.
7. Commit `field/block.json`, `field/render.php`, `field/view.ts`, `checkout/style.css` and the plan as `feat(blocks): hide the apartment field behind a link`.

### Task 2: Editor settings and canvas

**Files:** `packages/blocks/src/field/edit.tsx`, new `packages/blocks/src/field/editor.css`

1. `field/edit.tsx`:
   - When `fieldKey.endsWith( '_address_2' )`, add after the required controls a `ToggleControl` labelled `__( 'Hide behind a link', 'yaycheckout' )`. It is `disabled` when required, with the help text `A required field is always shown.`, and otherwise `Shoppers see a link and open the field when they need it.`.
   - While the toggle is on and the field is not required, add a `TextControl` labelled `__( 'Link text', 'yaycheckout' )` whose placeholder is the default text.
   - Canvas: when collapsed, render `<span className="yaycheckout-field__reveal">{ revealText || default }</span>` above the `FieldPreview`, which is wrapped in `<div className="yaycheckout-field__collapsed-preview">`.
2. `field/editor.css`, imported by `edit.tsx`:
   - `.editor-styles-wrapper .yaycheckout-field__reveal { display: block; margin-bottom: 0.5em; color: var(--wp--style--color--link, #2271b1); }`
   - `.editor-styles-wrapper .yaycheckout-field__collapsed-preview { opacity: 0.5; }`
3. Verify the build: `pnpm build:blocks` passes.
4. Verify in the editor. Select the Billing Apartment field:
   - The sidebar shows Hide behind a link (on) and Link text.
   - The canvas shows the link above the faded field, and the label is still editable on the canvas.
   - Typing `+ Add unit number` updates the canvas link. After saving, the preview link reads the same.
   - Turning on Required disables the toggle and removes the link from the canvas.
   - Select a non-address_2 field (Town / City): neither control appears.
5. Commit `field/edit.tsx` and `field/editor.css` as `feat(blocks): edit the apartment link in the Field block`.

### Final verification (acceptance criterion 6)

1. Add Shipping Apartment too, and save the draft.
2. The preview shows two links. Clicking the billing one opens `#billing_address_2` only, and `#shipping_address_2` stays hidden.
3. Turn Hide behind a link off on Billing Apartment and save: `#billing_address_2` is visible with no link before it.
4. Remove both test fields and save, which leaves the draft as it was.
5. Run `/yayplancheck docs/plans/2026-10-07-apartment-reveal-link.md`.

## Review focus

- **Existing layouts change on update:** `collapseBehindLink` defaults to `true`, so a store whose layout already has an Apartment field sees it collapse after updating. This is intended (the user chose "on by default"). Call it out in the PR.
- **A required field hidden at submit:** this is blocked twice. Server-side, a required field is never collapsed. Client-side, a locale that makes the field required opens it (Task 1 step 6, last two checks).
- **The country locale reopening the row:** WooCommerce's `.show()` targets `#{key}_field`, which sits inside our `hidden` container, so the field stays collapsed. Task 1 step 6 checks this after a country change.
- **Escaping:** `revealText` goes through `plain_text()` and `esc_html()`. Typing `<b>x</b>` as link text shows no bold.
