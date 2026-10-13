# Split Express Template Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no new spec; the Requirements below are the contract. The HTML mock is the visual reference: `/Users/onyxlai/Downloads/WooCommerce Checkout Templates_v1.1.html`, template id `03`, label "Split express".

**Status:** active
**Goal:** A merchant can create a layout from Split express, and it opens as a dark order summary on the left and the checkout form on the right.
**Spec:** none, planned from the request. The accepted [docs/specs/2026-10-10-layout-templates-design.md](docs/specs/2026-10-10-layout-templates-design.md) left templates 02–11 Coming soon; that spec stays a record and is not edited.
**Architecture:** A template is available only when `templates/layouts/<slug>/markup.php` and `design.json` both exist (`LayoutTemplateCatalogue::is_available()`). Slug `split-express` is already the second catalogue entry and is unlocked on Free, and the picker sketch already exists. Adding the two files is enough; no catalogue, REST, or SPA change. The markup is Gutenberg block comments in the shape Classic two-column already saves, not the mock's HTML.
**Touches:**
- Create: `templates/layouts/split-express/markup.php`, `templates/layouts/split-express/design.json`
- Modify: `tests/Integration/LayoutTemplateCatalogueTest.php` (`test_a_template_without_files_is_coming_soon` names `split-express` today)
- Modify: `docs/architecture.md` (the Classic two-column sentence around line 46)
- Create on Task 0: `docs/plans/2026-10-13-split-express.md` (this plan)
**Tests:** none new. `LayoutTemplateCatalogueTest` already checks every available template's markup and design. The Coming soon example moves from `split-express` to `editorial-fashion` so that assertion stays true. CLAUDE.md's test rule does not call for another test.

## Requirements

- **Goal:** In the New layout library, Split express can be selected. A layout created from it has the mock's structure, as far as the existing blocks allow, and its design tokens.
- **Out of scope:**
  - Templates other than `split-express`. They stay Coming soon.
  - New blocks or block options.
  - The three wallet buttons, shipping ETAs, and the card-number form. Those come from the gateway and WooCommerce.
  - The mock's "Add apartment…" disclosure. Address line 2 stays visible.
  - An order note and a return-to-cart block. The mock has neither.
  - `packages/blocks/src/order-summary/edit.tsx`. Split express uses the same inner tree that file already seeds: Header, then Details holding Items, Coupon Form, Breakdown, and Total.
  - Rewriting `docs/specs/2026-10-10-layout-templates-design.md`.
- **Acceptance criteria:**
  1. `GET /yaycheckout/v1/layouts/templates` lists `split-express` with `available: true` and `locked: false` on Free. `editorial-fashion` stays `available: false`.
  2. `POST /layouts` with `source: library` and `template_slug: split-express` creates a draft whose `post_content` equals `LayoutTemplateCatalogue::markup( 'split-express' )`, whose `_yaycheckout_design` equals `LayoutTemplateCatalogue::design( 'split-express' )->to_meta()`, and whose `_yaycheckout_template` is `split-express`.
  3. `design.json` sets every section of `LayoutDesign::schema()`: colours primary `#3858e9`, text `#1e1e1e`, background `#ffffff`, border `#dddddd`; typography `font_family` `""`, `base_size` `13`; inputs `boxed`, `medium`, radius `2`, background `#ffffff`, text `#1e1e1e`, border `#757575`, label `""`; `custom_css` `""`. `from_meta()` returns that file unchanged.
  4. The markup passes `LayoutMarkupReader::publish_error()` (returns `''`) and opens in the editor with no block flagged invalid.
  5. **End to end:** create a layout from Split express. The editor shows a dark left column (logo or site title, "Your order", items, coupons, total) and a white right column (express pay, contact, delivery, shipping options, payment, the same-address checkbox, Pay). Editorial fashion still shows Coming soon.
- **Decided with the user:**
  - Base the markup on the HTML mock for template 03, written as `markup.php`, not pasted HTML.
  - Finish both files so the template is usable, not markup alone.
  - Write this plan in the repo's task / step / branch / commit shape.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit `assets/dist/`. This change adds no generated file.
