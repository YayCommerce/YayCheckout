# Phase 3: Template 01 matches its mock

**Plan:** `README.md` in this directory, which holds the Status, constraints and interface contract
**Depends on:** Phase 1 (`templates/layouts/classic-two-column/markup.php` read by `LayoutTemplateCatalogue::markup()`, and `LayoutTemplateCatalogueTest`) and Phase 2 (the wizard creates from the template)
**Delivers:** a new Classic two-column layout has the structure the spec lists under **UI → Template 01 markup**.
**Exit criterion:** the spec's acceptance criterion 12 walkthrough passes end to end:
1. On a site with setup not done, run the setup wizard, pick Classic two-column and apply.
2. Open the created layout in the editor. Expected: no block is invalid, and the canvas shows a `#f0f0f1` page with the white summary card.
3. Activate the layout and load the checkout as a shopper with a product in the cart. Expected: the page matches the template 01 mock's structure, with billing before shipping and the spec's Out items absent. Place an order with a test gateway: it succeeds.
4. In wp-admin, change the layout's primary colour in the editor sidebar, then reset it from Tools. Expected: the colour returns to `#3858e9`.
5. In the New layout modal's library, every template except Classic two-column shows Coming soon.

`composer test` must also pass, including `LayoutTemplateCatalogueTest`.

### Task 5: Rebuild `classic-two-column/markup.php`

**Files:**
- Modify: `templates/layouts/classic-two-column/markup.php` (the whole body)
- Modify: `docs/architecture.md:46` (the description of the default layout's structure: header bar, breadcrumb, contact row, summary card, no footer)
- Modify, only if the Billing or Shipping inner structure changes: `packages/blocks/src/shared/address-template.ts` (spec: the fields keep today's arrangement, so this is expected to stay as it is)

**Interfaces:**
- Consumes: `LayoutTemplateCatalogue::markup()` (README contract); existing block attributes:
  - `yaycheckout/login` `text`, `linkText`
  - `yaycheckout/email` `placeholder`, `description`
  - `yaycheckout/billing` and `yaycheckout/shipping` `title`
  - `yaycheckout/shipping-methods` and `yaycheckout/payment` `label`
  - `yaycheckout/order-note` `disclosure: "checkbox"`, `disclosureText`, `placeholder`
  - `yaycheckout/place-order` `showTotal`
  - `yaycheckout/return-to-cart` `arrow`
  - `yaycheckout/order-summary` `label`
  - `yaycheckout/order-summary-items` `quantityDisplay: "badge"`, `showLabels: false`
  - `yaycheckout/coupon-form` `disclosure: "collapsible"`, `disclosureText`
- Produces: nothing new. The file's output is template 01.

**Steps:**
1. Build the layout in the YayCheckout editor on a scratch layout, exactly as the spec's **UI → Template 01 markup** lists. Build it with `wp.blocks.createBlock()` and `resetBlocks()` from the editor's console, reusing the current Billing and Shipping blocks. Save it, then read the saved `post_content`, which is what the Code editor shows.
2. Paste that markup into `markup.php` and put back the PHP:
   - `esc_html_e()` for every string, both in the block content and in the attribute JSON;
   - `has_custom_logo()` choosing Site Logo or Site Title;
   - `wc_get_cart_url()` for the breadcrumb's Cart link;
   - the terms sentence and the links under the summary, built at the top of the file, each printing only for the pages that exist. A paragraph whose links are all missing is left out with its block comment.
   - Drop the legacy `align` attribute the editor writes next to `typography.textAlign`.
3. Run `composer lint -- templates/layouts/classic-two-column/markup.php`. Expect it clean.
4. Render the template with the terms and privacy pages filtered into the four states (both, terms only, privacy only, neither). Expect `publish_error()` to be `''` and the sentence to read correctly in each. Parse each state in the editor with `wp.blocks.parse()`. Expect no invalid block.
5. Run `composer test`. Expect it to pass, including `LayoutTemplateCatalogueTest`.
6. Update `docs/architecture.md:46`, and the mirror comment in `packages/blocks/src/order-summary/edit.tsx`: the template puts Coupon Form between Items and Totals.
7. Run the exit criterion. Delete the layouts it creates, and leave layout 61 the only active layout.
8. Set the plan Status in `README.md` to `done YYYY-MM-DD` and the phase table rows to done.

Commit message: `feat(templates): rebuild Classic two-column to match its mock`
