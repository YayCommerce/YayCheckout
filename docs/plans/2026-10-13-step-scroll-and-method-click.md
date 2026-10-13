# Step Scroll and Whole-Item Method Click Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking.

**Status:** active
**Goal:** Opening another step scrolls the page only when the new step is off-screen. Clicking anywhere on a shipping or payment method item selects it, on the storefront and in the editor preview.
**Spec:** none; `## Requirements` below.
**Architecture:** One helper in `steps/view.ts` decides whether to scroll, and every way of opening a step calls it. Shipping and payment each get a `view.ts` that registers one shared delegated click handler. The handler clicks the item's radio when the click lands on the item's own box rather than on a child. The editor previews keep the selected row in local React state.
**Touches:**
- `packages/blocks/src/steps/view.ts`, `packages/blocks/src/steps/style.css`
- New `packages/blocks/src/shared/method-item-click.ts`. `shared/` is also the SPA's `@blocks-shared`, but the SPA does not import this file.
- New `packages/blocks/src/shipping-methods/view.ts`, `packages/blocks/src/payment/view.ts`; `block.json`, `style.css` and `edit.tsx` in both folders.
**Verified 2026-10-13** on yaydevs.local (layout 61: Steps accordion, Shipping Methods and Payment outside the steps). The test tab reported `visibilityState: hidden`, where Chrome skips smooth scrolls, so the scroll checks ran with `prefers-reduced-motion` stubbed to true (the instant branch). A pinned bar was simulated with `scroll-padding-bottom` on `<html>`. The six layouts were checked by switching `data-layout` in the DOM. Task 2 Step 4d was staged in the DOM (one rate's radio swapped for the hidden input Woo prints for a single rate): clicking the item changed nothing, sent no request and logged no error. After the tidy pass (`bindOnce`, `matches()`, mapped previews), real padding clicks on a rate and a gateway and the editor preview check were run again and passed. The editor check ran on the published layout 61 with no edits and no save, not on a new draft.
**Tests:** none. Neither change is logic that CLAUDE.md's test rule lists, and the blocks have no JS test suite. Each task has an exact manual check instead.

## Requirements

1. Continue, Back, Edit (or clicking a completed step's header), Steps Progress and Go to Step all follow one scroll rule when they open a step. If the opened step's header is fully inside the visible area, the page does not scroll. The visible area is the viewport minus `scroll-padding-top`/`scroll-padding-bottom` on `<html>`; a pinned bar sets the bottom one. With headings hidden, the step's top edge stands in for the header.
2. When the header is off-screen (above, or not fully visible below), the page scrolls so that the step's top edge sits 16px below the top of the visible area. The scroll is smooth, or instant under `prefers-reduced-motion: reduce`.
3. Focus does not change: Continue and Back leave focus where it is, and the reopen path (Edit, Steps Progress, Go to Step) still focuses `.yaycheckout-step__header`, without the browser's own scroll.
4. On the storefront, clicking anywhere on a shipping rate item (`li.yaycheckout-shipping-methods__option`) or a payment gateway item (`#payment .wc_payment_methods > li`) selects its radio. This covers the item's padding and gaps. WooCommerce reacts as it does to a radio click: shipping re-runs `update_order_review`, and payment opens that gateway's `.payment_box`.
5. A click inside content the item holds besides its label does not change the selection. That content is a gateway's `.payment_box` (card fields, description) and anything `woocommerce_after_shipping_rate` prints (pickup points, links). Items with no radio do nothing: a single rate has a hidden input, and a "no methods" notice has no input.
6. The whole item shows `cursor: pointer` in all six layouts (joined, cards, grid, checkmark, lines, box).
7. In the editor preview of both blocks, clicking any row moves the selected look to that row. This is preview-only state: no attribute changes and the post is not marked dirty.

## Global constraints

- Never hand-edit `assets/dist/`. Rebuild with `pnpm build:blocks`.
- Nothing type-checks or lints the blocks' TypeScript: `pnpm build:blocks` is the check, then the browser. Because a file is added under `shared/`, also run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. Its expected result is no new errors compared with `dev/edd`.
- No PHP changes, so `composer lint` is not needed.
- Never test edits on an existing draft layout, because autosave writes them. Use a new draft layout for every editor check, and delete it afterwards with the user's OK.
- The local site is `http://yaydevs.local/?page_id=9`. Put a real product in the cart first (`?add-to-cart=29`). The store needs at least two shipping rates and two gateways (for example Direct bank transfer and Cash on delivery).
- Branch `trace/feat/step-scroll-and-method-click` from `dev/edd`. Commits follow Conventional Commits with CLAUDE.md's trailer, and files are staged by name.

## Review focus

- **No scroll when visible:** the common case is a short step followed by a visible next header. The page must stay still (Task 1 Step 3a).
- **Pinned bar / sticky theme header:** a header hidden behind a pinned bottom bar counts as off-screen. A sticky top header's `scroll-padding-top` is respected (Task 1 Step 3c).
- **One-at-a-time layout:** the previous step disappears, so the new step usually starts above the viewport after a long step. It must scroll up (Task 1 Step 3b).
- **Payment box clicks:** typing into or clicking in a gateway's card fields must never re-select or re-fire the gateway (Task 2 Step 4c).
- **Double binding:** both blocks' view scripts import the same helper, and a document attribute guard keeps it to one listener (Task 2 Step 4e).

---

### Task 1: Scroll only when the opened step is off-screen

**Files:** `packages/blocks/src/steps/view.ts`, `packages/blocks/src/steps/style.css`

**Interfaces:**
- `revealStep( step: HTMLElement ): void` keeps its name and gets a new body. It returns without scrolling when `stepIsInView( step )`. Otherwise it calls `step.scrollIntoView( { block: 'start', behavior: reduced ? 'auto' : 'smooth' } )`. Update its doc comment to state the rule.
- New `stepIsInView( step: HTMLElement ): boolean`. Let `top = step.getBoundingClientRect().top` and `headerHeight` = the `.yaycheckout-step__header` rect height (0 when missing). Let `topInset`/`bottomInset` = `parseFloat` of `getComputedStyle( document.documentElement ).scrollPaddingTop`/`scrollPaddingBottom`, each 0 when `NaN` (`auto`). Return `top >= topInset && top + headerHeight <= window.innerHeight - bottomInset`.

- [x] **Step 1:** In `steps/style.css`, add `scroll-margin-top: 16px;` to `.yaycheckout-step`. It goes in a plain-specificity rule, not inside `:where()`, so merchant styles don't drop it. `scrollIntoView` adds this to `<html>`'s `scroll-padding-top`, which gives Requirement 2's 16px below the visible area.
- [x] **Step 2:** In `steps/view.ts`, write `stepIsInView()` and the new `revealStep()`. In `reopen()`, call `header.focus( { preventScroll: true } )` and then `revealStep( step )`. Leave `advance()` and `retreat()` as they are; they already call `revealStep()`.
- [x] **Step 3:** Run `pnpm build:blocks`; expect it to finish with no errors. Then, on the storefront with a layout that uses Steps (stacked headings, cards), at 1280×800, check:
  - a. Fill a short step 1 and click Continue while step 2's header is on screen. Run `scrollY` in the console before and after; expect the same value. Click Back; expect the same value again.
  - b. Switch the layout to one-at-a-time, or resize to 390px wide so that step 1 is taller than the screen. Scroll to step 1's Continue and click it. Expect a smooth scroll, ending with `document.querySelector('.yaycheckout-step[data-state="current"]').getBoundingClientRect().top` ≈ 16 (within 1px).
  - c. Add a Group pinned to the bottom (All screens) and scroll so that step 2's header sits behind the bar. Open step 2 with Steps Progress; expect a scroll. With the header just above the bar, expect no scroll.
  - d. Click Edit on a completed step whose header is on screen. Expect `scrollY` unchanged and `document.activeElement` to be that step's `.yaycheckout-step__header`. Do the same with the header scrolled above the viewport; expect a scroll so that its top is ≈ 16.
  - e. With "prefers-reduced-motion: reduce" emulated in DevTools (Rendering panel), repeat b; expect a jump with no animation.
- [x] **Step 4:** Commit `packages/blocks/src/steps/view.ts` and `packages/blocks/src/steps/style.css` as `fix(steps): scroll to an opened step only when it is off-screen`.

### Task 2: Select a shipping or payment method by clicking the whole item (storefront)

**Files:** new `packages/blocks/src/shared/method-item-click.ts`, new `packages/blocks/src/shipping-methods/view.ts`, new `packages/blocks/src/payment/view.ts`; `block.json` and `style.css` in `shipping-methods/` and `payment/`.

**Interfaces:**
- `shared/method-item-click.ts` exports `bindMethodItemClick(): void`. If `document.documentElement` already has `data-yaycheckout-method-click-bound`, it returns. Otherwise it sets that attribute and adds one `click` listener on `document`, as `steps/view.ts`'s `boot()` does.
- The listener finds `item = target.closest( '.yaycheckout-checkout .yaycheckout-shipping-methods__option, .yaycheckout-checkout .yaycheckout-payment #payment .wc_payment_methods > li' )`. It acts only when `event.target === item`. A click on the label already selects natively, and any other child (a `.payment_box`, extension output) is out of scope (Requirement 5). Then `radio = item.querySelector( ':scope > input[type="radio"]' )`. When `radio` exists, is not disabled and is not checked, it calls `radio.click()`. That fires both `click` and `change`; WooCommerce's `checkout.js` listens for `click` on `payment_method` and `change` on `shipping_method`.
- `shipping-methods/view.ts` and `payment/view.ts` each call `onDomReady( bindMethodItemClick )` (`../shared/dom-ready`). A one-line file comment says that the shared helper owns the behaviour.
- Both `block.json` files gain `"viewScript": "file:./view.js"`, as in `steps/block.json`.

- [x] **Step 1:** Write `shared/method-item-click.ts` and the two `view.ts` files, and add `viewScript` to both `block.json` files.
- [x] **Step 2:** In the "Shared" section of `shipping-methods/style.css`, add `cursor: pointer;` to the existing `.yaycheckout-shipping-methods__list > .yaycheckout-shipping-methods__option` rule. In the "Shared" section of `payment/style.css`, add it to `.wc_payment_methods > li`, scoped to items holding a radio: `> li:has(> input[type="radio"])`. That way a notice row keeps the default cursor.
- [x] **Step 3:** Run `pnpm build:blocks`. Expect `assets/dist/blocks/shipping-methods/view.js` and `assets/dist/blocks/payment/view.js` to exist. Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`; expect no new errors compared with `dev/edd`.
- [x] **Step 4:** On the storefront (cart with a product, two rates, two gateways), check:
  - a. For each of the six shipping layouts, click an unselected rate in its padding, for example 4px inside the item's top-left corner. Expect its radio to be checked, a `?wc-ajax=update_order_review` request in Network, and the totals to update.
  - b. Do the same for each of the six payment layouts. Expect the gateway to be selected and its `.payment_box` to open.
  - c. With Direct bank transfer selected, click inside its open `.payment_box` text. Expect the selection to stay unchanged and no new `update_order_review` request.
  - d. With a single shipping rate (hidden input), click the item. Expect nothing to change and no console error.
  - e. Run `document.documentElement.hasAttribute('data-yaycheckout-method-click-bound')` and expect `true`. Click a rate once and expect exactly one `update_order_review` request.
  - f. Run `[...document.querySelectorAll('.yaycheckout-shipping-methods__option, #payment .wc_payment_methods > li')].map(li => getComputedStyle(li).cursor)` and expect every value to be `'pointer'`.
- [x] **Step 5:** Commit the three new `.ts` files plus `block.json` and `style.css` from both block folders as `feat(blocks): select a shipping or payment method by clicking anywhere on it`.

### Task 3: Whole-item click in the editor preview

**Files:** `packages/blocks/src/shipping-methods/edit.tsx`, `packages/blocks/src/payment/edit.tsx`

**Interfaces:**
- Each `Edit` keeps `const [ selected, setSelected ] = useState( 0 )` (`@wordpress/element`). Each preview `<li>` gets `onClick={ () => setSelected( i ) }` with its row index. Its `<input>` drops `defaultChecked` and takes `checked={ selected === i }` and `readOnly`, and stays `disabled`. The block CSS's `:has(input:checked)` then paints the selected look, and Task 2's `cursor: pointer` already applies in the editor, because `style.css` loads there.
- No attribute is written; the state is lost on reload. That is intended.

- [x] **Step 1:** Make both changes.
- [x] **Step 2:** Run `pnpm build:blocks` and expect no errors. In a new draft layout, add Shipping Methods and Payment. In each block and for each of a few layouts (joined, grid, checkmark), click the second row's padding, then its label. Expect the selected look to move each time. Then run `wp.data.select('core/editor').isEditedPostDirty()`; expect `false` if the draft was saved before clicking. Expect no React warning in the console about `checked` without `onChange`.
- [x] **Step 3:** Commit both `edit.tsx` files as `feat(blocks): let the method previews select a row on click`.

### Final verification

- [ ] Rebuild with `pnpm build:blocks`, then run Requirement 1–7's checks once more on one layout that has Steps, Shipping Methods and Payment in different steps. Complete step 1 with Continue (no scroll while step 2 is visible). Pick a shipping rate by clicking its padding, then Continue. Pick a gateway by clicking its padding, then place an order with Cash on delivery. Expect the order-received page.
