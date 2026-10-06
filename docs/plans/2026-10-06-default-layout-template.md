# Default Layout as a PHP Template Implementation Plan

> **For agentic workers:** the approach below was approved in plan mode on 2026-10-06; execute its sections in order.

**Status:** active
**Goal:** Seed new layouts from `templates/default-layout.php`, styled by block settings instead of plugin classes and CSS.
**Spec:** none, planned from the request
**Tests:** none; verified by hand (see Verification).

## Context

The default layout (`LayoutRepository::default_markup()`, on `trace/feat/default-checkout-layout`) is built in PHP through `Helpers\BlockMarkup`, with hand-written saved HTML for each core block. Its look comes from custom classes (`yaycheckout-header`, `-footer`, `-summary`, `-summary-column`, `-checkout-columns`, `-secure-note`) read by `packages/blocks/src/checkout/style.css`. The user wants two things:

1. The default layout stored as a PHP template file that prints block markup, loaded instead of being built.
2. A clean template: no custom classes or CSS where a Gutenberg block setting does the job.

Decided with the user:
- Sticky summary: use core Group's Position → Sticky only. It sticks on themes that enable it (block themes such as Twenty Twenty-Five), and is static on classic themes such as Storefront. No CSS fallback.
- Mobile order: drop the summary-first rule. Columns stack in source order (form, then summary).

Work continues on `trace/feat/default-checkout-layout` as a new plan, `docs/plans/2026-10-06-default-layout-template.md` (copy of this file in yayplan format, Status `active`), because the previous plan is `done`.

## Approach

### 1. Template file: `templates/default-layout.php` (new)

- It prints the whole layout as block markup, the way a theme's block pattern file does. It ships with releases already: `scripts/run.sh:309` copies `templates/`, and `phpcs.xml` lints `templates/`.
- Author the markup in the block editor, then paste it from the Code editor, so every core block's saved HTML is exactly what `save()` prints. Then swap the dynamic parts for PHP:
  - logo or title: `<?php if ( has_custom_logo() ) : ?><!-- wp:site-logo /--><?php else : ?><!-- wp:site-title {"level":0} /--><?php endif; ?>`;
  - labels: `esc_html_e( '…', 'yaycheckout' )`;
  - copyright: `esc_html( get_bloginfo( 'name' ) )`;
  - footer links: cart (`wc_get_cart_url()`), plus privacy (`get_privacy_policy_url()`) and terms (`wc_terms_and_conditions_page_id() > 0` → `wc_get_page_permalink( 'terms' )`) when set, each through `esc_url()`;
  - address fields: all 14 field blocks written out, each label as `"label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>"` (the Field block has no translated fallback; an empty label shows the key).
- No helper functions, loops or variables: the file stays plain editor markup with inline PHP, so an update is a paste from the Code editor plus re-adding the PHP. The repetition is deliberate.
- Header comment: what it seeds, that the dynamic values are fixed at creation, and that its field list is a subset owned by `packages/blocks/src/shared/address-fields.ts`.

Block tree, same as today, with custom classes replaced by settings:

| Block | Settings (no `className`) |
|---|---|
| Group (outer) | `layout: { type: constrained, contentSize: 1200px }`, `style.spacing.padding` left/right 24px. Replaces the `.wp-site-blocks > .yaycheckout-page` container. |
| ├ Group `tagName: header` | layout flex, `justifyContent: space-between`, `verticalAlignment: center`, `flexWrap: wrap`; `style.spacing.padding` top/bottom 16px; `style.spacing.margin.bottom` 32px; `style.border.bottom` 1px solid #e0e0e0 |
| │ ├ Site Logo / Site Title | as above |
| │ └ Paragraph "Secure checkout" | `style.spacing.margin` top/bottom 0 |
| ├ Checkout | |
| │ └ Columns → Column 60% (Express Pay, Contact, Billing, Shipping, Payment, Place Order); Column 40% → Group `style.position: { type: sticky, top: 0px }` (Order Summary, Coupon Form) | |
| └ Group `tagName: footer` | flex, space-between, center, wrap; padding top/bottom 16px; `style.spacing.margin.top` 48px; `style.border.top` 1px solid #e0e0e0; `fontSize: small` |
| &nbsp;&nbsp;└ Paragraph "© site", Paragraph links | |

Core's sticky offset already accounts for the admin bar (`top: calc(0px + var(--wp-admin--admin-bar--position-offset, 0px))`), so there's no custom offset.

### 2. `includes/Repositories/LayoutRepository.php`

