# Group "Pin to Bottom" Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. This plan has no spec; `## Requirements` is the contract.

**Status:** done 2026-10-12
**Goal:** A merchant can pin a core Group in a checkout layout to the bottom of the shopper's screen, on all screens or on mobile only, so template 04's summary and action bar can be built.
**Spec:** none, planned from the request
**Architecture:** This copies Hide When Empty (`shared/hide-when-empty.tsx` + `Renderer\HideWhenEmpty`). The editor adds one attribute to `core/group` and a panel that shows only in the layout editor. A `render_block_core/group` filter adds a class, and storefront CSS makes that class `position: fixed`. A small jQuery-free script keeps the page's bottom clearance equal to the bar's height. Core's Position support was not used: it offers only sticky, its control shows only when the theme enables `position.sticky`, and a new core position type can't be added from a plugin. In the editor the Group stays in place, because the class is added only on render. A fixed bar inside the iframe would cover the blocks being edited.
**Touches:** new `includes/Renderer/PinToBottom.php`; `includes/YayCheckout.php`; `includes/Blocks/BlockRegistry.php`; `assets/css/checkout-base.css`; new `assets/js/checkout-pin-to-bottom.js`; new `packages/blocks/src/shared/pin-to-bottom.tsx`; `packages/blocks/src/checkout/index.ts`; `docs/architecture.md`. Nothing shared across plugins.
**Tests:** none. CLAUDE.md's test rule doesn't call for one here: this is CSS and JS positioning plus a one-line class filter, with no checkout logic. Each task ends in an exact manual check instead.

## Requirements

- **Goal:** a Group marked "Pin to bottom" sits fixed at the bottom of the storefront checkout. The page's last content stays reachable above it, and on touch screens the bar gets out of the keyboard's way.
- **Out of scope:**
  - Building template 04 (`accordion-one-page` stays Coming soon).
  - Pinning to the top, or pinning Columns, Column or YayCheckout blocks.
  - An editor preview of the fixed position.
  - "Show/Hide summary" text that changes with the state.
  - Moving the bar out of a theme ancestor that breaks `position: fixed` (a transformed ancestor). The bar can't leave `form.checkout`, or a Place Order inside it would stop submitting.
  - Pro gating. This is a Free feature.
