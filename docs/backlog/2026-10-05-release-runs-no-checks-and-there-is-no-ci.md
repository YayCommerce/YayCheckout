# The release runs no lint or boundary check, and no CI runs them either

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`cmd_release` in `scripts/run.sh` runs the admin-shell sync (`update_shell prerelease`), `composer dump-autoload --no-dev`, then `cmd_build`. The build type-checks the SPA (`tsc --noEmit` in the admin package's `build` script) and builds both bundles. The release then copies the files, deletes `includes/Pro/` for Free, zips, and counts the `includes/Pro/` entries in the zip.

It never runs `scripts/check-pro-boundary.sh`, phpcs or PHPUnit. After the sync it couldn't run phpcs anyway, because admin-shell's `yaycommerce-prerelease` ends with `composer install --no-dev` (the known issue "Release leaves only production packages" in `docs/known-issues.md`). The repo has no CI config and no git hooks, so `composer lint` runs only when a developer remembers it. `docs/architecture.md` records that the release "runs no lint, boundary check or tests". Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

The zip check proves only that `includes/Pro/` is gone. A Free file that names a `YayCheckout\Pro` class, merged without `composer lint`, passes that check and ships, and the Free store fatals when that line runs. phpcs errors ship the same way, and so would the PHP 8 syntax that "Nothing checks the PHP 7.4 floor" describes once that check exists. Installing the zip on a clean site would also catch "A release zip registers no admin script unless the site turns development mode off".

## Suggested fix

Run `composer lint` at the start of `cmd_release`, before `update_shell prerelease` removes phpcs, and stop on failure. Add a GitHub Actions workflow that runs `composer lint` and the SPA type-check on every pull request into `dev/edd`. Add `composer test` to it once the suite holds tests and the workflow has a database. Then update the Build and release section of `docs/architecture.md` and `.claude/rules/release.md`.

## Done when

- [ ] With a non-comment `YayCheckout\Pro` reference added to a file under `includes/Services/`, `./scripts/run.sh release --no-prompt` exits non-zero and writes no new zip to `release/`.
- [ ] With a phpcs error added to a file under `includes/`, `./scripts/run.sh release --no-prompt` exits non-zero and writes no new zip.
- [ ] A pull request into `dev/edd` shows a GitHub Actions check that runs `composer lint`, and the check fails when `composer lint` does.
- [ ] The Build and release section of `docs/architecture.md` names the lint and the boundary check among the release's steps.
