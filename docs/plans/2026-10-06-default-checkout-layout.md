# Default Checkout Layout with Header and Footer Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; `## Requirements` below is the contract.

**Status:** done 2026-10-06
**Goal:** A new or reset layout starts as a complete two-column checkout with a header and footer made of core blocks, so activating it as-is gives shoppers a finished-looking checkout.
**Spec:** none, planned from the request
**Architecture:** `LayoutRepository::default_markup()` keeps building the layout in PHP through `Helpers\BlockMarkup`, which gains two builders for static core blocks (Group, Columns, Column, Paragraph). Those blocks need their saved HTML in the markup: the editor rejects them without it, and `do_blocks()` drops their wrapper elements. The alternative, a hand-written markup file, would lose the per-site choices made when the layout is created (logo or title, which footer links exist, translated labels) and would add a second way to build markup. A small stylesheet on the Checkout block adds the sticky summary, the mobile order and the header/footer spacing. Core position support can't do the sticky part: classic themes don't enable `position.sticky`.
**Touches:** `includes/Helpers/BlockMarkup.php` (shared; its only consumer is `includes/Repositories/LayoutRepository.php`), `includes/Repositories/LayoutRepository.php`, `apps/admin/src/pages/tools/components/ResetCard.tsx`, `packages/blocks/src/checkout/{style.css,index.ts,block.json}`, `docs/architecture.md`, `docs/backlog/2026-10-05-account-fields-and-checkout-form-hooks-never-render.md`
**Tests:** none. Offered `tests/Integration/DefaultLayoutMarkupTest.php` (default markup publishes and holds every section); the user declined it. Verified by hand instead.

## Requirements

- **Goal:** every path that seeds a layout (New layout, the setup wizard, Tools → Reset) produces the layout below, and a shopper on it can place an order.
- **Out of scope:** new YayCheckout blocks (Header, Footer, Order Notes, Account); firing WooCommerce's missing form hooks; existing layouts, which keep their markup; the library templates in `LayoutTemplateCatalogue`, which still seed the same default; the Design page's tokens.
- **Acceptance criteria:**
  1. `LayoutRepository::default_markup()` returns, in order: a header Group, the Checkout block, a footer Group. Exact tree in Task 2.
  2. Opening a freshly created layout in the editor shows no "This block contains unexpected or invalid content" warning on any block.
  3. On the storefront at 1280px wide, the form column sits left, and the summary column (Order Summary, Coupon Form) sits right and stays in view while the page scrolls. At 390px wide the columns stack with the summary first and Place Order last.
  4. The header shows the site logo when the site has one, otherwise the site title, and "Secure checkout". The footer shows "© <site name>" and links to the cart, plus the privacy policy and terms pages when they are set.
  5. End to end, on Twenty Twenty-Five and on Storefront: create a layout, activate it, then as a guest with a product in the cart fill the required fields, choose Cash on delivery and click Place order. The order is placed and the order-received page shows.
- **Decided with the user:**
  - Header and footer: core blocks only, no new YayCheckout blocks.
  - Structure: two columns. Left: Express Pay, Contact, Billing, Shipping, Payment, Place Order. Right: sticky Order Summary with Coupon Form. On mobile it stacks, summary first.
  - Missing checkout pieces (order notes, account creation, login and notices): none in this plan; note them in the backlog.
  - Test: none; verify by hand.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types, `mixed`/`static` return types, or trailing commas after the last parameter of a declaration.
