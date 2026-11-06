---
name: yayhotfix
description: Cuts a patch release from an already-shipped tag when dev cannot be released as it stands, then forward-ports the fix into dev so the next release cannot lose it. Use when the user types /yayhotfix, or says "hotfix 1.0.1", "patch the released version", "fix production", "ship an emergency fix" or "release a fix off the tag". Do not use when dev holds nothing unreleasable — that is an ordinary /yayrelease patch — and do not use to draft notes alone (/yaychangelog).
argument-hint: "[version] [--from v1.0.0] [--dry-run] [--forward-port]"
disable-model-invocation: true
allowed-tools: Read, Edit, Bash, Grep, Glob
# Purpose: the flow that ships a fix to people running a released version when dev has moved on,
#   and — the half everyone forgets — gets that fix back into dev. It owns the hotfix branch
#   model, the choice of base commit, the forward-port reconciliation rules and its own unwind
#   table. It does NOT own the changelog rules (yaychangelog) or the packaging mechanics
#   (yayrelease's references/build-and-verify.md); it invokes and reads those.
# Update when: the branch model, tag format or the released/<version> convention changes; the
#   workflows' branches: filters change, which is what decides whether a hotfix PR gets CI at
#   all; scripts/run.sh changes how bump or release works; or yayrelease's stage split changes.
---

# Hotfix a released YayCheckout version

A release goes forward from `dev`. A hotfix goes **sideways from a tag** and then has to come
back. That return trip is the whole reason this is a skill: the failure mode is not a bad zip,
it is a **fix that ships to customers and never reaches `dev`**, so the next release quietly
reintroduces the bug you just patched. Every stage here ends by saying whether the fix is in
`dev` yet, and the flow refuses to call itself finished until it is.

Everything about *packaging* is the same as a release and is not repeated: phase 6 reads
[build-and-verify.md](../yayrelease/references/build-and-verify.md), the same file
`/yayrelease` phase 4 reads. Everything about the *changelog* belongs to `/yaychangelog`, which
phase 3 invokes. What is genuinely this skill's own is the base commit, the branch model, and
phase 9.

## Why this is not a flag on `/yayrelease`

`/yayrelease` is welded to `dev` on purpose: its preflight asserts the branch is exactly `dev`,
its stage routing reads `dev`'s state, phase 6 branches off `dev` and phase 8 pushes `dev`. A
`--from` flag would make almost every phase conditional in the one skill whose value is that its
irreversible step is not. So the two flows are separate, and the packaging they share is shared
as a file rather than as a flag.

## First, check that you need a hotfix at all

**A hotfix is only correct when `dev` holds work that must not ship.** If `dev` has nothing past
the released tag, or only work that is fine to release, then `dev` *is* the released tree plus
your fix, and the ordinary flow already ships exactly that — with CI, with the normal review path,
and with no forward-port to forget. Phase 0 checks this and stops. Being told to run a hotfix is
not a reason to skip the check: a hotfix line that was not needed is pure risk with no benefit.

## New conventions this skill establishes

The repository had published exactly one release and never hotfixed anything when this was
written, so these are **[new]** and named here in one place so the team can change them:

- **[new] The hotfix line** is a branch named like hotfix/1.0.x — one per `X.Y` series — cut from
  the tag being patched. It is **transient**: it exists for the hotfix and is deleted once the
  forward-port has landed in `dev`. `CLAUDE.md` says `dev` is the repository's only long-lived
  branch, and that stays true — the tag and the released/&lt;version&gt; branch are what preserve a
  shipped tree, not the hotfix line.
- **[new] The working branch** is named like trace/fix/hotfix-1.0.1, following `CLAUDE.md`'s
  &lt;name&gt;/&lt;type&gt;/&lt;topic&gt; rule, and carries the fix, the changelog entry and the bump as **one**
  PR into the hotfix line. One PR, not two, so that the squashed commit the tag names is also the
  single commit phase 9 cherry-picks.
- **[new] The forward-port** reaches `dev` by cherry-pick through its own PR, never by merging the
  hotfix line into `dev`. `dev` is squash-merge-only and has no merge commits; a real merge would
  also drag the hotfix's version bump in whether or not it is wanted. Phase 9 is the rules.

Tag format (`v<version>`, annotated) and the released/&lt;version&gt; branch are `/yayrelease`'s
conventions, unchanged here. A hotfix produces both, exactly as a release does.

## Arguments

`argument-hint` in the frontmatter is display text with no parser behind it. It lists only what
**varies**. **Keep the hint and this table in step**, because a stale hint is worse than none:

| Argument | Meaning |
|---|---|
| `<version>` | The patch version to ship, `X.Y.Z`. Omitted, phase 1 proposes the next patch of the base tag and asks. |
| `--from <tag>` | The tag to branch from. Omitted, the newest version tag by `sort -V`. |
| `--dry-run` | Run phases 0 to 6 for real up to Gate A, print phases 7 and 8 and execute none of them, then unwind. |
| `--forward-port` | Force **stage 3 only**: the fix is already tagged and shipped, and only `dev` is outstanding. |

**Nothing parses any of this.** The whole line after the skill name arrives as one string of free
text, and the table above is the only thing that gives it meaning. So read it generously: `--from
1.0.0` and `--from v1.0.0` are one request, "hotfix the 1.0.0 release with 1.0.1" names both
values, quotes are readability rather than syntax, and an obvious misspelling of a flag is that
flag. Being generous about *reading* is not being generous about *acting* — when a value is
genuinely ambiguous, say what was passed and ask; never invent one, and never widen what was
asked for.

It is also why nothing irreversible rests on a flag here. Phase 0 reads which stage to run from
the repository rather than from `--forward-port`, the base tag is resolved and named back in the
output before anything is cut from it, and Gate A and Gate B stop the run whatever was typed.

**Accepted, but not in the hint, because they change nothing.** Take them without complaint and
say what the behaviour already is:

- `both` — a hotfix always builds both editions. `free` and `pro` narrow a `--dry-run` only, with
  the same abort as a release (phase 6).
- `--draft`, `--no-draft` — the Release is **always** a draft, for the reasons `/yayrelease`
  phase 8 gives. There is no flag that publishes one outright.

## Three stages that resume

A hotfix stops twice for a human and must survive being picked up hours later, so like
`/yayrelease` it is **resumable, and which stage runs is read from the repository, not from a
flag.** After phase 0:

| State | Run |
|---|---|
| No hotfix PR for this version, and `v<version>` untagged | **Stage 1** — phases 1 to 7: the fix, the changelog, the bump, the commit, both zips, Gate A, the PR into the hotfix line. |
| The hotfix PR is open and unmerged | Neither. Say it is awaiting review, link it, and stop. |
| The hotfix PR is merged into the hotfix line, and `v<version>` untagged locally and on the remote | **Stage 2** — phase 8: rebuild from the squashed commit, Gate B, tag, push, released branch, draft Release. |
| `v<version>` is tagged, and its fix is **not** in `dev` | **Stage 3** — phase 9: the forward-port. This is the state to detect loudly; see phase 9 for how to test it. |
| `v<version>` is tagged and its fix **is** in `dev` | Done. Say so, and say whether the draft Release was ever published. |

Keep a todo list with one item per phase, so the operator can see where the flow is — and so that
an interrupted hotfix shows which side of phase 9 it stopped on.

---

## Phase 0 — Preflight

Every check aborts with its own message. **Never make a check pass on the operator's behalf** —
no stashing, no committing, no pulling, no `git clean`. Report and stop.

```bash
cd "$(git rev-parse --show-toplevel)"
git status --porcelain                            # must be empty
git fetch origin 'refs/tags/*:refs/tags/*' dev
BASE=${FROM:-$(git tag --list 'v*' | sort -V | tail -1)}    # the tag being patched
git rev-parse -q --verify "refs/tags/$BASE"       # exists locally
git ls-remote --tags origin "$BASE"               # and on the remote
git log -1 --format='%H %s' "$BASE^{commit}"      # name it back to the operator
git rev-list --count "$BASE..origin/dev"          # see "do you need a hotfix" below
./scripts/run.sh info                             # the version dev currently claims
git tag --list 'v*'; git ls-remote --tags origin 'v*'
gh auth status
gh repo view --json visibility,nameWithOwner
command -v composer php zip unzip perl patch git gh
php -r 'exit(version_compare(PHP_VERSION, "8.2", ">=") ? 0 : 1);'
node --version                                    # must satisfy .nvmrc (22); run `nvm use` if not
pnpm --version || corepack pnpm --version
```

Rules for reading those:

- **Clean tree.** Must be empty. A hotfix that starts dirty ships files the tag does not cover.
- **Fetch tags explicitly.** A plain `git fetch origin dev` does not update tags that were pushed
  from another machine, and this whole flow is anchored to a tag. Fetching the refspec above means
  the base you resolve is the base that shipped.
