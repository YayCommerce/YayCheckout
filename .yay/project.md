<!--
Purpose: what a session needs to know about YayCheckout that reading the code would not tell
it, or would cost ten reads to learn.
Update when: an invariant, a gotcha, the edition policy or the public-contract policy
changes. Structure is scout's; sections 4 and 6 are yours.
-->

# YayCheckout

<!-- yay:generated:identity -->
## 1. Identity and boundary

A WooCommerce checkout builder. A merchant composes checkout layouts (one `yay_checkout_layout` post each) from dynamic blocks in core's block editor, embedded in the plugin's admin SPA. On the storefront the active layout replaces WooCommerce's classic checkout form while still printing WooCommerce's own fields, hooks and markup. It is not a payment, shipping or cart plugin, and it does not replace order-received or order-pay. Pro's thank-you page is the exception: it takes over order-received.

**Must never:** touch stock WooCommerce checkout while no layout resolves. A storefront hook stands down when `CheckoutPage::get_instance()->layout()` is null; the one exception is the opt-in `advanced.force_enqueue_checkout_scripts` setting (`Renderer\CheckoutScripts`).

- **Slug:** `yaycheckout` (Pro zips as `yaycheckout-pro`) · **main file:** `yaycheckout.php` · **text domain:** `yaycheckout`
- **Runs on:** PHP 7.4+, WP 6.5+, WC 10.0+
- **Editions:** `directory`, both from `dev`; the wp.org slug is not decided yet (`harness.yml` needs_confirmation)
<!-- /yay:generated:identity -->

<!-- yay:generated:contract -->
## 2. Public contract

The contract is `.yay/api.yml`, not this file. It holds 0 public, 0 internal and 13 unreviewed hooks, 25 unreviewed REST routes (`yaycheckout/v1`) and 6 options.

Read it before any change that renames, removes or re-signatures a hook, filter, REST route, option or table. `verify` blocks on removing a `public` entry and warns on `unreviewed`. An `unreviewed` count that is not falling is debt.

**Policy:** not set. Proposed: Pro consumes Free's filters (`yaycheckout_editor_views`, `yaycheckout_built_blocks`, `yaycheckout_live_values_source`, …) from the same codebase, so those are `internal`. A filter documented for site owners (`yaycheckout_themeless_checkout`) or hooked by another Yay plugin (`yaycheckout_plugin_blocks`, YayBoost) is `public`.
<!-- /yay:generated:contract -->

<!-- yay:generated:map -->
## 3. Bootstrap and map

