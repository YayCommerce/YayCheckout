---
name: yayrelease
description: Cuts a YayCheckout release end to end: preflight, version, the changelog through /yaychangelog, the bump, both edition zips, a release PR into dev, then the annotated tag, the released branch and a draft GitHub Release. Use when the user types /yayrelease, or says "release 1.1.0", "cut a release", "ship a release", "bump the version" or "publish the release". Do not use to draft release notes alone (/yaychangelog) or to build a test zip (./scripts/run.sh release).
argument-hint: "[version] [both|free|pro] [--publish] [--draft] [--dry-run]"
disable-model-invocation: true
allowed-tools: Read, Edit, Bash, Grep, Glob
# Purpose: the one linear flow that cuts a release of this plugin, from preflight to the published
#   GitHub Release, with the irreversible steps gated. It owns the tag format, the release commit
#   and PR shape, the artifact checks beyond the ones run.sh already does, and the unwind table.
#   The changelog rules and their commit screen are NOT here: they belong to the yaychangelog
#   skill, which phase 2 invokes. This header covers the whole skill directory.
# Update when: cmd_release in scripts/run.sh changes its flags, its copy list or its artifact
#   checks; a version location is added or removed; the branch model, tag format or PR rule
#   changes; the repository's visibility changes; the Free and Pro zips stop being two zips from
#   one branch; or yaychangelog is renamed, moved or stops writing readme.txt.
---

# Cut a YayCheckout release

One flow, with one irreversible end. The failure mode to avoid is a **half-release** — a tag with
no bump, a zip that does not match its own header — so the sequence from preflight to published
Release is one skill, not a set of steps anyone can run out of order.

The changelog is the one piece that lives in its own skill, `/yaychangelog`, because drafting
release notes is useful on its own: it reads commits and writes `readme.txt`, and it bumps,
builds, commits and tags nothing. Phase 2 invokes it. Splitting it out costs no ordering
guarantee — a release still cannot reach phase 3 without it — and it keeps the `(Pro)` rule, which
is pinned to the code, in exactly one place.

This repo had **never published a release** when this skill was written: `gh release list` was
empty, the only tag was `harness-v1-final`, and the `readme.txt` changelog held one placeholder
entry. So several things here are conventions this skill *establishes* rather than ones it
found. Each is marked **[new]** and named in one place, so the team can change it:

- **[new] Tag format:** `v<version>`, annotated — `v1.1.0`. The repo's one existing tag is not a
  version tag, so there was no precedent.
- **[new] Release branch:** a `released/<version>` branch pushed at the tagged commit, so a
  hotfix has somewhere to branch from without rewinding `dev`.
- **[new] Changelog house style:** owned by `/yaychangelog`, in
  [its style file](../yaychangelog/references/changelog-style.md).

## Arguments

`argument-hint` in the frontmatter is display text with no parser behind it. These are the real
arguments — **keep the two in step**, because a stale hint is worse than none:

| Argument | Meaning |
|---|---|
| `<version>` | `X.Y.Z`, `X.Y.Z.W` or `X.Y.Z-tag`. Omitted, phase 1 proposes one and asks. |
| `both` *(default)*, `free`, `pro` | Which editions to build, as a bare word after the version. `both` is what a release is and what you get by passing nothing. `free` and `pro` narrow phase 4 to one edition, **only with `--dry-run`**; on a real run they abort (see phase 4). `full` is an alias for `pro` and `lite` for `free`, and `--plan=<name>` is a synonym. |
| `--publish` | Force **stage 2 only**: skip phases 0–6 and publish an already-merged release PR. Rarely needed — publishing is the default endpoint (see below), so this is for when stage 1 ran elsewhere or you want to skip its verification build. |
| `--draft` *(default, and the only behaviour)* | The Release is always created as a draft. The flag is accepted so it can be written out, and changes nothing. There is deliberately **no opt-out** — see phase 7. |
| `--dry-run` | Run phases 0–5 for real, print phase 7 and execute none of it, then unwind. |

Both bare arguments are positional and tell themselves apart by shape, so their order does not
matter: anything that looks like a version (`1.1.0`) is the version, and `both`, `free`, `pro`,
`full` or `lite` is what to build. A bare word that is neither aborts rather than being guessed at — say
what was passed and what the two shapes are.

