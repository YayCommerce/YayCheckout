# Phase 1: Header and Details blocks

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** nothing
**Delivers:** A new Order Summary starts as a Summary Header, holding a Summary Toggle that holds a Heading "Order summary", and a Summary Details holding Items and Totals. The header is a flex row the merchant lays out left and right. Old-shape summaries are unchanged. Nothing collapses yet.
**Exit criterion:** `pnpm build:blocks` succeeds. In a fresh layout, inserting a new Order Summary shows that tree in List View, and the storefront prints the Header and the Details with no `#order_review_heading`. A layout saved before this change prints the same storefront HTML as on `dev/edd`.

### Task 1: Summary Header and Summary Details, Order Summary's new shape

**Files:**
- Create: `packages/blocks/src/order-summary-header/{block.json,index.ts,edit.tsx,render.php}`
- Create: `packages/blocks/src/order-summary-details/{block.json,index.ts,edit.tsx,render.php}`
- Create: `packages/blocks/src/order-summary/shape.ts`
- Modify: `packages/blocks/src/order-summary/edit.tsx`, `packages/blocks/src/order-summary/render.php`, `packages/blocks/src/shared/section-label.tsx` (shared)
- Modify: `includes/Blocks/BlockRegistry.php` (`ORDER`)
- Modify: `packages/blocks/src/shared/block-icons.tsx` (shared)

**Interfaces:**
- Consumes: nothing.
- Produces: block names `yaycheckout/order-summary-header` and `yaycheckout/order-summary-details`; the wrapper classes and Details id; `isNewShape()`. All are in the README contract.

- [ ] **Step 1: Create `order-summary-header`**

  - **`block.json`:**
    - `apiVersion` 3, title "Summary Header", category `yaycheckout`, `parent: [ "yaycheckout/order-summary" ]`.
    - `supports`: `html: false`, `multiple: false`, `reusable: false`, `color: { text: true, background: true }`, `__experimentalBorder` (color, radius, style, width), `spacing: { margin: true, padding: true, blockGap: true }`, and `layout: { default: { type: "flex", justifyContent: "space-between", verticalAlignment: "center", flexWrap: "wrap" }, allowSwitching: false }`.
    - `editorScript`, `editorStyle` and `render` as in `order-summary/block.json`.
  - **`edit.tsx`:** `useInnerBlocksProps( useBlockProps( { className: 'yaycheckout-order-summary-header' } ), { allowedBlocks: useBlocksAllowedExcept( isCheckoutSection ), templateLock: false } )`.
  - **`index.ts`:** `registerDynamicBlock( metadata, Edit, saveNestedBlocks )`.
  - **`render.php`:** `<div get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-summary-header' ] )>` around `$content`. It uses the same docblock and phpcs-ignore shape as `order-summary/render.php`.

- [ ] **Step 2: Create `order-summary-details`**

  - **`block.json`:** the same as the Header, except:
    - title "Summary Details";
    - no `layout` support;
    - `spacing.blockGap` printed through `BlockGap`, as `order-summary` does.
  - **`edit.tsx`:**
    - Inner blocks use the class `yaycheckout-order-summary-details`.
    - `allowedBlocks: useBlocksAllowedExcept( isCheckoutSectionOrToggle )`, where that is a module-level `( type ) => isCheckoutSection( type ) || type.name === 'yaycheckout/order-summary-toggle'`. It's module-level so the `useMemo` in `useBlocksAllowedExcept` stays stable.
    - Gap CSS through `useBlockGapCSS`, as in `order-summary/edit.tsx`.
  - **`render.php`:** `<div id="yaycheckout-order-summary-details" get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-summary-details' . BlockGap::wrapper_class( $attributes, 'yaycheckout/order-summary-details', 'yaycheckout-order-summary-details' ) ] )>` around `$content`.
  - **`BlockRegistry::block_settings()`:** add `order-summary-details` to the `$gap_block` list.

