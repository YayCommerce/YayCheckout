---
area: known-issues
updated: 2026-11-12
updated_ref: 164770c
covers: [tests/bootstrap.php, tests/wp-tests-config.php, scripts/free-admin-shell.patch, apps/admin/package.json, .github/workflows/, yaycheckout.php, uninstall.php, includes/Controllers/LayoutTemplateCatalogue.php]
---
# Known issues

> Update when: one of these is fixed, or a new trap costs someone time.

## Checks and environment

### Tests don't run from a worktree
`tests/wp-tests-config.php` defaults `ABSPATH` to four levels above `tests/`. `tests/bootstrap.php` requires WooCommerce from `dirname( __DIR__, 2 ) . '/woocommerce/woocommerce.php'`, which has no override. Both assume the checkout is `wp-content/plugins/yaycheckout/`.
- In a `.claude/worktrees/<slug>/` worktree (where `/yay:implement` and `/yay:fix` work), `ABSPATH` lands on the plugin root and WooCommerce is looked for in `.claude/worktrees/woocommerce/`, so `composer test` fails.
- Until the bootstrap takes the WooCommerce path from the environment (or `WP_TESTS_ABSPATH` is derived from it), run the suite from the main checkout, or set `WP_TESTS_ABSPATH` and give the worktree a `woocommerce` sibling.

### Admin `prettier --check` fails on `dev`
CI runs ESLint only for this reason (comment in `.github/workflows/js.yml`), so `pnpm --filter @yaycheckout/admin-page lint` exits 1. A likely cause is the `importOrder*` options in `apps/admin/package.json` that `@ianvs/prettier-plugin-sort-imports` 4 rejects (unverified). Check your own files with `npx --no-install prettier --check <files>` from `apps/admin`.

### Other environment traps
- **Use Node 22.** CI's comment says pnpm 11 crashes on older Node. `scripts/run.sh` switches Node through nvm.
- **A fresh worktree has no `vendor/` or `node_modules/`**, and `pnpm install` runs `composer install` (private admin-shell repo). `yayh doctor` reports both. Run `yayh run install` once per worktree.
- **`composer test` needs a MySQL database and the Local socket.** See [release.md § Tests](release.md#tests).

## Release

- **The Free admin-shell patch is out of date.** `scripts/free-admin-shell.patch` was made against admin-shell 2.8.6 (its context shows `VERSION = '2.8.6'`), but `vendor-prefixed/src/AdminShell.php` is 2.10.0. It still applies with offsets. The next `shell-update` may break it, and the Free release then aborts with "regenerate the patch".
- **A Free release must start from a committed bump.** The source-commit stamp is checked before `run.sh` writes the version. See [release.md](release.md#what-a-release-does).
- **A failed zip check leaves the zip in `release/`.** The checks run after zipping. Delete the zip.

## Looks wrong, is intentional

- **`YAYCHECKOUT_IS_DEVELOPMENT` is `true` in the committed `yaycheckout.php`.** The release flips it. A site running the branch with no `./scripts/run.sh dev` shows a blank admin page: define the constant as `false` in the site's wp-config.php and run `./scripts/run.sh build`.
- **`YayCheckoutPluginAdapter.php` is un-namespaced and unscoped on purpose**, so `class_exists( 'WooCommerce' )` sees real symbols. Don't namespace it.
- **Its `get_plugin_slug()` prefixes the license options.** Changing it loses customers' keys.
- **Two classes are named `Settings`:** `Models\Settings` (data) and `Engine\Admin\Settings` (enqueue). Check the namespace.
- **`uninstall.php` repeats option and post-type literals**, because it runs without the autoloader. Rename them in both places.
- **Archived layouts never auto-purge.** Archive is the trash with the trash timestamp removed (`Engine\LayoutTrashSync`).
- **Safe mode and Share usage data have no UI on purpose** (`apps/admin/src/pages/settings/components/GeneralTab.tsx`).
- **The themeless repick reloads once only** (`Renderer\CheckoutPage::maybe_repick`). With cookies blocked, the page keeps the theme it got and logs a warning. Don't remove the guard.
- **`wp_is_mobile()` counts tablets as mobile**, so there's no tablet device condition (`TODO(device-detection)` in `Renderer\CheckoutPage`).

## Open questions

- **`digital-product`.** `Controllers\LayoutTemplateCatalogue` lists it as a Free template, but `templates/layouts/digital-product/` holds only `preview.html` (no `markup.php` or `design.json`). Is it a placeholder, or is creating from it broken?
- **Which tests cover the Pro assignment, rules and export controllers?** `tests/Integration/Pro/` has only `ThankYouPageTest` and `LayoutImportReviewTest`.
