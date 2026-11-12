---
area: release
updated: 2026-11-12
updated_ref: 44fdc3b
covers: [scripts/, scoper.inc.php, composer.json, package.json, pnpm-workspace.yaml, .nvmrc, phpcs.xml, phpunit.xml.dist, tests/, .github/workflows/, readme.txt, editions/]
---
# Build, release, tests and CI

> Update when: `scripts/run.sh`, the zip checks, the composer or pnpm scripts, the test bootstrap or a workflow changes.

## Toolchain

- **Node and pnpm.** Node 22 (`.nvmrc`, `engines.node >=22`) with `pnpm@11.15.1`. The workspace is `apps/admin` + `packages/blocks`. `scripts/run.sh` finds pnpm through corepack, or the newest Node under `~/.nvm`.
- **`pnpm install` also runs `composer install`** (the root `postinstall`), which needs access to the private admin-shell VCS repo. CI installs with `--filter @yaycheckout/admin-page --ignore-scripts` instead.
- **`vendor-prefixed/` is tracked.** It holds the scoped AdminShell (prefix `YayCheckoutScoped`, see `scoper.inc.php`). Update it only with `./scripts/run.sh shell-update`, which also regenerates `scoper.inc.php` (comment in `scripts/run.sh`), then commit both.
- **No lockfiles are tracked.** `composer.lock` and `pnpm-lock.yaml` are gitignored, so versions can differ between machines.

## `scripts/run.sh`

| Command | Does | Trap |
|---|---|---|
| `dev` | Vite on :3040 + blocks watcher | needs `YAYCHECKOUT_IS_DEVELOPMENT` true (the default) |
| `build` | admin `tsc --noEmit && vite build` → `assets/dist/admin`, then blocks `wp-scripts build` → `assets/dist/blocks` | blocks must build, or no block registers |
| `release <v> --plan=free\|pro [--no-prompt]` | stages a copy, strips, zips, verifies → `release/yaycheckout-<v>.zip` (Free) or `release/yaycheckout-pro-<v>.zip` | see below |
| `shell-update` | `yaycommerce-update`, refreshes `vendor-prefixed/` | review and commit |
| `bump <v>`, `info`, `previews`, `thank-you-previews`, `clean` | small helpers | `clean` prompts with `read` (needs a TTY) |

## What a release does

1. It writes the version (header `Version:`, `YAYCHECKOUT_VERSION`, `readme.txt` `Stable tag:`) and the tested-up-to values **into the working tree**. Check `git status` afterwards.
2. Free needs a clean tree. The readme's `@SOURCE_COMMIT@` is stamped with `HEAD`, and wordpress.org fetches the source at that commit. That check runs before the bump, so **cut a Free release from an already-committed bump**.
3. It copies only the shipped files into a staging copy. There it:
   - rescopes with `vendor/bin/yaycommerce-prerelease`;
   - runs `composer dump-autoload --no-dev --classmap-authoritative`;
   - deletes `vendor/composer/installed.json` but keeps `vendor/composer/installed.php`, which `InstalledVersions` requires unguarded;
   - flips `YAYCHECKOUT_IS_DEVELOPMENT` to false and deletes `RegisterDev.php`;
   - rewrites AdminShell's `'yaycommerce'` text domain to `'yaycheckout'`.
4. **For `--plan=free`** it:
   - deletes `includes/Pro/`, `assets/dist/blocks/pro/` and `templates/layouts/pro/` (keeping each `preview.html`);
   - applies `scripts/free-admin-shell.patch` and strips AdminShell's licensing (EDD updater) and Recommended Plugins files;
   - swaps in `editions/YayCheckoutPluginAdapter.free.php`;
   - renames the plugin header and adds `Requires Plugins: woocommerce`.
5. It checks the finished zip.
   - **Both editions:** no JS source (`apps`, `packages`, `package.json`…), no `node_modules`, the readme links `https://github.com/YayCommerce/YayCheckout`, and the flag is false.
   - **Free also:** no Pro entries except `preview.html`, and nothing naming `EDD_SL`, `LicensesPage` and the like.
   - **Pro also:** it must contain the Pro paths and the EDD updater.
   - A failed check exits 1 **after** the zip exists. Delete the half-built zip.

## Composer scripts

| Script | Runs | Note |
|---|---|---|
| `composer lint` | `scripts/check-pro-boundary.sh`, then `phpcs` (`phpcs.xml`: WordPress ruleset, text domain `yaycheckout`, prefixes `yaycheckout`/`YAYCHECKOUT`/`YayCheckout`) | lint fails first on a boundary violation |
| `composer lint:fix` | `phpcbf` | |
| `composer test` | `phpunit` | see Tests |

There's no PHP typecheck. The only typecheck is the admin `tsc --noEmit`, inside its build.

## Tests

- **Integration only.** The suite is `tests/Integration/`. Helpers are in `tests/Support/` (`LayoutFactory`, fake gateways) and fixtures in `tests/fixtures/`. Pro tests sit in `tests/Integration/Pro/` and skip when `! Plan::is_pro()`. `phpunit.xml.dist` sets `failOnWarning`.
- **`tests/bootstrap.php`:**
  - loads `wp-phpunit`;
  - requires WooCommerce from the **sibling** `../woocommerce/woocommerce.php` and the plugin as mu-plugins;
  - reinstalls WooCommerce at `setup_theme`.
- **It needs a real MySQL database** (`yaycheckout_tests` by default), and the library drops and recreates its tables. Never point `WP_TESTS_DB_NAME` at a site database.
  - The overrides are `WP_TESTS_DB_NAME`, `_USER`, `_PASSWORD`, `_HOST` and `WP_TESTS_ABSPATH` (in `tests/wp-tests-config.php`).
  - On Local, `WP_TESTS_DB_HOST` needs the site's socket (`localhost:/…/mysqld.sock`).
- **Run one test:** `vendor/bin/phpunit --filter <TestName>`.
- **Build first.** Block render tests need `./scripts/run.sh build`, because unbuilt blocks never register.
- **Worktrees can't run the suite as it stands.** The bootstrap assumes the checkout sits at `wp-content/plugins/yaycheckout/`; see [known-issues.md](known-issues.md#tests-dont-run-from-a-worktree).

## CI

- **`.github/workflows/php.yml`** runs on pushes and PRs to `dev` and `hotfix/**`, path-filtered. It runs `php -l` on PHP 7.4 and `scripts/check-pro-boundary.sh`.
- **`.github/workflows/js.yml`** runs the admin `tsc --noEmit` and `eslint .`, without prettier.
- **CI never runs phpcs, phpunit, the blocks build or a release.** A broken Free zip only shows up locally, at release time.

## readme.txt

- Changelog headings are `= Mon D, YYYY - Version X.Y.Z =`, with labelled hyphen bullets (`- Added: …`). Pro-only entries end with ` (Pro)`.
- `Tested up to:` is WordPress. WooCommerce's tested-up-to is `WC tested up to:` in the plugin header.
- `== Source code ==` must keep the GitHub URL and `@SOURCE_COMMIT@`, and the release checks both. `== External services ==` lists every outbound request, so update it when you add one.
