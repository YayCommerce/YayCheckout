# Step Controls Outside Checkout Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec: the requirements below are the brief.

**Status:** active
**Goal:** the Next Step Button, the Previous Step Button and Progress can be placed anywhere in a layout, outside the Checkout block too, and still drive and show the layout's Steps. Next and Back scroll the opened step into view, and the Previous button returns to the cart, or hides, on the first step.
**Spec:** none, planned from the request
**Architecture:**
- Placement: drop `"ancestor": [ "yaycheckout/checkout" ]` from the three blocks. Checkout itself, Steps and every other block keep their rules.
- Storefront: a layout has exactly one Checkout (`supports.multiple: false`), so a script that can't find Checkout among an element's ancestors uses the page's one `.yaycheckout-checkout`. A new shared helper owns that lookup for `steps/view.ts` and `progress/view.ts`.
- Previous on the first step: the block renders its Back `<button>` and, with `firstStep: "cart"`, an `<a>` to the cart right after it. On first load step 1 is always open, so PHP renders the first-step state: button hidden, link shown (or both hidden with `"hide"`). The steps script re-syncs both on every step change. With no Steps block in the layout the steps script isn't loaded, the first-step state stays, and the cart link still works because it is a real link.
- Progress titles: today they reach Progress as block context set on Checkout, which a block outside Checkout never receives. `CheckoutProgress` instead reads them from the layout being rendered (`CheckoutPage::get_instance()->layout()`). Because there is one Checkout per layout, "the layout's first Steps block" is the same block as "the first Steps block inside Checkout". The context path is removed, not kept alongside, so there is one source.

**Touches:**
- Blocks:
  - `packages/blocks/src/shared/checkout-root.ts` (new, shared: consumed by `steps/view.ts` and `progress/view.ts`)
  - `packages/blocks/src/shared/allowed-blocks.ts` (shared: consumed by `shared/address-section-edit.tsx` (Billing, Shipping) and `step/edit.tsx`)
  - `packages/blocks/src/steps/view.ts`
  - `packages/blocks/src/step-button/block.json`, `packages/blocks/src/back-button/block.json`
  - `packages/blocks/src/progress/{block.json,edit.tsx,render.php,view.ts,style.css}`
- `packages/blocks/src/back-button/{block.json,edit.tsx,render.php,style.css}` (Task 4)
- PHP: `includes/Renderer/CheckoutProgress.php`, `includes/YayCheckout.php`
- Docs: `docs/architecture.md`

**Tests:** none. None of the logic CLAUDE.md's Tests rule names changes (`publish_error()`, `LayoutAssignment::pick()`, `RequiredFieldOverlay::apply()`, `LayoutImportReview`). Each task gets a manual check.

## Requirements

- **Goal:** a merchant can put Next, Previous and Progress outside Checkout, for example in the layout's header Group or at the root under it, and on the storefront they work exactly as they do inside Checkout.
- **Out of scope:**
  - Removing or changing the Checkout block, the page-wide form idea (dropped by the user on 2026-10-07), or the Steps and Step blocks' placement.
  - Letting Progress into Billing or Shipping. Address sections keep excluding it, as they do today.
  - Disabling Next, or any new Next option. The only Next change beyond placement is Task 5.
  - Moving keyboard focus when a step opens. Scroll only.
  - Scrolling when a step opens by any way other than Next or Back: the Progress jump, or a click on a complete step's header.
  - `templates/default-layout.php`. The default layout has no Steps and stays as it is.