- **Acceptance criteria:**
  1. In the layout editor, a selected Group's sidebar shows a **Pin to bottom** panel with Off / All screens / Mobile only. Other post types never show it.
  2. Given `yaycheckoutPinToBottom: "all"`, the storefront Group carries `yaycheckout-pinned`. Its computed style is `position: fixed; bottom: 0px; left: 0px; right: 0px` at every width, and the page enqueues `yaycheckout-pin-to-bottom`.
  3. Given `"mobile"`, the Group also carries `yaycheckout-pinned--mobile`. It is fixed at a viewport width of 781px or less, and at 782px or more it renders in its normal place (with core's sticky, if set).
  4. Given a pinned Group that also has core's sticky (`style.position.type: "sticky"`, `top: 24px`), it is fixed to the bottom with `top: auto`, not stretched from the top.
  5. Given a Group with no background colour, the pinned bar shows a white background and the shadow `0 -2px 3px rgba(0,0,0,.05)`. Given a background colour, it keeps that colour.
  6. While a pinned bar is fixed, `<html>` carries `yaycheckout-has-pinned`, `--yaycheckout-pinned-height` equals the bar's `offsetHeight` in px, and the body's bottom padding and `scroll-padding-bottom` both use it. These update when the bar grows (summary details opening) and when the viewport crosses 782px.
  7. On a `(pointer: coarse)` device, focusing a text-like field outside the bar gives the bar `is-keyboard-hidden` (`visibility: hidden`). Focus leaving all such fields removes it. Focusing a field inside the bar (a Coupon Form in the details) does not hide it.
  8. An Order Summary Details inside a pinned Group scrolls within `max-height: 50vh`.
  9. A Group without the attribute renders byte-identical to before.
  10. **End-to-end:** build template 04's bar on a new layout from template 01. That means a Group pinned "All screens" holding an Order Summary (Collapsible, Opens Up, header with the live Order total) and the Place Order block. Open it on the storefront at 390px and 1280px with two products in the cart. Expected: the bar stays at the bottom while scrolling, the last field is reachable above it, opening the summary grows the bar upward with scrolling details, and Place Order in the bar places an order with a test gateway.
- **Decided with the user:**
  - Where the option lives: a setting on core Group, built like Hide When Empty, not on Order Summary.
  - Breakpoints: the merchant chooses All screens or Mobile only (below 782px).
  - Clearance: JS measures the bar's height, and the bar hides while a text field has focus on touch screens.

## Global constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types. WordPress ≥ 6.5 (`WP_HTML_Tag_Processor`).
- This is a Free feature: no `Plan::is_pro()` check and nothing under `includes/Pro/`.
- Never edit `assets/dist/`. Block inputs live in `packages/blocks/src/`, and `pnpm build:blocks` rebuilds them. `assets/js/` and `assets/css/` are hand-written and get edited directly.
- The TypeScript constants carry `/** Mirrors PinToBottom::<NAME> in PHP. */` (docs/conventions.md: TypeScript copies of PHP).
- Class names and attribute names are class constants on `PinToBottom`.
- Branch from `dev/edd` as `trace/feat/group-pin-to-bottom`. Use Conventional Commits, with each commit ending `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Stage files by name.
- `composer lint -- <files>` lints the changed PHP. The admin-page eslint fails on `dev/edd` today (docs/known-issues.md) and is not part of this plan's checks.
- For editor checks, use a new throwaway layout, never an existing draft, because autosave writes into it. Preview without activating through `\YayCheckout\Helpers\PreviewToken::mint( <id>, 60 )`, then open `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>` with a real product in the cart (`?add-to-cart=29`).

## Review focus

- **Sticky plus pin on one Group** (template 01's summary column pinned on mobile only): core's `top: 24px` would stretch the bar from the top. Task 1 Step 5 checks `top: auto`.
- **Place Order inside the bar:** it must stay inside `form.checkout` and submit. Task 3 Step 2 places an order from the bar.
- **Last field hidden behind the bar,** or a focused field scrolled under it on desktop when tabbing: Task 1 Step 6 checks the padding and `scroll-padding-bottom`.
- **Mobile keyboard:** a fixed bar rides up over the field being typed in. Task 1 Step 7 checks the hide in Chrome's touch emulation.
- **Theme with a transformed ancestor** (fixed acts like absolute): this is out of scope, but Task 3 Step 2 runs on the active theme and on Storefront to know whether it happens.
- **Two pinned Groups overlap:** the editor warns (Task 2 Step 3). The storefront doesn't prevent it.

---

### Task 1: Storefront pins a marked Group

**Files:**
- Create: `includes/Renderer/PinToBottom.php`
- Create: `assets/js/checkout-pin-to-bottom.js`
- Modify: `includes/YayCheckout.php` (boot beside `HideWhenEmpty::get_instance()`, line 33)
- Modify: `includes/Blocks/BlockRegistry.php` (register the script beside `HideWhenEmpty::SCRIPT`, ~line 286)
- Modify: `assets/css/checkout-base.css` (after the `.yaycheckout-hidden-when-empty` rule)

**Interfaces:**
- Produces, on `YayCheckout\Renderer\PinToBottom` (uses `SingletonTrait`):
  - `ATTRIBUTE = 'yaycheckoutPinToBottom'`, with values `ALL = 'all'` and `MOBILE = 'mobile'`. Anything else counts as off.
  - `PINNED_CLASS = 'yaycheckout-pinned'`, `MOBILE_CLASS = 'yaycheckout-pinned--mobile'`, `SCRIPT = 'yaycheckout-pin-to-bottom'`
  - `public function filter_group( $block_content, $block ): string`, hooked on `render_block_core/group`, priority 10, 2 args.
- Produces, CSS and JS names: `html.yaycheckout-has-pinned`, the custom property `--yaycheckout-pinned-height`, and the bar state class `is-keyboard-hidden`.

- [x] **Step 1: Create `PinToBottom`**

The constructor adds the filter. Like HideWhenEmpty, it isn't gated on `CheckoutPage::layout()`, and the docblock says why. `filter_group()` returns the content untouched unless `$block['attrs'][ATTRIBUTE]` is `ALL` or `MOBILE`. Otherwise `WP_HTML_Tag_Processor` adds `PINNED_CLASS` (plus `MOBILE_CLASS` for mobile) to the first tag, and the method calls `wp_enqueue_script( self::SCRIPT )`. Boot it in `YayCheckout.php`, and in `BlockRegistry` register `assets/js/checkout-pin-to-bottom.js` with no dependencies, `YAYCHECKOUT_VERSION`, in the footer, with the comment `// Enqueued by PinToBottom::filter_group() for a pinned Group.`

- [x] **Step 2: Add the CSS to `checkout-base.css`**

- `.yaycheckout-pinned:not(.yaycheckout-pinned--mobile)`, and the same selector with `.yaycheckout-pinned--mobile` inside `@media (max-width: 781px)`: `position: fixed !important; top: auto !important; bottom: 0 !important; left: 0 !important; right: 0 !important; z-index: 100; margin: 0 !important; max-width: none !important; max-height: 100dvh; overflow-y: auto; box-shadow: 0 -2px 3px rgba(0, 0, 0, .05);`. The `!important` is needed because it must beat core's sticky container class, whose order is unknown.
- Add `:not(.has-background)` to the same selectors with `background-color: #fff`.
- `.yaycheckout-pinned .yaycheckout-order-summary-details { max-height: 50vh; overflow-y: auto; }`
- `.yaycheckout-pinned.is-keyboard-hidden { visibility: hidden; }`
- `html.yaycheckout-has-pinned { scroll-padding-bottom: var(--yaycheckout-pinned-height, 0px); }` and `html.yaycheckout-has-pinned body { padding-bottom: var(--yaycheckout-pinned-height, 0px); }`
- One comment above the block names `PinToBottom::PINNED_CLASS` and says the editor never gets the class.

- [x] **Step 3: Write `checkout-pin-to-bottom.js`**

Write it in plain ES5-safe JS, like the other `assets/js` files, inside an IIFE that runs on DOMContentLoaded.

- **`update()`:** set `--yaycheckout-pinned-height` on `document.documentElement` to the largest `offsetHeight` among `.yaycheckout-pinned` elements whose `getComputedStyle().position === 'fixed'`, and toggle `yaycheckout-has-pinned` on when that height is above 0.
- **When to call it:** from a `ResizeObserver` on each bar, and from `matchMedia('(max-width: 781px)')`'s `change` event.
- **Keyboard hiding:** only when `matchMedia('(pointer: coarse)').matches`. On `focusin` of a document-level `input` (not checkbox, radio, hidden, submit, button, or range), `textarea` or `select` that is not inside `.yaycheckout-pinned`, add `is-keyboard-hidden` to every bar. On `focusout`, check `document.activeElement` on the next tick and remove the class when no such field still has focus.
- **`update()` and the hide:** `update()` ignores `is-keyboard-hidden`. Visibility doesn't change the height, so the page doesn't shift while the shopper types.

- [x] **Step 4: Lint**

Run: `composer lint -- includes/Renderer/PinToBottom.php includes/Blocks/BlockRegistry.php includes/YayCheckout.php`
Expected: no errors.

- [x] **Step 5: Verify pinning (criteria 2–5, 9)**

1. Create a throwaway layout from template 01. In the code editor, add `"yaycheckoutPinToBottom":"all"` to the Group holding the Order Summary, the one with `"position":{"type":"sticky","top":"24px"}`. Save it as a draft.
2. Mint a preview token and open the preview at 1280px with `?add-to-cart=29`.

Expected:
- The Group has the class `yaycheckout-pinned`.
- DevTools shows `position: fixed; top: auto; bottom: 0px`, and the bar is full width with a white background and shadow.
- `checkout-pin-to-bottom.js` is loaded.

3. Change the value to `"mobile"` and reload.

Expected: at 1280px the summary is back in its column and sticky. At 390px it is fixed at the bottom.

4. Remove the attribute.

Expected: the Group's HTML matches the page source from before this change, and the script is not loaded.

- [x] **Step 6: Verify clearance (criterion 6)**

With `"all"` set, scroll to the end of the page.

Expected:
- `<html>` has `yaycheckout-has-pinned`.
- `getComputedStyle(document.documentElement).getPropertyValue('--yaycheckout-pinned-height')` equals the bar's `offsetHeight` + `px`.
- Return to cart is fully visible above the bar.
- After adding a Paragraph to the bar in the code editor (the bar grows), the variable follows it.
- With `"mobile"`, resizing from 390px to 1280px drops the class.

- [x] **Step 7: Verify the keyboard hide (criterion 7)**

In Chrome DevTools, set device toolbar to iPhone 14 (touch) and reload.

Expected:
- Tapping Billing First name gives the bar `is-keyboard-hidden`, so it is invisible.
- Tapping a blank area removes the class.
- With the details open, tapping the Coupon Form input inside the bar keeps it visible.

- [x] **Step 8: Commit**

Stage: `includes/Renderer/PinToBottom.php`, `assets/js/checkout-pin-to-bottom.js`, `includes/YayCheckout.php`, `includes/Blocks/BlockRegistry.php`, `assets/css/checkout-base.css`, `docs/plans/2026-10-12-group-pin-to-bottom.md`
Message: `feat(renderer): pin a group to the bottom of the checkout`

### Task 2: Editor panel

**Files:**
- Create: `packages/blocks/src/shared/pin-to-bottom.tsx`
- Modify: `packages/blocks/src/checkout/index.ts` (import it after `'../shared/hide-when-empty'`)

**Interfaces:**
- Consumes: `PinToBottom::ATTRIBUTE`, `ALL`, `MOBILE` (Task 1), and `useIsLayoutEditor()` from `shared/layout-editor.ts`.
- Produces: `core/group` attribute `yaycheckoutPinToBottom: { type: 'string', default: '' }`, registered in every editor the way Hide When Empty's is.

- [x] **Step 1: Register the attribute and panel in `pin-to-bottom.tsx`**

- Add a `blocks.registerBlockType` filter (`yaycheckout/pin-to-bottom/attribute`) for `core/group` only.
- Add an `editor.BlockEdit` higher-order component (`yaycheckout/pin-to-bottom/panel`) that renders only while a Group is selected and `useIsLayoutEditor()` is true. That keeps the same `LayoutOnlyPanel` split as `hide-when-empty.tsx`.
- **Panel:** `<InspectorControls>` › `<PanelBody title="Pin to bottom" initialOpen={ value !== '' }>` holding a `SelectControl` with Off (`''`), All screens (`all`) and "Mobile only (below 782px)" (`mobile`).
- **Help text:** "On the checkout this block stays at the bottom of the screen. The editor shows it in place."

- [x] **Step 2: Warn about sticky and about a second pin**

Add a `Notice status="warning" isDismissible={ false }` in two cases:

- **Sticky on All screens:** when the value is `all` and `attributes.style?.position?.type === 'sticky'`, the notice reads "Pinned to the bottom, this block ignores its sticky position."
- **A second pin:** when any other `core/group` in the editor (`getClientIdsWithDescendants()`) has the attribute set, the notice reads "Another block is already pinned to the bottom. They will overlap."

The second check is a `useSelect` that runs only while the panel is mounted.

- [x] **Step 3: Build and verify (criterion 1)**

Run: `pnpm build:blocks` then `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`
Expected: the build succeeds, and tsc shows no new errors.

Then check the panel in the throwaway layout from Task 1:

Expected:
- Selecting the summary Group shows **Pin to bottom**, and choosing Mobile only writes `"yaycheckoutPinToBottom":"mobile"` in the code editor.
- Choosing All screens shows the sticky notice.
- Pinning a second Group shows the overlap notice on both.
- In a regular post (`/wp-admin/post-new.php`), a Group has no Pin to bottom panel.

- [x] **Step 4: Commit**

Stage: `packages/blocks/src/shared/pin-to-bottom.tsx`, `packages/blocks/src/checkout/index.ts`
Message: `feat(blocks): add pin to bottom setting to group`

### Task 3: Document and verify end to end

**Files:**
- Modify: `docs/architecture.md`. In the layouts/blocks section, beside the Hide When Empty description, add one sentence: a Group's `yaycheckoutPinToBottom` is applied only on render by `Renderer\PinToBottom`, so the editor shows it in place, and `checkout-pin-to-bottom.js` reserves the bar's height.

- [x] **Step 1: Update `docs/architecture.md` as above**

- [x] **Step 2: End-to-end check (criterion 10)**

1. In a new layout from template 01, add a Group after the Checkout block's Columns, inside `yaycheckout/checkout`, and pin it All screens.
2. Move into it an Order Summary with Collapsible on, Starts Collapsed and Opens Up, whose header holds a Paragraph with the live Order total.
3. Move the Place Order block into it too.
4. Save as a draft and open a preview with two products in the cart, at 390px and 1280px, on the active theme and then on Storefront.

Expected:
- The bar stays at the bottom while scrolling, and the last field sits above it.
- Clicking the summary header opens the details above the header, inside the bar, scrolling within 50vh. The bar grows, and the page's bottom padding follows.
- Changing the shipping method updates the bar's total.
- Place Order in the bar places an order with Cash on delivery and reaches Order received.
- Note any theme where the bar is not fixed to the viewport (a transformed ancestor) in the PR description.

5. Delete the throwaway layouts, with the user's OK.

- [x] **Step 3: Commit**

Stage: `docs/architecture.md`, and `docs/plans/2026-10-12-group-pin-to-bottom.md` with its Status set to `done YYYY-MM-DD`
Message: `docs(architecture): describe group pin to bottom`
