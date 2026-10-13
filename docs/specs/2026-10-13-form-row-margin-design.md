# Form Row Margin Design

**Status:** accepted 2026-10-13
**Related:** [Checkout base CSS](2026-10-08-checkout-base-css-design.md)

## Goal

On the storefront, field rows are spaced only by the layout's own spacing (the section gap, Block spacing, the Column rule), as they already are in the layout editor, instead of by an extra 16px above and below every row.

## Context

- Field, Email, Extra field, Order note, Additional fields and Create account print WooCommerce's rows through `woocommerce_form_field()`, which hard-codes `<p class="form-row …">` (`woocommerce/includes/wc-template-functions.php:3453`, hidden variant `:3491`). Different address and Create account hard-code their own `<p class="form-row …">` in `render.php`.
- `CheckoutPage::enqueue_scripts()` dequeues `woocommerce-general`, which used to reset that margin, and `assets/css/checkout-base.css` sets none. So the browser default `p { margin: 1em 0 }` applies: 16px at the theme's 16px font size.
- `checkout-base.css` already spaces fields with the section's flex gap (`:is(.yaycheckout-billing, .yaycheckout-shipping, …) { gap: 12px }`, or Block spacing via `Renderer\BlockGap`) and with `.wp-block-column > * + * { margin-block-start: 12px }`. In a flex column the row's margins don't collapse, so input → next label measures 44px (16 + 12 + 16) instead of 12px (measured on Twenty Twenty-Five, layout 1251).
- The editor's field preview (`packages/blocks/src/shared/field-preview.tsx`) is a `<div class="yaycheckout-field-preview form-row">` with no margin, so the editor already shows the intended spacing and only the storefront is loose.
- A Field, Extra field or Order note that the merchant hides behind a toggle puts its row in `.yaycheckout-disclosure__panel` instead of directly in the block wrapper.

## Scope

**In:**
- Zero block margin on a `.form-row` that sits directly in one of these: a YayCheckout block wrapper that itself carries `.form-row` (Email, Field, Extra field, Order note), `.yaycheckout-disclosure__panel`, `.yaycheckout-different-address`, `.yaycheckout-create-account`, `.woocommerce-additional-fields__field-wrapper`, or `.yaycheckout-create-account > .create-account`.
- 12px between consecutive rows inside `.woocommerce-additional-fields__field-wrapper` and `.yaycheckout-create-account > .create-account`, which have no gap of their own.

**Out:**
- Changing the row tag from `<p>` to `<div>` (see Alternatives).
- Rows that other plugins print through WooCommerce checkout hooks (`woocommerce_before_order_notes`, `woocommerce_after_order_notes`, `woocommerce_before_checkout_registration_form`, and others) outside the wrappers above. They keep their spacing.
- The login dialog's rows, which `packages/blocks/src/login/style.scss` spaces itself (`margin: 0 0 1rem`).
- A Block spacing setting for the rows inside Additional fields and Create account. They use a fixed 12px.
- Layouts whose markup holds `"layout":{"type":"flow"}`: only local layouts 1251 and 1253, created before `aee0584` removed it from the template. That group renders with no Block spacing (`class="wp-block-group wp-block-group-"`), so its heading touches the Email label once the margin goes. The plugin has never been released, so no migration is needed. Recreate those layouts from the current template.

## Acceptance criteria