## One command that resumes, in two stages

**Publishing is the default endpoint.** `/yayrelease 1.1.0` means "take this release as far as it
can go", and that includes the tag and the GitHub Release. There is no mode to opt into.

What it cannot do is skip the merge. `CLAUDE.md` says every change lands through a squash-merged
PR into `dev` and never a direct commit, and the release's bump and changelog are a change — so a
human merges in the middle of the flow, and the flow stops there and resumes:

- **Stage 1** — phases 0 to 6. Ends with a pushed branch, an open PR, and nothing tagged.
- **A teammate reviews and squash-merges** the PR. Not this skill's work, and not something to
  wait on in a loop — the run ends.
- **Stage 2** — phases 7 to 8. Rebuilds the zips from the merged commit, tags it, pushes the tag
  and the released branch, creates the Release.

**Which stage runs is read from the repository, not from a flag.** On entry, after phase 0:

| State | Run |
|---|---|
| No release commit for this version on `dev` | Stage 1 |
| The release commit is on `dev`, and `v<version>` is untagged locally and on the remote | Stage 2 |
| `v<version>` is already tagged | Neither. Say the version is released and stop. |
| A release PR for this version is open but unmerged | Neither. Say it is awaiting review, link it, and stop. |

So the operator runs the same `/yayrelease 1.1.0` again after the merge and it carries on.
`--publish` forces stage 2 without that detection; it is an override, not the normal path.

There are still **two** stop-and-wait gates, not one: Gate A before the first push in stage 1, and
Gate B before the tag in stage 2. The merge in between is the reason. Do not collapse them, and do
not treat "publishing is the default" as licence to pass either one.

Keep a todo list with one item per phase, so the operator can see where the flow is.

---

## Phase 0 — Preflight

Every check aborts with its own message. **Never make a check pass on the operator's behalf** —
no stashing, no committing, no pulling, no `git clean`. Report and stop.

```bash
cd "$(git rev-parse --show-toplevel)"
git rev-parse --abbrev-ref HEAD          # must be exactly: dev
git status --porcelain                   # must be empty
git fetch origin dev
git rev-list --count HEAD..origin/dev    # must be 0  -> else "behind origin/dev"
git rev-list --count origin/dev..HEAD    # report it  -> ahead is fine, see below
./scripts/run.sh info                    # current version
git tag --list 'v*'                      # local version tags
git ls-remote --tags origin 'v*'         # remote version tags
gh auth status
gh repo view --json visibility,nameWithOwner
command -v composer php zip unzip perl patch git gh
php -r 'exit(version_compare(PHP_VERSION, "8.2", ">=") ? 0 : 1);'
node --version                           # must satisfy .nvmrc (22); run `nvm use` if not
pnpm --version || corepack pnpm --version # either one is enough
```

Rules for reading those:

- **Branch.** Must be `dev`. Nothing else releases.
- **Clean tree.** Must be empty. A release that starts dirty ships files the tag does not cover.
- **Behind, not ahead.** Abort only when the count of commits in HEAD..origin/dev is non-zero. Being *ahead* is the
  normal case — commits already merged into `dev` locally but not yet pushed, which stage 1
  pushes anyway. Print the ahead count and the subjects (`git log --oneline origin/dev..HEAD`)
  and ask the operator to confirm those commits belong in this release.
- **Not already tagged.** Check both local **and** remote. A tag that exists only on the remote
  is invisible to `git tag`, and `git push --tags` would then fail after the branch push had
  already landed.
- **Then route.** With the tags and `git log` already in hand, read which stage this run is: the
  state table under "One command that resumes" decides, and `gh pr list --base dev --state open
  --search "chore(release): $NEW"` answers the awaiting-review row. Say which stage you are
  entering and why, in one line, before phase 1. A run that routes to "neither" ends here.
