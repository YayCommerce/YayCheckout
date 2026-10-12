# Phase 3: Old layouts and template 01

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** Phase 1 (the blocks and `isNewShape()`) and Phase 2 (collapsing, used by the end-to-end check).
**Delivers:** A merchant converts an old-shape Order Summary with one click. Template 01 creates the new shape. The architecture doc describes summary collapsing.
**Exit criterion:** spec acceptance criterion 13 (end-to-end) passes: run `pnpm build:blocks`, then do the walkthrough as written in the spec.

### Task 1: Use header and details conversion

**Files:**
- Modify: `packages/blocks/src/order-summary/edit.tsx`

**Interfaces:**
- Consumes: `isNewShape()`; the block names.
- Produces: in the old shape, Order Summary's sidebar shows a `Notice` (status `info`, not dismissible): "This summary uses the old layout. Switch to a header and a details part to make it collapsible." Its button is "Use header and details". On click it calls `replaceInnerBlocks( clientId, [ header, details ] )`, where:
  - `header` = `createBlock( headerMeta.name, {}, [ createBlock( toggleName, {}, [ createBlock( 'core/heading', { level: 3, content: label || __( 'Your order', 'yaycheckout' ), anchor: 'order_review_heading' } ) ] ) ] )`;
  - `details` = `createBlock( detailsMeta.name, { lock: { remove: true } }, currentInnerBlocks )`, keeping the old children and their attributes in order;
  - `collapsible` stays unset.

Steps: expand after Phase 2 lands. Include a step that checks two things on an old-shape layout (made on `dev/edd`, then opened after building):
- the notice shows, and the List View is unchanged until the button is clicked;
- after clicking and publishing, the storefront heading still has `id="order_review_heading"`, and the children print in their old order.

### Task 2: Template 01 and docs

**Files:**
- Modify: `templates/layouts/classic-two-column/markup.php` (lines 175–183, the Order Summary)
- Modify: `docs/architecture.md` (the storefront section, after the Quantity stepper bullet)

**Interfaces:**
- Consumes: everything above.
- Produces:
  - **Template 01's Order Summary** has a Header holding a Toggle holding a Heading (h3, `esc_html_e( 'Order summary', 'yaycheckout' )`), and a Details (`lock.remove`) holding Items (`showLabels: false`, `quantityDisplay: badge`), the Coupon Form (collapsible "Add coupons") with its Coupon button, and Totals. Collapsible is off. The markup is copied from the editor's Code editor, with the PHP translation calls put back.
  - **An architecture bullet, "Summary collapsing"**, covering three points:
    - `OrderSummaryCollapse::state()` is the single owner, read by four `render.php` files;
    - the header overlay button and the `role="button"` Toggles flip the same state;
    - the block order sets the direction.

Steps: expand after Phase 2 lands. The last step is the phase exit criterion (spec criterion 13). Also check spec criterion 12: a new layout from template 01 opens in the editor with no invalid block.
