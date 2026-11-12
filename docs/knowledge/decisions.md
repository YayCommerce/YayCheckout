---
area: decisions
updated: 2026-11-12
updated_ref: c7ee247
covers: [includes/YayCheckout.php, includes/Support/Plan.php, scripts/run.sh, includes/Renderer/LayoutPin.php, includes/Renderer/DesignFonts.php]
---
# Decisions

> Update when: a decision here is reversed, or a new one shapes how the plugin is built. Newest first. Each has its evidence.

### 2026-11-12 Layout priority is the order of the active set; country rules are pinned like cart rules
- **Why:** the active option's order already decided which match wins, so priority needed only a writer (`LayoutRepository::set_priority()`), not a number per layout. Overlap warnings were rejected: undecidable cheaply, and the list order answers "which wins".
- **How:** `set_priority()` refuses a list that isn't exactly the active set (409 from `PUT /layouts/priority`), and re-activating an active layout leaves it in place. Billing/shipping country is read once at the checkout page load and pinned; a mid-form change doesn't swap the layout (BL-6).
- **Evidence:** P-1, BS-1, `tests/Integration/Pro/LayoutPriorityTest.php`, `includes/Renderer/CustomerFacts.php`.

### 2026-11-06 Thank-you page gated as WooCommerce gates order-received
The right order key, then the account owner, or a guest within the grace period or after email confirmation. It fires `woocommerce_thankyou`, so other plugins' thank-you hooks still run. Evidence: `062f792`, `tests/Integration/Pro/ThankYouPageTest.php`.

### 2026-11-06 Neither zip ships JS source; the readme links the public repo at a stamped commit
- **Why:** wordpress.org guideline 4 accepts a link to the public development location, and Free's zip shrank by about 575 KB.
- **Accepted risks:** a reviewer round-trip, and the public repo has no tags, so the commit SHA is the only version pin.
- **Also:** the zips drop `vendor/composer/installed.json` and keep `vendor/composer/installed.php`.
- **Evidence:** `4b7b87f`, `scripts/run.sh` (artifact checks). Replaced the 2026-10-30 approach of shipping source (`9a6c54d`).

### 2026-11-05 One branch builds both editions
`dev` is Pro, and Free is made at release with `--plan=free`.
- **Rejected:** separate `dev/edd` and `dev/lite` branches. `dev/lite` had drifted 32 commits behind, with merge conflicts on the adapter and header.
- **Evidence:** `c2abc8c`, `2ed58b1` (rename to `dev`), `editions/YayCheckoutPluginAdapter.free.php`.

### 2026-11-05 `vendor-prefixed/` is tracked; dev and release never rescope in place
- **Why:** the site fatalled while the directory was being rebuilt, and releases left `vendor/` as `--no-dev`.
- **Now:** release rescopes a staging copy instead.
- **Evidence:** `f57d61e`.

### 2026-10-29 A layout picked from cart facts is pinned per checkout page load
- **Why:** the form keeps its layout while the cart changes mid-checkout.
- **How:** the pin lives in the WC session, plus a signed cookie used only as a theme hint.
- **Evidence:** `a9c3ab7`, `includes/Renderer/LayoutPin.php`.

### 2026-10-20 Plugin spots print the other plugin's shortcode, in every edition
- **What:** a placeholder prints its shortcode only when that shortcode exists.
- **Why:** a missing plugin leaves no empty box.
- **Evidence:** `0955b02`, `packages/blocks/src/plugin-block/render.php`.

### 2026-10-19 Free has no layout limit; Free serves one active layout and ignores rules
- **What:** the Free 3-layout cap was removed. `Plan` now decides only how many layouts can be active and whether assignment rules apply.
- **Evidence:** `7b2505f`, `LayoutRepository::active_ids()`.

### 2026-10 Edition boundary: Pro is a removable tree, checked on the zip
- **What:** Free is Pro minus `includes/Pro/`, the Pro blocks and the Pro templates (their `preview.html` kept), with one guarded bootstrap.
- **Why it's checked on the zip:** a dev tree always contains everything, so a Pro leak only shows on the zip.
- **Pro blocks:** they ship only in Pro, and are locked stubs in Free.
- **Evidence:** `scripts/check-pro-boundary.sh`, `scripts/run.sh` (`PRO_PATHS`), `includes/Blocks/BlockRegistry.php` (`PRO_BLOCKS`), `5e40d02`.

### 2026-10 The checkout runs themeless and owns its base styles
- **What:** the site theme is swapped out for the checkout request, and `assets/css/checkout-base.css` replaces WooCommerce's general styles.
- **Why:** themes' and WooCommerce's `.form-row` rules no longer fight the layout's design.
- **Block CSS:** moved into each block's `style.css` (`3368e5c`).
- **Evidence:** `includes/Renderer/ThemelessCheckout.php`, `includes/Renderer/CheckoutPage.php`.

### 2026-10 A layout is one post that owns its design
- **What:** the markup is `post_content`, and the design is `_yaycheckout_design` meta, revisioned.
- **Why:** a layout's look travels with it (duplicate, export, Reset).
- **Fonts:** a picked font is installed into the Font Library when the design is saved, so shoppers never fetch from Google.
- **Evidence:** `includes/Models/Layout.php`, `includes/Renderer/DesignFonts.php`, `includes/Engine/DesignFontInstall.php`.

### 2026-10 Deactivate returns a layout to draft; the active layout can't be deleted
- **What:** deactivate returns a layout to draft, and the active layout can't be deleted.
- **Why:** a layout nobody serves isn't left "published", and shoppers never lose the checkout they're on.
- **Evidence:** `LayoutRepository::deactivate()`, `includes/Engine/LayoutActiveGuard.php`.
