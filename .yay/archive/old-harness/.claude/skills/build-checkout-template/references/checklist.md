# Files and checks

## Files a template touches

**Slug already in `LayoutTemplateCatalogue::catalogue()`** (all 11 mock templates are). Below, `templates/layouts/<slug>/` means `templates/layouts/pro/<slug>/` for an entry marked `'pro' => true` (one built from Pro blocks, such as the step blocks):

| File | Change |
|---|---|
| `templates/layouts/<slug>/markup.php` | create. Copy the header docblock and the shared preamble from `classic-two-column/markup.php` |
| `templates/layouts/<slug>/design.json` | create with `scripts/design_json.sh` |
| `tests/Integration/LayoutTemplateCatalogueTest.php` | `test_a_template_without_files_is_coming_soon` names an unbuilt slug. If that's this slug, move it to one still without files |
| `docs/architecture.md` | the templates bullet (around line 46): add one sentence describing this template's structure, after the existing ones |

That's all. `is_available()` turns the template on once both files exist. The REST controller and the SPA picker already know every slug, and the picker's preview (`templates/layouts/<slug>/preview.html`, from `./scripts/run.sh previews`) already exists for every slug in the mock.

**A brand-new slug** also needs:
- an entry in `catalogue()` with slug, name and categories (one or more filter chips), plus `'pro' => true` if it uses any Pro block (`BlockRegistry::PRO_BLOCKS`, today the step blocks); its files then go in `templates/layouts/pro/<slug>/`, which Free neither lists nor ships. Ask the user where in the order it goes.
- a `preview.html`: run `./scripts/run.sh previews` once the catalogue entry exists. Without one the card shows the template's name on a plain box.

**Only if the address or summary structure changes** (rare):
- the mirrors named in the `classic-two-column/markup.php` docblock: `packages/blocks/src/shared/address-template.ts`, `address-fields.ts` and `order-summary/edit.tsx`;
- `.claude/rules/blocks.md` and `docs/conventions.md`, which name the classic file.

## Checks

On a Local site, wp-cli and PHPUnit must reach the database through the site's socket, not `localhost`; `docs/known-issues.md` covers it for PHPUnit. For wp-cli, use `php -d mysqli.default_socket="$HOME/Library/Application Support/Local/run/<run id>/mysql/mysqld.sock" $(which wp) …`. The run id is the folder whose site matches this one. Put that command in `WP` for `design_json.sh`.

1. **Lint:** `composer lint -- templates/layouts/<slug>/markup.php`
2. **Suite:** `composer test -- --filter LayoutTemplateCatalogueTest`. It checks that `publish_error()` is `''`, that `design.json` equals `to_meta()`, and that the layout places a WooCommerce Hook block for `woocommerce_before_checkout_form`, where plugins print checkout notices, for every available template. Put it where the shopper reads the notices plugins print there: at the top of the main form column, below any header, progress bar or order summary and above the first section, as the other templates do.
3. **Markup renders for each destination.** Run `LayoutTemplateCatalogue::markup( '<slug>' )` under each value of `woocommerce_ship_to_destination` (`shipping`, `billing`, `billing_only`), using `wp eval` with `update_option`. Restore the option afterwards. Each run must return non-empty markup with no PHP notice.
4. **Editor.**
   - Create a **new** layout from the library. Never paste into an existing draft: autosave writes to it.
   - Open it. No block may be flagged invalid. In the editor console, run:
     ```js
     const walk = (b) => [b, ...b.innerBlocks.flatMap(walk)];
     wp.data.select('core/block-editor').getBlocks().flatMap(walk).filter((b) => !b.isValid).map((b) => b.name)
     ```
     Expect `[]`.
   - The canvas background must match `colours.background`.
5. **Storefront.** Mint a preview token with `wp eval 'echo \YayCheckout\Helpers\PreviewToken::mint( <id>, 120 );'`, then open the checkout page with a product in the cart and `&yaycheckout_preview=<token>` (on the team Local site: `?page_id=9&add-to-cart=29`). Check:
   - the layout matches the mock at desktop width and at 375px;
   - every checkbox and radio toggles, more so with floating labels;
   - a test order with a test gateway goes through.
6. **Side by side.** Open `t<id>.html` from the extract next to the preview and list every difference. Each one is either fixed, or on the Out list with a reason.