- Build server-side markup with `BlockMarkup`, never string literals of block comments (`includes/Helpers/BlockMarkup.php` docblock).
- Never hand-edit `assets/dist/`; change `packages/blocks/src/` and run `pnpm build:blocks`.
- Free names Pro only in `boot_pro()`; nothing here touches Pro, and the default layout is the same on both plans.
- Start after `trace/feat/no-theme-header-footer` has merged into `dev/edd`; otherwise the theme's header prints above this one. Branch from `dev/edd` as `trace/feat/default-checkout-layout`. Conventional Commits, with the trailer and PR footer from CLAUDE.md's Git section. Stage files by name.
- Base state: `composer lint -- includes/Helpers/BlockMarkup.php includes/Repositories/LayoutRepository.php` passes on `dev/edd`. `pnpm build:blocks` is the only check on block TypeScript and CSS.
- The active plan `docs/plans/2026-10-06-official-block-apis.md` also edits `packages/blocks/src/checkout/block.json` and `index.ts`. If it has landed, add to its version of those files and don't revert its keys.

## Review focus

- **Core block validation.** A saved-HTML string that differs from the core block's `save()` output by one class or style makes the editor offer "Attempt recovery". Task 2 Step 3 opens a new layout in the editor and checks every block.
- **Shipping rates printed twice.** Order Summary prints WooCommerce's review-order table, which already holds the shipping-method radios. Adding the Shipping Methods block would print a second `shipping_method[0]` group. The default leaves Shipping Methods out, and Task 2 Step 4 checks for exactly one rate list.
- **Order-review hooks stop firing on the default layout.** The current default uses Order Review, which fires `woocommerce_checkout_before_order_review`, `woocommerce_checkout_order_review` and `woocommerce_checkout_after_order_review`. Order Summary, Payment and Place Order don't. Plugins that print there (gateways rarely; some upsell and gift-message plugins) show nothing on new layouts. This is accepted for the chosen structure and recorded in the backlog (Task 4). Task 2 Step 4 still checks that gateways render and an order goes through.
- **Sticky summary under the admin bar.** A logged-in merchant on a preview link has the fixed admin bar on top. The sticky offset includes `--wp-admin--admin-bar--height` (Task 3), checked in Task 3 Step 3.
- **The summary is first in mobile order.** Without the `order` rule a phone shopper reaches Place Order before seeing the totals. Checked at 390px in Task 3 Step 3.

---

### Task 1: `BlockMarkup` builds static core blocks

**Files:**
- Modify: `includes/Helpers/BlockMarkup.php` (shared; consumer: `includes/Repositories/LayoutRepository.php`)

**Interfaces:**
- Produces:
  - `BlockMarkup::wrapper( string $name, array $attrs, string $open, string $close, array $inner_blocks = [] ): array`, a container whose saved HTML is `$open . $close`. `innerHTML` = `$open . $close`; `innerContent` = `[ $open, null × count( $inner_blocks ), $close ]`.
  - `BlockMarkup::html( string $name, array $attrs, string $html ): array`, a leaf with saved HTML. `innerHTML` = `$html`; `innerContent` = `[ $html ]`; no inner blocks.
  - `BlockMarkup::block()` and `BlockMarkup::serialize()` are unchanged.

- [ ] **Step 1: Add `wrapper()` and `html()` to `includes/Helpers/BlockMarkup.php`**

Docblocks follow `block()`'s: say the caller passes HTML that matches the core block's `save()` output for those attributes exactly, already escaped, or the editor flags the block.

- [ ] **Step 2: Verify**

Run: `composer lint -- includes/Helpers/BlockMarkup.php`
Expected: no errors.