- `markup.php` is the markup as the block editor saves it. PHP appears only for translated strings (`yaycheckout` text domain), the logo-or-title choice, the cart link, and conditional policy links. A hand edit that differs by one class or style makes the editor flag the block as invalid.
- Branch from `dev/edd` as `trace/feat/split-express`. Conventional Commits, `type(scope): summary`, ending with `Co-Authored-By: Claude Grok 4.7 <noreply@anthropic.com>`. Stage files by name. Commit only at the commit steps below. Do not push unless asked.
- `composer lint -- <files>` for every changed PHP file. `composer test -- --filter LayoutTemplateCatalogueTest` is the suite check.

## Review focus

- **Hand-written block comments.** A class or style that does not match the comment JSON makes the editor show "This block contains unexpected or invalid content". Task 1 Step 4 checks a new draft in the editor.
- **Coming soon test still names `split-express`.** After the files exist, `test_a_template_without_files_is_coming_soon` fails unless the example slug moves. Task 1 Step 5.
- **Dark panel vs page tokens.** `design.json` background is the white page (`#ffffff`). The `#1e1e1e` panel is the left column's own background. A layout whose canvas is grey has the tokens on the wrong side. Task 1 Step 6.
- **Address field keys.** Reordering fields (country first) must keep Classic's `fieldKey` values and required flags, or checkout drops a required field. Task 1 Step 3 lists the keys.

---

### Task 0: Branch and save the plan

**Files:**
- Create: `docs/plans/2026-10-13-split-express.md`

**Interfaces:**
- Consumes: nothing
- Produces: the branch `trace/feat/split-express` and this plan on disk

- [ ] **Step 1: Branch**

Run: `git switch dev/edd && git pull && git switch -c trace/feat/split-express`
Expected: `Switched to a new branch 'trace/feat/split-express'`.

- [ ] **Step 2: Save the plan**

Copy this plan, from the `# Split Express Template Implementation Plan` heading down, to `docs/plans/2026-10-13-split-express.md`. Leave the Cursor frontmatter out.

- [ ] **Step 3: Commit**

Stage: `docs/plans/2026-10-13-split-express.md`
Message: `docs(plans): plan the Split express template`

### Task 1: Add the Split express template files

**Files:**
- Create: `templates/layouts/split-express/markup.php`
- Create: `templates/layouts/split-express/design.json`
- Modify: `tests/Integration/LayoutTemplateCatalogueTest.php:29-35`
- Modify: `docs/architecture.md` (the Classic two-column sentence, about line 46)

**Interfaces:**
- Consumes: `LayoutTemplateCatalogue::markup( string $slug ): string`, `LayoutTemplateCatalogue::design( string $slug ): LayoutDesign`, `LayoutTemplateCatalogue::is_available( string $slug ): bool`. No new signature.
- Produces: nothing new. `split-express` becomes available because both files exist.

- [ ] **Step 1: Write `design.json`**

Same keys and order as `templates/layouts/classic-two-column/design.json`, with the values in acceptance criterion 3. Tabs for indentation, matching that file.

- [ ] **Step 2: Write `markup.php`**

File header comment names the slug and says the look is `design.json` beside it, as Classic's header does. Copy Classic's PHP preamble for the terms sentence, the privacy link, and the refund link (`wc_terms_and_conditions_page_id()`, `get_privacy_policy_url()`, `wc_get_page_id( 'refund_returns' )` only when published).

One full-width group (`minHeight` `100vh`) holding `yaycheckout/checkout`, then `core/columns`. No top header bar and no 1120px frame.

Left column, `width` `520px`, background `#1e1e1e`, text `#f0f0f0`, padding `32px 40px`, inner group `position` sticky `top` `0`:

- Logo or site title, the same `has_custom_logo()` branch as Classic, with light text.
- Heading "Your order" (level 2, 20px, weight 400, color `#ffffff`) and a paragraph link "Edit cart" to `wc_get_cart_url()`.
- `yaycheckout/order-summary`. The heading "Your order" sits inside `order-summary-toggle`. No item-count paragraph.
- `order-summary-items`: `showLabels` false, `quantityDisplay` `badge`, `badgeBackgroundColor` `#ffffff`, `badgeTextColor` `#1e1e1e`.
- `coupon-form`: `disclosure` `collapsible`, `disclosureText` "Add coupons", placeholder "Enter code". `coupon-button` text "Apply", transparent background, text `#ffffff`, border `#757575`.
- `order-summary-breakdown` and `order-summary-total`: light text, top border `#2f2f2f`. Total has `showCurrencyCode` true.
- Paragraph "Encrypted, secure payment. 30-day free returns." in `#949494`.

Right column, white. Inner group `contentSize` `600px`, padding `32px 40px 80px`, block gap `32px`, in this order:

1. `yaycheckout/express-pay`
2. Heading "Contact information" (20px, weight 400), `yaycheckout/login` with `text` "Have an account? Log in" and `linkText` "Log in", `yaycheckout/email` with `description` "You are currently checking out as a guest."
3. `yaycheckout/shipping` `title` "Delivery". Field keys and required flags, country first: `shipping_country` (type `country`, required), `shipping_first_name` and `shipping_last_name` (required, two columns), `shipping_company`, `shipping_address_1` (required), `shipping_address_2`, then `shipping_city` 50% / `shipping_state` type `state` 25% / `shipping_postcode` 25% (city, state, postcode required), `shipping_phone` type `tel`.
4. `yaycheckout/shipping-methods` `label` "Shipping options", `layout` `box`, and the same `yaycheckout` colour object Classic uses on that block.
5. `yaycheckout/payment` `label` "Payment options", `description` "All transactions are secure and encrypted.", `layout` `box`, and Classic's payment colour object.
6. `yaycheckout/different-address` `sameLabel` "Use shipping address as billing address", then `yaycheckout/billing` `title` "Billing address" with the `billing_*` keys in the same order and required flags.
7. The terms paragraph only when `$yaycheckout_terms_sentence` is not empty. `yaycheckout/place-order` `buttonText` "Pay" plus the `cart_total` live span Classic uses (`data-prefix` ` · `). Then the policy-links paragraph only when `$yaycheckout_policy_links` is not empty.

Every visible string goes through `esc_html_e()` or the preamble's `esc_html__()`. Pair each block comment's JSON with the HTML classes and inline styles the editor would save, copying that pairing from Classic for any block Classic already contains.

- [ ] **Step 3: Lint the markup**

Run: `composer lint -- templates/layouts/split-express/markup.php tests/Integration/LayoutTemplateCatalogueTest.php`
Expected: no errors.

- [ ] **Step 4: Point the Coming soon example at another slug**

In `test_a_template_without_files_is_coming_soon`, replace both `split-express` uses with `editorial-fashion`. The exception message stays `This template is coming soon.`

- [ ] **Step 5: Run the catalogue test**

Run: `composer test -- --filter LayoutTemplateCatalogueTest`
Expected: PASS. Split express is in the available loop (publish error `''`, design survives `from_meta()`). `editorial-fashion` is still unavailable.

- [ ] **Step 6: Check the editor**

Create a new draft from the library with Split express. Do not edit an existing layout.
Expected: no block shows "This block contains unexpected or invalid content". The canvas is white (`#ffffff`) with a dark left column and the form on the right, in the order in Step 2. In the library, Editorial fashion still shows Coming soon and cannot be selected.

- [ ] **Step 7: Update the architecture sentence**

After the Classic two-column description in `docs/architecture.md`, add one sentence: Split express is a full-height two-column Checkout with no header bar; the left column is a 520px `#1e1e1e` panel (logo or site title, "Your order", Order Summary, the secure-payment line) and the right column is the white form (Express Pay, contact, Delivery, Shipping methods, Payment, Different address, Billing, the terms sentence, Place Order, policy links).

- [ ] **Step 8: Commit**

Stage: `templates/layouts/split-express/markup.php`, `templates/layouts/split-express/design.json`, `tests/Integration/LayoutTemplateCatalogueTest.php`, `docs/architecture.md`, `docs/plans/2026-10-13-split-express.md`
Message: `feat(templates): add the Split express layout template`

Set this plan's Status to `done` with the date only in the PR that finishes it, not in this commit.
