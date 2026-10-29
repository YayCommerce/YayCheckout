<!--
Purpose: the index every Claude Code session loads first: what this repo is, the commands, the hard rules, when to add a test, the git rules and where to look next.
Update when: a command, hard rule, the test rule or a git rule changes, or a harness doc is added, moved or removed. Keep it under 100 lines. Detail goes in a linked doc, reached by a "read when…" link.
-->

# YayCheckout

WooCommerce checkout builder. A merchant builds checkout layouts from blocks in an embedded block editor, and the storefront renders the active layout in place of WooCommerce's checkout form. One repo ships two builds from two branches: Pro from `dev/edd`, and Free from `dev/lite`, which differs only in `YayCheckoutPluginAdapter.php` (no licensing) and the plugin header, and whose zip is Pro without `includes/Pro/` and with the SPA built as `free`.
PHP lives in `includes/`. The admin SPA is `apps/admin/` (React, Vite, TypeScript), and the checkout blocks are `packages/blocks/` (`@wordpress/scripts`).

## Commands

Run from the repo root on Node 22: `nvm use` reads `.nvmrc`, and pnpm 11 [fails on older Node](docs/known-issues.md). Use pnpm only; `apps/admin` rejects npm and Yarn. Composer tooling needs PHP ≥ 8.2.

- `pnpm install`: JS dependencies, then `composer install` (root `postinstall`). A fresh clone also needs `composer shell-update` before WordPress can load the plugin ([known issue](docs/known-issues.md)).
- `composer lint`: `scripts/check-pro-boundary.sh`, then phpcs (WordPress standard). `composer lint -- <files>` lints only those files.
- `composer test`: PHPUnit integration suite, inside WordPress with WooCommerce, against a test database the bootstrap drops and recreates. Its tests live in `tests/Integration/`. Setup is in the header of `phpunit.xml.dist`; Local sites need [the per-site socket](docs/known-issues.md).
- `pnpm --filter @yaycheckout/admin-page lint`: eslint, then prettier. [Fails today](docs/known-issues.md).
- `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`: type-check the SPA.
- `pnpm build:blocks`: build the blocks into `assets/dist/blocks/`. Without a build, the editor has no YayCheckout blocks and says nothing.
- `./scripts/run.sh dev`: Vite on :3040, which wp-admin loads while `YAYCHECKOUT_IS_DEVELOPMENT` is true (the default), plus a blocks watcher. It first runs `composer update` and the admin-shell sync, which can [rewrite `composer.json` and `scoper.inc.php`](docs/known-issues.md): check `git status` before committing.
- `./scripts/run.sh build`: type-check and build the SPA into `assets/dist/admin/`, then the blocks into `assets/dist/blocks/`.
- `./scripts/run.sh release [version] [--plan=free|pro] [--wp-tested=X.Y] [--wc-tested=X.Y] [--no-prompt]`: package and check a zip in `release/` for the branch's edition (Pro on `dev/edd`, Free on `dev/lite`); a `--plan` that disagrees fails. It prompts for the version and the WP and WC tested-up-to versions. Pass a version or `--no-prompt` when there is no terminal to answer the prompts; then the tested-up-to values change only through their flags. It leaves [only `--no-dev` packages](docs/known-issues.md): run `composer install` afterwards.
- `./scripts/run.sh backlog`: list the open items in `docs/backlog/` and their unticked boxes.

Any pnpm command may [run `pnpm install`, and with it `composer install`, first](docs/known-issues.md). `./scripts/run.sh help` lists the rest. Judge a failing check against `dev/edd`. If it fails there too, it was already failing: leave it unless asked, and don't add to it.

## Hard rules

1. Never hand-edit generated output: `vendor/`, `vendor-prefixed/`, `node_modules/`, `assets/dist/`, `release/`, `templates/layouts/*/preview.html` and `templates/layouts/pro/*/preview.html`, and the `thank-you-preview.html` beside each. Change the input and rerun the command that builds it. A block's `render.php` is edited in `packages/blocks/src/<block>/`, never in `assets/dist/blocks/`, a template's `preview.html` is rebuilt from the HTML mock with `./scripts/run.sh previews`, and its `thank-you-preview.html` from its `design.json` with `./scripts/run.sh thank-you-previews`.
2. Free names Pro in exactly one place: the guarded `boot_pro()` in `includes/YayCheckout.php`. Pro attaches only through the hooks and filters that `includes/Pro/ProServiceProvider.php` adds. `scripts/check-pro-boundary.sh`, run by `composer lint`, fails any other reference to `YayCheckout\Pro` in `includes/`.
3. Pro features are withheld by PHP: the Free build has no Pro REST routes, and the limits and templates check `Plan::is_pro()`. The SPA's `isPro` (`apps/admin/src/lib/plan.ts`) only decides what the SPA shows; the template list comes from PHP, for the installed plan, and Free has no Pro block or template code at all. Free's upsells (`ProBadge`, `UpgradeButton`, the locked mocks, `<ProGate>`) ship to Free, so never put real data or a real endpoint behind them.
4. Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types. phpcs checks no PHP version and the dev tooling runs on PHP 8, so only review catches it.