- **Acceptance criteria:**
  1. In the layout editor, with a block outside Checkout selected (for example the header Group), the inserter offers Next Step Button, Previous Step Button and Progress, and each can be inserted at the post root or inside a core Group or Columns outside Checkout.
  2. Inside Checkout, all three still insert where they do today. Progress is still not offered directly inside Billing or Shipping, and the step buttons still are.
  3. On the storefront, a Next button outside Checkout validates the open step and opens the next one. A Previous button outside Checkout opens the previous step.
  4. A Progress block outside Checkout lists the titles of the layout's first Steps block, marks steps current, complete and locked as the shopper moves, and its jump button on a complete step reopens that step.
  5. In the editor, a Progress outside Checkout shows the Steps titles and lets you edit them in place. It shows the "Add a Steps block to use Progress." placeholder only when the layout has no Steps block.
  6. After Next opens the next step, or Back opens the previous one, the page scrolls so the opened step's top sits at the top of the viewport. The scroll is smooth, or instant under `prefers-reduced-motion: reduce`. Focus does not move. A Next that fails validation does not scroll.
  7. The Previous Step Button has a sidebar setting "On the first step" with "Return to cart" (`firstStep: "cart"`, the default, which saved buttons get too) and "Hide" (`"hide"`). With "Return to cart" there is also a "Cart button text" field (`cartText`, placeholder and fallback "Return to cart").
  8. While the open step is the first step, or the layout has no Steps, the Previous button is a link to `wc_get_cart_url()` showing `cartText`, or is hidden. On any later step it is the Back button again. A Back button inside a step follows its own step.
  9. Next is never hidden. Clicking it on the last step does nothing: no validation, and the step stays open.
  10. End to end: on a layout whose Checkout holds Steps (step 1: Billing; step 2: Payment and Place order), with Progress and the Next and Previous buttons in the header Group outside Checkout, a guest moves forward and back through the steps from the header and places an order.
- **Decided with the user (2026-10-07):**
  - The Checkout block stays. Only the step buttons and Progress gain freedom to sit outside it.
  - Next and Back scroll the opened step into view and do not move focus.
  - Previous gets a first-step option, Return to cart or Hide, defaulting to Return to cart, with its own editable cart text in the sidebar. The sidebar is used, not the canvas, because the canvas shows the Back text. The Coupon form's button text is precedent.
  - Next is never hidden: the old rule that hid it on the step holding Place order, or with no Steps, is removed. On the last step Next does nothing (Task 5).
- **Assumed while planning (no question asked; change it here if wrong):**
  - Progress stays excluded from address sections.
  - Outside-Checkout placement means anywhere in the layout, the post root included, not only next to Checkout.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit `assets/dist/`. Edit `packages/blocks/src/<block>/` and run `pnpm build:blocks`.
- Run commands from the repo root on Node 22 (`nvm use`). Use pnpm only.
- Branch from `dev/edd` as `<name>/feat/step-controls-outside-checkout`. Commit messages are Conventional Commits and end with the `Co-Authored-By` trailer CLAUDE.md names. Land through a PR into `dev/edd` (squash merge).

## Base branch state

- `pnpm build:blocks` passes on `dev/edd`. It is the only check on the blocks' TypeScript.
- `composer lint -- <files>` passes on the PHP files this plan touches.

## Review focus

- **A layout saved before this change, with Progress and the buttons inside Checkout.** It must render and behave exactly as before: titles, state painting, jump-to-reopen. Task 2, Step 7 checks this.
- **A button or Progress outside Checkout on a layout with no Steps.** Next shows and does nothing, Progress prints nothing on the storefront, and nothing throws in the console. Task 1, Step 5 and Task 2, Step 7 check this.
- **Next clicked on the step holding Place order.** It must not validate or close that step, which would hide Place order. Task 5, Step 2 checks this.
- **A preview link** (`PreviewRequest`) of a draft layout with Progress outside Checkout. The titles must come from the previewed layout, not the live one. `CheckoutPage::layout()` already resolves the preview first, and Task 2, Step 7 checks it.
- **A theme that styles `a` and `a:hover`** (underline, link colour) would make the cart link look unlike the Back button. Task 4, Step 5 checks it on Storefront.
- **A shopper on a slow connection clicking the cart link before the steps script boots** must still reach the cart. The link is a real `href`, which Task 4, Step 5 checks with the script blocked.
- **Two Progress blocks**, one in the header and one inside Checkout. Both must paint the same state. Task 2, Step 7 checks this.

---

### Task 1: Step buttons outside Checkout

**Files:**
- Create: `packages/blocks/src/shared/checkout-root.ts`
- Modify: `packages/blocks/src/steps/view.ts` (`stepForButton()`)
- Modify: `packages/blocks/src/step-button/block.json`, `packages/blocks/src/back-button/block.json`
- Modify: `packages/blocks/src/shared/allowed-blocks.ts` (comment above `ALLOWED_IN_ADDRESS_SECTIONS`)

