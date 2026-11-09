---
paths:
  - "scripts/**/*"
  - "scoper.inc.php"
---

<!--
Purpose: what the release tooling will do to your working tree, and what it must never ship.
Ownership: anything below the `your rules` marker at the foot is this repo's and is
  never regenerated; everything above it comes from the kit.
Update when: the build's inputs or outputs change.
-->

- **Never hand-edit generated output.** Change the input and rerun the command that builds
  it. The guards refuse the write, including from the shell.
- **Ship by allowlist, not by exclusion.** An exclude list silently ships whatever is added
  later and not added to the list; an allowlist fails closed.
- **Deleting a file is not enough to un-ship it.** A Composer classmap entry for an update
  checker reads as an update checker even once the file is gone — rebuild the autoloader
  from what the copy actually holds.
- **A staged substitution is verified as a replacement, not an absence.** The original is
  gone whether the staging applied or failed, so assert the replacement's content is there.
- **Check `git status` before committing after a build.** These commands can rewrite tracked
  files, and a dev-mode default or a dependency bump must not land by accident.

<!-- --- your rules --- -->

<!-- Carried wholesale from this repo's own .yay/archive/old-harness/.claude/rules/release.md when the kit was adopted. The kit
     wrote none of it and has not checked any of it. Trim what the rules above now cover;
     keep every line they do not — that is why this was kept rather than replaced. -->

<!--
Purpose: rules for changing the build and release tooling in scripts/ and the php-scoper config, scoper.inc.php. Loads when Claude reads a matching file.
Update when: run.sh gains or loses a step, a shipped path, a prompt or a version location; the boundary check changes; or admin-shell's sync changes what it regenerates.
-->

# Release tooling

What the release does is in [docs/architecture.md § Build and release](../../docs/architecture.md#build-and-release). The commands are in `CLAUDE.md`.

- **`scoper.inc.php` is generated.** Every `./scripts/run.sh dev`, `release` and `composer shell-update` rewrites it from a template in admin-shell's own scripts (`vendor/bin/yaycommerce-update` and `yaycommerce-prerelease`), keeping only the `prefix` and the adapter class. Any other edit is lost on the next run, so change the template upstream, in admin-shell.
- **`run.sh` runs under macOS's `/bin/bash` 3.2 and BSD tools, as well as GNU.** Use no bash 4 features, such as `mapfile`, `declare -A` or `${x,,}`. Edit files in place only through `sed_inplace`.
- **Every prompt needs a way around it.** `set -e` is on, so a `read` that reaches the end of input ends the run with exit 1. `release` skips its prompts when given a version or `--no-prompt`, and skips a tested-up-to prompt when given `--wp-tested=` or `--wc-tested=`; give any new prompt the same escape.
- **The zip holds only what `cmd_release` in `scripts/run.sh` copies.** A new runtime file outside its copy list must be added to that list. A dev-only file inside a shipped directory must be deleted in its strip step — for example the strip after `composer dump-autoload` for `vendor/composer/installed.json`, which that dump does not rewrite. `vendor/composer/installed.php` must stay: `Composer\InstalledVersions::getInstalled()` ends in an unguarded `require` of it, and this copy classmaps that class. Each strip has an artifact check; add one with any new strip.
- **No edition ships the JS source.** wordpress.org guideline 4 takes a readme link to the public development location instead, so `readme.txt` § Source code names `SOURCE_REPO_URL` and the commit the zip was built from, stamped over `@SOURCE_COMMIT@` in the staging copy. Keep that placeholder in `readme.txt` and the link in step with `SOURCE_REPO_URL`: the artifact check fails the release if the zip loses the link, keeps the unstamped placeholder, or gains a `JS_SOURCE_PATHS` entry. A Free release off a dirty tree fails, because the commit it names would not match the zip. The gate runs before `update_version`, so a version passed on the command line still dirties the tree afterwards and the zip names the commit before the bump: for a wordpress.org release, commit the version bump first and cut with `--no-prompt`.
- **The version lives in three lines:** the plugin header's `Version:` and the `YAYCHECKOUT_VERSION` define in `yaycheckout.php`, and `Stable tag:` in `readme.txt`. `update_version` writes all three with `sed`, and `get_version` reads the define back. Change a line's shape, or add a location, in all of them at once.
- **"Tested up to" lives in two lines:** WordPress's `Tested up to:` in `readme.txt` and `WC tested up to:` in the `yaycheckout.php` header. The plugin header has no WordPress `Tested up to:`. `update_wp_tested` and `update_wc_tested` write them, and `get_wp_tested` and `get_wc_tested` read them back.
- **The edition comes from `--plan`.** One branch builds both, Pro by default; nothing reads the branch. The branch's root `YayCheckoutPluginAdapter.php` and header are the Pro ones. For `--plan=free`, `cmd_release` stages `editions/YayCheckoutPluginAdapter.free.php` over the staging copy's adapter and rewrites that copy's header; the branch's own files are never touched. `assert_staged_plan()` then fails the release unless the staged adapter and plugin name match the plan. A new edition-specific file must be staged there and asserted there too.
- **The header's edition lines are rewritten for Free:** `Plugin Name:` loses ` Pro`, and ` * Requires Plugins:  woocommerce` is inserted after `Requires PHP:`. The insert uses `perl -pi`, not `sed_inplace`: a literal newline in a `sed` replacement is not portable between GNU and BSD, which is why the text-domain rewrite uses perl too.
- **The Pro boundary check** (`scripts/check-pro-boundary.sh`) skips comment lines and wants exactly one reference to `YayCheckout\Pro` outside `includes/Pro/`, in `includes/YayCheckout.php`. Zero fails as well as two, and so does a move of `boot_pro()` to another file.

## Checking a change

- phpcs doesn't scan `scripts/`. Run `bash -n` on each shell script you changed, and `php -l` on any PHP script.
- Run `release` only to cut a release. It writes the version into `yaycheckout.php` and `readme.txt` when you pass one or type a new one at the prompt, always runs the admin-shell sync, and leaves only `--no-dev` packages behind ([docs/known-issues.md](../../docs/known-issues.md)).