## Tests

The team keeps only tests that guard real risk, so a test is warranted in two cases: logic whose failure silently breaks a shopper's checkout (publish rules in `LayoutMarkupReader::publish_error()`, layout assignment in `LayoutAssignment::pick()`, required fields in `RequiredFieldOverlay::apply()`, import review in `LayoutImportReview`), or a fixed bug that a test reproduces cheaply. Before adding any test, ask with AskUserQuestion: name the test, its file and suite, and what it guards. Write it only if the user accepts; a request for that test, or a plan whose `**Tests:**` line lists it, counts as acceptance. Otherwise verify with an exact manual check: the command or UI action and the result that means it passed.

## Git

- Branch from `dev/edd` as `<name>/<type>/<topic>`.
- Every change, docs included, lands through a PR into `dev/edd` (squash merge). Never commit to `dev/edd`, `dev/lite` or `main` directly; `dev/edd` reaches `main` through its own PR.
- `dev/lite` is the Free edition: `dev/edd` is merged into it (merge commit, not squash), keeping its own `YayCheckoutPluginAdapter.php` and `Plugin Name` header. Never carry either file's edition lines across.
- Commit messages follow Conventional Commits: `type(scope): summary`.
- End each commit message with `Co-Authored-By: Claude <model> <noreply@anthropic.com>`, naming the model you run as, and each PR description you write with `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. A personal `CLAUDE.local.md` may drop them.
- Commit or push only when asked. Never force-push a shared branch, rewrite pushed history, skip hooks (`--no-verify`) or change git config.
- Check `git status` and stage files by name, so personal and generated files stay out.

## Plans and specs

Plans live in `docs/plans/` and specs in `docs/specs/`; once their work ships they are deleted, and git history keeps them. Act only on a plan whose `Status:` is `active` or a spec whose `Status:` is `accepted`. Any other status makes it a record, not an instruction.

## Where to look

- [docs/architecture.md](docs/architecture.md): how the pieces fit: boot, the admin-shell sync, layers and who writes what, layouts and how the storefront picks one, preview links, blocks and the embedded editor, Free/Pro, the SPA↔WordPress bridge, the compatibility scan, release and tests. Read it before a change that crosses layers, adds a stored value or a writer, touches the Free/Pro split, or changes how the SPA or the editor loads or calls WordPress.
- [docs/conventions.md](docs/conventions.md): team rules the code can't show: storage keys, settings and design schemas, TypeScript copies of PHP, wire casing and dates, file names, logging and comments. Read it before adding a file, a stored key or setting, a TypeScript copy of a PHP shape, or a REST field.
- [docs/decisions/](docs/decisions/): the reasons behind architectural choices: the Free/Pro split, admin-shell scoping, a layout as one post and its design, wire timestamps, checkout base styles and fonts, block visibility, and this harness. Read the matching record before a change that alters an architectural choice; [the decision-record practice](docs/decisions/2026-10-05-decision-record-practice.md) says when to write a new one. Only `accepted` records are in force.
- [docs/known-issues.md](docs/known-issues.md): checks that fail today and gotchas, each with a workaround. Read it when a command fails or does something unexpected, before you debug it.
- [docs/backlog/](docs/backlog/): follow-ups that reviews left open, one file per item, each with a `## Done when` checklist. Read it when you start work in an area, or before you fix something a review flagged. `/yaybacklog` lists, checks and adds items; `./scripts/run.sh backlog` lists them.
- [README.md](README.md): onboarding, the review, promotion and release practice, when to use each skill, and the owner map of the harness. Read it before adding or moving a harness doc, rule or skill.
- [docs/plans/](docs/plans/): phased plans. Read it when you continue planned work; the plan with `Status: active` says what comes next.