**Interfaces:**
- Produces: `export const CHECKOUT_SELECTOR = '.yaycheckout-checkout';` and `export function checkoutFor( element: Element ): Element | null` in `shared/checkout-root.ts`. It returns `element.closest( CHECKOUT_SELECTOR )`, or else `document.querySelector( CHECKOUT_SELECTOR )`. A doc comment says why the fallback is safe: Checkout is `multiple: false`, so a page holds one. Task 2 consumes both.

- [ ] **Step 1: Create `packages/blocks/src/shared/checkout-root.ts`** with the two exports above.

- [ ] **Step 2: In `steps/view.ts`, make `stepForButton()` fall back to `checkoutFor( button )`** in place of `button.closest( 'form.checkout' )`. Keep the rest: a button inside a Step still uses that Step, and a button anywhere else uses the open (`current`) Step of the page's Checkout. `onReopen()` stays as it is. Task 2 dispatches the reopen event on the Checkout element, where its own `closest()` already matches.

- [ ] **Step 3: Remove `"ancestor"` from both button `block.json` files** and change their descriptions:
  - step-button: `"Validates the open checkout step and opens the next one. Place it anywhere in the layout."`
  - back-button: `"Opens the previous checkout step. Place it anywhere in the layout."`

  In `allowed-blocks.ts`, change the comment "The step buttons find the open step from anywhere in the form" to "The step buttons find the open step from anywhere in the layout". `isCheckoutSection()` needs no change for the buttons: they were already exempt through `ALLOWED_IN_ADDRESS_SECTIONS`.

- [ ] **Step 4: Build**

  Run: `pnpm build:blocks`
  Expected: compiles with no errors. `assets/dist/blocks/step-button/block.json` has no `ancestor` key.

- [ ] **Step 5: Verify in the editor and on the storefront**

  Setup: edit a layout whose Checkout holds Steps with two Steps (step 1: Billing; step 2: Payment and Place order), and make it the active layout.

  1. In the editor, select the header Group and open the inserter.
     Expected: Next Step Button and Previous Step Button are offered.
  2. Insert one of each into the header Group and save.
     Expected: the save is not locked.
  3. On the checkout page, leave a required billing field empty and click the header Next.
     Expected: the browser flags the field, and step 1 stays open.
  4. Fill step 1 and click the header Next.
     Expected: step 1 shows its summary, and step 2 opens.
  5. Click the header Previous.
     Expected: step 1 opens again.
  6. Remove the Steps block (keep Billing etc. inside Checkout), save and reload the storefront.
     Expected: the header Next shows, clicking it does nothing, and the console shows no errors.

- [ ] **Step 6: Commit**

  Stage: `packages/blocks/src/shared/checkout-root.ts`, `packages/blocks/src/steps/view.ts`, `packages/blocks/src/step-button/block.json`, `packages/blocks/src/back-button/block.json`, `packages/blocks/src/shared/allowed-blocks.ts`
  Message: `feat(blocks): let the step buttons sit outside Checkout`

### Task 2: Progress outside Checkout

**Files:**
- Modify: `includes/Renderer/CheckoutProgress.php`
- Modify: `includes/YayCheckout.php:28` (the `CheckoutProgress::get_instance()` boot line)
- Modify: `packages/blocks/src/progress/{block.json,edit.tsx,render.php,view.ts,style.css}`
- Modify: `packages/blocks/src/shared/allowed-blocks.ts` (`isCheckoutSection()`)
- Modify: `docs/architecture.md` (the blocks bullet that begins "`Blocks\BlockRegistry::register_blocks()` registers every built directory")

**Interfaces:**
- Consumes: `checkoutFor()` from Task 1.
- Produces: `CheckoutProgress::markup(): string` (was `markup( \WP_Block $block )`). `CheckoutProgress::step_title( string $raw ): string` is unchanged; `step/render.php` and `shared/step-title.ts` keep using it.

- [ ] **Step 1: Rework `includes/Renderer/CheckoutProgress.php`**
  - Remove `use SingletonTrait`, the constructor, the `render_block_context` filter, `provide_step_titles()` and the `STEP_TITLES` constant. The class becomes static-only.
  - Add `private static function layout_titles(): array`. It memoises the result in `private static $titles` (null until first read). It returns `self::titles_in( parse_blocks( $layout->content ) )` for `CheckoutPage::get_instance()->layout()`, or `[]` when that is null.
  - Change `markup()` to take no argument and read `self::layout_titles()`.
  - Rewrite the class doc: titles come from the rendered layout's first Steps block, so Progress works inside or outside Checkout.
  - Change the `titles_in()` `@param` to "The layout's blocks".

  Remove the `\YayCheckout\Renderer\CheckoutProgress::get_instance();` line from `includes/YayCheckout.php`.