- **Prerequisites.** Composer needs SSH access to the private admin-shell repository, which it
  clones; a release cannot build without it. `pnpm-lock.yaml` must exist (Free's zip ships it) —
  run `pnpm install` if it does not. `vendor/bin/php-scoper` must be executable; `ensure_composer`
  installs it, but say so rather than letting the build fail three minutes in.
- **Node, not pnpm, is the gate.** `node --version` must satisfy `.nvmrc`; pnpm 11 crashes on
  anything older than 22 ([docs/known-issues.md](../../../docs/known-issues.md)), and on a machine
  whose active node is older the `pnpm` shim itself errors out. Do not abort on that: `resolve_pnpm`
  in `scripts/run.sh` falls back to `corepack pnpm`, and then to the newest node under the nvm
  directory. Abort only when neither `pnpm --version` nor `corepack pnpm --version` works. Tell the
  operator to run `nvm use` rather than relying on the fallback, because the fallback picks the
  newest installed node, which is not necessarily the one `.nvmrc` names.
- **Visibility.** Record it. It decides one step in phase 7, and only that step.

Compute the release date **once**, here, and reuse it:

```bash
RELEASE_DATE=$(date +%F)
```

It is used in the tag annotation and nowhere else. The `readme.txt` changelog headings in this
repo carry **no date** — do not add one. Computing it once is still right: it stops the tag
annotation and anything else dated from disagreeing when a run straddles midnight.

Finally, read [docs/known-issues.md](../../../docs/known-issues.md) before this run. Two entries
change what phases 4 and 5 must do, and both are live.

## Phase 1 — Version

The source of truth is the `YAYCHECKOUT_VERSION` define in `yaycheckout.php` — it is what
`get_version()` in `scripts/run.sh` reads back, and what `./scripts/run.sh info` prints.

Assert the new version is **strictly greater**:

```bash
[ "$(printf '%s\n%s\n' "$CURRENT" "$NEW" | sort -V | tail -1)" = "$NEW" ] && [ "$CURRENT" != "$NEW" ]
```

Shape must satisfy `validate_version` in `scripts/run.sh`: `X.Y.Z`, `X.Y.Z.W`, or either with a
`-tag` suffix. Anything else is rejected by the build too.

If the operator named no version, draft the changelog first (phase 2), then propose from its
content and **ask**:

- Any bullet that is a new capability → **minor** (`1.0.0` → `1.1.0`).
- Fixes and behaviour corrections only → **patch** (`1.0.0` → `1.0.1`).
- A change that breaks a stored layout, a setting, or the minimum WordPress, WooCommerce or PHP
  version → say so and let the operator pick; this plugin has no shipped users yet, so a major
  bump is a product decision, not a mechanical one.

Do not bump anything in this phase. Phase 3 writes.

## Phase 2 — Changelog, through `/yaychangelog`

**This skill does not own the changelog rules.** Invoke `/yaychangelog` with the new version and
`--write`, and let it draft the entry, decide each `(Pro)` suffix and write it into `readme.txt`:

```
/yaychangelog <version> --write
```

It resolves the range itself (the newest `v*` tag to HEAD, the whole history when there is none),
screens the commits, reads their bodies, and reports the range it used. One skill owning those
rules is the point: the `(Pro)` rule is pinned to `BlockRegistry::PRO_BLOCKS` and the template
catalogue, and a second copy of it here would be the drift `/yaytidy` exists to find.

**If `/yaychangelog` is not available**, do not stop and do not improvise: read
[its style file](../yaychangelog/references/changelog-style.md) and follow it here, then carry on.
The rules are what matter; which skill applies them does not.

Two things stay this skill's business:

- **Show the operator the draft and let them edit it before anything else happens.** The changelog
  is the one part of a release no later step can check, so it is the one part a human must read.
  Do not move on to phase 3 until they have seen it.
- **Zero bullets stops the release.** If `/yaychangelog` reports nothing user-facing, repeat its
  range, say there is nothing to release, and stop. Do not bump, do not build, and do not talk the
  operator into an entry.

## Phase 3 — Bump

```bash
./scripts/run.sh bump "$NEW"
```

That one command writes all three live locations — the header `Version:` line and the
`YAYCHECKOUT_VERSION` define in `yaycheckout.php`, and `Stable tag:` in `readme.txt` — through
`sed`, with exact context. **Never run a repo-wide `sed` of a bare version string**, and never
hand-edit the three lines: `scripts/run.sh` and [.claude/rules/release.md](../../rules/release.md)
own their shape, and a fourth location added by hand would go stale on the next release.

### Do not touch

These also hold `1.0.0` and are **not** release locations. Nothing syncs them, and changing one
is a silent bug:

| Location | Why it stays |
|---|---|
| `apps/admin/package.json`, `packages/blocks/package.json` | Private workspace packages. Nothing reads their version; it matches the plugin's only by coincidence. |
| `templates/themeless/yaycheckout-themeless/style.css` | The bundled fallback theme's own version. |
| `languages/yaycheckout.pot` | Generated by `pnpm makepot`. Already stale, and the release does not regenerate it — see the note at the end of this file. |
| `readme.txt` changelog headings for earlier versions | History. |
| Anything under `vendor-prefixed/` | Generated output, hard rule 1 in `CLAUDE.md`. |

There are no `@since` or `@deprecated` tags in this codebase, so there is no historical marker in
product code to move. The self-check below asserts that stays true rather than assuming it.

### Self-check

```bash
grep -c "^ \* Version:           $NEW\$" yaycheckout.php                 # 1
grep -c "define( 'YAYCHECKOUT_VERSION', '$NEW' )" yaycheckout.php         # 1
grep -c "^Stable tag: $NEW\$" readme.txt                                  # 1
./scripts/run.sh info                                                     # reports $NEW
git diff --name-only                                                      # only those two (phase 0 left the tree clean)
git diff -U0 | grep -E '^\+.*(@since|@deprecated)'                        # must print NOTHING
```

The last one is the positive assertion that no historical marker moved. If it prints anything,
stop: something rewrote a docblock, and this skill does not know what else it touched.

## Phase 4 — Build (verification build)

Stage 1's build exists to prove the release builds **before** a teammate is asked to review it.
Its zips are discarded; stage 2 rebuilds the ones that ship, from the merged commit. Say that
out loud, so nobody "simplifies" it later by attaching stage 1's artifacts.

**Warn the operator first: each build takes minutes** — a Composer update, an admin-shell
rescope, a TypeScript build and a blocks build per edition. On the machine this skill was
rehearsed on, each edition took roughly a minute; a cold `vendor/` or `node_modules/` makes it
several. Give each edition its own command, never both in one, and either run it in the
background and wait to be notified, or run it in the foreground with the maximum timeout the
tool allows. Do not ask for a timeout above that maximum: the call is rejected, and the build
you were trying to protect never starts.

```bash
PLANS="pro free"                 # both, unless a dry run named one edition to narrow to
for plan in $PLANS; do ./scripts/run.sh release --plan="$plan" --no-prompt; done
```

Run each edition as its own tool call, not the loop above in one call — the loop is only there to
name `$PLANS`, which phase 4's artifact checks reuse so a single-plan dry run checks one zip.

`--no-prompt` is not optional. `scripts/run.sh` runs under `set -e`, so a `read` that reaches
the end of input ends the run with exit 1 — and with no version argument, `--no-prompt` also
means the build uses the version phase 3 already wrote and leaves the tested-up-to lines alone.
To move those, pass `--wp-tested=X.Y` and `--wc-tested=X.Y` (major.minor only) on both commands;
otherwise they stay as they are.

### The edition argument narrows a rehearsal, never a release

**The plugin has two editions, `free` and `pro`.** `cmd_release` accepts only those two names and
exits on anything else, so the skill resolves the argument before it reaches `run.sh`:

| Typed | `$PLANS` | |
|---|---|---|
| `both`, or nothing | `pro free` | A whole release. The default. |
| `pro`, `full` | `pro` | The paid build. |
| `free`, `lite` | `free` | The wordpress.org build. |

`both` is never passed to `run.sh`; it is the word for building each edition in turn. `full` and
`lite` are aliases kept because the team says them — the deleted dev/lite was the Free edition's
branch until one branch started building both. Resolve any of these and say which plans you
resolved to in the output, so nobody is left believing there is a third edition. Anything else
aborts: name what was passed and the words accepted, and do not pass it through to `run.sh` to
fail there with a less helpful message.

`free` and `pro` **narrow** the build, and narrowing is for a rehearsal. Each build takes about a
minute, so halving it is worth having on the step you repeat most.

**On a real run, narrowing aborts** — `both` is accepted anywhere, being the default — with this
reason and not a softer one: releasing one edition alone does not work here.

- Both zips must come from the same tagged commit at the same version. Ship Free at 1.1.0 alone
  and Pro's newest zip is still 1.0.0, while `yaycheckout.php` and `readme.txt` on `dev` already
  say 1.1.0 — so the next Pro build silently carries notes for a release Pro never had.
- `readme.txt`, changelog and all, is copied into both zips unchanged. That is why the `(Pro)`
  suffix exists; one changelog cannot describe half a release.
- The leak checks are cross-edition by construction. `cmd_release` can only prove the Free zip
  carries no Pro code by building the Free zip. Build one and you have verified half.

So say: "`free` and `pro` are for `--dry-run` only; a release builds `both`", and stop. Do not
build both anyway and ignore the argument, and do not ask whether to proceed — naming one edition
means the operator expected one, and silently doing something else is worse than the abort.

A single-plan dry run is therefore a **build check, not an edition check**: it cannot test that
Pro's files are absent from Free, or that Pro carries no `apps/` source. Say that in the dry run's
output, and run the last rehearsal before a real release with both editions.

### Build before commit, and why

The build **can** rewrite tracked files: the admin-shell sync changes `composer.json` (its
`require-dev`) and regenerates `scoper.inc.php` ([docs/known-issues.md](../../../docs/known-issues.md)).
Whether it does on a given run depends on what has moved upstream — in the rehearsal that validated
this skill, neither file changed. That is exactly why the order matters: committing first would, on
the run where it *does* rewrite them, leave those changes uncommitted and outside the tag. So:
build, then discard those two, then commit. Do **not** reorder this on the grounds that it did
nothing last time.

```bash
git status --porcelain                     # see what the build actually touched
git checkout -- composer.json scoper.inc.php
composer install
```

`git checkout` discards the sync's rewrites — they are not part of the release, and known-issues
says to revert them unless they were meant. It is a no-op when the build left them alone, so run
it either way rather than deciding. `composer install` puts the dev packages back: the sync is
documented to end with `composer install --no-dev`, which leaves `composer lint` and `composer
test` failing with phpcs and PHPUnit simply gone. The `--stage` path this skill uses reported
leaving the plugin root untouched in the rehearsal and `vendor/bin/phpcs` survived, so treat this
as cheap insurance rather than a certainty — and still run it, because the known issue is live and
the failure it causes is very hard to connect back to a release. Both commands belong in the
unwind too.

### Verify the artifacts, not the exit status

`cmd_release` already checks a great deal inside the zip — Pro leaks per plan, the stripped
admin-shell and its autoloader, the text-domain rewrite, the header name, the development
constant, and `assert_staged_plan()` before zipping. **Print its output and read it.** Then add
the checks it does not make — it never asserts the version inside the zip:

```bash
for plan in $PLANS; do            # both, or the one edition a dry run narrowed to
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
  neither, and nothing under `packages/blocks/src/pro/` but `block.json` files.
- Pro contains no `apps/` or `packages/` source; Free contains both, plus `pnpm-lock.yaml`, and
  no `node_modules/`.
- Both headers carry the version from phase 3; Pro's names `YayCheckout Pro`, Free's names no Pro.

Any mismatch: stop. Nothing has been pushed yet, and a wrong zip at this point costs one rebuild.

## Phase 5 — Commit

Branch, then commit. Conventional Commits, as `CLAUDE.md` requires, with this repo's long
explanatory body:

```bash
git switch -c "trace/chore/release-$NEW"
git add yaycheckout.php readme.txt
git status --short            # confirm: nothing else staged
git commit
```

Stage files **by name**. `git add -A` would sweep in whatever the build left behind.

The subject is `chore(release): <version>`. The body says what the release contains and what was
verified, in the house style — what changed, what was checked, what was deliberately left alone —
and ends with the trailer naming the model, as `CLAUDE.md` asks:

```
chore(release): 1.1.0

<one paragraph: what a site owner gets, in the same words as the changelog entry>

Bumped the three version locations (the yaycheckout.php header and define, and readme.txt's
Stable tag) with ./scripts/run.sh bump. Built both editions with --no-prompt and checked each
zip: the version inside the header and Stable tag, the root directory and slug, the plan's
header name, and Pro's files absent from the Free zip.

The admin-shell sync's rewrites to composer.json and scoper.inc.php were reverted; they are
not part of this release.

Co-authored-by: Claude <model> <noreply@anthropic.com>
```

## Phase 6 — Gate A, then open the PR

**Stop and wait.** Print, in this order:

1. The version, and the version it replaces.
2. The changelog entry exactly as it will ship, read back out of `readme.txt`.
3. `git show --stat HEAD`.
4. Every artifact path with its byte size, and — for each — whether it will be attached in
   stage 2, with the reason.
5. That the Release will be a draft, and so that nothing becomes public until someone clicks
   Publish — including any attached Pro zip.
6. The exact commands about to run.

Then wait for the operator's answer.

> A `--dry-run` request, a plan they approved earlier, a go-ahead given before this gate, and
> this skill's own confidence that everything is fine are **none of them an answer to this
> gate**. The only answer is the operator saying yes, now, to what was just printed.

On yes:

```bash
git push -u origin "trace/chore/release-$NEW"
gh pr create --base dev --title "chore(release): $NEW" --body "<notes>"
```

The PR body carries the changelog entry and the artifact check results, and ends with the
generated-with line `CLAUDE.md` asks for. Then **stop**, and tell the operator the PR needs one
approval from someone other than its author and a squash merge — then the same
`/yayrelease <version>` picks up at stage 2.

Expect a permission prompt on the push and on `gh pr create`. That is a feature on an
outward-facing command — warn the operator it is coming so it does not look stuck, and do not
try to smooth it away.

---

## Phase 7 — Publish (stage 2)

Stage 2 runs when phase 0's state check says the release commit is merged and untagged, or when
`--publish` forces it. Re-preflight first either way; it may be hours later, on a tree that has
moved:

```bash
git switch dev && git pull --ff-only origin dev
git log --oneline -3                              # the squashed release commit is HEAD
./scripts/run.sh info                             # reports the version being released
git status --porcelain                            # empty
git tag --list "v$NEW"; git ls-remote --tags origin "v$NEW"   # both empty
gh repo view --json visibility
```

If the release commit is not `HEAD`, say so and stop: either the PR is not merged, or something
merged after it. A tag must name a commit whose tree is what was built.

**Rebuild both zips from this commit** (phase 4's commands and all of its checks). Stage 1's
zips were built from the pre-squash commit and must not be shipped — the squash gives a new
commit, and the artifact has to be the tagged tree. The rebuild writes the same two file names,
and `cmd_release` removes an existing zip before writing it, so there is no way to attach a
stale one by mistake. Re-run every phase 4 check on the rebuilt zips anyway; they are the ones
that ship.

Then tag, push, and only then create the Release:

```bash
git tag -a "v$NEW" -m "YayCheckout $NEW — released $RELEASE_DATE"
git push origin dev
git push origin "v$NEW"
git branch "released/$NEW" "v$NEW"
git push origin "released/$NEW"
```

**Push the tag before creating the Release**, so `gh` attaches to the existing tag instead of
creating one of its own at whatever the remote's default branch happens to point to.

```bash
gh release create "v$NEW" --title "v$NEW" --notes-file <notes> --draft <assets>
```

**`--draft` is always passed. This skill has no flag that publishes a Release outright**, and that
is deliberate rather than an omission:

- Making a draft live is **one click** in the GitHub UI, so an opt-out saves a click and nothing
  else. `gh release edit "v$NEW" --draft=false` does it from a terminal if you prefer.
- That click is the only moment a human looks at the finished Release before anyone else can. On
  this repository it is also the moment an attached Pro zip becomes a public download. A flag
  whose whole effect is to skip that look is not worth having, whoever asks for it.

The notes are the changelog entry. Tell the operator where the draft is and that publishing it is
that click — a draft nobody publishes is the one way this flow quietly does nothing, so it must be
said at the end of every run, not assumed.

Drafting also buys one thing worth knowing: **release creation stops being irreversible.** A draft
is visible only to people who can write to the repository, so a wrong one is deleted with
`gh release delete "v$NEW"` and created again. The tag is still permanent — see phase 8, which
marks exactly where that line now falls.

### Which assets — the one step visibility decides

The operator's standing decision is **both zips**, on the grounds that the repository is going
private. The repository was **PUBLIC** when this skill was written, and a GitHub release asset
on a public repo is a permanent, indexable, mirrorable public download. For the Free zip that is
intended. For the Pro zip it would publish paid code, and no later deletion takes it back.

So this one step reads the visibility recorded in preflight:

- **`PRIVATE`** → attach both:
  `release/yaycheckout-$NEW.zip` and `release/yaycheckout-pro-$NEW.zip`. No extra step.
- **`PUBLIC`** → attach the Free zip, and **stop before the Pro upload**. Print the repository's
  visibility, the Pro zip's path and size, and the sentence "attaching this publishes the paid
  Pro build as a public download". Attach it only if the operator says so in words, in answer to
  that. Their earlier "both zips" is not that answer — it was given on the premise that the
  repository would be private by now, and the premise is checkable, so check it.

This is not a second opinion about their decision. It is the preflight visibility check they
asked for, pointed at the only step it can protect.

**The draft does not relax this.** A draft's assets are hidden only until someone clicks Publish,
and that click carries no warning and runs no check. Treat attaching the Pro zip as publishing it,
because from the operator's next click onwards it is. This is the reason the skill has no flag for
publishing a Release outright: that click is where the decision belongs.

### After

Nothing in CI runs on tags, and no workflow builds or inspects the zip
([.github/workflows/php.yml](../../../.github/workflows/php.yml) and the JS one trigger on
`dev` pushes and PRs only). So the build's own checks plus phase 4's are the whole verification.
Do not wait for a check that will not arrive; say what was and was not verified.

Then say what is left for a person, because by default none of it has happened yet:

1. **The Release is a draft.** Give its URL and say it goes live only when someone clicks Publish
   in the GitHub UI — and that the Pro zip, if attached, becomes a public download at that moment.
2. The zips in `release/` still need uploading to wordpress.org (Free) and the store (Pro) by
   hand. That is outside this skill.

Never report a drafted Release as released.

## Phase 8 — Failure handling

Reversibility ends at the **first push in phase 7**. Everything before it is local — and because
the Release is a draft by default, the last step is reversible again: the tag is what cannot be
taken back, not the Release.

| Where it fails | Reversible | Recovery |
|---|---|---|
| Phase 0 preflight | — | Fix the condition yourself and re-run. Never stash, commit or pull to make a check pass. |
| Phase 2 changelog | yes | `git checkout -- readme.txt`. |
| Phase 3 bump | yes | `git checkout -- yaycheckout.php readme.txt`. |
| Phase 4 build fails | yes | Read the error. `scripts/free-admin-shell.patch` no longer applying means the admin-shell moved: regenerate the patch, do not skip the step. Then `git checkout -- composer.json scoper.inc.php`, `composer install`, and remove **only this version's** leftovers (see the warning under phase 9). |
| Phase 4 artifact check fails | yes | Nothing is committed. Fix the cause and rebuild. Do not edit a zip. |
| Phase 5 commit | yes | `git reset --soft HEAD~1`, then `git switch dev && git branch -D trace/chore/release-$NEW`. |
| Gate A declined | yes | Same as above. Nothing left the machine. |
| **Branch pushed / PR open** | mostly | `gh pr close` and `git push origin --delete trace/chore/release-$NEW`. Shared, but no tag and no release exist. |
| **PR merged** | no | `dev` now carries the bump. Roll forward: either publish it, or land a follow-up PR. Never rewrite `dev`. |
| `git push origin dev` fails | yes | Rejected means someone pushed first. Re-run stage 2's preflight from the top; do not force. |
| **Tag pushed** | no | The tag is public. If it is wrong, release the next patch version. Never delete or move a pushed tag, and never force-push. |
| `git push released/$NEW` fails after the tag landed | no | The tag is correct and that is what matters. Retry the branch push alone. |
| **`gh release create` fails after the pushes** | no | The git side is already correct and complete. **Retry only the release creation.** Never re-run the pushes, and never re-tag. |
| Draft Release wrong in any way | yes | It is a draft: `gh release edit "v$NEW" --notes-file <fixed>`, or `gh release delete "v$NEW"` and create it again. Deleting a draft does not touch the tag, and nobody outside the repository ever saw it. |
| **A published Release is wrong** | mostly | `gh release edit` fixes the notes and `gh release delete-asset` removes an asset, but anything already downloaded is out. Prefer leaving it and releasing the next patch version. |

Two rules sit above the table. After the first push, the answer is always to **roll forward** —
a new commit, a new patch version, an edited release — never a force-push and never a rewrite of
pushed history, which `CLAUDE.md` forbids outright. And after **any** failed or abandoned run,
`composer install`, because the build can leave `vendor/` with production packages only.

## Phase 9 — Dry run (`--dry-run`)

Run phases 0 to 5 for real. Print phase 7 in full — every command, the tag, the branch, the
assets and their sizes, the visibility decision, and that the Release would be a draft — and
execute **none** of it. Open no PR.

Given `free` or `pro` — `/yayrelease 1.1.0 pro --dry-run` — phase 4 builds that one only, and the
run says which cross-edition checks it therefore did not make (see phase 4). With `both`, or
nothing, both are built. The unwind below removes whichever zips were produced;
`rm -f` on a name that was never written is harmless, so run both lines either way.

Then unwind completely, and print the commands as they run:

```bash
git tag -d "v$NEW" 2>/dev/null                   # only if phase 7 tagged locally
git branch -D "released/$NEW" 2>/dev/null
git switch dev
git branch -D "trace/chore/release-$NEW"         # drops the release commit
git checkout -- yaycheckout.php readme.txt composer.json scoper.inc.php
rm -f "release/yaycheckout-$NEW.zip" "release/yaycheckout-pro-$NEW.zip"
rm -rf release/yaycheckout release/yaycheckout-pro   # staging dirs an aborted build left
composer install                                 # gitignored: restores the dev packages
git status --porcelain                           # must be empty
./scripts/run.sh info                            # must report the ORIGINAL version
composer lint -- yaycheckout.php                 # proves composer install took
```

> **Never `rm -rf release/*`.** That directory is gitignored, so nothing in it is recoverable
> from git, and it holds **earlier releases' zips** alongside this run's. Deleting them loses the
> exact artifacts that were shipped; the only way back is to rebuild at that version, which gives
> a functionally equal but not byte-identical zip. Remove this version's two files by name, plus
> the two staging directories a failed build can leave behind. The first rehearsal of this skill
> wiped two earlier zips with exactly that glob, which is why this paragraph is here.

The last four commands in that block — `composer install`, `git status`, `run.sh info` and
`composer lint` — are the point. `release/`, `vendor/` and the lockfiles are gitignored, so
`git reset` and `git checkout` do not touch them — a rehearsal that stops at `git status` leaves
`vendor/` without phpcs and PHPUnit, and the next `composer test` fails for a reason nobody will
connect to a dry run days earlier. Restore gitignored state explicitly, then prove it.

---

## Known gaps, named on purpose

- **`languages/yaycheckout.pot` is stale and this skill does not fix it.** Its
  `Project-Id-Version` still reads `YayCheckout - WooCommerce Checkout Builder 1.0.0`, its
  `POT-Creation-Date` is 2026-09-16, and it names a plugin title the header no longer uses. The
  release never regenerates it, `pnpm makepot` needs WP-CLI, and whether the pot should carry the
  Pro or the Free title is a product question nobody has answered. Mention it once per release and
  move on; it does not block one.
- **Nothing downstream checks the artifact.** No workflow runs on tags. The in-script checks and
  phase 4's are all there is.
- **The changelog is shared between editions.** `readme.txt` is copied into both zips unchanged,
  which is why the `(Pro)` suffix is decided mechanically rather than by feel.
