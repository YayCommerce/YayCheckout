# The changelog's house style, and the two rules that were measured rather than assumed

**Purpose:** the reasoning behind this repo's `readme.txt` changelog conventions, kept here so the harness's style rules can state the rules without carrying the argument for them.

**Update when:** never edit it. Supersede it whole when the heading shape, the labels, the bullet marker or the way a Pro-only bullet is decided changes.

## Status

Accepted, 2026-11-06. The rules themselves live in the yay harness's notes style, driven by the `changelog` keys in `.claude/yay-profile.json`. This record holds only why.

## Context

Before the first release the changelog held one entry, `- Initial release.` under a bare `= 1.0.0 =`. That was a placeholder, not a style, so the conventions in force are ones this repo chose rather than inherited.

Three of them were argued at the time and the argument is worth keeping:

**The heading carries a date, unpadded, in English.** `= Nov 6, 2026 - Version 1.1.0 =`. The command that produces it is `LC_ALL=C date +'%b %e, %Y' | tr -s ' '`, and all three pieces are load-bearing: without `LC_ALL=C` the month follows the operator's locale and a French one writes a French month into the readme; `%e` is used rather than `%-d` because `%-d` is a GNU extension and this tooling must run under the BSD tools on macOS; and `tr -s ' '` squeezes the padding `%e` leaves on single-digit days. The release flow computes the date **once** in its preflight and passes it to the notes step, so a run that straddles midnight cannot ship a changelog and a tag that disagree about the day.

**The bullet marker is a hyphen, which is very likely right rather than confirmed.** The directory renders readme sections through a Markdown parser, and Markdown accepts `-`, `*` and `+` alike for a list item — but every official example and nearly every plugin in the wild uses `*`, and no documentation found says in so many words that `-` is parsed. It is cheap to settle: after the first directory submission, look at the rendered Changelog tab. If the bullets came out as one run-on paragraph, the marker is the cause, and switching is one substitution over `readme.txt` plus one profile value.

**A Pro-only bullet is decided from the code, and the obvious rule is wrong.** `readme.txt` is copied into both zips unchanged, so one changelog is read by Free and Pro site owners alike, and an unmarked Pro-only bullet advertises to a Free site owner something their copy does not have. The obvious rule — a bullet is Pro when every commit behind it touches only Pro paths — was **run over the last 25 commits on `dev` and fired zero times**, because every Pro block also edits the shared block registry to list itself. Save as PDF and Order Again would both have shipped unsuffixed. So the verdict comes from the authoritative lists instead: `BlockRegistry::PRO_BLOCKS` for a block, the `'pro' => true` flag in `LayoutTemplateCatalogue` for a template, and the Pro directories only for anything in neither list. Those two sources are `editions.pro_verdict_sources` in the profile.

That same shared-registry edit is why a Pro feature earns a bullet in the shared changelog at all: the Free build ships the block visible but locked behind a Pro badge, so a Free site owner does see it appear — they just cannot use it. Hence a marker rather than silence.

## Decision

The shape, the labels, the marker and the suffix are profile values, and the rules for applying them belong to the harness. This record is the justification, and it is not restated in either place.

A shipped version's entry is history: it is never touched again, not to add a label, not to change a marker.

## Consequences

- Changing a convention means changing a profile value, not editing a skill.
- The hyphen decision has an open check against it, named above, which someone has to actually perform after the first directory submission.
- The Pro verdict depends on two code locations. If either moves, `editions.pro_verdict_sources` has to move with it, and the profile check fails when the file is gone — but it cannot tell whether the symbol inside it still means what it did.