Run in `wp shell` (Local: `wp --require=<file defining DB_HOST as localhost:<the site's mysqld.sock>>`):
`echo \YayCheckout\Helpers\BlockMarkup::serialize( [ \YayCheckout\Helpers\BlockMarkup::wrapper( 'core/group', [ 'className' => 'x' ], '<div class="wp-block-group x">', '</div>', [ \YayCheckout\Helpers\BlockMarkup::html( 'core/paragraph', [], '<p>Hi</p>' ) ] ) ] );`
Expected, on one line (core's PHP serializer adds no newlines between comments):
`<!-- wp:group {"className":"x"} --><div class="wp-block-group x"><!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph --></div><!-- /wp:group -->`

- [ ] **Step 3: Commit**

Stage: `includes/Helpers/BlockMarkup.php`
Message: `feat(layouts): build static core blocks with BlockMarkup`

### Task 2: The two-column default layout with a header and footer

**Files:**
- Modify: `includes/Repositories/LayoutRepository.php:460-475` (`default_markup()` and its docblock), plus new private static helpers next to `address_field_blocks()`
- Modify: `docs/architecture.md`, Layouts section: add one bullet
- Modify: `apps/admin/src/pages/tools/components/ResetCard.tsx:20` (description string)

**Interfaces:**
- Consumes: Task 1's `BlockMarkup::wrapper()` and `BlockMarkup::html()`.
- Produces: `LayoutRepository::default_markup(): string` (signature unchanged; callers `create()`, `reset()` and the setup wizard through `create()` are untouched). Private: `header_block(): array`, `checkout_block(): array`, `footer_block(): array`, each one top-level block. The class names below are what Task 3's stylesheet targets.

- [ ] **Step 1: Rebuild `default_markup()` as this tree**

Attributes are exact; the saved HTML for each static block is in the right-hand column. "dyn." means a dynamic block built with `BlockMarkup::block()`.

| Block | Attributes | Saved HTML |
|---|---|---|
| `core/group` | `tagName: header`, `className: yaycheckout-header`, `layout: { type: flex, justifyContent: space-between, flexWrap: wrap }` | `<header class="wp-block-group yaycheckout-header">` … `</header>` |
| ├ `core/site-logo` if `has_custom_logo()`, else `core/site-title` with `level: 0` | | dyn. |
| └ `core/paragraph` | `className: yaycheckout-secure-note` | `<p class="yaycheckout-secure-note">` + `esc_html__( 'Secure checkout', 'yaycheckout' )` + `</p>` |
| `yaycheckout/checkout` | | dyn. |
| └ `core/columns` | `className: yaycheckout-checkout-columns` | `<div class="wp-block-columns yaycheckout-checkout-columns">` … `</div>` |
| &nbsp;&nbsp;├ `core/column` | `width: 60%` | `<div class="wp-block-column" style="flex-basis:60%">` … `</div>` |
| &nbsp;&nbsp;│ ├ `yaycheckout/express-pay`, `yaycheckout/contact` | | dyn. |
| &nbsp;&nbsp;│ ├ `yaycheckout/billing`, `yaycheckout/shipping` | inner: `address_field_blocks( 'billing' / 'shipping' )`, as today | dyn. |
| &nbsp;&nbsp;│ └ `yaycheckout/payment`, `yaycheckout/place-order` | | dyn. |
| &nbsp;&nbsp;└ `core/column` | `width: 40%`, `className: yaycheckout-summary-column` | `<div class="wp-block-column yaycheckout-summary-column" style="flex-basis:40%">` … `</div>` |
| &nbsp;&nbsp;&nbsp;&nbsp;└ `core/group` | `className: yaycheckout-summary` | `<div class="wp-block-group yaycheckout-summary">` … `</div>` |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└ `yaycheckout/order-summary`, `yaycheckout/coupon-form` | | dyn. |
| `core/group` | `tagName: footer`, `className: yaycheckout-footer`, `layout: { type: flex, justifyContent: space-between, flexWrap: wrap }` | `<footer class="wp-block-group yaycheckout-footer">` … `</footer>` |
| ├ `core/paragraph` | | `<p>&copy; ` + `esc_html( get_bloginfo( 'name' ) )` + `</p>` |
| └ `core/paragraph` | | `<p>` + links joined by ` &middot; ` + `</p>` |

Footer links, each `<a href="esc_url( $url )">esc_html( $label )</a>`, in this order:
- `wc_get_cart_url()`, labelled 'Return to cart';
- `get_privacy_policy_url()`, labelled 'Privacy policy', only when it isn't '';
- `get_permalink( wc_terms_and_conditions_page_id() )`, labelled 'Terms and conditions', only when the page id is > 0.

Labels use the `yaycheckout` text domain.

Shipping Methods and Order Review are deliberately absent; see Review focus. Rewrite the docblock: "Block markup for a new layout: a header, the checkout in two columns (form; sticky order summary with coupon), a footer. Logo, footer links and labels are fixed when the layout is created."

- [ ] **Step 2: Update the descriptions of the default layout**

`LayoutRepository::reset()` docblock: "blank starting layout" becomes "default layout". `ResetCard.tsx:20`: the string becomes `'Replaces the selected layout\'s blocks with the default layout: a header, the checkout in two columns and a footer.'`, keeping the `yaycheckout` text domain.

`docs/architecture.md`, Layouts section, after the first bullet, add: "A new or reset layout, and the setup wizard's, starts from `LayoutRepository::default_markup()`: a header Group (logo or site title, "Secure checkout"), the Checkout block holding a two-column Columns (form left; Order Summary and Coupon Form right, sticky), and a footer Group (copyright, cart, privacy and terms links). Its logo, links and labels are fixed at creation."

- [ ] **Step 3: Verify in the editor**

Run: `composer lint -- includes/Repositories/LayoutRepository.php`
Expected: no errors.

Run: `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`
Expected: no errors.

Open: YayCheckout → Layouts → New layout, Blank, any name. In the editor that opens:
Expected: the canvas shows the header, the two columns and the footer. No block shows "This block contains unexpected or invalid content". List View shows the tree from Step 1. Then use Tools → Reset on that layout and reopen it: same result.

- [ ] **Step 4: Verify on the storefront**

Activate the layout from Step 3. With safe mode off and a product in the cart, as a guest, open the checkout page on Twenty Twenty-Five.
View source. Expected:
- one `<header class="wp-block-group yaycheckout-header` and one `<footer class="wp-block-group yaycheckout-footer`;
- one `<form name="checkout"` holding `wp-block-columns yaycheckout-checkout-columns`;
- exactly one element with `name="shipping_method[0]"` per rate (no second rate list), when the store has more than one shipping rate;
- `name="payment_method"` radios present.

Then place a Cash on delivery order. Expected: redirect to order-received; the order is listed under WooCommerce → Orders.

Switch to Storefront (`wp theme activate storefront`), repeat the order, and switch back to the previous theme.

- [ ] **Step 5: Commit**

Stage: `includes/Repositories/LayoutRepository.php`, `apps/admin/src/pages/tools/components/ResetCard.tsx`, `docs/architecture.md`
Message: `feat(layouts): seed new layouts with a header, two columns and a footer`

### Task 3: Layout styles: sticky summary, mobile order, header and footer spacing

**Files:**
- Create: `packages/blocks/src/checkout/style.css`
- Modify: `packages/blocks/src/checkout/index.ts` (add `import './style.css';`), `packages/blocks/src/checkout/block.json` (add `"style": "file:./style-index.css"`)

**Interfaces:**
- Consumes: Task 2's class names `yaycheckout-header`, `yaycheckout-footer`, `yaycheckout-secure-note`, `yaycheckout-checkout-columns`, `yaycheckout-summary-column`, `yaycheckout-summary`.
- Produces: the `style-index.css` handle for `yaycheckout/checkout`, which core loads wherever the Checkout block renders.

- [ ] **Step 1: Write `packages/blocks/src/checkout/style.css`**

Rules, at the specificity shown (keep to one class where possible, per the active simplify-block-css plan):
- `.yaycheckout-header`: `align-items: center; padding-block: 16px; margin-block-end: 32px; border-block-end: 1px solid #e0e0e0;`
- `.yaycheckout-footer`: `align-items: center; padding-block: 16px; margin-block-start: 48px; border-block-start: 1px solid #e0e0e0; font-size: 0.875em;`
- `.wp-site-blocks > .yaycheckout-page` (the template's page wrapper): `box-sizing: border-box; max-width: 1200px; margin-inline: auto; padding-inline: 24px;`. Added during implementation: with no theme header or footer, classic themes lose the container their header.php opened, and the layout ran edge to edge on Storefront. On the wrapper, not the default layout's classes, so blocks a merchant adds at the top level are contained too. It also applies to existing layouts.
- `.yaycheckout-checkout #order_review_heading, .yaycheckout-checkout #order_review`: `float: none; width: auto;`. Added during implementation: Storefront floats both right at 41% by id, which put the Coupon Form beside the summary. Scoped to Checkout, not the summary Group, so it holds wherever a merchant moves Order Summary or Order Review.
- `.yaycheckout-header, .yaycheckout-footer`: `align-items: center; padding-block: 16px;`, shared, so the two rules below keep only what differs.
- `.yaycheckout-secure-note`: `margin: 0;`
- `@media (min-width: 782px)`, `.yaycheckout-summary`: `position: sticky; top: calc(var(--wp-admin--admin-bar--height, 0px) + 24px);`
- `@media (max-width: 781px)`, `.yaycheckout-checkout-columns > .yaycheckout-summary-column`: `order: -1;`

782px is where core Columns stops stacking.

- [ ] **Step 2: Build**

Run: `pnpm build:blocks`
Expected: exits 0, and `assets/dist/blocks/checkout/style-index.css` exists and contains `.yaycheckout-summary`.

- [ ] **Step 3: Verify on the storefront**

With the Task 2 layout active, open the checkout page as a guest on Twenty Twenty-Five.
- At 1280px wide: the summary sits right of the form and stays in view while scrolling to Place Order. Logged in as the admin, its top edge stays below the admin bar.
- At 390px wide (devtools device mode): the summary comes first, then Express Pay through Place Order. Header and footer items wrap without horizontal scroll.
- Repeat both widths on Storefront, then restore the theme.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/checkout/style.css`, `packages/blocks/src/checkout/index.ts`, `packages/blocks/src/checkout/block.json`
Message: `feat(blocks): style the default layout's columns, header and footer`

### Task 4: Note what the default layout still lacks in the backlog

**Files:**
- Modify: `docs/backlog/2026-10-05-account-fields-and-checkout-form-hooks-never-render.md`

**Interfaces:** none.

- [ ] **Step 1: Update the item**

At the end of `## Today`, add: "As of 2026-10-06 the default layout (`LayoutRepository::default_markup()`) is a header, a two-column Checkout (Express Pay, Contact, Billing, Shipping, Payment, Place Order; Order Summary and Coupon Form) and a footer. It still has no account fields, order notes, login form or before/after-form hooks. It no longer uses Order Review, so `woocommerce_checkout_before_order_review`, `woocommerce_checkout_order_review` and `woocommerce_checkout_after_order_review` don't fire on it either."

Tick `- [ ] With coupons enabled, a shopper can apply a coupon on a checkout page that uses the default layout.` only after this check passes: with coupons enabled and a coupon created, enter it in the Coupon Form on the Task 2 layout. Expected: the totals show the discount without a page reload.

- [ ] **Step 2: Verify**

Run: `./scripts/run.sh backlog`
Expected: the item is listed, and the coupon box is no longer among its unticked boxes.

- [ ] **Step 3: Commit**

Stage: `docs/backlog/2026-10-05-account-fields-and-checkout-form-hooks-never-render.md`
Message: `docs(backlog): note what the new default layout still lacks`

### Final verification

Acceptance criterion 5: on Twenty Twenty-Five and on Storefront, create a fresh layout (New layout → Blank), activate it, and as a guest with a product in the cart fill the required fields, choose Cash on delivery and click Place order. Expected: the order is placed, the order-received page shows, and the order is listed under WooCommerce → Orders. Then restore the previously active layout and theme.
