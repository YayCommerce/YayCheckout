# YayCheckout: WooCommerce Checkout Builder

A checkout builder for WooCommerce: merchants build checkout layouts from blocks, assign them to shoppers, and style them, and the storefront renders the active layout in place of WooCommerce's checkout form. One branch, `dev`, ships both a Pro and a Free build, chosen by `--plan` at release time ([how they split](docs/knowledge/architecture.md#editions)).

## Requirements

- A WordPress site with WooCommerce active. The minimum WordPress, WooCommerce and PHP versions are in the plugin header of `yaycheckout.php`.
- PHP 8.2 or later for the dev tooling. The product itself must run on PHP 7.4.
- Composer 2.
- Node 22, with pnpm through Corepack. `package.json` pins the pnpm version.
- A GitHub account with read access to the private [admin-shell repository](https://github.com/YayCommerce/yaycommerce-plugin-admin-shell). Composer clones it from GitHub.
- WP-CLI, for `pnpm makepot`.
- One of `jq`, `node`, `php` or `python3`, and `gh` logged in, for the yay harness.
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

   The dev script ends by starting Vite on port 3040 and a blocks watcher, which keep running. `pnpm install` also runs `composer install` (the root `postinstall`).

4. Activate the plugin in wp-admin. `YAYCHECKOUT_IS_DEVELOPMENT` defaults to `true`, so wp-admin loads the SPA from the dev server: keep `./scripts/run.sh dev` running while you work in the admin. To load a built bundle instead, define the constant as `false` in `wp-config.php` and run `./scripts/run.sh build` ([known issues](docs/knowledge/known-issues.md#looks-wrong-is-intentional)).

5. Run the lint and test commands in [CLAUDE.md § Commands](CLAUDE.md#commands). The admin lint fails today on `dev`, and `composer test` needs a test database; [docs/knowledge/known-issues.md](docs/knowledge/known-issues.md) and [docs/knowledge/release.md § Tests](docs/knowledge/release.md#tests) give the output to expect and the setup.

6. Install the team's Claude Code harness once per machine, then start Claude Code in the repo and accept the trust dialog:

   ```bash
   claude plugin marketplace add ~/yayharness   # a clone of the kit, until YayCommerce/yayharness is published
   claude plugin install yay@yayharness
   claude
   ```

   Run `/yay:setup` in the first session: it checks the machine (`yayh doctor`) and installs what's missing.

## Daily workflow

- **Review.** Every PR into `dev` needs one approval from a teammate other than its author, then it is squash-merged.
- **Working with Claude.** This repo uses the shared **yay** harness: the kit-managed block of [CLAUDE.md](CLAUDE.md), the rules in `.claude/rules/yay-*.md` and the `/yay:*` skills. They take a change from roadmap item to PR and release the same way in every Yay plugin. Not sure which skill? Type `/yay:go` and say what you want. The worked example, roadmap to release tag, is [docs/yay-workflow.md](docs/yay-workflow.md); the git rules are in `.claude/rules/yay-git.md`.
- **Releases.** `/yay:release` bumps the version, writes the `readme.txt` changelog, builds and checks both editions with `./scripts/run.sh release <version> --plan=free|pro`, and opens the release PR; `/yay:release --tag-only` tags it after the merge. What the release builds and checks is in [docs/knowledge/release.md](docs/knowledge/release.md).

## Project knowledge

What Claude (and a new developer) needs to change this plugin safely lives in the project block of [CLAUDE.md](CLAUDE.md) and in [docs/knowledge/](docs/knowledge/README.md): architecture, the storefront, the admin SPA, release and tests, conventions, integrations, known issues, decisions, and the helpers to reuse. `/yay:knowledge-update` keeps them true; edit them in the PR that makes them wrong.

**Personal overrides** are gitignored, and the harness works without them:
- `.yay/local.json`: your own config values, such as `site.url` for your local site;
- `.claude/settings.local.json`: your own permissions on top of `.claude/settings.json`;
- `CLAUDE.local.md`: your own instructions on top of `CLAUDE.md`.

Repo-specific Claude settings, such as the extra command allowlist, go in `.yay/settings.extra.json`; `/yay:harness-update` merges them into `.claude/settings.json`, which is kit-owned.

## License

GPLv2 or later. See the plugin header in `yaycheckout.php`.
