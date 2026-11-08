<!--
Purpose: claims lifted out of the harness this repo had before the kit, each one a single
assertion with the file and line it came from and whether anyone has checked it since.
Update when: a claim is verified (set `status: verified` and cite the code that proves it),
refuted (delete it) or superseded by a fact in `harness.yml`.
-->

# Carryover claims

Every claim below earned its place by beating "the agent could have just looked": it is
expensive to derive, impossible to derive from the tree, or catastrophic to get wrong.
`status: unverified` means the old harness asserted it and nobody has checked it since.
A claim is not a fact until something confirms it, so nothing here may be cited as one.

## Governance

```yaml
- claim: "Three decision areas have no record in force: an accepted record was set to
    `Superseded by` a record that is still `Proposed`, and the practice says a proposed
    record is one nothing acts on. Base styles and heading font, Pro template previews in
    Free, and the plugin spot are each governed by neither end of their chain."
  source: docs/decisions/2026-10-05-decision-record-practice.md:36
  status: unverified
- claim: "Six of 24 decision records are `Proposed` and were never accepted, while
    CLAUDE.md states that only accepted records are in force. The code follows several of
    them anyway."
  source: CLAUDE.md:57
  status: unverified
- claim: "The harness that came before the current one is preserved at the git tag
    `harness-v1-final`, not deleted."
  source: docs/decisions/2026-10-05-harness-with-review-backlog.md:9
  status: unverified
- claim: "The previous harness was replaced because its docs restated the code and drifted,
    ten code comments cited doc paths that broke silently when a doc moved, `.gitignore`
    hid `.claude/` so new harness files were invisible to git, about 680 lines loaded in
    every session, and an owner table reached 24 rows and needed its own skill to police."
  source: docs/decisions/2026-10-05-harness-with-review-backlog.md:13
  status: unverified
```

## Things that are silently wrong

```yaml
- claim: "The generated-directory guard only covers Claude's file tools. A shell command
    that writes into a generated directory goes through, and because those directories are
    gitignored, `git status` does not show it either. Without `jq` the guard blocks nothing
    and reports a PreToolUse error on every edit."
  source: docs/known-issues.md:77
  status: unverified
- claim: "Product PHP must run on PHP 7.4, but phpcs checks no PHP version and the dev
    tooling runs on PHP 8, so only review catches a 7.4 violation."
  source: CLAUDE.md:34
  status: unverified
- claim: "`uninstall.php` runs without the autoloader, so it repeats `yaycheckout_settings`,
    `general.delete_data_on_uninstall` and `yay_checkout_layout` as string literals. It is
    the one place a storage key is not read from its owning constant."
  source: docs/conventions.md:11
  status: unverified
- claim: "Free's upsell components ship to the Free build, so real data or a real endpoint
    placed behind one would ship to Free too."
  source: CLAUDE.md:33
  status: unverified
```

## Commands that leave the tree changed

```yaml
- claim: "`run.sh dev`, `run.sh release` and `composer shell-update` can rewrite tracked
    `composer.json` and `scoper.inc.php`, and move every installed Composer package. A
    lasting edit to `scoper.inc.php` belongs in admin-shell's template, not here."
  source: docs/known-issues.md:28
  status: unverified
- claim: "A release run ends with `composer install --no-dev`, so `composer lint` and
    `composer test` fail afterwards until `composer install` is run again. This happens
    even when the release failed."
  source: docs/known-issues.md:35
  status: unverified
- claim: "`composer.lock` and `pnpm-lock.yaml` are gitignored and `run.sh` runs
    `composer update`, so two machines can resolve different versions and each dev run can
    move every Composer package."
  source: docs/known-issues.md:42
  status: unverified
```

## Checks already failing on dev

```yaml
- claim: "`./scripts/check-harness.sh` exits 1 on `dev` with 27 `[backticked_paths]
    MISSING` lines, because it resolves every backticked name containing a slash against
    the repo root. A change is judged by not adding a line."
  source: docs/known-issues.md:84
  status: unverified
- claim: "`pnpm --filter @yaycheckout/admin-page lint` exits 1 on `dev`: six eslint
    warnings and prettier failures in three files. A change is judged by not adding a
    finding."
  source: docs/known-issues.md:21
  status: unverified
```

## Local setup

```yaml
- claim: "`YAYCHECKOUT_IS_DEVELOPMENT` defaults to true, so the admin page is blank unless
    the Vite dev server on :3040 is running."
  source: docs/known-issues.md:49
  status: unverified
- claim: "`composer test` connects to its own database from `WP_TESTS_DB_*` and drops and
    recreates its tables. It must never be pointed at a site's database."
  source: docs/known-issues.md:56
  status: unverified
```

## Mirrors and wp.org

```yaml
- claim: "Every PHP/TypeScript mirror names PHP as its owner except one:
    `packages/blocks/src/shared/address-fields.ts` owns the address-field list, and PHP
    seeds a new address section from it."
  source: docs/conventions.md:23
  status: unverified
- claim: "An unescaped exception message fails wordpress.org's Plugin Check as
    `ExceptionNotEscaped`, and Plugin Check does not read `phpcs.xml`. Messages are decoded
    again through `ExceptionHelper::message()` on the way out."
  source: docs/conventions.md:65
  status: unverified
```
