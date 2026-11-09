# Build both editions, and verify the zips

<!--
Purpose: the mechanics of turning a committed tree into two checked edition zips — the build
  commands, the edition resolution, the tracked files the build can rewrite, and the artifact
  checks beyond the ones run.sh already makes. Shared, because two skills package a release:
  /yayrelease phase 4 and /yayhotfix phase 5. Neither owns it; both point here.
Update when: cmd_release in scripts/run.sh changes its flags, its copy list, its slugs or its
  own in-script checks; JS_SOURCE_PATHS changes meaning; the plugin stops shipping exactly two
  editions from one tree; or the admin-shell sync stops rewriting composer.json and
  scoper.inc.php.
-->

This file is the **how**. Each skill keeps its own **why** — why it builds at that point in its
flow, whose zips ship, and whether narrowing to one edition is allowed there — because those
differ between a release and a hotfix. Do not move that framing in here.

## Warn first: each build takes minutes

A Composer update, an admin-shell rescope, a TypeScript build and a blocks build, per edition.
On the machine this was rehearsed on, each edition took roughly a minute; a cold `vendor/` or
`node_modules/` makes it several.

Give each edition its own command, never both in one, and either run it in the background and
wait to be notified, or run it in the foreground with the maximum timeout the tool allows. Do
not ask for a timeout above that maximum: the call is rejected, and the build you were trying to
protect never starts.

```bash
PLANS="pro free"                 # both, unless the calling skill narrowed to one edition
for plan in $PLANS; do ./scripts/run.sh release --plan="$plan" --no-prompt; done
```

Run each edition as its own tool call, not the loop above in one call — the loop is only there to
name `$PLANS`, which the artifact checks below reuse, so a single-plan run checks one zip.

`--no-prompt` is not optional. `scripts/run.sh` runs under `set -e`, so a `read` that reaches the
end of input ends the run with exit 1 — and with no version argument, `--no-prompt` also means the
build uses the version already written into the tree and leaves the tested-up-to lines alone. To
move those, pass `--wp-tested=X.Y` and `--wc-tested=X.Y` (major.minor only) on both commands;
otherwise they stay as they are.

## Resolving the edition argument

**The plugin has two editions, `free` and `pro`.** `cmd_release` accepts only those two names and
exits on anything else, so resolve the argument before it reaches `run.sh`:

| Typed | `$PLANS` | |
|---|---|---|
| `both`, or nothing | `pro free` | A whole release. The default. |
| `pro`, `full` | `pro` | The paid build. |
| `free`, `lite` | `free` | The wordpress.org build. |

`both` is never passed to `run.sh`; it is the word for building each edition in turn. `full` and
`lite` are aliases kept because the team says them — the deleted Free-edition branch was called
dev/lite until one branch started building both. Resolve any of these and say which plans you
resolved to in the output, so nobody is left believing there is a third edition. Anything else
aborts: name what was passed and the words accepted, and do not pass it through to `run.sh` to
fail there with a less helpful message.

### Why narrowing is never a whole release

Each calling skill decides whether narrowing is allowed at its own build step. Wherever it is
refused, these are the reasons, and they are not softer ones:

- Both zips must come from the same tagged commit at the same version. Ship Free at 1.1.0 alone
  and Pro's newest zip is still 1.0.0, while `yaycheckout.php` and `readme.txt` already say
  1.1.0 — so the next Pro build silently carries notes for a release Pro never had.
- `readme.txt`, changelog and all, is copied into both zips unchanged. That is why the `(Pro)`
  suffix exists; one changelog cannot describe half a release.
- The leak checks are cross-edition by construction. `cmd_release` can only prove the Free zip
  carries no Pro code by building the Free zip. Build one and you have verified half.

So a single-plan run is a **build check, not an edition check**: it cannot test that Pro's files
are absent from the Free zip. Say that in its output, and run the last rehearsal before anything
ships with both editions.

## Commit the version bump *before* building

Two constraints pull in opposite directions, and exactly one order satisfies both.

**1. The Free build refuses a dirty tree.** Before it builds anything, `cmd_release` resolves
`HEAD` and stamps it over `@SOURCE_COMMIT@` in the staging copy's `readme.txt`, because that
section is what stands in for the JS source wordpress.org would otherwise fetch. For
`--plan=free` a dirty tree is **fatal** — "the zip would name commit X, which does not match
it" — and for `--plan=pro` it is a warning, since Pro's customers never fetch the source. The
gate runs *before* `update_version`, so it sees the tree as the bump left it.

A tree that has just been bumped and not committed **is** dirty. So building Free straight after
the bump fails in the first second, every time.

