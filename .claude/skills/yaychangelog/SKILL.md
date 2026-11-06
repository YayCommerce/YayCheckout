---
name: yaychangelog
description: Turns a commit range into readme.txt changelog entries in this plugin's house style, screening out what no site owner would notice and marking Pro-only bullets. Use when the user says "draft the changelog", "prepare release notes", "what changed since 1.0.0", "write the release notes", "update readme.txt's changelog" or mentions YayChangelog. /yayrelease calls it for the release it is cutting. Do not use to bump a version, build a zip or tag anything: that is /yayrelease.
argument-hint: "[version] [<git range>] [--write] [--date 'Mon D, YYYY']"
# Purpose: drafts and writes the readme.txt changelog entry for a commit range. It owns the
#   changelog house style (references/changelog-style.md) and the commit screen
#   (scripts/first-pass.sh), which /yayrelease's phase 2 calls rather than copying. This header
#   covers the whole skill directory.
# Update when: the changelog's heading or bullet shape changes, the Pro block or template lists
#   move, or a path stops or starts being able to produce a user-visible change (keep
#   scripts/first-pass.sh in step with references/changelog-style.md).
---

# YayChangelog: draft the release notes

Release notes live in one place: the `== Changelog ==` section at the end of `readme.txt`. There
is no CHANGELOG.md, and nothing else in the repo carries them.

This skill drafts an entry, and writes it when asked. It **never** bumps a version, builds a zip,
commits, tags or pushes — all of that belongs to `/yayrelease`, which calls this skill for the
release it is cutting. So it is safe to run mid-cycle, as often as you like, just to see what has
accumulated.

Read [references/changelog-style.md](references/changelog-style.md) before writing a single
bullet. It is the authority on what earns an entry, what to drop, how to group and split, the
`(Pro)` rule and the sentence style. This file is the workflow; that file is the rules.

## Arguments

`argument-hint` in the frontmatter is display text with no parser behind it. These are the real
arguments — **keep the two in step**, because a stale hint is worse than none:

| Argument | Meaning |
|---|---|
| `<version>` | The heading to write the entry under, as `X.Y.Z`. Without one, the draft is printed and nothing is written. |
| `<git range>` | Any range `git log` takes, such as `v1.0.0..HEAD`. Default: the newest `v*` tag to HEAD, or the whole history when there is no version tag. |
| `--write` | Write the entry into `readme.txt`. Needs a version; without one, say so and print the draft instead. |
| `--date 'Mon D, YYYY'` | The release date for the heading. `/yayrelease` passes the date it computed once in its preflight, so the heading and the tag annotation cannot disagree. Omitted, this skill uses today's: `LC_ALL=C date +'%b %e, %Y' \| tr -s ' '`. |

Default behaviour is to print, not to write. A draft costs nothing; a bad entry in `readme.txt`
is read by every site owner in their updates screen.

## 1. Collect and screen the range

```bash
PREV=$(git tag --list 'v*' --sort=-v:refname | head -1)
RANGE=${PREV:+$PREV..HEAD}
bash .claude/skills/yaychangelog/scripts/first-pass.sh ${RANGE:-HEAD}
```

[scripts/first-pass.sh](scripts/first-pass.sh) sorts each commit into `DROP`, `PRO?` or
`BULLET?` by the paths it touches.

- `DROP` is reliable. Nothing under those paths can reach a site owner; do not read them.
- `BULLET?` and `PRO?` both need the commit body read.
- `PRO?` means only that the commit touched a Pro directory. It is **not** the verdict on the
  suffix; the style file's three lists decide that.

Say which range you used. When there is no previous version tag — still true until the first
release is cut — the range is the whole history, and most of it drops.

## 2. Read the bodies

```bash
git log --no-merges --format='--- %h %s%n%b' ${RANGE:-HEAD}
```

Read the **bodies**, not only the subjects. This repo writes long bodies that state the
user-visible symptom, what was verified and what was deliberately left alone. The subject is
usually written for a reviewer; the symptom in the body is usually the bullet.

## 3. Write the bullets

Apply the style file. Every bullet is `- ` — a hyphen, not an asterisk — then one of exactly
three labels: `Added:`,
`Updated:` or `Fixed:` — then one sentence; the style file's table says which label and why it is
decided from what a site owner sees rather than from the commit's type.