- **The base must be a tag, not a branch.** Resolve `--from` to a tag and say which commit it
  names. The released/&lt;version&gt; branch usually points at the same commit and is the friendlier
  name, but a branch can be moved and a tag is what shipped by definition. Use the released branch
  only if the tag is somehow missing, and say that you did.
- **Do you need a hotfix at all?** If the count of commits in the base-to-origin/dev range is
  **0**, stop: `dev` is the released tree, so run `/yayrelease <patch>` instead and get CI, the
  normal review path and no forward-port. If it is non-zero, print those subjects
  (`git log --oneline "$BASE..origin/dev"`) and ask the operator to confirm that work must not
  ship yet. Their confirmation is what justifies the hotfix line; "the user asked for a hotfix" is
  not, because they may not know `dev` is clean.
- **Not already tagged.** Check the new version local **and** remote. A tag that exists only on
  the remote is invisible to `git tag`, and the push in phase 8 would then fail after the branch
  push had already landed.
- **Visibility.** Record it. It decides one step in phase 8 and only that step.
- **Then route.** Say which stage you are entering and why, in one line, before phase 1.

### The check only a hotfix needs: has the packaging moved?

A hotfix builds from the **old** tree, which means it builds with the **old** `scripts/run.sh`,
the old `scoper.inc.php` and the old edition files. If packaging has been fixed since the tag, a
zip built from the tag reships the bugs those fixes removed:

```bash
git log --oneline "$BASE..origin/dev" -- scripts/ editions/ scoper.inc.php composer.json composer.lock
```

