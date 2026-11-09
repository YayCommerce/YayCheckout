# The harness comes from the team's yay kit: facts derived into `.yay/`, a short pointer index, kit-rendered rules carrying this repo's lines, and the plugin's skills

**Purpose:** why this repo's agent and developer harness (`CLAUDE.md`, `.claude/`, `.yay/`, `docs/` and `README.md`) and the skills it uses have the shape they have. It states every harness decision in force, in one place.

**Update when:** never edit it. Supersede it whole with a new record when the harness's shape changes: what makes up the harness, where its facts live and who derives them, how Claude reaches a doc, who owns a topic, the committed tooling, the base branch, or where the skills come from. Adding, removing or changing a skill is not such a change.

## Status

Accepted, 2026-11-09, by Trace Bui. It supersedes [Harness and shared skills, with a review backlog](2026-10-05-harness-with-review-backlog.md). The harness it replaces is archived in `.yay/archive/old-harness/`. The one before that is still at the tag `harness-v1-final`.

## Context

- The previous harness was hand-written in every YayCommerce repo: a `CLAUDE.md` index, four path-scoped rules, nine skills under `.claude/skills/`, a guard hook, an owner map in `README.md` and `scripts/check-harness.sh`. Five of the skills were copies of YayLoyalty's that this repo was never to edit, so a fix had to land there first and be copied back. Two more (`yaybacklog`, `yaytidy`) were retired across the team on 2026-11-08.
- Its facts drifted from the code with nothing to catch it. When this harness was adopted, its index still said `./scripts/run.sh dev` ran `composer update` and the admin-shell sync, and that `release` left only `--no-dev` packages. Both had stopped being true with the PRs that committed `vendor-prefixed/` (#257) and built both editions from one branch (#263). It also still named `dev/edd` as the base branch.
- The team's `yay` plugin derives a repo's facts from its code into `.yay/harness.yml` (versions, editions, commands, stored keys) and `.yay/api.yml` (hooks, routes and options, each with a stability). `/yay:harness-scout --check` reports when they stop matching the code. Its skills read those facts, so one copy of each skill serves every repo.
- Before the old harness was removed, its claims about this repo were extracted and checked against the code. Only the ones the code confirmed went into the new files.
- Options turned down:
  - keeping the hand-written harness and fixing its drift: nothing would catch the next drift;
  - keeping the copied skills beside the plugin's: two harnesses, and the unmaintained one misleads the next session;
  - letting the kit regenerate the rules from its templates alone: this repo's four rules held about a hundred lines of couplings no template contains, so they are carried below a marker instead;
  - keeping `scripts/check-harness.sh`: most of its checks policed the removed skills, rules and owner map, and the rest ran on `dev` with findings nobody cleared.

## Decision

**What the harness is**

- `CLAUDE.md`, `.claude/`, `.yay/`, `docs/` and `README.md` make up the harness. Claude Code is the only agent supported, so there is no `AGENTS.md`.
- Designs for unbuilt features don't stay in the tree. Code says "designed, not built yet", and a new spec is written when the feature is built.
- There are no feature IDs, roadmap or development changelog. A to-do names its topic, as in `TODO(fonts)`, and release notes stay in `readme.txt`.
- Follow-ups that a review or a change leaves open live in `docs/backlog/`, one file per item, each with a `## Done when` checklist of conditions someone can check. A box is ticked when its condition is met, and an item whose boxes are all ticked is removed. The backlog holds no feature ideas and no schedule. No skill manages it: items are plain Markdown, and `./scripts/run.sh backlog` lists them.

**Its shape**

- `.yay/harness.yml` holds the facts the skills read, as deltas from the kit's `wp-plugin` profile, and `.yay/api.yml` the public contract. Both are derived from the code by `/yay:harness-scout`. A value the code can't settle is asked and recorded in `needs_confirmation`, never guessed. Stabilities in `api.yml` are human-owned.
- `.yay/project.md` holds the invariants, the edition boundary and the gotchas, and `.yay/inventory.md` the reusable surface. Scout rewrites only their `yay:generated` fences; everything outside them belongs to whoever wrote it.
- `CLAUDE.md` is a pointer file of about 40 lines: what the repo is, the commands, the hard rules, the test and git rules, and where to look. Its hard rules keep their numbers, since other files cite them by number. It reaches docs through plain links, never `@` imports.
- Path-scoped rules in `.claude/rules/`, rendered by the kit with globs it derives from this repo's paths. The kit owns the part above each file's `<!-- --- your rules --- -->` marker. This repo owns everything below it, which is never regenerated.
- Each topic has one home, and the others link to it: facts in `.yay/harness.yml`, the contract in `.yay/api.yml`, invariants and gotchas in `.yay/project.md`, how the parts fit in `docs/architecture.md`, the rules the code can't show in `docs/conventions.md`, the reasons in `docs/decisions/`, the checks that fail today in `docs/known-issues.md`, and onboarding and team practice in `README.md`.
- Every doc, rule and record opens with Purpose and Update when. Docs cite code by path and symbol. Code never cites a doc, a section or a feature ID, and review is what enforces it.
- Status, progress and counts appear only in plans, in the Status lines of specs and records, and in the `## Done when` checkboxes of backlog items. Plans and specs are deleted once their work ships, and an agent acts only on a plan whose Status is `active` or a spec whose Status is `accepted`.

**Tooling**

- A committed `.claude/settings.json` holds an allowlist for the routine checks, the generated-directory hook (`.claude/hooks/guard-generated-paths.sh`) and `includeGitInstructions: false`. That setting removes Claude Code's built-in git guidance, so `CLAUDE.md` states the git rules itself. The kit's own guard also covers the generated directories, shell writes included.
- `.gitignore` ignores personal Claude files and the kit's working state (`.yay/tasks/`, `.yay/carryover.md`), and nothing else of the harness.
- `.nvmrc` pins Node 22, and the root `package.json` declares it.
- The lockfiles stay gitignored, and `docs/known-issues.md` records the cost.

**Workflow**

- `dev` is the only long-lived branch and the base of every change, docs included, which reaches it through a squash-merged PR.

**Skills**

- The team's skills come from the `yay` plugin, called as `/yay:<name>`. Their formats, the spec and plan Status words included, belong to the plugin and are fixed there, not here.
- A skill that only this repo needs lives in `.claude/skills/` and is edited here like any other file. Today that is `build-checkout-template`.

## Consequences

- A session starts from a short pointer file. A scoped rule loads when Claude touches a file it matches, and the facts load only when a skill or a link reaches them.
- A fact that drifts from the code shows up in `/yay:harness-scout --check` instead of misleading a session for weeks. A fact the code can't settle stays visibly open in `needs_confirmation`.
- One copy of each skill serves every repo, so a fix reaches this repo when the plugin updates, with nothing to copy back. The cost is a dependency on the plugin: a teammate without it has `CLAUDE.md`, the rules and `docs/`, but no skills, and only the repo's own guard hook, which can't see shell writes.
- Rules carry two halves. The repo's half can hold lines the kit's half now repeats, and trimming them is a reviewer's call, made against the rendered file.
- With `check-harness.sh` gone, nothing checks mechanically that code never cites a doc or that records and backlog items keep their headings. Review is the only gate.
- With no skill managing it, nothing checks a backlog box against the code. Someone has to read the code to tell whether an item is done.
- With the built-in git guidance off, the git rules in `CLAUDE.md` are the only ones, so they have to stay complete.
- With untracked lockfiles, a documented command can still behave differently on another machine.
- Old designs and the previous harnesses are read in the archive or at the tag, not in the live tree.