- [ ] **Step 2: Update `progress/render.php`**
  - Call `CheckoutProgress::markup()` with no argument.
  - Change the header line "The list is built in CheckoutProgress, which is loaded before Checkout starts." to "The list is built in CheckoutProgress from the layout's first Steps block."

- [ ] **Step 3: Update `progress/block.json`**
  - Remove `"ancestor"` and `"usesContext"`.
  - Keep the description.

  In `shared/allowed-blocks.ts`, keep Progress out of address sections now that it has no ancestor rule. `isCheckoutSection()` returns true for `progressMeta.name` (imported from `../progress/block.json`) as well as for blocks whose ancestor names Checkout. Update its doc comment to say so.

- [ ] **Step 4: Update `progress/edit.tsx`**
  - The selector takes the post's first Steps block, `( getBlocksByName( STEPS ) as string[] )[ 0 ]`, and returns its `getBlockOrder()`, or `null` when there is none.
  - Drop the Checkout lookup and the `checkoutMeta` import.
  - Change the mirror comment to "Mirrors CheckoutProgress::titles_in(): the layout's first Steps block." `getBlocksByName()` returns ids in document order, parents first, which is the same order `LayoutMarkupReader::flatten()` uses.

- [ ] **Step 5: Update `progress/view.ts` and `progress/style.css`**
  - `sync( checkout )` still reads step states from `checkout`. It paints every `.yaycheckout-progress__item` in `document`, not only those inside `checkout`.
  - `onClick()` gets the checkout from `checkoutFor( button )` and dispatches `yaycheckout:step-reopen` on it.
  - Replace the local `CHECKOUT` constant with `CHECKOUT_SELECTOR` from `../shared/checkout-root`, used in `onStepChange()`.
  - In `style.css`, change both `.yaycheckout-checkout button.yaycheckout-progress__jump` selectors to `.yaycheckout-progress button.yaycheckout-progress__jump`. The specificity stays the same, and the reset now also applies outside Checkout.

- [ ] **Step 6: Lint and build**

  Run: `composer lint -- includes/Renderer/CheckoutProgress.php includes/YayCheckout.php packages/blocks/src/progress/render.php`
  Expected: no errors.

  Run: `pnpm build:blocks`
  Expected: compiles with no errors. `assets/dist/blocks/progress/block.json` has neither `ancestor` nor `usesContext`.

- [ ] **Step 7: Verify in the editor and on the storefront**

  Setup: use the Steps layout from Task 1, Step 5. Put Steps back if Step 5.6 removed it.

  1. Select the header Group and insert Progress.
     Expected: it lists both step titles. Editing a title in Progress changes the matching Step's title.
  2. Select Billing and open its inserter.
     Expected: Progress is not offered there, and Next Step Button is.
  3. Save. On the storefront, check the header Progress.
     Expected: it lists both titles, step 1 is current and step 2 is locked.
  4. Complete step 1 with the header Next.
     Expected: the Progress marks step 1 complete with a check, and step 2 current.
  5. Click step 1 in Progress.
     Expected: step 1 reopens.
  6. Also insert Progress inside Checkout, above Steps. Save and reload.
     Expected: both Progress blocks paint the same state through steps 3 to 5.
  7. Save a draft change to a step title and open the layout's preview link.
     Expected: the header Progress shows the draft title.
  8. Remove Steps, save and reload the storefront.
     Expected: no Progress markup is printed, and the console shows no errors. In the editor, Progress shows "Add a Steps block to use Progress."
  9. On a layout saved before this branch, with Progress inside Checkout, check the storefront.
     Expected: it renders and repaints as before.

- [ ] **Step 8: Update `docs/architecture.md`**

  In the blocks bullet, after "Placement rules live in each `block.json` (`parent`, `ancestor`, `supports.multiple`).", add:

  > Progress and the Next and Previous Step buttons have no ancestor rule, so they can sit outside Checkout. Their storefront scripts fall back to the page's one `.yaycheckout-checkout` (`packages/blocks/src/shared/checkout-root.ts`), and `Renderer\CheckoutProgress` reads step titles from the rendered layout's first Steps block, not from Checkout's block context.