The parts most often got wrong, in order of how often:

1. **A bug introduced and fixed inside the same range is not a bullet.** The site owner saw
   neither. Check this before anything else; it removes more candidate bullets than any other rule.
2. **Commits and bullets are not one to one.** A feature plus its three follow-up fixes is one
   bullet, written in its finished state.
3. **The previous release's own commit drops**, even though the screen reports it as `BULLET?`
   because it edits `readme.txt` and `yaycheckout.php`.
4. **Refactors drop**, however large and wherever they sit.

## 4. Decide each suffix from the code

`readme.txt` is copied into **both** zips unchanged, so one changelog is read by Free and Pro site
owners alike. A Pro-only bullet with no suffix advertises to a Free site owner something their copy
does not have.

The verdict comes from the code, never from the diff: `BlockRegistry::PRO_BLOCKS` in
`includes/Blocks/BlockRegistry.php` for a block, the catalogue's `'pro' => true` in
`includes/Controllers/LayoutTemplateCatalogue.php` for a template, and the implementing files'
location for anything else. The style file has the three lists in full, and says why the obvious
path-only rule was removed after firing zero times across 25 commits.

## 5. Output

**Without `--write`,** print the entry as it would appear, plus one line naming the range and the
counts the screen reported. Change no file. Offer to write it.

**With `--write` and a version,** insert it into `readme.txt` immediately below `== Changelog ==`
and above the previous heading, under a heading of the shape
`= <Mon D, YYYY> - Version <version> =`, then check:

```bash
grep -n '^= ' readme.txt | head -5        # the new entry is first
V=$(printf '%s' "$NEW" | sed 's/\./\\./g')
grep -cE "^= [A-Z][a-z]{2} ([1-9]|[12][0-9]|3[01]), [0-9]{4} - Version $V =\$" readme.txt   # exactly 1
git diff --stat readme.txt                # readme.txt only
```

That middle one asserts the whole heading shape, not just the version, and each part of it earns
its place — it was written once with `[0-9]{1,2}` for the day and **passed a padded `Nov 06`**,
which is why the day is spelled out as `[1-9]|[12][0-9]|3[01]`: unpadded means no leading zero,
and a character class cannot say that. It also rejects a localised month (`nov.`), a missing
`Version `, and a date format pasted from another plugin. The `V` line escapes the version's dots,
which are otherwise regex wildcards.

Then read the diff once and check all of it:

- The heading carries the release date and the version, in that order, in the shape above. The
  day is unpadded and the month is the English three-letter abbreviation.
- Every new bullet starts `- ` and then `Added: `, `Updated: ` or `Fixed: `, and nothing else.
  A `* ` bullet is the old marker and must be changed.
- The groups are in that order, `Added:` first and `Fixed:` last.
- One sentence per bullet, and any suffix spelled exactly ` (Pro)`, after the full stop.
- No earlier entry changed — `- Initial release.` under the bare `= 1.0.0 =` stays unlabelled and
  undated, because 1.0.0 has not been released and has no date to carry.

```bash
awk '/^== Changelog ==/{c=1} c && /^= /{v=$0}
     c && /^[-*] / { if ($0 !~ /^- (Added|Updated|Fixed): / && $0 != "- Initial release.") print v": "$0 }' readme.txt
```

That prints every bullet that is unlabelled **or** still uses the old `*` marker, with its
version, and nothing on a correct file. Run it rather
than reading for it: a missing label is the easiest thing here to skim past.

The `c` flag is not decoration. `readme.txt`'s Description uses the same `= Heading =` and `* `
and `- `
shapes for its feature lists — eleven bullets of them — so an `awk` that scans the whole file
reports every one of those as unlabelled. Only the `== Changelog ==` section has labels.

## Zero bullets is a valid answer

If the range holds nothing user-facing, say "nothing user-facing to release", name the range you
read, and stop. Do not invent a bullet, do not write `- Minor improvements and bug fixes.`, and do
not write an empty heading into `readme.txt`. When `/yayrelease` called this skill, that answer is
its cue to stop too and ask whether to release at all.
