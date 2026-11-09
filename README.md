<!--
Purpose: the human front door: requirements, onboarding, the team workflow, and where the agent harness lives.
Update when: a requirement or a setup step changes, the review, promotion or release practice changes, or the harness moves.
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
- `jq`, which the repo's generated-paths guard hook (`.claude/hooks/`) needs.
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

   The dev script installs what is missing, then starts Vite on port 3040 and a blocks watcher, which keep running. `vendor-prefixed/` is committed, so a fresh clone needs no rescoping.

4. Activate the plugin in wp-admin. `YAYCHECKOUT_IS_DEVELOPMENT` defaults to `true`, so wp-admin loads the SPA from the dev server: keep `./scripts/run.sh dev` running while you work in the admin. To load a built bundle instead, define the constant as `false` in `wp-config.php` and run `./scripts/run.sh build` ([known issue](docs/known-issues.md#the-admin-page-is-blank-without-the-dev-server)).

5. Run the lint and test commands in [CLAUDE.md § Commands](CLAUDE.md#commands). The admin lint fails today on `dev`, and `composer test` needs a test database; [docs/known-issues.md](docs/known-issues.md) gives the output to expect and the setup. Your result should match it.

6. Start Claude Code in the repo with `claude`, and accept the trust dialog. Until you do, Claude ignores the command allowlist in `.claude/settings.json`. With the team's `yay` plugin installed, sessions load `CLAUDE.md`, the path-scoped rules in `.claude/rules/`, and the facts in `.yay/`.

## Daily workflow

The git rules are in [CLAUDE.md § Git](CLAUDE.md#git): branch names, Conventional Commits, PR-only into `dev`, and what an agent may never do. The parts only people do:

- **Review.** Every PR into `dev` needs one approval from a teammate other than its author, then it is squash-merged.
- **Releases.** Build both editions from `dev` with `--plan=pro` and `--plan=free`. The release commands are in [CLAUDE.md § Commands](CLAUDE.md#commands). What the release builds and checks is in [docs/architecture.md § Build and release](docs/architecture.md#build-and-release), and the rules for changing it are in [.claude/rules/release.md](.claude/rules/release.md). Release notes go in `readme.txt`. `/yay:release` runs the flow (`/yay:changelog` drafts the notes); `/yay:fix` repairs a diagnosed defect.

## Agent tooling

The agent harness is the team's `yay` plugin plus this repo's `.yay/` folder: `.yay/harness.yml` holds the facts every skill reads, `.yay/project.md` the invariants and gotchas, `.yay/inventory.md` what to reuse, and `.yay/api.yml` the public contract. `/yay:harness-scout --check` reports when they stop matching the code, and `--update` re-derives them. `CLAUDE.md` is the short pointer file every session loads, and `.claude/rules/` holds the path-scoped rules (the kit's half above each file's `your rules` marker, this repo's below it).

Skills come from the plugin: `/yay:brainstorm`, `/yay:plan`, `/yay:implement`, `/yay:fix`, `/yay:verify`, `/yay:build`, `/yay:changelog`, `/yay:release` and the rest. One skill is this repo's own: [`/build-checkout-template`](.claude/skills/build-checkout-template/SKILL.md) builds one template of the HTML checkout-templates mock as `templates/layouts/<slug>/markup.php` and `design.json`.

The old harness (its skills, rules and index) is archived in `.yay/archive/old-harness/`, inert. `docs/backlog/` has no tooling since `yaybacklog` was retired; its items are plain Markdown.

**Status lifecycle.** A spec or plan is deleted once its work ships; git history keeps it. An agent acts only on a spec whose Status is `accepted` or a plan whose Status is `active`.

**Decision records.** Write one when a change makes or reverses a choice that is costly to undo and that the code can't justify by itself. [The decision-record practice](docs/decisions/2026-10-05-decision-record-practice.md) gives the examples, the file name and the four headings. The record lands in the PR that makes the change.

**Personal overrides.** These are gitignored, and the harness must work without them:
- `.claude/settings.local.json`: your own permissions and settings on top of `.claude/settings.json`;
- `CLAUDE.local.md`: your own instructions on top of `CLAUDE.md`. It may drop the commit and PR trailers that `CLAUDE.md` asks for.

## License

GPLv2 or later. See the plugin header in `yaycheckout.php`.
