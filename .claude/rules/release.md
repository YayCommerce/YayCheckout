---
paths:
  - "scripts/**/*"
  - "scoper.inc.php"
---

<!--
Purpose: rules for changing the build and release tooling in scripts/ and the php-scoper config, scoper.inc.php. Loads when Claude reads a matching file.
Update when: run.sh gains or loses a step, a shipped path, a prompt or a version location; the boundary check changes; or admin-shell's sync changes what it regenerates.
-->

# Release tooling

What the release does is in [docs/architecture.md § Build and release](../../docs/architecture.md#build-and-release). The commands are in `CLAUDE.md`.

- **`scoper.inc.php` is generated.** Every `./scripts/run.sh dev`, `release` and `composer shell-update` rewrites it from a template in admin-shell's own scripts (`vendor/bin/yaycommerce-update` and `yaycommerce-prerelease`), keeping only the `prefix` and the adapter class. Any other edit is lost on the next run, so change the template upstream, in admin-shell.
- **`run.sh` runs under macOS's `/bin/bash` 3.2 and BSD tools, as well as GNU.** Use no bash 4 features, such as `mapfile`, `declare -A` or `${x,,}`. Edit files in place only through `sed_inplace`.
- **Every prompt needs a way around it.** `set -e` is on, so a `read` that reaches the end of input ends the run with exit 1. `release` skips its prompt when given a version or `--no-prompt`; give any new prompt the same escape.
- **The zip holds only what `cmd_release` in `scripts/run.sh` copies.** A new runtime file outside its copy list must be added to that list. A dev-only file inside a shipped directory must be deleted in its strip step.
- **The version lives in three lines:** the plugin header's `Version:` and the `YAYCHECKOUT_VERSION` define in `yaycheckout.php`, and `Stable tag:` in `readme.txt`. `update_version` writes all three with `sed`, and `get_version` reads the define back. Change a line's shape, or add a location, in all of them at once.
- **The Pro boundary check** (`scripts/check-pro-boundary.sh`) skips comment lines and wants exactly one reference to `YayCheckout\Pro` outside `includes/Pro/`, in `includes/YayCheckout.php`. Zero fails as well as two, and so does a move of `boot_pro()` to another file.

## Checking a change

- phpcs doesn't scan `scripts/`. Run `bash -n` on each shell script you changed, and `php -l` on any PHP script.
- Run `release` only to cut a release. It writes the version into `yaycheckout.php` and `readme.txt` when you pass one or type a new one at the prompt, always runs the admin-shell sync, and leaves only `--no-dev` packages behind ([docs/known-issues.md](../../docs/known-issues.md)).