- [ ] **Step 9: Final verification (acceptance criterion 10)**

  Setup: the Steps layout with Progress, Next and Previous in the header Group, outside Checkout.
  Action: as a guest with one product in the cart:
  1. Use the header Next to complete step 1.
  2. Use the header Previous to go back, then the header Next again.
  3. In step 2, pick a payment method and click Place order.

  Expected: the order-received page loads, and the order appears in WooCommerce → Orders.

- [ ] **Step 10: Commit**

  Stage: `includes/Renderer/CheckoutProgress.php`, `includes/YayCheckout.php`, `packages/blocks/src/progress/block.json`, `packages/blocks/src/progress/edit.tsx`, `packages/blocks/src/progress/render.php`, `packages/blocks/src/progress/view.ts`, `packages/blocks/src/progress/style.css`, `packages/blocks/src/shared/allowed-blocks.ts`, `docs/architecture.md`
  Message: `feat(blocks): let Progress sit outside Checkout`

### Task 3: Scroll the opened step into view

**Files:**
- Modify: `packages/blocks/src/steps/view.ts`

**Interfaces:**
- Produces: `function revealStep( step: HTMLElement ): void` in `steps/view.ts`. It calls `step.scrollIntoView( { block: 'start', behavior } )`, where `behavior` is `'auto'` when `window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches`, otherwise `'smooth'`.

- [ ] **Step 1: Call `revealStep()`** at the end of `advance()` on the step it opened, and at the end of `retreat()` on the previous step. `advance()` returns before that point when `complete()` fails, so a failed Next does not scroll. `reopen()` does not call it.

- [ ] **Step 2: Build and verify**

  Run: `pnpm build:blocks`
  Expected: compiles.

  Storefront: use the Steps layout, with a window short enough that step 2 starts below the fold.
  1. Complete step 1 with Next.
     Expected: the page scrolls smoothly until step 2's top border is at the top of the viewport.
  2. Click Back.
     Expected: the page scrolls to step 1.
  3. Click Next with a required field empty.
     Expected: no scroll; the browser's own validation bubble shows.
  4. Turn on reduced motion (macOS: System Settings → Accessibility → Display → Reduce motion) and repeat step 1.
     Expected: the page jumps without animating.

- [ ] **Step 3: Commit**

  Stage: `packages/blocks/src/steps/view.ts`
  Message: `feat(blocks): scroll the opened checkout step into view`

### Task 4: Previous button on the first step

**Files:**
- Modify: `packages/blocks/src/back-button/{block.json,edit.tsx,render.php,style.css}`
- Modify: `packages/blocks/src/steps/view.ts`

**Interfaces:**
- Produces:
  - Attributes on `yaycheckout/back-button`: `firstStep` (`string`, `enum: [ "cart", "hide" ]`, default `"cart"`) and `cartText` (`string`, default `""`).
  - Storefront markup: `<button class="yaycheckout-back-button" type="button" data-first-step="cart|hide">`, and with `"cart"` an adjacent `<a class="yaycheckout-back-button yaycheckout-back-button--cart" href="{wc_get_cart_url()}">`. Both carry the block wrapper attributes.
  - `function syncBackButtons(): void` in `steps/view.ts`.

- [ ] **Step 1: `back-button/block.json`**: add the two attributes. Change the description to `"Opens the previous checkout step. On the first step it returns to the cart or hides. Place it anywhere in the layout."`

- [ ] **Step 2: `back-button/render.php`**
  - Read `firstStep` (anything but `"hide"` counts as `"cart"`). Read `cartText` through `LayoutMarkupReader::plain_text( $attributes, 'cartText', __( 'Return to cart', 'yaycheckout' ) )`.
  - Print the button with `data-first-step` and the `hidden` attribute: page load always opens step 1, so the first-step state is the initial one.
  - With `"cart"` and `function_exists( 'wc_get_cart_url' )`, print the link right after the button, so `button.nextElementSibling` is the link (it skips the whitespace between them).
  - Update the header comment.