- [ ] **Step 3: Implement `isNewShape( childNames: string[] ): boolean` in `packages/blocks/src/order-summary/shape.ts`**

  It is true when `childNames` includes `headerMeta.name` or `detailsMeta.name`, both imported from their `block.json`.

- [ ] **Step 4: Change `packages/blocks/src/order-summary/edit.tsx`**

  - **`TEMPLATE`:**
    ```
    [ headerMeta.name, {}, [ [ 'yaycheckout/order-summary-toggle', {}, [ [ 'core/heading', { level: 3, content: __( 'Order summary', 'yaycheckout' ) } ] ] ] ] ],
    [ detailsMeta.name, { lock: { remove: true } }, [ [ itemsMeta.name ], [ totalsMeta.name ] ] ]
    ```
    Until Task 2 registers the Toggle, the editor drops that unknown entry. Task 2 Step 4 checks the full tree.
  - **`allowedBlocks`:** `[ headerMeta.name, detailsMeta.name ]` while `isNewShape( childNames )`, where `childNames` comes from `getBlocks( clientId )` via `useSelect`. Otherwise it stays today's `useBlocksAllowedExcept( isCheckoutSection )`.
  - **Canvas heading:** show `SectionLabel`'s canvas `<h3>` only in the old shape. In the new shape, keep the sidebar Label field with help text "Read by screen readers as the name of the header's open/close button."; `SectionLabel` gets a `showHeading?: boolean` prop, default `true`.
  - Update the "change both" comment so it names the new tree.

- [ ] **Step 5: Change `packages/blocks/src/order-summary/render.php`**

  - Compute `$yaycheckout_new_shape`. It is true when any of `$block->parsed_block['innerBlocks']` has `blockName` `yaycheckout/order-summary-header` or `yaycheckout/order-summary-details`.
  - In the new shape, skip the `<h3 id="order_review_heading">` and print `$content`.
  - The old-shape and empty-children branches stay as they are.

- [ ] **Step 6: Register, add icons, build and verify**

  - `BlockRegistry::ORDER`: insert `order-summary-header`, `order-summary-details` after `order-summary`.
  - `block-icons.tsx`: `yaycheckout/order-summary-header` is a 24×24 stroke icon of a bar with a chevron (`<path d="M4 6h16v5H4zM15 9l1.5 1.5L18 9" />`). `yaycheckout/order-summary-details` is a box with lines (`<path d="M4 4h16v16H4zM8 9h8M8 13h8M8 17h5" />`).

  Run: `pnpm build:blocks`, then `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`.
  Expected: both exit 0.

  Run: `composer lint -- packages/blocks/src/order-summary/render.php packages/blocks/src/order-summary-header/render.php packages/blocks/src/order-summary-details/render.php includes/Blocks/BlockRegistry.php`.
  Expected: no errors.

  Check the editor:
  - Action: in a **fresh** layout, delete the Order Summary and insert a new one.
  - Expected: List View shows Order Summary > Summary Header (empty), Summary Details > Order Summary Items, Order Summary Totals.
  - Expected: the inserter inside Order Summary offers only Summary Header, and it doesn't offer Summary Details while one exists.
  - Expected: Summary Details has no Remove item in its menu.
  - Expected: the Header's sidebar shows the flex Justification, Orientation and Wrap controls.

  Check the storefront:
  - Action: publish that layout and assign it.
  - Expected: the checkout source has `.yaycheckout-order-summary-header` before `#yaycheckout-order-summary-details`, and no `#order_review_heading`.

  Check an old layout:
  - Action: before building, on `dev/edd`, use a layout whose Order Summary holds Items and Totals, with a product in the cart. In the browser, copy the Order Summary element's outer HTML (DevTools, `.yaycheckout-order-summary`) into the scratchpad as `before.html`. After building, reload and copy it again as `after.html`.
  - Expected: `diff before.html after.html` prints nothing. The first child is still `<h3 id="order_review_heading">`.

- [ ] **Step 7: Commit**

