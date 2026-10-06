# Remove the Theme Header and Footer from the Checkout Page Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; `## Requirements` below is the contract.

**Status:** active
**Goal:** The checkout page that renders a layout prints no theme header or footer, on classic and block themes alike, so header and footer sections can later come from blocks.
**Spec:** none, planned from the request
**Architecture:** `templates/checkout-page.php` stops calling `get_header()`/`get_footer()` (classic) and `block_header_area()`/`block_footer_area()` (block) and prints one document shell of its own for every theme: the shell the block-theme branch already prints, minus the template parts. `wp_head()`/`wp_footer()` stay, so the theme's stylesheet, global styles and every plugin's head/footer output still load. The alternative, keeping the two theme branches and dropping only the header/footer calls, would leave classic themes with no `<html>`/`<head>`, because their header.php and footer.php print the document.
**Touches:** `templates/checkout-page.php`, `includes/Renderer/CheckoutPage.php` (docblock), `docs/architecture.md`, `tests/fixtures/template-themes/classic-shell/` (deleted)
**Tests:** none. Template markup isn't among CLAUDE.md's test-worthy risks, and no bug is being fixed. Verified by hand on Storefront (classic) and Twenty Twenty-Five (block), both installed on the Local site.

## Requirements

- **Goal:** a shopper on a checkout page served by a YayCheckout layout, or a merchant on a preview link, sees the layout and the preview banner with no theme header or footer above or below it.
- **Out of scope:** header and footer blocks (later work); dequeuing theme styles; the order-received and order-pay endpoints, which stay WooCommerce's and keep the theme's header and footer; checkout pages where the renderer stands down (safe mode, no layout resolves).
- **Acceptance criteria:**
  1. On a classic theme (Storefront) and a block theme (Twenty Twenty-Five), the rendered checkout page has no theme header or footer markup: no `<header id="masthead">`/`<footer id="colophon">` on Storefront, and no `wp-block-template-part` element on Twenty Twenty-Five.
  2. On both themes the page is one well-formed document: exactly one `<!doctype html>`, one `<head>` holding `wp_head()` output with the theme's stylesheet, one `<title>`, a `<body>` whose classes include `yaycheckout-page`, then `wp_footer()` output before `</body>`.
  3. End to end, on both themes: as a guest with a product in the cart, open the checkout page, pick a payment method, fill the required fields and click Place order. The order is placed and the order-received page shows (with the theme's own header and footer, as before).
- **Decided with the user:**
  - Keep theme styles or render a blank canvas? Keep theme styles: only the header and footer markup goes.
  - One document shell for all themes, or separate classic and block paths? One shell for all themes.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in the guarded `boot_pro()` in `includes/YayCheckout.php`; nothing here touches Pro.
- Never hand-edit `vendor/`, `vendor-prefixed/`, `node_modules/`, `assets/dist/`, `release/`.
- Branch from `dev/edd` as `<name>/<type>/<topic>`, for example `<name>/feat/no-theme-header-footer`. Land through a PR into `dev/edd`. Conventional Commits, with the trailer and PR footer CLAUDE.md's Git section requires. Stage files by name.
- `composer lint -- templates/checkout-page.php includes/Renderer/CheckoutPage.php` passes on `dev/edd` today (2/2 files clean); it must still pass.

## Review focus

- **`<title>` on classic themes without `title-tag` support.** Such a theme prints `<title>` in its header.php, which no longer loads, so the page would have no title. Expect exactly one `<title>`. Task 1 Step 1 adds a fallback and Step 3 checks the count on both themes.
- **Block themes' layout styles.** Global styles put root padding and block gap on `.wp-site-blocks`. Without that wrapper the layout runs edge to edge. Keep the wrapper on every theme (Task 1 Step 1), and check in Step 3 that the layout keeps its side padding on Twenty Twenty-Five.
- **Script modules on block themes.** The layout must still render before `wp_head()`, or the import map misses the blocks' modules and the browser refuses to load them (`be833b1`). Task 1 Step 3 checks the console for module errors.

---

### Task 1: One document shell with no theme header or footer

**Files:**
- Modify: `templates/checkout-page.php` (whole file; the top docblock and lines 12–73)
- Modify: `includes/Renderer/CheckoutPage.php:12-19` (class docblock)
- Modify: `docs/architecture.md:61` (the **Rendering:** bullet)

**Interfaces:**
- Consumes: `CheckoutPage::layout(): ?Layout` and `PreviewRequest::get_instance()->active()`/`->banner( Layout )`, both unchanged.
- Produces: nothing new. The template path (`CheckoutPage::page_template()`) and the `template_include` hook at `PHP_INT_MAX` are unchanged.

- [ ] **Step 1: Rewrite `templates/checkout-page.php` as one shell**

Keep the `ob_start()` block that renders the preview banner and `do_blocks()` into `$yaycheckout_main` before `wp_head()`, and keep the comment explaining why it comes first. Delete `$yaycheckout_is_block`, the classic `get_header()`/`get_footer()` branch, and the `block_header_area()`/`block_footer_area()` buffers. For every theme, print the shell the block branch prints today: `<!doctype html>`, `<html language_attributes()>`, `<head>` with the charset and viewport metas and `wp_head()`, `<body body_class( 'yaycheckout-page' )>`, `wp_body_open()`, `<div class="wp-site-blocks">` around `$yaycheckout_main` alone, then `wp_footer()`.

Title fallback: inside `<head>`, before `wp_head()`, print `<title><?php echo esc_html( wp_get_document_title() ); ?></title>` only when `! current_theme_supports( 'title-tag' ) && false === has_action( 'wp_head', '_block_template_render_title_tag' )`. Block themes get their title from that core action, and themes with `title-tag` get theirs from `_wp_render_title_tag`, so the guard prevents a second `<title>`.

Rewrite the file's top docblock to say the page is the layout's blocks inside the plugin's own document, with no theme header or footer, and that the theme's styles still load through `wp_head()`.

- [ ] **Step 2: Update the descriptions that name the theme header and footer**

`includes/Renderer/CheckoutPage.php` class docblock: replace "the theme's header and footer around the layout's blocks (the root block is the form)" with wording that the template prints the layout's blocks in its own document, without the theme's header or footer. `docs/architecture.md:61`: replace "then the theme's header and footer around it" with "inside the template's own document, on every theme: no theme header or footer, while `wp_head()` still loads the theme's styles". Make no other change to that bullet.

- [ ] **Step 3: Verify on both themes**

Run: `composer lint -- templates/checkout-page.php includes/Renderer/CheckoutPage.php`
Expected: no errors, 2/2 files.

Then, for Storefront and then Twenty Twenty-Five (Appearance → Themes → Activate), with safe mode off, an active layout and a product in the cart, open the checkout page as a guest:
- View source. Expected: one `<!doctype html>`, exactly one `<title>`, the theme's stylesheet link in `<head>` (`storefront-style-css` or `twentytwentyfive-style-css`), `yaycheckout-page` in the body class, and `<div class="wp-site-blocks">` holding only `<div class="woocommerce yaycheckout-page">`. No `id="masthead"`, no `id="colophon"`, no `wp-block-template-part`.
- Browser console. Expected: no module-resolution or 404 errors that `dev/edd` doesn't also show.
- On Twenty Twenty-Five, the layout keeps the theme's side padding rather than touching the viewport edges.
- Open a preview link for a draft layout (made from the layout's preview-link action on the Layouts page, `PreviewLinkModal`). Expected: the preview banner shows above the layout, with no header or footer.

Restore the theme that was active before.

- [ ] **Step 4: Verify checkout end to end (acceptance criterion 3)**

On Storefront and on Twenty Twenty-Five, as a guest: fill the required fields, choose Cash on delivery (or any enabled offline gateway), click Place order.
Expected: the order is placed and redirects to the order-received page, which still shows the theme's header and footer. WooCommerce → Orders lists the new order.

- [ ] **Step 5: Commit**

Stage: `templates/checkout-page.php`, `includes/Renderer/CheckoutPage.php`, `docs/architecture.md`
Message: `feat(renderer): render the checkout page without the theme's header and footer`

### Task 2: Remove the classic-shell test fixture

The fixture exists so template tests could check that a classic theme's header.php and footer.php wrap the layout. The template no longer loads them, no test uses the fixture (`tests/Integration/` is empty), and `tests/bootstrap.php` and `phpunit.xml.dist` don't name it.

**Files:**
- Delete: `tests/fixtures/template-themes/classic-shell/` (`footer.php`, `header.php`, `index.php`, `style.css`)
- Modify: `docs/architecture.md:124`

**Interfaces:**
- Consumes: Task 1 (the template no longer calls `get_header()`/`get_footer()`).
- Produces: nothing.

- [ ] **Step 1: Delete the fixture and its mention**

`git rm -r tests/fixtures/template-themes`. In `docs/architecture.md:124`, delete ", and a minimal classic theme (`tests/fixtures/template-themes/classic-shell/`) for template tests" so the sentence ends at "for the hook scan.".

- [ ] **Step 2: Verify nothing still names it**

Run: `git grep -n -E "template-themes|classic-shell"`
Expected: matches only in this plan, `docs/plans/2026-10-06-remove-theme-header-footer.md`.

- [ ] **Step 3: Commit**

Stage: `tests/fixtures/template-themes` (the deletion), `docs/architecture.md`
Message: `chore(tests): remove the classic-shell template fixture`
