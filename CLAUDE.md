<!--
Purpose: the pointer file every session loads first: what this repo is, the commands, the hard rules, where to look next.
Update when: a command or hard rule changes. Facts live in .yay/harness.yml; the bulk lives in .yay/ and docs/. Hard rules are cited by number elsewhere (.claude/rules/, .github/workflows/php.yml, docs/known-issues.md): keep the numbering.
-->

# YayCheckout

WooCommerce checkout builder: a merchant builds checkout layouts from blocks in an embedded block editor, and the storefront renders the active layout in place of WooCommerce's checkout form. One branch, `dev`, builds both editions; `./scripts/run.sh release --plan=free|pro` picks one, Pro by default. PHP is in `includes/`, the admin SPA in `apps/admin/` (React, Vite, TypeScript), the checkout blocks in `packages/blocks/` (`@wordpress/scripts`).

## Commands

Node 22 (`nvm use`) and pnpm only; Composer tooling needs PHP ≥ 8.2. Every command is in `.yay/harness.yml` (`commands.*`).

- `composer lint` (boundary check, then phpcs; `composer lint -- <files>`) · `composer test` (own DB, see known issues)
- `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` · `pnpm --filter @yaycheckout/admin-page lint` (fails on `dev` today)
- `pnpm build:blocks`: without it the editor has no YayCheckout blocks and says nothing. `./scripts/run.sh build` builds the SPA, then the blocks.
- `./scripts/run.sh dev`: Vite on :3040 (wp-admin loads it while `YAYCHECKOUT_IS_DEVELOPMENT` is true, the default) plus a blocks watcher.
- `./scripts/run.sh release [version] [--plan=free|pro] [--wp-tested=X.Y] [--wc-tested=X.Y] [--no-prompt]`: builds in a staging copy (`yaycommerce-prerelease --stage`); without a terminal pass a version or `--no-prompt`. `./scripts/run.sh shell-update` is what rewrites `composer.json`, `scoper.inc.php` and `vendor-prefixed/`: check `git status` after it.

A pnpm command may run `pnpm install`, and with it `composer install`, first. A check that fails on `dev` too was already failing: don't add to it. Details for each are in [docs/known-issues.md](docs/known-issues.md).

## Hard rules

1. Never hand-edit generated output: `vendor/`, `vendor-prefixed/` (committed; `./scripts/run.sh shell-update`), `node_modules/`, `assets/dist/`, `release/`, each template's `preview.html` (`./scripts/run.sh previews`) and `thank-you-preview.html` (`./scripts/run.sh thank-you-previews`). Edit a block's `render.php` in `packages/blocks/src/<block>/`.
2. Free names Pro in exactly one place, the guarded `boot_pro()` in `includes/YayCheckout.php`; Pro attaches only through hooks `includes/Pro/ProServiceProvider.php` adds. `scripts/check-pro-boundary.sh` fails any other `YayCheckout\Pro` reference.
3. Pro is withheld by PHP (`Plan::is_pro()`, no Pro REST routes in Free). The SPA's `isPro` only decides what shows; Free's upsells ship to Free, so never put real data or a real endpoint behind them.
4. Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted properties, `?->`, named arguments or union types. Nothing but review and CI's `php -l` on 7.4 catches it.

## Tests

Tests only guard real risk: logic whose failure silently breaks a shopper's checkout (`LayoutMarkupReader::publish_error()`, `LayoutAssignment::pick()`, `RequiredFieldOverlay::apply()`, `LayoutImportReview`), or a fixed bug a test reproduces cheaply. Ask before adding one (name, file, what it guards); otherwise verify with an exact manual check.

## Git

Branch from `dev` as `<name>/<type>/<topic>`; everything lands by squash-merged PR into `dev`, never a direct commit. Conventional Commits, ending with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`; PR bodies end with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. Commit or push only when asked; never force-push, rewrite pushed history or skip hooks. Stage files by name.

## Where to look

- `.yay/project.md`: invariants, the edition boundary and gotchas. `.yay/inventory.md`: what to reuse. `.yay/api.yml`: the public contract. `.yay/harness.yml`: the facts skills read.
- [docs/architecture.md](docs/architecture.md), [docs/conventions.md](docs/conventions.md), [docs/decisions/](docs/decisions/) (only `accepted` records are in force), [docs/known-issues.md](docs/known-issues.md).
- [docs/plans/](docs/plans/) and `docs/specs/`: act only on a plan with `Status: active` or a spec with `Status: accepted`. [docs/backlog/](docs/backlog/): open review follow-ups.