- `default_markup(): string` keeps its signature and callers (`create()`, `reset()`, setup wizard). Its body becomes `ob_start(); include YAYCHECKOUT_PLUGIN_DIR . 'templates/default-layout.php'; return (string) ob_get_clean();`.
- Delete `header_block()`, `checkout_block()`, `footer_block()`, `row_group()`, `column()` and `address_field_blocks()`; the field list moves into the template. Drop `use YayCheckout\Helpers\BlockMarkup;`.

### 3. Delete `includes/Helpers/BlockMarkup.php`

Its only consumer was `LayoutRepository` (`git grep BlockMarkup` finds nothing else outside `docs/plans/`).

### 4. `packages/blocks/src/checkout/style.css`

- Keep only the Storefront compatibility rule `.yaycheckout-checkout #order_review_heading, .yaycheckout-checkout #order_review { float: none; width: auto; }`. It targets WooCommerce ids that blocks print, not template classes, and no block setting can undo a theme's float.
- Delete the page-wrapper container, header/footer, secure-note, sticky and mobile-order rules, and the marker comment naming `default_markup()`.
- `block.json` `style` and the `index.ts` import stay.

### 5. Docs and owner markers

- `packages/blocks/src/shared/address-fields.ts:11-12`: point the marker at `templates/default-layout.php`.
- `docs/conventions.md:23`: replace `LayoutRepository::address_field_blocks()` with `templates/default-layout.php`.
- `docs/architecture.md`, Layouts bullet:
  - The default comes from `templates/default-layout.php` through `default_markup()`.
  - It's an outer constrained Group (1200px) holding header, Checkout and footer.
  - The summary is sticky only on themes that enable sticky positioning.
  - It no longer reorders on mobile.
- Leave the ticked line in `docs/backlog/2026-10-05-rule-copies-between-php-and-javascript-name-no-owner.md:35` alone: it's a record.

## Critical files

- `templates/default-layout.php` (new)
- `includes/Repositories/LayoutRepository.php` (`default_markup()` at ~460, helpers to 650)
- `includes/Helpers/BlockMarkup.php` (delete)
- `packages/blocks/src/checkout/style.css`
- `packages/blocks/src/shared/address-fields.ts`, `docs/conventions.md`, `docs/architecture.md`

Reused: core `has_custom_logo()`, `wc_get_page_permalink()`, the `templates/` include pattern from `CheckoutPage::page_template()`.

## Constraints

- PHP 7.4 (CLAUDE.md hard rule 4).
- Never hand-edit `assets/dist/`; rebuild with `pnpm build:blocks`.
- Tests: none. The user declined a default-markup test last plan; verify by hand.
- Commits: Conventional Commits with the `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>` trailer, staged by name.

## Verification

1. `composer lint -- templates/default-layout.php includes/Repositories/LayoutRepository.php`: no errors (`templates/` is in phpcs's file list).
2. `source ~/.nvm/nvm.sh && nvm use && pnpm build:blocks`: compiles. `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`: exit 0.
3. Check the template's output with wp-cli:
   - Run `wp eval 'echo \YayCheckout\Services\LayoutMarkupReader::publish_error( \YayCheckout\Repositories\LayoutRepository::default_markup() );'`. Expected: empty.
   - Run `parse_blocks()` on the output. Expected top-level blockNames: `core/group` only (whitespace-only `null` entries are fine).
   - `git grep -n "yaycheckout-\(header\|footer\|summary\|checkout-columns\|secure-note\)"`: no matches outside `docs/plans/`.
4. Editor: create a layout (Layouts → New layout → Blank) and open it at `post.php?post=<id>&action=edit`.
   - In the console, walk `wp.data.select('core/block-editor').getBlocks()`. Expected: every block `isValid`, and no "unexpected or invalid content" warning.
   - Repeat after Tools → Reset.
5. Storefront render, using a preview link so the active set isn't touched (`PreviewToken::mint( <id>, 15 )` + `PreviewToken::checkout_url()`), with a product in the cart:
   - On **Twenty Twenty-Five** at 1920px:
     - the outer Group is 1200px wide and centered;
     - the header has its bottom border, the footer its top border;
     - the summary Group computes `position: sticky` and stays in view while scrolling.
   - In a 390px iframe:
     - columns stack form-then-summary;
     - no horizontal scroll.
   - On **Storefront** (`wp theme activate storefront`, then switch back):
     - same container and borders;
     - the summary is static;
     - `#order_review` computes `float: none`.
6. End to end, on both themes: activate a fresh default layout, then place a guest Cash on delivery order. Expected: redirect to order-received. Restore the previously active layouts and theme afterwards.
7. `/yayplancheck docs/plans/2026-10-06-default-layout-template.md`, with `/yaytidy` first.