**2. The build can rewrite tracked files.** The admin-shell sync changes `composer.json` (its
`require-dev`) and regenerates `scoper.inc.php`
([docs/known-issues.md](../../../../docs/known-issues.md)). Whether it does on a given run depends
on what has moved upstream; in the rehearsal that validated this, neither file changed.

**The order is therefore: bump, commit, build, discard the build's rewrites.**

```bash
git add yaycheckout.php readme.txt          # by name; see the calling skill's commit phase
git commit                                  # the tree is now clean, so --plan=free can run
# ... build both editions ...
git status --porcelain                      # see what the build actually touched
git checkout -- composer.json scoper.inc.php
composer install
```

This is worth stating plainly because the opposite order is intuitive and wrong. The argument for
building first was that committing first would leave the build's rewrites uncommitted and outside
the tag — but that argument defeats itself: those rewrites are **discarded**, not shipped. Known
issues says to revert them unless they were meant, and both skills do. Nothing the build touches
ever belongs in the commit, so there is nothing to protect by building first, and building first
costs you the Free zip.

`git checkout` is a no-op when the build left those two files alone, so run it either way rather
than deciding. `composer install` puts the dev packages back: the sync is documented to end with
`composer install --no-dev`, which leaves `composer lint` and `composer test` failing with phpcs
and PHPUnit simply gone. The `--stage` path these skills use reported leaving the plugin root
untouched in the rehearsal and `vendor/bin/phpcs` survived, so treat this as cheap insurance
rather than a certainty — and still run it, because the known issue is live and the failure it
causes is very hard to connect back to a release. Both commands belong in every unwind.

> **One consequence worth knowing.** The commit the zip names is the pre-merge commit, on the
> working branch. That is fine: those zips are a verification build and are discarded, and each
> skill's publish phase rebuilds from the merged, tagged commit — whose stamped commit is the one
> that is permanently reachable. Never ship a zip built before the merge.

## Verify the artifacts, not the exit status

`cmd_release` already checks a great deal inside the zip — Pro leaks per plan, the stripped
admin-shell and its autoloader, the text-domain rewrite, the header name, the development
constant, the absence of every `JS_SOURCE_PATHS` entry and of `node_modules/`, the absence of
`vendor/composer/installed.json` with the autoloader's own files still present, the stamped
`@SOURCE_COMMIT@` and the source-repository link in `readme.txt`, and `assert_staged_plan()`
before zipping. **Print its output and read it.** Then add the checks it does not make — it never
asserts the version inside the zip:

```bash
for plan in $PLANS; do            # both, or the one edition that was narrowed to
  slug=yaycheckout; [ "$plan" = pro ] && slug=yaycheckout-pro
  zip="release/$slug-$NEW.zip"
  ls -l "$zip"
  unzip -Z1 "$zip" | cut -d/ -f1 | sort -u                                 # exactly: $slug
  unzip -p "$zip" "$slug/yaycheckout.php" | grep -E "^ \* Version:|YAYCHECKOUT_VERSION"
  unzip -p "$zip" "$slug/readme.txt" | grep '^Stable tag:'
  unzip -p "$zip" "$slug/yaycheckout.php" | grep -m1 '^ \* Plugin Name:'
done
```

Cross-check the editions against each other, since a dev tree always contains every file and a
leak is invisible there:

- The Pro zip's root directory is `yaycheckout-pro`, the Free zip's is `yaycheckout`. Different
  slugs on purpose: a wordpress.org update to `yaycheckout` must not overwrite a paid install.
- Pro contains `includes/Pro/` and the shell's `vendor-prefixed/src/License/`; Free contains
  neither.
- **Neither** contains a JS source tree. `apps/`, `packages/`, `pnpm-lock.yaml`,
  `pnpm-workspace.yaml`, the root `package.json`, `.nvmrc` and `node_modules/` are absent from
  both: since [#267](https://github.com/YayCommerce/YayCheckout/pull/267) the source is *linked*
  rather than shipped, and `readme.txt`'s Source code section is that link. `JS_SOURCE_PATHS` in
  `scripts/run.sh` is the list, and it is a must-**not**-contain list for both editions.
- `readme.txt` inside each zip names the public repository and carries a real commit in place of
  the `@SOURCE_COMMIT@` placeholder. That link is now the only thing standing in for the source,
  so a zip whose readme lost it, or kept the literal placeholder, fails wordpress.org's guideline
  4 — which is why `cmd_release` checks both.
- Both headers carry the version just bumped to; Pro's names `YayCheckout Pro`, Free's names no
  Pro.

Any mismatch: **stop**. Nothing has been pushed at this point in either skill's flow, and a wrong
zip here costs one rebuild.
