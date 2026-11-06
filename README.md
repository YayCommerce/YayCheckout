<!--
Purpose: the human front door: requirements, onboarding, the team workflow, when to use the agent tooling, and how to keep the harness current.
Update when: a requirement or a setup step changes, the review, promotion or release practice changes, a skill is added, renamed or removed, or a harness file gains or loses a topic in the owner map.
-->

# YayCheckout: WooCommerce Checkout Builder

A checkout builder for WooCommerce: merchants build checkout layouts from blocks, assign them to shoppers, and style them, and the storefront renders the active layout in place of WooCommerce's checkout form. One branch, `dev`, ships both a Pro and a Free build, chosen by `--plan` at release time ([how they split](docs/architecture.md#free-and-pro)).

## Requirements

- A WordPress site with WooCommerce active. The minimum WordPress, WooCommerce and PHP versions are in the plugin header of `yaycheckout.php`.
- PHP 8.2 or later for the dev tooling. The product itself must run on PHP 7.4.
- Composer 2.
- Node 22, with pnpm through Corepack. `package.json` pins the pnpm version.
- A GitHub account with read access to the private [admin-shell repository](https://github.com/YayCommerce/yaycommerce-plugin-admin-shell). Composer clones it from GitHub.
- WP-CLI, for `pnpm makepot`.
- `jq`, which the Claude Code guard hook needs.
- Claude Code 2.1.283 or later.

## Onboarding

1. Clone into the site's plugins directory:

   ```bash
   cd <site>/wp-content/plugins
   git clone https://github.com/YayCommerce/YayCheckout.git yaycheckout
   cd yaycheckout
   ```

2. Switch to Node 22 and enable pnpm:

   ```bash
   nvm use
   corepack enable
   ```

3. Run the dev script, then install from a second terminal:

   ```bash
   ./scripts/run.sh dev
   pnpm install
   ```

   On a fresh clone, `pnpm install` fails until the dev script has created `vendor-prefixed/` ([known issue](docs/known-issues.md#a-fresh-clones-first-install-fails-on-vendor-prefixedsrc)). The dev script ends by starting Vite on port 3040 and a blocks watcher, which keep running. Run `git status` once it has started: the dev script can rewrite `composer.json` and `scoper.inc.php`.

4. Activate the plugin in wp-admin. `YAYCHECKOUT_IS_DEVELOPMENT` defaults to `true`, so wp-admin loads the SPA from the dev server: keep `./scripts/run.sh dev` running while you work in the admin. To load a built bundle instead, define the constant as `false` in `wp-config.php` and run `./scripts/run.sh build` ([known issue](docs/known-issues.md#the-admin-page-is-blank-without-the-dev-server)).

5. Run the lint and test commands in [CLAUDE.md § Commands](CLAUDE.md#commands). The admin lint fails today on `dev`, and `composer test` needs a test database; [docs/known-issues.md](docs/known-issues.md) gives the output to expect and the setup. Your result should match it.

6. Start Claude Code in the repo with `claude`, and accept the trust dialog. Until you do, Claude ignores the command allowlist in `.claude/settings.json`. Then:
   - `CLAUDE.md` loads at the start of every session;
   - a rule in `.claude/rules/` loads when Claude reads a file its `paths` match;
   - the yay skills arrive from the harness marketplace declared in `.claude/settings.json`, prefixed as `/yay:…`, and read this repo's facts from `.claude/yay-profile.json`. `/yay:release` and `/yay:hotfix` run only when you call them by name, so they are not in the skill list;
   - `build-checkout-template`, the one skill still in `.claude/skills/`, loads unprefixed when you call it or your request matches its description;
   - the guard hook the harness ships stops Claude's file tools from editing the generated directories that profile names.

## Daily workflow

The git rules are in [CLAUDE.md § Git](CLAUDE.md#git): branch names, Conventional Commits, PR-only into `dev`, and what an agent may never do. The parts only people do:

- **Review.** Every PR into `dev` needs one approval from a teammate other than its author, then it is squash-merged.
- **Releases.** Build both editions from `dev` with `--plan=pro` and `--plan=free`. The release commands are in [CLAUDE.md § Commands](CLAUDE.md#commands). What the release builds and checks is in [docs/architecture.md § Build and release](docs/architecture.md#build-and-release), and the rules for changing it are in [.claude/rules/release.md](.claude/rules/release.md). Release notes go in `readme.txt`. `/yay:release` runs the whole flow: the bump, the notes, both zips, the release PR, then the tag and the GitHub Release. When a shipped version needs a patch and `dev` holds work that cannot go out with it, `/yay:hotfix` cuts that patch from the tag instead and then forward-ports the fix into `dev`; the two share their packaging step as one reference inside the harness.

## Agent tooling

The team's skills cover a change from idea to PR, and the release that ships it. Each one is called by name, and its `SKILL.md` holds the details:

| Skill | Run it when | It writes |
|---|---|---|
| `/yay:brainstorm` | you want to design a feature, a behaviour change, a refactor or an integration before planning it | a design doc in `docs/specs/` |
| `/yay:plan` | you have an accepted design doc, or a request you want planned without one | a plan in `docs/plans/` |
| `/yay:implement` | you have an active plan and want it built task by task, with a gate at every task | committed work, one task at a time, and never a commit you did not approve |
| `/yay:plancheck` | after each phase of a plan, and before merging | a PASS or FAIL review against the plan, from a fresh subagent |
| `/yay:tidy` | before you open a PR | a report of duplicated facts and code, and the fixes you pick |
| `/yay:backlog` | you want to see what reviews left open, check whether an item is done, or record a finding you won't fix in this PR | ticked boxes and new items in `docs/backlog/`, and removes the items that are done |
| [`/build-checkout-template`](.claude/skills/build-checkout-template/SKILL.md) | you build one template of the HTML checkout-templates mock as a layout template | `templates/layouts/<slug>/markup.php` and `design.json`, committed on a branch |
| `/yay:changelog` | you want the release notes for a commit range, on their own or as part of a release | the `readme.txt` changelog entry, in house style, with Pro-only bullets marked |
| `/yay:release` | you cut a release of the plugin | the version bump, the changelog (through `/yay:changelog`) and a release PR into `dev`, then the annotated tag, the released branch and the GitHub Release |
| `/yay:hotfix` | you patch a released version and `dev` holds work that must not ship yet | a patch release built from the tag — its own PR, tag, released branch and draft Release — and then the forward-port PR that puts the fix into `dev` |
| `/yay:harnessdocs` | a harness doc needs creating, or bringing back in step with a change | these docs and the owner map below |

A typical change runs `/yay:brainstorm`, then `/yay:plan`, then `/yay:implement`, running `/yay:plancheck` after each phase. Before the final review, `/yay:plancheck` asks whether to run `/yay:tidy` first. A change may also skip the design and go straight to `/yay:plan`. A review finding you don't fix in the PR goes to the backlog with `/yay:backlog add`, and the PR that fixes it later ticks its boxes with `/yay:backlog check`.

Every one of those skills reads this repo's own facts — its paths, commands, branch model, editions and version locations — from [.claude/yay-profile.json](.claude/yay-profile.json). None of them is edited here.

**Status lifecycle.** A spec or plan is deleted once its work ships; git history keeps it. An agent acts only on a spec whose Status is `accepted` or a plan whose Status is `active`. The Status words and when each one is set belong to the harness: its spec format for designs, and its plan format for plans.

**Decision records.** Write one when a change makes or reverses a choice that is costly to undo and that the code can't justify by itself. [The decision-record practice](docs/decisions/2026-10-05-decision-record-practice.md) gives the examples, the file name and the four headings. The record lands in the PR that makes the change.

## Keeping the harness current

**Owner map.** Each topic has one home. Other files only link to it.

| Topic | Home |
|---|---|
| Daily commands, hard rules, git rules | [CLAUDE.md](CLAUDE.md) |
| PHP, admin SPA, blocks and release-tooling rules | [.claude/rules/](.claude/rules/) |
| How the pieces fit | [docs/architecture.md](docs/architecture.md) |
| Conventions the code can't show | [docs/conventions.md](docs/conventions.md) |
| Gotchas and checks that fail today | [docs/known-issues.md](docs/known-issues.md) |
| Follow-ups that reviews left open | [docs/backlog/](docs/backlog/) |
| Why X, not Y | [docs/decisions/](docs/decisions/) |
| The facts the skills substitute: paths, commands, branches, versions, editions | [.claude/yay-profile.json](.claude/yay-profile.json) |
| Every skill's own procedure, format and Status words | the [yay plugin harness](https://github.com/thithiennta/yay-plugin-harness), not this repo |
| Turning a template of the HTML mock into a layout template, and the pitfalls templates 01–03 taught | the `build-checkout-template` skill, which is this repo's own |
| Onboarding, review, promotion and release practice, agent tooling, harness upkeep | this README |

**Update-when triggers.** Every harness file opens with a Purpose line and an Update when line: in an HTML comment in `CLAUDE.md`, this README and the rules, in the first lines of each doc, decision record and backlog item, and in YAML comments in each skill's frontmatter. When your change matches a file's trigger, update that file in the same PR.

**Personal overrides.** These are gitignored, and the harness must work without them:
- `.claude/settings.local.json`: your own permissions and settings on top of `.claude/settings.json`;
- `CLAUDE.local.md`: your own instructions on top of `CLAUDE.md`. It may drop the commit and PR trailers that `CLAUDE.md` asks for.

**Where the skills come from.** They are installed, not copied: `.claude/settings.json` declares the [yay plugin harness](https://github.com/thithiennta/yay-plugin-harness) marketplace and enables its one plugin, and everything repo-specific they need is in `.claude/yay-profile.json`. Nothing under `.claude/skills/` is a yay skill any more, and a fix to one lands in the harness repo. See [the record](docs/decisions/2026-11-06-skills-move-to-a-plugin-marketplace.md).

`build-checkout-template` is the exception and stays here: it is this product's own, tied to the layout templates, the block registry and the design-token schema, and it keeps loading unprefixed.

**Mechanical checks.** Run `./scripts/check-harness.sh` before merging a PR that changes a harness file. It checks paths, links, headers, rule globs, skill names, decision records and backlog items, prints one line per finding, and exits 1 if there is any. It fails on `dev` today ([known issue](docs/known-issues.md)); your change must not add a finding.

**Prompt audit.** Run `/doctor prompt-audit` in Claude Code after upgrading Claude Code and before merging a PR that changes `CLAUDE.md`, a rule or this repo's own skill. Every finding gets a fix, or a reason to keep the text.

## License

GPLv2 or later. See the plugin header in `yaycheckout.php`.
