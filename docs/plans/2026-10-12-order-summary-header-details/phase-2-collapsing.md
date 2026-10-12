# Phase 2: Collapsing

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** Phase 1 Tasks 1 and 2 (the three blocks, `isNewShape()` and the wrapper classes).
**Delivers:** Order Summary's Collapsible, Starts and Opens settings. On the storefront, the header and every Toggle open and close the details, in sync.
**Exit criterion:** spec acceptance criteria 4–10 pass on a fresh layout after `pnpm build:blocks`. Run the Review focus checks owned here too: clicks in the header, AJAX refresh, Opens Up, no trigger, and links inside a Toggle.

### Task 1: `OrderSummaryCollapse` and the collapsible markup

**Files:**
- Create: `includes/Services/OrderSummaryCollapse.php`
- Modify: `packages/blocks/src/order-summary/{block.json,render.php}`, `packages/blocks/src/order-summary-header/render.php`, `packages/blocks/src/order-summary-details/render.php`, `packages/blocks/src/order-summary-toggle/render.php`

**Interfaces:**
- Consumes: the README contract's markup names.
- Produces:
  - `OrderSummaryCollapse::DETAILS_ID`, `state()` and `of_layout()`, plus the `collapsible` and `initialState` attributes (README contract);
  - the Order Summary wrapper classes `is-collapsible` and `is-open`;
  - the overlay button: `<button type="button" class="yaycheckout-order-summary__header-button" aria-expanded="…" aria-controls="yaycheckout-order-summary-details" aria-label="{label}"></button>`;
  - the collapsible Toggle: `<div class="yaycheckout-order-summary-toggle" role="button" tabindex="0" aria-expanded="…" aria-controls="yaycheckout-order-summary-details" data-yaycheckout-summary-toggle>`, holding the content with links unwrapped (`wp_kses( $content, $allowed )`, where `$allowed` is `wp_kses_allowed_html( 'post' )` minus `a`), then the chevron when `showChevron` is on;
  - Details gets `hidden` when collapsible and not open.

Steps: expand after Phase 1 lands. Include a step that **verifies the state** against spec criteria 4, 5 and 9, by reading the storefront HTML for each combination.

### Task 2: Storefront toggling

**Files:**
- Create: `packages/blocks/src/order-summary/view.ts`, `packages/blocks/src/order-summary/style.css`
- Modify: `packages/blocks/src/order-summary/block.json` (`viewScript: "file:./view.js"`, `style: "file:./style-index.css"`), `packages/blocks/src/order-summary/index.ts` (import `./style.css`)

**Interfaces:**
- Consumes: Task 1's markup.
- Produces:
  - **Delegated listeners on `document`:**
    - `click` on `.yaycheckout-order-summary__header-button` or `[data-yaycheckout-summary-toggle]`;
    - `keydown` Enter or Space on `[data-yaycheckout-summary-toggle]`, with `preventDefault`.

    Each flips `#yaycheckout-order-summary-details`'s `hidden`, the closest `.yaycheckout-order-summary`'s `is-open`, and `aria-expanded` on every bound element. It is bound once, guarded by `data-yaycheckout-summary-bound` on `<html>`, like `steps/view.ts`.
  - **CSS:**
    - `.is-collapsible .yaycheckout-order-summary-header { position: relative; cursor: pointer; }`.
    - The overlay button is `position: absolute; inset: 0; background: none; border: 0; padding: 0; margin: 0; z-index: 0;`, with a `:focus-visible` outline.
    - `.is-collapsible .yaycheckout-order-summary-header :is(a, button:not(.yaycheckout-order-summary__header-button), input, select, textarea, label, [role="button"], [tabindex]) { position: relative; z-index: 1; }`.
    - `[data-yaycheckout-summary-toggle] { cursor: pointer; }`.
    - `[aria-expanded="true"] > .yaycheckout-order-summary-toggle__chevron { transform: rotate(180deg); }`.

Steps: expand after Phase 1 lands. Include a step to **verify clicks**:
- a Step button and a link in a collapsible header perform their own action and don't toggle;
- a Toggle inside the header flips the state once;
- a Toggle outside the summary flips it and both chevrons follow.

Include a step to **verify refresh** (spec criterion 10).

### Task 3: Editor settings

**Files:**
- Modify: `packages/blocks/src/order-summary/{edit.tsx,shape.ts}`, `packages/blocks/src/order-summary-toggle/edit.tsx`
- Create: `packages/blocks/src/order-summary/editor.css`

**Interfaces:**
- Consumes: Task 1's attributes.
- Produces:
  - **`useSummaryCollapse()`** (README contract).
  - **Order Summary sidebar** (new shape only):
    - `ToggleControl` "Collapsible". It's disabled with help "Add a Summary Header or a Summary Toggle to make the summary collapsible." while `! hasTrigger`.
    - `ToggleGroupControl` "Starts", "Collapsed" or "Open", shown while `collapsible`.
    - `ToggleGroupControl` "Opens", "Down" or "Up", shown while a Header exists. Its value is "Up" when the Header's index is greater than the Details' index. Choosing a value calls `moveBlockToPosition( headerId, clientId, clientId, newIndex )`.
  - **Editor CSS:** `.editor-styles-wrapper .yaycheckout-order-summary.is-preview-collapsed .yaycheckout-order-summary-details { opacity: .5; }`. The class is added while `collapsible && initialState === 'collapsed'`.
  - **Toggle chevron:** the chevron SVG (the path in `Disclosure::open()`, class `yaycheckout-order-summary-toggle__chevron`) renders after the inner blocks while `collapsible && showChevron`, and is rotated while `open`, both from `useSummaryCollapse()`.

Steps: expand after Phase 1 lands. Include a step for spec criterion 8: dragging the Header below Details in List View flips the Opens control to "Up", and the storefront DOM prints Details first.