`yaycheckout.php` defines constants (each `if ( ! defined )`, so `wp-config.php` overrides any), requires `vendor/autoload.php` and the adapter, and registers a `YayCheckout\` loader onto `includes/` that requires a file only if it exists. That is how a Free tree without `includes/Pro/`, or a zip without `RegisterDev.php`, still boots. On `plugins_loaded` at 5 admin-shell boots; at 10 `plugin_init()` checks WooCommerce and builds the `YayCheckout` singleton. Its constructor runs `boot_pro()` first, so `Plan::is_pro()` is settled before anything reads it.

| Path | Holds | Read it when |
|---|---|---|
| `includes/YayCheckout.php` | hand wiring of every component; the one `YayCheckout\Pro` reference | adding a component, or touching the edition seam |
| `includes/Pro/ProServiceProvider.php` | every hook and filter Pro attaches through | adding a Pro feature |
| `includes/Repositories/LayoutRepository.php` | every layout write and the active set's invariants | changing how a layout is saved, activated or archived |
| `includes/Services/LayoutMarkupReader.php` | `publish_error()`, slots, fields, extra-field keys, text sanitising | a block that affects publishing, fragments or text output |
| `includes/Renderer/CheckoutPage.php` | storefront takeover and which layout resolves | anything on the storefront |
| `includes/Blocks/BlockRegistry.php` | block registration, `ORDER`, `PRO_BLOCKS`, editor data globals | adding a block |
| `scripts/run.sh` (`cmd_release`) | the release copy list, strips, staging and artifact checks | changing what ships |
| `docs/architecture.md` | the long-form map of every layer, writer and storefront path | a change that crosses layers |
<!-- /yay:generated:map -->

## 4. Invariants

- **Free names Pro once.** The only non-comment `YayCheckout\Pro` reference outside `includes/Pro/` is `includes/YayCheckout.php:63`, inside `boot_pro()`. `scripts/check-pro-boundary.sh` (in `composer lint` and CI) fails zero or two such references, and fails a move to another file. Break it and the Free zip, which deletes `includes/Pro/`, fatals.
- **Pro is withheld server-side.** `LayoutTemplateCatalogue::assert_available()` checks `Plan::is_pro()` (`LayoutTemplateCatalogue.php:71`), and Free registers no Pro REST routes. The SPA's `isPro` and the upsell components ship to Free. Put a real endpoint or real data behind them and Free hands out Pro.
- **Product PHP runs on PHP 7.4.** CI's `php.yml` runs `php -l` on 7.4. phpcs checks no version and the dev tooling runs on PHP 8, so 8.x syntax passes locally and fatals on customers' 7.4 sites.
- **No SQL transactions for WordPress writes.** `BaseRestController` says why: a ROLLBACK would leave a persistent object cache holding the rolled-back write. Nothing calls `exec_write()`, and there are no custom tables. Use `exec_write_wp()`, and order the writes so a partial failure leaves a safe state.
- **Storage keys are class constants** (`Models\Layout`, `Models\Settings`, `CheckoutExtraFields`, `LayoutPin`, `CompatibilityCatalogue`). A mistyped string reads nothing and raises nothing; a mistyped constant is fatal. `uninstall.php` is the one exception, because it runs without the autoloader: it repeats `yaycheckout_settings`, `delete_data_on_uninstall` and `yay_checkout_layout` as literals (`uninstall.php:7`).
- **A TypeScript copy of PHP names its owner.** The copy carries a `Mirrors …` comment (62 files under `apps/admin/src` and `packages/blocks/src`), and both sides change in one commit. The PHP side is the owner. Change one side alone and the editor and the server disagree about what is publishable.
- **Blocks are dynamic.** `save()` returns null, or saves only inner blocks through `saveNestedBlocks()` (`packages/blocks/src/shared/register-dynamic-block.tsx`). A renamed or added attribute therefore needs a default in `render.php`, not a deprecation: stored layouts keep their old markup.
- **The release ships by allowlist.** `cmd_release` copies a fixed list. A new runtime file outside the list is silently absent from the zip, and its exit code is still 0.

## 5. Edition boundary

<!-- yay:generated:edition -->
**Mechanism:** `directory`, both editions from `dev`. `--plan=free` deletes the Pro paths in `harness.yml` (`editions.pro.markers`) from the staging copy, stages `editions/YayCheckoutPluginAdapter.free.php` over the adapter, rewrites the header and applies `scripts/free-admin-shell.patch`. `assert_staged_plan()` and the zip checks in `cmd_release` assert the result. `scripts/check-pro-boundary.sh` guards the seam in source.
<!-- /yay:generated:edition -->

**Policy:** not set. Proposed for a human to accept: a Pro feature's code lives only under `includes/Pro/`, `packages/blocks/src/pro/` or `templates/layouts/pro/`, never behind a runtime check in Free, because a disabled feature in the Free zip fails wp.org Guideline 5. Free may show a teaser (badge, locked mock, preview image) but not the feature.

## 6. Gotchas and history

- **A Free release off a dirty tree fails; Pro only warns** (`run.sh:401`). The zip's `readme.txt` names `HEAD`'s SHA over `@SOURCE_COMMIT@`, and a dirty tree would name a commit the zip doesn't match. The gate runs *before* `update_version` (`run.sh:411`), so passing a version on the command line dirties the tree after the check. For a Free release, commit the bump first and cut with `--no-prompt`.
- **The Source code link is not public yet.** `readme.txt` says the source "is public at github.com/YayCommerce/YayCheckout", but on 2026-11-09 the repo was private and anonymous requests got a 404. wp.org guideline 4 needs that link to work for the Free zip. Re-check before submitting.
- **`vendor/composer/installed.php` must ship; `installed.json` must not** (`run.sh:528-535`). `InstalledVersions::getInstalled()` ends in an unguarded `require` of `installed.php`, and this copy classmaps that class for any plugin. `dump-autoload` rewrites the maps but not `installed.json`, which would describe the dev tree's packages.
- **The Free header edit uses `perl -pi`, not `sed`** (`run.sh:520`). A literal newline in a `sed` replacement isn't portable between GNU and BSD. `run.sh` must also run under macOS's bash 3.2.
- **`YAYCHECKOUT_IS_DEVELOPMENT` defaults to true** (`yaycheckout.php:92`). The admin loads Vite from :3040 and is blank without `./scripts/run.sh dev`. A zip has no `RegisterDev.php`, so a site whose constant is still true registers no SPA at all.
- **The required-fields filter is not gated on the checkout page** (`CheckoutRequiredFields.php:35`, priority 9999). The checkout POST validates at `wp_loaded`, before `is_page()` works. It therefore applies to any classic checkout form on the site.
- **A `<form>` nested in the checkout form becomes a `<div>`** (`Renderer\CheckoutNestedForms`). Browsers drop a nested form's opening tag but honour its `</form>`, which would end the checkout form early and leave the later fields out of the order.
- **An extra-field key may not start with an underscore** (`LayoutMarkupReader::EXTRA_FIELD_KEY_PATTERN`). A leading underscore reaches WooCommerce's internal order keys, where `WC_Data::update_meta_data()` calls the matching setter.
- **Lockfiles aren't tracked** (`.gitignore:4`, `:8`). Two machines can resolve different versions. Compare `composer show` and `pnpm list` before debugging a failure only one teammate sees.
- **`shell-update` rewrites tracked files.** `yaycommerce-update` runs `composer update` and `composer require --dev` for php-scoper and thecodingmachine/safe, and regenerates `scoper.inc.php` from admin-shell's template. A lasting edit to `scoper.inc.php` therefore belongs in admin-shell. `dev` and `release` no longer do this, since #257 and #263.

<!-- yay:generated:workflows -->
## 7. Workflows

Commands live in `harness.yml` (`commands.*`), and this file does not duplicate them. lint, lint_js, typecheck, test, build, dev, release and shell_update all exist.

Expected failures today, each recorded in `docs/known-issues.md`:
- the SPA lint (eslint warnings, prettier fails on three files);
- `composer test` without a local `phpunit.xml` pointing at a test database.
<!-- /yay:generated:workflows -->

---
<!-- yay:generated:footer -->
Derived by `/yay:harness-scout` from yaycheckout at 062f7926 on 2026-11-09.
`/yay:harness-scout --check` re-verifies; a claim here that the code no longer supports is a
bug in this file, not in the code.
<!-- /yay:generated:footer -->
