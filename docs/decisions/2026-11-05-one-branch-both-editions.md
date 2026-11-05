# One branch builds both editions

**Purpose:** why Pro and Free are built from a single branch, with `--plan` choosing the edition and the Free adapter kept as a build input.

**Update when:** never edit it. Supersede it with a new record if the editions stop being chosen at release time.

## Status

Accepted, 2026-11-05, by Trace Bui.

## Context

- [Two edition branches](2026-10-19-edition-branches.md) put each edition's identity on its own branch: `dev/edd` Pro, `dev/lite` Free. Every release needed a merge into `dev/lite`, and every merge had to dodge a conflict on the adapter and the header.
- It drifted. By 2026-11-05 `dev/lite` was 32 commits behind `dev/edd`, missing the Order Again, Thank You Text and Save as PDF blocks, both CI workflows and the matching documentation. Nothing enforced the merge, so the Free branch's content was only as fresh as the last person to remember it.
- The split is already build-time in every other respect ([the build-time split](2026-10-05-build-time-free-pro-split.md)): `Plan::is_pro()` is derived from whether `includes/Pro/` booted, `cmd_release` strips `PRO_PATHS`, patches the admin-shell and checks the finished zip against the plan. The branch only ever decided two files.
- The 2026-10-19 record rejected choosing the adapter *at runtime* from `includes/Pro/`, because it adds a seam and a load-order constraint. Choosing it at *build* time, in the staging copy, has neither: the shipped plugin still loads exactly one adapter from one path.

## Decision

- One branch, `dev/edd`, holds both editions. `dev/lite` is deleted.
- `--plan=free|pro` chooses the edition at release time, defaulting to `pro`. Nothing reads the branch.
- The branch's own files are Pro: the root `YayCheckoutPluginAdapter.php` implements `LicenseConfigAdapter` (slug `yaycheckout`, EDD item 1412) and the header names YayCheckout Pro. So a dev tree, phpcs, `php -l`, the composer classmap and the generated `scoper.inc.php` are unchanged, and the dev site runs Pro.
- Free's adapter is a build input at `editions/YayCheckoutPluginAdapter.free.php`: a `PluginMenuAdapter` with a "Go Pro" link and no license methods. `cmd_release` stages it over the root adapter when `--plan=free`, and rewrites the staged header's `Plugin Name:` and adds `Requires Plugins: woocommerce`. `editions/` is outside the copy list, so neither zip ships it.
- `assert_staged_plan()` checks the staged adapter and header against the plan before the zip is made: Pro's adapter must implement `LicenseConfigAdapter` and name YayCheckout Pro, Free's must do neither.
- Everything else is as it was. The Free zip still deletes `includes/Pro/` and the shell's licensing, `Plan::is_pro()` still describes the build and not the license, and Pro still wins when both editions are active.

## Consequences

- No edition merge, no conflict and no drift: a feature is in both editions the moment it lands.
- Both zips are cut from one checkout, so a release no longer begins with a branch switch.
- Nothing but `--plan` separates the editions now. `assert_staged_plan()` and the post-zip artifact checks are the whole guard, so neither may be weakened, and a new edition-specific file must be staged and asserted alongside the adapter.
- A dev tree is Pro, so it opens on YayCommerce → Licenses until a key is activated. There is no longer a Free branch to work on instead; build a Free zip to see Free.
- Keeping the two adapters' menu methods in step is now a convention, not something a merge conflict forces. They sit in two files that both declare `YayCheckoutPluginAdapter`, so only one may ever be in composer's classmap.