Empty is the happy case — the packaging that shipped is still the packaging you want. Anything
listed is a decision, not a warning to log and move past. Print the subjects and ask: cherry-pick
those packaging commits onto the hotfix line as well, or ship knowingly with the old packaging.
Either answer is defensible; choosing silently is not. This exact class of bug is why the check
exists — [#266](https://github.com/YayCommerce/YayCheckout/pull/266) and
[#267](https://github.com/YayCommerce/YayCheckout/pull/267) each removed files that both zips had
been shipping, and a hotfix cut from a tag before them would have put them straight back.

### Compute the date once

```bash
RELEASE_DATE=$(LC_ALL=C date +'%b %e, %Y' | tr -s ' ')      # Nov 6, 2026
```

Two consumers — the changelog heading phase 3 writes and the tag annotation in phase 8 — which is
why it is computed once and passed on. A run that straddles midnight would otherwise ship a
changelog and a tag that disagree about the day it happened. `LC_ALL=C` pins the month to English,
`%e` is the portable unpadded day (`%-d` is GNU-only and this tooling must run under macOS's BSD
`date`), and `tr -s ' '` squeezes the padding `%e` leaves on days 1 to 9. The
[changelog style file](../yaychangelog/references/changelog-style.md) explains each.

Finally, read [docs/known-issues.md](../../../docs/known-issues.md) before this run.

## Phase 1 — Version

The source of truth is the `YAYCHECKOUT_VERSION` define in `yaycheckout.php` — but on a hotfix,
read it **from the base tag**, not from the working tree, which may be sitting on a `dev` that
claims something higher:

```bash
git show "$BASE:yaycheckout.php" | grep -m1 "YAYCHECKOUT_VERSION"
```

A hotfix is a **patch** bump of that: 1.0.0 becomes 1.0.1, 1.0.1 becomes 1.0.2. Propose it and
say so. Assert it is strictly greater than the base, and that the shape satisfies
`validate_version` in `scripts/run.sh`:

```bash
[ "$(printf '%s\n%s\n' "$BASEV" "$NEW" | sort -V | tail -1)" = "$NEW" ] && [ "$BASEV" != "$NEW" ]
```

If the operator asks for a minor or major bump off a tag, say what that means and let them
choose: a hotfix is defined by shipping *only* a fix to people already on the released version,
and a new capability off an old tag is a release, not a hotfix. It is their call, not a refusal —
but make them make it.

**Do not compare against `dev`'s version, and do not require the new version to exceed it.** It is
normal and correct for `dev` to claim 1.1.0 while you ship 1.0.1. Phase 9 reconciles that; phase 1
must not treat it as an error.

Do not bump anything here. Phase 4 writes.

## Phase 2 — The fix

Cut the lines and get the fix in, before any release machinery:

```bash
git switch -c hotfix/1.0.x "$BASE"       # the transient hotfix line, if it does not exist yet
git switch -c trace/fix/hotfix-"$NEW"    # the working branch, where the fix is written
```

**Author the fix here, against the old tree.** When `dev` has moved, the code around the bug may
have changed, and what matters is that the fix is correct in the tree customers are running. The
inverse — fixing it on `dev` and back-porting — makes the untested direction the one people get.

If the fix already exists as a commit on `dev`, cherry-pick it rather than rewriting it:

```bash
git cherry-pick -x <sha>                 # -x records where it came from
```

and expect conflicts in proportion to how far `dev` has moved. Resolve them for the **old**
tree's shape, not by importing `dev`'s surrounding code.

**Commit the fix here, on its own**, staging by name. Phase 5 commits the release machinery
separately, which keeps the two legible while a reviewer reads the PR; the squash merge joins
them. It also means phase 6 builds from a clean tree, which is the only way the Free zip builds
at all.

**Scope discipline is the point of a hotfix.** It carries the fix and nothing else — no
refactors, no drive-by tidying, no dependency bumps, and nothing from `dev` that happens to be
nearby. Anything beyond the fix is a reason the patch needs reviewing as if it were a release, and
it defeats the only argument for shipping off an old tag. The one exception is the packaging
commits phase 0 may have identified, taken deliberately.

### Run the checks locally anyway

Both workflows' base-branch filters cover `dev` **and** the hotfix lines
([.github/workflows/php.yml](../../../.github/workflows/php.yml),
[.github/workflows/js.yml](../../../.github/workflows/js.yml)), so a hotfix PR does get CI — that
filter was widened for exactly this flow, because a hotfix reaches customers without passing
through `dev`. Do not assume it arrives, though: the paths filters still apply, so a fix outside
them triggers nothing at all, and a hotfix is the run you least want to spend waiting. Run the
equivalents locally and print the output in the PR body:

```bash
scripts/check-pro-boundary.sh
composer lint -- <the files you changed>
php -l <each changed PHP file>
```

And read hard rule 4 in `CLAUDE.md` before writing a line of product PHP: it must run on PHP 7.4,
no enums, `match`, `readonly`, promoted properties, `?->`, named arguments or union types. Nothing
local catches that — the dev tooling runs PHP 8 — so on a hotfix, with no CI either, **you** are
the check.

### A test, if one is cheap

`CLAUDE.md` names "a fixed bug that a test reproduces cheaply" as one of exactly two cases where a
test is warranted, and a bug that reached customers is the clearest example there is. So ask, with
AskUserQuestion, naming the test, its file and suite, and what it guards. Write it only if they
accept. If they decline, record the exact manual check instead — the command or UI action and the
result that means it passed — in the commit body, because this is a fix whose regression would be
the second time.

Add the test on the **forward-port** in phase 9 rather than to the hotfix zip when it is anything
more than trivial: `tests/` is not shipped in either edition, so a test adds nothing to the
artifact, and keeping the hotfix minimal is worth more than keeping it symmetrical. Say which you
chose.

## Phase 3 — Changelog, through `/yaychangelog`

**This skill does not own the changelog rules.** Invoke `/yaychangelog` with the new version, the
range from the base tag, and `--write`:

```
/yaychangelog <version> <base tag>..HEAD --write --date "$RELEASE_DATE"
```

Pass the range explicitly. Left to itself the changelog skill resolves the newest `v*` tag to
HEAD, and on a hotfix line the newest tag may not be the one you branched from. Pass `--date`
too, for the reason phase 0 computes it once.

**If `/yaychangelog` is not available**, do not stop and do not improvise: read
[its style file](../yaychangelog/references/changelog-style.md) and follow it here, then carry on.

Two things stay this skill's business:

- **Show the operator the entry and let them edit it.** The changelog is the one part no later
  step can check.
- **A hotfix with no user-facing bullet is a contradiction.** If the screen finds nothing a site
  owner would notice, stop and say so: either the fix is invisible to site owners, in which case it
  does not need shipping off a tag at all, or the screen is wrong about it. Resolve that before
  bumping.

Expect the entry to be short — usually one `- Fixed:` line. That is what a hotfix should look
like, and a long entry is a signal that phase 2's scope discipline slipped.

## Phase 4 — Bump

```bash
./scripts/run.sh bump "$NEW"
```

One command, all three live locations, with the "do not touch" list and the self-check in
`/yayrelease` [phase 3](../yayrelease/SKILL.md) — which is unchanged here and not repeated.
Run its self-check verbatim, including the last line:

```bash
git diff -U0 | grep -E '^\+.*(@since|@deprecated)'        # must print NOTHING
```

One addition for a hotfix: the bumped tree must still claim the **base** lineage. Confirm
`./scripts/run.sh info` reports the new patch version and not `dev`'s, which is the symptom of
having cut the line from the wrong place.

## Phase 5 — Commit the changelog and bump

**Before the build, not after.** The Free build hard-fails on a dirty tree and the bump has just
made the tree dirty; [build-and-verify.md](../yayrelease/references/build-and-verify.md) sets out
both constraints and why this is the only order that satisfies them. Do not reorder phases 5 and
6.

```bash
git add yaycheckout.php readme.txt
git status --short            # confirm: nothing else staged
git commit
```

Stage files **by name**. The fix is already committed from phase 2, so this commit is the release
machinery alone — which also keeps the two legible to a reviewer, and the PR squashes them into
one commit anyway.

Subject `chore(release): <version>`, Conventional Commits as `CLAUDE.md` requires, and a body
that says what the hotfix fixes, what was checked, and — because this is the thing a reviewer
cannot see — **that it was cut from the base tag and carries nothing else**:

```
chore(release): 1.0.1

<one paragraph: what broke for site owners and what now happens instead, in the same words as
the changelog entry>

Cut from v1.0.0, not from dev, because <the dev work that must not ship yet>. The range
v1.0.0..HEAD holds the fix, this changelog entry and the bump, and nothing else.

Bumped the three version locations with ./scripts/run.sh bump. Ran check-pro-boundary.sh,
composer lint and php -l locally. <The manual check or the test that guards the regression.>

Co-authored-by: Claude <model> <noreply@anthropic.com>
```

## Phase 6 — Build

Follow [build-and-verify.md](../yayrelease/references/build-and-verify.md) exactly: the build
commands, `--no-prompt`, the edition resolution, the two tracked files the build can rewrite and
the build-before-commit order that implies, and every artifact check. That file is shared with
`/yayrelease` phase 4 so the packaging rules exist once.

What is this skill's own:

- **Both editions, always.** A hotfix has the same cross-edition obligation as a release: both
  zips from the same tagged commit at the same version, one shared `readme.txt`, and leak checks
  that only work when both are built. `free` or `pro` narrow a `--dry-run`; on a real run they
  abort with the reference's reasons.
- **Stage 1's zips are discarded.** Phase 8 rebuilds from the squashed commit, exactly as a
  release does. Never attach a zip built before the merge.
- **Check the base, not just the version.** Add one assertion the reference does not make,
  because it is the mistake only a hotfix can make — that the zip was built from the hotfix
  lineage and not from `dev`:

```bash
git merge-base --is-ancestor "$BASE" HEAD && echo "built on top of $BASE"
git log --oneline "$BASE..HEAD"            # the fix, the changelog and the bump. Nothing else.
```

That second command is the scope check from phase 2, enforced. A commit in that list you cannot
account for means something from `dev` came along, and the zip is not a hotfix.

## Phase 7 — Gate A, then open the PR

**Stop and wait.** Print, in this order:

1. The new version, the base tag, and the commit the base names.
2. The commits in the base-to-HEAD range — the whole content of this hotfix.
3. The changelog entry exactly as it will ship, read back out of `readme.txt`.
4. Every artifact path with its byte size, and whether it will be attached in phase 8.
5. That the Release will be a draft, so nothing becomes public until someone clicks Publish.
6. **That `dev` will not have this fix until phase 9**, and that phase 9 is a separate run.
7. The exact commands about to run.

Then wait for the operator's answer.

> A `--dry-run` request, a plan they approved earlier, a go-ahead given before this gate, and
> this skill's own confidence that everything is fine are **none of them an answer to this
> gate**. The only answer is the operator saying yes, now, to what was just printed.

On yes:

```bash
git push -u origin hotfix/1.0.x                      # the base must exist on the remote first
git push -u origin trace/fix/hotfix-"$NEW"
gh pr create --base hotfix/1.0.x --title "fix: <what broke> ($NEW)" --body "<notes>"
```

The base is the hotfix line, **not `dev`** — this PR is the one that ships, so this is where
review belongs. `CLAUDE.md`'s rule that every change lands through a PR into `dev` is satisfied by
phase 9, and the PR body should say so in a line, so a reviewer is not left wondering why the base
is unfamiliar.

The body carries the changelog entry, the artifact checks, phase 2's local lint and `php -l`
output, and the generated-with line `CLAUDE.md` asks for. Say whether CI ran: the filters cover
this base, but the paths filters may skip it, and a reviewer should not have to guess which
checks a hotfix actually passed. Then
**stop**: the PR needs one approval from someone other than its author and a squash merge, after
which the same `/yayhotfix <version>` picks up at stage 2.

Expect a permission prompt on each push and on `gh pr create`. That is a feature on an
outward-facing command — warn the operator it is coming so it does not look stuck.

---

## Phase 8 — Publish (stage 2)

Re-preflight first; it may be hours later:

```bash
git switch hotfix/1.0.x && git pull --ff-only origin hotfix/1.0.x
git log --oneline -3                              # the squashed hotfix commit is HEAD
./scripts/run.sh info                             # reports $NEW
git status --porcelain                            # empty
git tag --list "v$NEW"; git ls-remote --tags origin "v$NEW"    # both empty
git merge-base --is-ancestor "$BASE" HEAD         # still the right lineage
gh repo view --json visibility
```

If the squashed commit is not HEAD, say so and stop. A tag must name a commit whose tree is what
was built.

**Rebuild both zips from this commit**, with every check in
[build-and-verify.md](../yayrelease/references/build-and-verify.md). Stage 1's zips came from the
pre-squash commit and must not ship.

**Gate B.** Print the tag, the branch, the assets and sizes, and the exact commands, and stop and
wait — the same gate `/yayrelease` phase 8 keeps, for the same reason. Then:

```bash
git tag -a "v$NEW" -m "YayCheckout $NEW — hotfix of ${BASE#v}, released $RELEASE_DATE"
git push origin hotfix/1.0.x
git push origin "v$NEW"
git branch "released/$NEW" "v$NEW"
git push origin "released/$NEW"
gh release create "v$NEW" --title "v$NEW" --notes-file <notes> --draft <assets>
```

**Push the tag before creating the Release**, so `gh` attaches to the existing tag instead of
creating one of its own at whatever the remote's default branch points to — which on a hotfix
would be `dev`, a tree that does not contain this fix. That is a worse outcome here than on a
release, where the default branch at least holds the right code.

Note what this does **not** touch: `dev` is not pushed, because nothing on it changed. A release
pushes `dev` in this step; a hotfix cannot, and phase 9 is why.

**Assets** follow `/yayrelease` phase 8's rule exactly, since it is the same decision: visibility
`PRIVATE` attaches both zips, `PUBLIC` attaches Free and stops before the Pro upload to ask in
words, because a public release asset is a permanent public download of paid code. Read the
visibility phase 0 recorded rather than remembering what it was last time.

**The Release is a draft.** Give its URL, say it goes live only when someone clicks Publish, and
say that an attached Pro zip becomes a public download at that moment. Never report a drafted
Release as released.

### Then say, every time, that `dev` does not have this fix

End stage 2 by printing the forward-port as outstanding work, with the command that does it:
`/yayhotfix <version> --forward-port`. This is the one sentence that must not be dropped on a
rushed run. A shipped hotfix that never reaches `dev` is not a smaller problem than the bug it
fixed — it is the same bug, scheduled.

Also still outstanding, and outside this skill: uploading the Free zip to wordpress.org (the
`Stable tag:` there must move to the new version) and the Pro zip to the store.

## Phase 9 — Forward-port into `dev` (stage 3)

The point of the whole skill. Detect the state honestly first — "is the fix in `dev`" is not
answered by the version number, since `dev`'s version tells you nothing about which commits it
contains:

```bash
git fetch origin dev
git log --oneline --cherry-pick --right-only "origin/dev...v$NEW"
```

What that prints is the commits present on the tag and **not** in `origin/dev`, with anything
already cherry-picked across excluded by patch-id. Empty means the fix is in `dev` and stage 3 is
done. Non-empty is the work. Do not substitute `git branch --contains` or a version comparison:
after a squash merge the SHAs differ, and only the patch-id comparison gets the right answer.

```bash
git switch dev && git pull --ff-only origin dev
DEVV=$(./scripts/run.sh info)                     # what dev claims BEFORE the port
git switch -c trace/fix/forward-port-"$NEW"
git cherry-pick -x <the squashed hotfix commit>
```

`-x` records the commit it came from, so the provenance survives into the squashed body on `dev`.
Then reconcile the three things the cherry-pick cannot get right by itself:

**1. The version.** The hotfix commit bumps to the patch version, and whether that is right for
`dev` depends on where `dev` already was:

| `dev` claimed | Do |
|---|---|
| The base version (e.g. 1.0.0, having never been bumped past it) | Keep the bump. `dev` should now claim the patch version, so the next release is strictly greater than what shipped. |
| A higher version already (e.g. 1.1.0 bumped by a release in flight) | Restore it: `./scripts/run.sh bump "$DEVV"`. The hotfix's bump is not wanted on `dev`. |

The rule in one line: **`dev` ends up claiming the greater of the two.** Never let it end up
claiming less than what has shipped — that is what makes `/yayrelease`'s "strictly greater"
assertion a lie, and it lets someone cut the same version twice.

**2. The changelog entry's position.** On the hotfix line the new entry is at the top of
`== Changelog ==`, which is where the cherry-pick will put it. On `dev` it belongs in version
order — **below** any entry for a higher unreleased version:

```bash
grep -n '^= ' readme.txt | head -5
```

If the first heading after the port is a higher version than the one you just shipped, move the
hotfix's block below it. Expect a conflict in `readme.txt` whenever `dev` has its own newer entry;
resolve it by that rule rather than by taking a side wholesale. Keep both entries — the heading
format and bullet labels are the
[changelog style](../yaychangelog/references/changelog-style.md)'s, unchanged.

**3. The test, if phase 2 deferred it.** Add it here, in `tests/Integration/`, with the
AskUserQuestion acceptance phase 2 describes if it has not already been given.

Then the normal path, because this one is an ordinary change to `dev` and gets the full treatment
the hotfix PR could not:

```bash
git status --short                                 # stage by name; nothing the build left behind
composer lint -- <the files you changed>
git push -u origin trace/fix/forward-port-"$NEW"
gh pr create --base dev --title "fix: <what broke> (forward-port of $NEW)" --body "<notes>"
```

The body says what shipped as the hotfix, links the hotfix PR and the tag, and states which of
the three reconciliations applied — especially whether the version bump was kept or restored,
since that is the one a reviewer cannot infer from the diff.

### After it merges

```bash
git fetch origin dev
git log --oneline --cherry-pick --right-only "origin/dev...v$NEW"   # must now be EMPTY
git push origin --delete hotfix/1.0.x
git branch -D hotfix/1.0.x trace/fix/hotfix-"$NEW" trace/fix/forward-port-"$NEW"
```

Prove it with that first command before deleting anything. Then the hotfix line is finished: the
tag and released/&lt;version&gt; preserve the shipped tree, so the branch has nothing left to hold. If
a 1.0.2 follows later, cut a fresh line from the **newest** tag in the series, not from the one
you just deleted.

Only now is the hotfix complete. Say so explicitly, and say whether the draft Release was
published and whether the zips were uploaded — because those are still a person's job.

## Phase 10 — Failure handling

Reversibility ends at the **first push in phase 7**, and the tag in phase 8 is the line nothing
crosses back over.

| Where it fails | Reversible | Recovery |
|---|---|---|
| Phase 0 preflight | — | Fix the condition and re-run. Never stash, commit or pull to make a check pass. |
| Phase 0 says a hotfix is not needed | — | Run `/yayrelease <patch>` instead. Do not override this by cutting the line anyway. |
| Phase 2, the fix is wrong or too broad | yes | Nothing has left the machine. Reset the working branch and start the fix again. |
| Phase 3 changelog | yes | `git checkout -- readme.txt`. |
| Phase 4 bump | yes | `git checkout -- yaycheckout.php readme.txt`. |
| Phase 5 commit | yes | `git reset --soft HEAD~1`. The fix's own commit from phase 2 is untouched. |
| Phase 6 build fails | yes | Read the error. `scripts/free-admin-shell.patch` not applying on an old tree is the expected hotfix failure — the admin-shell moved after the tag; regenerate the patch on the hotfix line, and treat it as a packaging commit phase 0's check should have caught. Then `git checkout -- composer.json scoper.inc.php`, `composer install`, and remove **only this version's** zips by name. |
| Phase 6 artifact check fails | yes | The fix and the bump are committed, but nothing has been pushed. Fix the cause and rebuild; amend if the fix touches a tracked file. Never edit a zip. |
| Gate A declined | yes | `git switch dev`, then delete both branches. Nothing left the machine. |
| **Hotfix PR open** | mostly | `gh pr close`, delete both remote branches. Shared, but no tag and no release exist. |
| **Hotfix PR merged** | no | The hotfix line carries the fix and the bump. Roll forward: publish it, or land a follow-up PR on the line. Never rewrite it after phase 8 has tagged it. |
| **Tag pushed** | no | Permanent. If it is wrong, ship the next patch. Never delete or move a pushed tag, and never force-push. |
| `git push released/$NEW` fails after the tag landed | no | The tag is correct and that is what matters. Retry the branch push alone. |
| **`gh release create` fails after the pushes** | no | The git side is already correct. **Retry only the release creation.** Never re-run the pushes, and never re-tag. |
| Draft Release wrong in any way | yes | It is a draft: `gh release edit`, or `gh release delete "v$NEW"` and create it again. Deleting a draft does not touch the tag. |
| **Phase 9 cherry-pick conflicts badly** | yes | `git cherry-pick --abort`. Nothing on `dev` moved. Port it by hand on a fresh branch if the conflict is structural — the fix matters, the commit's shape does not. |
| **Phase 9 never ran** | yes, and urgently | The fix is shipped and `dev` does not have it. Run `/yayhotfix <version> --forward-port`. This is the one failure here with no deadline and real cost: it surfaces as the bug returning in the next release. |
| Forward-port PR merged, hotfix line already deleted | — | Fine. Nothing needs the line once the detection command prints empty. |

Two rules sit above the table. After the first push the answer is always to **roll forward** — a
new commit, a new patch, an edited draft — never a force-push and never a rewrite of pushed
history, which `CLAUDE.md` forbids outright. And after **any** failed or abandoned run,
`composer install`, because the build can leave `vendor/` with production packages only.

## Phase 11 — Dry run (`--dry-run`)

Run phases 0 to 6 for real, stop at Gate A without pushing, then print phases 8 and 9 in full —
the tag, the branch, the assets and their sizes, the visibility decision, the forward-port
commands and the reconciliation that would apply to today's `dev` — and execute **none** of it.
Open no PR.

A dry run is worth more here than on a release, because the thing most likely to be wrong is
whether the fix even applies to the old tree, and that is answered by phases 2 and 6 alone.

Given `free` or `pro`, phase 6 builds that one only and the run says which cross-edition checks it
therefore did not make. Then unwind completely, printing the commands as they run:

```bash
git tag -d "v$NEW" 2>/dev/null                   # only if a rehearsal tagged locally
git branch -D "released/$NEW" 2>/dev/null
git switch dev
git branch -D trace/fix/hotfix-"$NEW" hotfix/1.0.x
git checkout -- yaycheckout.php readme.txt composer.json scoper.inc.php
rm -f "release/yaycheckout-$NEW.zip" "release/yaycheckout-pro-$NEW.zip"
rm -rf release/yaycheckout release/yaycheckout-pro   # staging dirs an aborted build left
composer install                                 # gitignored: restores the dev packages
git status --porcelain                           # must be empty
./scripts/run.sh info                            # must report dev's ORIGINAL version
composer lint -- yaycheckout.php                 # proves composer install took
```

> **Never `rm -rf release/*`.** That directory is gitignored, so nothing in it is recoverable from
> git, and it holds **earlier releases' zips** alongside this run's — including the ones that
> actually shipped to customers, which is exactly what a hotfix exists to patch. Remove this
> version's two files by name, plus the two staging directories a failed build can leave. The
> first rehearsal of `/yayrelease` wiped two earlier zips with that glob, which is why this
> paragraph is in both skills.

The last four commands are the point: `release/`, `vendor/` and the lockfiles are gitignored, so
`git reset` and `git checkout` do not touch them. Restore gitignored state explicitly, then prove
it.

---

## Known gaps, named on purpose

- **The publish and forward-port phases have never been exercised.** This repository has one
  release, tagged 1.0.0, and has never hotfixed anything. Phases 7 and 8 are written from
  `/yayrelease`'s rehearsed phase 8 and from the git behaviour the detection commands rely on,
  not from a hotfix that happened. Treat the first real run as the rehearsal, and fix what it
  exposes.
- **CI on a hotfix line is untested.** The workflows' base-branch filters were widened to cover
  hotfix branches when this skill was written, so a hotfix PR should get the same `php -l` on PHP
  7.4 and Pro-boundary check a `dev` PR gets — but no hotfix PR has ever been opened, so that
  filter has never actually fired. Check that the run appears on the first real hotfix, and fall
  back on phase 2's local checks if it does not.
- **`languages/yaycheckout.pot` is stale and this skill does not fix it**, exactly as a release
  does not. Mention it once and move on.
- **Nothing downstream checks the artifact.** No workflow runs on tags. The in-script checks and
  the reference's are all there is.
