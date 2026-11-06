# The yay skills are installed from a plugin marketplace, not copied into the repo

**Purpose:** why `.claude/skills/` no longer holds the team's workflow skills, and why this repo's own facts now live in `.claude/yay-profile.json`. It supersedes the shared-skills part of [the harness record](2026-10-05-harness-with-review-backlog.md); everything else that record decided still stands.

**Update when:** never edit it. Supersede it whole when the skills stop arriving as a plugin, or when the profile stops being where a skill reads this repo's facts.

## Status

Accepted, 2026-11-06.

## Context

The harness record settled that the YayCommerce plugins share one set of skills, that their canonical copy lives in another repo, and that a copy here is replaced whole and never edited. The copying is what failed.

- **The pointer went dead and nothing noticed.** Five skills' headers cited a canonical-copy record dated 2026-10-03, under `docs/decisions/`, that this repo does not have. `scripts/check-harness.sh` could not catch it, because `check_cited_docs` excluded `.claude/skills/`. Writing that path out here would make this record the next thing the un-blinded check trips over, which is why it is described rather than cited.
- **Other repos' facts leaked in.** `yaytidy` carried another plugin's branch name and source layout in its examples; `yaybacklog` cited a symbol from another plugin.
- **The two release skills drifted apart while duplicating each other.** `yayhotfix` was 684 lines, about 380 of them a near-verbatim copy of `yayrelease`'s 571, and seven cross-references between them named step numbers that had moved. The two contradicted each other on whether this repo had ever published a release.
- **The repo-neutral skills had to guess.** Because they could hold no repo facts, they read them out of `CLAUDE.md`'s prose — "the directory CLAUDE.md names", "the base that CLAUDE.md names". That works here and in the handful of repos with the same harness, and nowhere else. The harness record predicted exactly this: repo-neutral skills lean on `CLAUDE.md`, and if it does not name the PR base, the commands or the failing checks, they have to guess.

Options turned down:

- **keeping the copy and adding a check** that the copies match their canonical source: it makes drift visible without removing the cause, and it cannot fix the guessing;
- **a git submodule or subtree** for `.claude/skills/`: it keeps the skills in the tree, so every repo still carries a version of them to diverge, and it adds a checkout step to onboarding;
- **tailoring the skills to this repo**: it forks the family's skills, which is what the harness record already refused.

## Decision

The skills are a Claude Code plugin, installed from a marketplace and never copied. `.claude/settings.json` declares the marketplace and enables the one plugin, `yay`; both keys are committed, so a teammate who clones the repo and accepts the trust dialog gets the skills with no install command of their own. They are invoked prefixed: `/yay:plan`, `/yay:implement`, `/yay:release`.

Everything repo-specific they need is in **`.claude/yay-profile.json`**, committed, validated against the harness's schema and against this repo by `scripts/check-harness.sh`. The rule that divides it from the docs: **if a skill substitutes the value into a path, a command, a branch name or a pattern, it is a profile key; if a person has to understand it, it stays in `CLAUDE.md`, a rule or a record. Nothing is in both, and a skill may not read a repo fact out of this repo's prose.**

`.claude/skills/` keeps one skill, `build-checkout-template`, which is this product's own.

The generated-paths guard hook also ships with the plugin and reads its path-to-command map from the profile, so `.claude/hooks/` is gone. `.claude/settings.json` keeps the command allowlist, because a plugin cannot supply permissions.

## Consequences

- **The repo must be trusted once, interactively.** Until someone opens it in Claude Code and accepts the trust dialog, the settings keys do nothing and no yay skill is available — and a non-interactive run skips that dialog rather than granting trust. Onboarding says so.
- **A fix to a skill lands in the harness repo**, not here. What lands here is a profile change.
- **A wrong profile is now a way to break every skill at once**, which is why `scripts/check-harness.sh` validates it first: that the paths exist, the commands resolve, the base branch is real, and each version source matches exactly one line.
- **A value that is `null` in the profile means this repo deliberately has no such thing**, and a skill then says it skipped that step and names the key. A value merely absent takes the schema's default. The two are different, and the profile's comment says so.
- The old unprefixed names no longer resolve. A repo that kept a copy would list both, which the harness check now flags.
- While the harness lives on one machine, the marketplace source is an absolute path, so that one line in `.claude/settings.json` is machine-specific until the harness is pushed and the entry becomes a repository reference.