- [ ] **Step 3: `back-button/edit.tsx`**
  - Add an `InspectorControls` → `PanelBody` titled "Previous step button".
  - It holds a `SelectControl` "On the first step", with options "Return to cart" (`cart`) and "Hide" (`hide`).
  - With `cart` it also holds a `TextControl` "Cart button text", placeholder "Return to cart", value through `decodeEntities()` as in `coupon-form/edit.tsx`.
  - The canvas keeps `StepNavButtonEdit` for the Back text.
  - The props type extends `StepNavButtonEditProps` with the two attributes.

- [ ] **Step 4: `steps/view.ts` and `back-button/style.css`**
  - `onClick()` matches `.yaycheckout-step-button, button.yaycheckout-back-button`, so the cart link is not caught or prevented.
  - Add `syncBackButtons()`:
    - For each `button.yaycheckout-back-button`, `atFirst` is `! step || ! siblingStep( step.previousElementSibling )`, where `step` is `stepForButton( button )`.
    - The button gets `hidden = atFirst`.
    - The link, `button.nextElementSibling` when it matches `.yaycheckout-back-button--cart`, gets `hidden = ! atFirst`.
    - With `data-first-step="hide"` there is no link, so the button simply hides on the first step.
  - Call `syncBackButtons()` wherever `syncStepButtons()` is called (`boot()`, `setState()`).
  - In `style.css`, add `.yaycheckout-back-button[hidden] { display: none; }`, since `display: block` would otherwise override `hidden`. Add `text-decoration: none` on `a.yaycheckout-back-button`, including `:hover` and `:focus`.

- [ ] **Step 5: Lint, build and verify**

  Run: `composer lint -- packages/blocks/src/back-button/render.php` and `pnpm build:blocks`
  Expected: no errors.

  Editor:
  1. Select a Previous button that was saved before this task.
     Expected: the sidebar shows "On the first step: Return to cart" and an empty "Cart button text" with the placeholder "Return to cart".
  2. Switch to Hide.
     Expected: the text field disappears.

  Storefront (Storefront theme, Steps layout, Previous in the header in cart mode):
  1. Load the checkout page.
     Expected: the header shows "Return to cart", styled like the Back button with no underline.
  2. Complete step 1.
     Expected: it shows "Back" instead.
  3. Click Back.
     Expected: step 1 opens, and the header shows "Return to cart" again.
  4. Click "Return to cart".
     Expected: the cart page loads.
  5. Block `steps/view.js` in DevTools (Network → Block request URL), reload, and click "Return to cart".
     Expected: the cart page still loads.
  6. Set the button to Hide and reload.
     Expected: no Previous control on step 1, and "Back" on step 2.
  7. Put a Previous button inside step 2's body.
     Expected: on step 2 it shows "Back".
  8. Remove Steps from the layout.
     Expected: the Previous button shows "Return to cart" (or nothing with Hide), and the console shows no errors.

- [ ] **Step 6: Commit**

  Stage: `packages/blocks/src/back-button/block.json`, `packages/blocks/src/back-button/edit.tsx`, `packages/blocks/src/back-button/render.php`, `packages/blocks/src/back-button/style.css`, `packages/blocks/src/steps/view.ts`
  Message: `feat(blocks): let the Previous button return to the cart on the first step`

### Task 5: Next always shows

**Files:**
- Modify: `packages/blocks/src/steps/view.ts`
- Modify: `packages/blocks/src/step-button/style.css`

**Interfaces:**
- Removes: `canAdvance()` and `syncStepButtons()` from `steps/view.ts`, and their calls in `boot()` and `setState()`.

- [ ] **Step 1: Implement**
  - Delete the two functions and their calls.
  - `advance()` looks up the next step first and returns when there is none, before `complete()` runs.
  - Remove `.yaycheckout-step-button[hidden]` from `step-button/style.css`.

- [ ] **Step 2: Build and verify**

  Run: `pnpm build:blocks`
  Expected: compiles.

  Storefront, using the Steps layout:
  1. Check the Next buttons.
     Expected: Next shows on every step, step 2 included.
  2. On step 2, leave the payment method unchosen and click Next.
     Expected: no validation bubble, step 2 stays open, and Place order stays visible.
  3. Remove Steps from the layout.
     Expected: Next shows and does nothing.

- [ ] **Step 3: Commit**

  Stage: `packages/blocks/src/steps/view.ts`, `packages/blocks/src/step-button/style.css`
  Message: `feat(blocks): keep the Next step button visible on every step`
