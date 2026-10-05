# Known issues

**Purpose:** the checks that fail today and the gotchas that bite, each reproduced or traced to the code that causes it, with its cause, a workaround and the condition for deleting it. A check listed here was already failing on `dev/edd`, so judge your change by not adding to it ([CLAUDE.md](../CLAUDE.md)).

**Update when:** an issue is fixed (delete its entry), a new one is reproduced (add it with all four fields), or a symptom changes.

## pnpm crashes on Node older than 22

- **Symptom:** every pnpm command dies with `TypeError [ERR_VM_DYNAMIC_IMPORT_CALLBACK_MISSING]: A dynamic import callback was not specified`.
- **Cause:** `package.json` pins pnpm 11 (`packageManager`), which needs Node 22 or later. An nvm shell can still default to an older Node.
- **Workaround:** run `nvm use` in the repo root, which reads `.nvmrc`, before any pnpm command.
- **Remove when:** every teammate's shell defaults to Node 22 or later.

## A fresh clone's first install fails on `vendor-prefixed/src`

- **Symptom:** on a fresh clone, `composer install`, and so `pnpm install` through the root `postinstall`, fails with `Could not scan for classes inside "vendor-prefixed/src" which does not appear to be a file nor a folder`. Composer downloads the packages but never writes `vendor/autoload.php`, which `yaycheckout.php` requires, so WordPress fatals when it loads the plugin.
- **Cause:** `composer.json` classmaps `vendor-prefixed/src`, a gitignored directory that only the [admin-shell sync](architecture.md#admin-shell-sync-and-scoping) fills. Composer stops on a classmap path that doesn't exist.
- **Workaround:** on a fresh clone, run `./scripts/run.sh dev` first; it creates the directory before it installs. Or run `mkdir -p vendor-prefixed/src`, then `composer install` and `composer shell-update`.
- **Remove when:** `composer install` succeeds on a fresh clone.

## pnpm installs on its own, and the install runs `composer install`

- **Symptom:** a command that should only run a tool first runs `pnpm install`, for example `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` on a clone without `node_modules`. The root `postinstall` then runs `composer install`, which can rewrite `vendor/`. `.claude/settings.json` lets Claude run the admin lint and type-check without asking, so Claude can set off both installs with no prompt.
- **Cause:** pnpm 11 checks the dependencies before `run` and `exec`, and installs when `node_modules` is missing or stale. The root `package.json` chains `composer install` onto every install.
- **Workaround:** run `pnpm install` on purpose after pulling or switching branches, so the check finds nothing to do. To run a check with no install at all, call the tool directly, for example `npx --no-install tsc --noEmit` from `apps/admin`.
- **Remove when:** the root `postinstall` is gone, or pnpm's pre-run check is off for this repo.

## The admin lint fails on `dev/edd`

- **Symptom:** `pnpm --filter @yaycheckout/admin-page lint` exits 1. eslint reports 4 warnings and exits 0: three `better-tailwindcss/enforce-canonical-classes` (in `src/components/ui/dialog.tsx` and `src/pages/layouts/LayoutsPage.tsx`) and an unused `result` in `src/pages/tools/components/ImportCard.tsx`. Prettier then fails on `src/lib/utils.ts`, `src/styles/custom-header.css` and `tsconfig.node.json`, and warns that it ignores five `importOrder*` options.
- **Cause:** code that landed without the lint passing. The `importOrder*` options in `apps/admin/package.json` belong to an import-sort plugin the config doesn't load.
- **Workaround:** check only your own files: `npx --no-install eslint <files>` and `npx --no-install prettier --check <files>` from `apps/admin`. Your change must not add a finding.
- **Remove when:** the lint exits 0 on `dev/edd`.

## The dev script and release rewrite `composer.json` and `scoper.inc.php`

- **Symptom:** after `./scripts/run.sh dev`, `./scripts/run.sh release` or `composer shell-update`, `git status` can show `composer.json` (its `require-dev`) or `scoper.inc.php` changed, and the installed versions of every Composer package can move.
- **Cause:** both commands run a full `composer update`, then admin-shell's `yaycommerce-update` or `yaycommerce-prerelease`. Those require php-scoper and thecodingmachine/safe at their latest versions and regenerate `scoper.inc.php` from a template, keeping only the prefix and the adapter's name.
- **Workaround:** check `git status` before committing, and revert those two files unless you meant to change them. A lasting edit to `scoper.inc.php` belongs in admin-shell's template.
- **Remove when:** the admin-shell sync stops writing tracked files.

## Release leaves only production packages

- **Symptom:** after `./scripts/run.sh release`, even a failed one, `composer lint` and `composer test` fail because phpcs and PHPUnit are gone from `vendor/`.
- **Cause:** admin-shell's `yaycommerce-prerelease` ends with `composer install --no-dev`.
- **Workaround:** run `composer install` after every release run.
- **Remove when:** the release builds from a copy of the tree, or restores the dev packages itself.

## The lockfiles aren't tracked

- **Symptom:** the same command can resolve different package versions on two machines, and `./scripts/run.sh dev` moves every Composer package to its newest allowed version on each run.
- **Cause:** `.gitignore` ignores `composer.lock` and `pnpm-lock.yaml`, and `run.sh` runs `composer update`, not `composer install`.
- **Workaround:** when a check behaves differently for a teammate, compare `composer show` and `pnpm list` output before debugging the code.
- **Remove when:** the lockfiles are committed.

## The admin page is blank without the dev server

- **Symptom:** the YayCheckout admin page renders nothing, and the browser console shows failed requests to `localhost:3040`.
- **Cause:** `YAYCHECKOUT_IS_DEVELOPMENT` defaults to `true` in `yaycheckout.php`, so `Engine\Register\RegisterFacade` loads the SPA from the Vite dev server instead of `assets/dist/admin/`.
- **Workaround:** keep `./scripts/run.sh dev` running while you work in the admin. To use a built bundle, add `define( 'YAYCHECKOUT_IS_DEVELOPMENT', false );` to `wp-config.php` and run `./scripts/run.sh build`.
- **Remove when:** the constant defaults to `false`.

## `composer test` needs its own database

- **Symptom:** `composer test` exits 1 with `Error establishing a database connection`. On a Local site this happens even when the database is running.
- **Cause:** the bootstrap connects to the database in `WP_TESTS_DB_*` (default `yaycheckout_tests` on `localhost`), and drops and recreates its tables. Local sites listen on a per-site socket, not on `localhost`.
- **Workaround:** create the database (`CREATE DATABASE yaycheckout_tests;`), then copy `phpunit.xml.dist` to the gitignored `phpunit.xml` and set `WP_TESTS_DB_PASSWORD` and `WP_TESTS_DB_HOST`, for Local `localhost:<the site's mysqld.sock path>`. Never point it at a site's database.
- **Remove when:** the test setup finds the database without a local config.

## A bare `vite build` writes outside `assets/`

- **Symptom:** running `vite build` in `apps/admin` without the package script writes the bundle to `apps/assets/dist/`, emptying that directory first, and wp-admin keeps loading the old bundle.
- **Cause:** `apps/admin/vite.config.ts` resolves `outDir` against the working directory; only the `--outDir` flag in the `build` script points it at `assets/dist/admin/`.
- **Workaround:** build through `./scripts/run.sh build` or `pnpm build:admin`.
- **Remove when:** `vite.config.ts` resolves `outDir` to `assets/dist/admin/` itself.

## `./scripts/run.sh clean` leaves the blocks and stops without a terminal

- **Symptom:** `clean` removes only `assets/dist/admin/`. Without a terminal, it ends with exit 1 at its "Remove node_modules?" prompt.
- **Cause:** `cmd_clean` in `scripts/run.sh` never touches `assets/dist/blocks/`, and its `read` reaches the end of input under `set -e`.
- **Workaround:** delete `assets/dist/blocks/` yourself if you need it gone, and run `clean` from a terminal.
- **Remove when:** `clean` removes both outputs and has a way around its prompt.