1. Given a layout made from the Classic two-column template on Twenty Twenty-Five, when the checkout preview loads, then every `p.form-row` inside Email, Field, Extra field and Order note computes `margin-top` and `margin-bottom` of `0px`.
2. Given the Billing section with no Block spacing set, then the distance from a field's input bottom to the next field's label top is 12px (±1px) for every pair of consecutive fields.
3. Given the Billing section with Block spacing 24px, then that distance is 24px (±1px).
4. Given two Field blocks stacked in one Column, then the distance from the first input to the second label is 12px (±1px).
5. Given an Order note hidden behind its toggle and opened, then its `p.form-row` inside `.yaycheckout-disclosure__panel` computes margin `0px`.
6. Given WooCommerce order fields from a filter (two or more rows in `.woocommerce-additional-fields__field-wrapper`), then consecutive rows are 12px apart, and the first row's top and the last row's bottom margins are `0px`.
7. The layout editor canvas shows the same field spacing as before the change.
8. The login dialog's rows keep `margin-bottom: 16px` (1rem).
9. End-to-end verification: create a layout from the Classic two-column template, open its preview at `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>`, and run this in the console: `[...document.querySelectorAll('.yaycheckout-checkout :is(.form-row, .yaycheckout-disclosure__panel) > .form-row')].filter(r => parseFloat(getComputedStyle(r).marginTop) || parseFloat(getComputedStyle(r).marginBottom)).length`. It returns `0`. Then confirm by eye that the Contact information heading → Email label gap is 12px and the Billing fields are 12px apart.

## Constraints

- CSS only, in `assets/css/checkout-base.css`. No PHP or block markup changes.
- Selectors start with `.yaycheckout-checkout` and use no `@layer`, as the file's header requires.
- Set `margin-block`, never `margin`, so a theme's or block's inline margins stay intact.
- No `!important`.

## Approach

**Chosen:** Scoped CSS reset in `checkout-base.css`.

**Architecture:** The block wrapper and its container own a row's spacing, and WooCommerce's inner row adds none. One rule zeroes the block margin of a `.form-row` directly inside the wrappers listed in Scope. A second rule gives consecutive rows in the two multi-row wrappers 12px, the same pattern as the existing `.wp-block-column > * + *` rule. Both rules go next to the existing field-row rules, each with a one-line comment saying why.

**Components:**
- `assets/css/checkout-base.css`: the two rules, roughly:
  ```css
  /* WooCommerce prints each row as a <p>, whose browser margin would add to the layout's own spacing. */
  .yaycheckout-checkout :is(.form-row, .yaycheckout-disclosure__panel, .yaycheckout-different-address, .yaycheckout-create-account, .yaycheckout-create-account > .create-account, .woocommerce-additional-fields__field-wrapper) > .form-row {
  	margin-block: 0;
  }

  .yaycheckout-checkout :is(.yaycheckout-create-account > .create-account, .woocommerce-additional-fields__field-wrapper) > .form-row + .form-row {
  	margin-block-start: 12px;
  }
  ```

**Data and storage:** None.

**Integration points:** None. WooCommerce's JS selects rows by `.form-row` and `#<key>_field`, which are unchanged.

**UI:** Storefront only. The editor canvas loads the same stylesheet, but its field previews are `<div>`s with no margin, so it looks the same.

**Edge cases and failure modes:**
- A classic theme that sets `.woocommerce form .form-row { margin… }` (0,2,1) loses to the reset's specificity (0,3,0 or higher), so its rows tighten too, which is intended.
- A row that WooCommerce's `address-i18n.js` hides (`display: none`) is unaffected. The existing rule still hides its empty Field wrapper.
- `<div class="clear">` inside Create account's account fields is not a `.form-row`, so it gets no margin.

**Decision record:** None.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Swap `<p>` for `<div>`: a helper calls `woocommerce_form_field( …, 'return' => true )` and rewrites the outer tag, and the hard-coded rows change in `render.php` | Rewrites WooCommerce's HTML string after third-party filters have run, and the user preferred the CSS-only change. |
| Reset every `p.form-row` in `.yaycheckout-checkout` | Rows that other plugins print through checkout hooks, outside any gap container, would stack with no space. |
| Reset every `.form-row` | Zeroes the block wrappers' own margins too, so a flow parent's Block spacing would be lost (for example, Order note's 40px). |

## Rollout

None. The change applies to every layout on update, and the plugin has never been released.

## Open questions

None.