Stage: the eight new files, `packages/blocks/src/order-summary/{edit.tsx,render.php,shape.ts}`, `packages/blocks/src/shared/section-label.tsx`, `packages/blocks/src/shared/block-icons.tsx`, `includes/Blocks/BlockRegistry.php`
Message: `feat(blocks): add Summary Header and Summary Details to Order Summary`

### Task 2: Summary Toggle block (static)

**Files:**
- Create: `packages/blocks/src/order-summary-toggle/{block.json,index.ts,edit.tsx,render.php,style.css}`
- Modify: `includes/Blocks/BlockRegistry.php` (`ORDER`), `packages/blocks/src/shared/block-icons.tsx`

**Interfaces:**
- Consumes: Task 1's `TEMPLATE` entry for the Toggle.
- Produces: block `yaycheckout/order-summary-toggle` with attribute `showChevron`; wrapper `.yaycheckout-order-summary-toggle` (README contract).

- [ ] **Step 1: Create `block.json`**

  - Title "Summary Toggle", description "Opens and closes the Order Summary while it is collapsible.", with no `parent` or `ancestor`.
  - Attribute `showChevron` (`boolean`, default `true`).
  - `supports`: `html: false`, `reusable: false`, color (text, background), `__experimentalBorder`, `spacing` (margin, padding, blockGap), and typography (`fontSize`, `lineHeight`), with `layout: { default: { type: "flex", flexWrap: "nowrap", verticalAlignment: "center" }, allowSwitching: false }`, so the chevron sits after the content on one line.
  - `"style": "file:./style-index.css"`.

- [ ] **Step 2: Create `edit.tsx` and `index.ts`**

  - **`edit.tsx`:**
    - Inner blocks with `allowedBlocks: [ 'core/paragraph', 'core/heading', 'core/image', 'core/group', 'core/spacer' ]`, `templateLock: false`, and `template: [ [ 'core/paragraph', { content: __( 'Order summary', 'yaycheckout' ) } ] ]`.
    - No chevron yet. The storefront prints one only while the summary is collapsible, and Phase 2 Task 3 adds it to the editor under that condition.
    - Inspector panel "Summary toggle": a `ToggleControl` "Show chevron", plus a `<p>` with the block description.
  - **`index.ts`:** `registerDynamicBlock( metadata, Edit, saveNestedBlocks )`.

- [ ] **Step 3: Create `render.php` (static mode only in this phase)**

  `<div get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-summary-toggle' ] )>` around `$content`, with no chevron, `role` or `tabindex`. Phase 2 Task 1 adds the collapsible branch.

  `style.css`: `.yaycheckout-order-summary-toggle__chevron { width: 1.25em; height: 1.25em; flex: none; fill: currentColor; transition: transform .15s; }`.

- [ ] **Step 4: Register, add the icon, build and verify**

  - `ORDER`: insert `order-summary-toggle` after `order-summary-details`.
  - Icon: a chevron in a rounded box, `<path d="M4 7h16v10H4zM9 11l3 3 3-3" />`.

  Run: `pnpm build:blocks`, then `composer lint -- packages/blocks/src/order-summary-toggle/render.php includes/Blocks/BlockRegistry.php`.
  Expected: exit 0, and no lint errors.

  Check the editor:
  - Action: in a fresh layout, insert a new Order Summary.
  - Expected: List View shows Summary Header > Summary Toggle > Heading "Order summary", and the Toggle's sidebar has the "Show chevron" control.
  - Action: insert a Summary Toggle at the top of the Checkout block, outside the summary.
  - Expected: it inserts.
  - Expected: inside Summary Details, the inserter doesn't offer Summary Toggle.
  - Expected: inside a Toggle, the inserter offers only Paragraph, Heading, Image, Group and Spacer.

  Check the storefront:
  - Action: publish, assign and load the checkout.
  - Expected: "Order summary" prints inside `.yaycheckout-order-summary-toggle` with no `role` or `tabindex`.

  This is the phase's exit criterion.

- [ ] **Step 5: Commit**

Stage: the five new files, `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/shared/block-icons.tsx`
Message: `feat(blocks): add Summary Toggle block`
