# Inline help text Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking.

**Status:** active
**Goal:** Every field's help text shows inline under the field, always visible, in place of WooCommerce's focus popup.
**Spec:** none, planned from the request. Follows [2026-10-07-field-help-text.md](2026-10-07-field-help-text.md) on the same branch, `onyx/feat/field-help-text`.
**Architecture:** Keep WooCommerce's markup: `woocommerce_form_field()`'s `description` argument and its `span.description`. One CSS rule restyles it as inline text and neutralises WooCommerce's toggle. WooCommerce shows and hides the span with jQuery `slideDown()`/`slideUp()`, which animate inline `height`, `padding`, `margin`, `overflow` and `display`. Stylesheet rules marked `!important` beat inline styles, so the animation runs but nothing moves. The user chose this over printing our own span.
**Touches:** `packages/blocks/src/checkout/style.css` only.
**Tests:** none. CLAUDE.md's test rule names publish rules, layout assignment, required fields and import review. This is a stylesheet change, so it ends in an exact manual check.

## Requirements

- **Goal:** help text is always visible under its field, with no setting.
- **Out of scope:** PHP and markup changes, a style setting, and editor changes. The canvas already shows help text inline through `FieldPreview`. WooCommerce's `aria-hidden="true"` on the span also stays: screen readers still read the text through the input's `aria-describedby`, but skip it in browse mode.
- **Acceptance criteria:**
  1. On the storefront, `span.description` for Field, Email and Extra field is visible on load, has no popup background, arrow, padding or rounded corners, and shows as small, muted text in the theme's text colour about 0.25em under the field.
  2. Focusing the input, clicking elsewhere and pressing Escape in it never hide or move the text.
  3. The hand-written spans also match: Extra field's checkbox-choices list, which sits outside `.woocommerce-input-wrapper`.
  4. End to end: on one layout with help text on Email, Billing Town / City and an Extra field (Text, then Checkbox with two options), the storefront preview shows all three inline before and after interacting with each field.
- **Decided with the user:** inline only, with no setting, through CSS over WooCommerce's description, not our own span.

## Global constraints

- Never hand-edit generated output: `assets/dist/` is rebuilt with `pnpm build:blocks`.
- Render WooCommerce's own markup (`woocommerce_form_field()`, its class names and ids), because WooCommerce's checkout scripts and themes target it (`.claude/rules/blocks.md`).

## Review focus

- **Specificity against WooCommerce:** its rule is `.woocommerce form .form-row .woocommerce-input-wrapper .description` (specificity 0,4,1), so ours must be higher. The selector below is 0,5,0. Step 2 checks the computed `background-color` is transparent.
- **Animation leftovers:** after a `slideUp()`, jQuery leaves inline `display: none` and an animated `height`. Step 2 blurs a field and checks that the computed `display` is `block` and the height has not shrunk.
- **Themes that style the popup** with a more specific selector would win. That is accepted. Step 2 is run on the site's active theme only.

---

### Task 1: Inline help text stylesheet

**Files:**
- Modify: `packages/blocks/src/checkout/style.css` (the Checkout block's front style, loaded wherever a field renders)

- [ ] **Step 1: Add the rule.** Its comment says WooCommerce animates this span with `slideUp()`/`slideDown()`, and that the `!important` declarations exist only to outrank those inline styles. Selector: `.yaycheckout-checkout.woocommerce-checkout :is(.yaycheckout-field, .yaycheckout-email, .yaycheckout-extra-field).form-row .description`. Every one of those classes is always on the Checkout and field wrappers that `render.php` prints.
  - Declarations with `!important`: `display: block; height: auto; overflow: visible; padding: 0; margin: 0.25em 0 0;`. These are the properties jQuery's slide animation writes inline.
  - Declarations without `!important`: `position: static; background: none; color: inherit; border-radius: 0; font-size: 0.875em; opacity: 0.75;`. These undo WooCommerce's popup look.
  - Plus `<same selector>::before { display: none; }` to remove WooCommerce's arrow.
- [ ] **Step 2: Verify.** `pnpm build:blocks` gives `compiled successfully`. Then use the draft layout's preview link (`POST /yaycheckout/v1/layouts/{id}/preview-link`). Set Email to `We send your receipt here`, Town / City to `As on your ID`, and add an Extra field with Key `gift_note` (Text) and Help text `Printed on the gift card`. Save, then on the preview:
  - Each of `#billing_email-description`, `#billing_city-description` and `#gift_note-description` is visible on load, with computed `display: block`, `background-color: rgba(0, 0, 0, 0)` and `padding-top: 0px`, and no `::before` arrow.
  - Focus `#billing_city`, wait 300 ms, click the page body, wait 300 ms, focus it again and press Escape. After each action, `#billing_city-description` keeps `display: block` and the same `offsetHeight` it had on load.
  - Switch `gift_note` to Checkbox with two options and save. The span after the choices has the same computed styles.
  - Then clear the three help texts, remove `gift_note` and save. No `span.description` remains in the fields.
- [ ] **Step 3: Commit** `packages/blocks/src/checkout/style.css` and `docs/plans/2026-10-07-help-text-style.md` as `feat(blocks): show help text inline under each field`. Then run `/yayplancheck docs/plans/2026-10-07-help-text-style.md`.
