# YayCheckout Pro - WooCommerce Checkout Builder

<!-- yay:managed:start v=0.13.0 -->
<!-- Kit-managed by the yay plugin 0.13.0. Don't edit this block; run /yay:harness-update. Project knowledge goes in the yay:project block below. -->
## How we work here

This repo uses the **yay** harness. Work through its skills so every developer and every Claude session follows the same flow:

| Step | Skill | Writes |
|---|---|---|
| Not sure? Say it in plain words | `/yay:go` | nothing: shows what's next, or picks the skill below |
| Plan the horizon | `/yay:roadmap` | the roadmap (`R-<n>`): one file per sprint, a list per version |
| Explore an idea | `/yay:brainstorm` | `docs/brainstorms/` (`BS-<n>`) |
| Design a UI | `/yay:design` | `docs/designs/` (`UX-<n>`) |
| Decide the work | `/yay:plan` | `docs/plans/` (`P-<n>`) |
| Build it | `/yay:implement` | worktree, branch, commits, PR |
| Run a whole queue | `/yay:autopilot` | all of the above per item, one decision sheet, `.yay/runs/` |
| Find a bug's cause | `/yay:debug` | `docs/bugs/` (`B-<n>`) |
| Fix a bug | `/yay:fix` | worktree, branch, commits, PR |
| Test build for QA | `/yay:release --bundle` | `release/bundles/<slug>-<x.y.z>-rc.<n>/` |
| Ship | `/yay:release` → merge → `/yay:release --tag-only` | readme.txt changelog, `docs/releases/`, checked bundle, tag |
| Keep docs true | `/yay:knowledge-update` | this file's project block, `docs/knowledge/` |

- A worked example from roadmap to release tag, with the exact prompts, is in `docs/yay-workflow.md`.
- Each skill checks for the previous step's output and says when it's missing. You can still run any skill on its own.
- Lost? `yayh status` lists the work in flight in every worktree, most urgent first, each with the command that moves it on. `yayh next [ID]` prints just that command.
- The repo's commands, editions, branches and generated paths are in `.yay/config.json`. Read them with `yayh config get <key>`; never guess a command.
- Get IDs and slugs from `yayh id next <R|BS|P|B|BL|UX>` and `yayh slug "<text>"`.
- Git: branches are `<type>/<slug>`, commits are `type(scope): summary`, and you never commit to `dev` directly. The full rules are in `.claude/rules/yay-git.md`.
- Never hand-edit generated paths: vendor/, vendor-prefixed/, node_modules/, assets/dist/, dist/, release/. A hook blocks it.
- Product PHP runs on PHP 7.4+. `.claude/rules/yay-wordpress.md` lists what that rules out.
<!-- yay:managed:end -->

<!-- yay:project:start ref=edf2074 updated=2026-11-12 -->
## What this is
YayCheckout replaces WooCommerce's classic checkout with a layout the merchant builds from blocks in the block editor. The storefront prints that layout in place of the theme, but the form, fields, fragments, payments and orders stay WooCommerce's own. One branch, `dev`, is the Pro edition. Free (for wordpress.org) is built from it at release by deleting `includes/Pro/` and the Pro blocks and templates. Pro adds multi-step checkouts, assignment rules, a thank-you page, Order Again and export/import.

## Layout
| Path | What |
|---|---|
| `yaycheckout.php` | boot, constants, Free/Pro coexistence guards |
| `includes/` | PHP: Models, Repositories, Services, Controllers (REST), Engine (hook glue) |
| `includes/Renderer/` | storefront takeover, fragments, design CSS |
| `includes/Pro/` | the Pro edition; booted once from `includes/YayCheckout.php` |
| `packages/blocks/` | every checkout block (`block.json` + `render.php` + editor script); Pro ones under `src/pro/` |
| `apps/admin/` | admin SPA (React, Vite, Tailwind) |
| `templates/` | checkout page, themeless theme, layout templates (`templates/layouts/`) |
| `assets/css/`, `assets/js/` | storefront base styles and view scripts (`assets/dist/` is built) |
| `vendor-prefixed/` | scoped AdminShell, tracked; update only with `./scripts/run.sh shell-update` |
| `scripts/run.sh` | dev, build, release |
| `editions/` | the Free adapter the release swaps in |

## Commands
- `pnpm install && composer install`: install. Use Node 22. The root `postinstall` also runs composer, which needs access to the private admin-shell repo.
- `./scripts/run.sh dev`: Vite on :3040 plus the blocks watcher. Without it, the admin page is blank (`YAYCHECKOUT_IS_DEVELOPMENT` defaults to true).
- `./scripts/run.sh build`: admin SPA and blocks into `assets/dist/`. Unbuilt blocks never register.
- `composer lint`: the Pro boundary check, then phpcs.
- `pnpm --filter @yaycheckout/admin-page lint`: ESLint and prettier. prettier fails on `dev` today, so check only your files.
- `composer test`: the WordPress integration suite. It needs a MySQL test DB and doesn't run from a worktree as it stands ([known issues](docs/knowledge/known-issues.md)).
- `./scripts/run.sh release {version} --plan={edition} --no-prompt`: builds and checks one zip. It writes the version into the tree, and Free needs a committed bump.

## Hard rules
1. Nothing outside `includes/Pro/` and `packages/blocks/src/pro/` names `YayCheckout\Pro`, except the one bootstrap in `includes/YayCheckout.php`. Otherwise Free can't be built by deleting Pro (`composer lint` checks it).
2. Edition checks go through `Plan::is_pro()` in PHP and `lib/plan.ts` in the SPA. Pro UI goes behind `isPro &&`, never only `ProGate`, which still ships its children in Free.
3. Product PHP runs on PHP 7.4: no `match`, enums, named arguments or promoted constructors.
4. Layout and settings storage goes through `LayoutRepository` and `SettingsRepository`. Never write `yaycheckout_active_layout` or the `_yaycheckout_*` meta directly.
5. The checkout form stays WooCommerce's: fields through `woocommerce_form_field()`, refreshes through `update_order_review` fragments. Otherwise gateways and plugins break.
6. Every REST route extends `BaseRestController` and uses its `manage_woocommerce` permission check. Sanitize in, escape out (`esc_html()`, `KsesHelper::storefront_html()`).
7. Change PHP and TypeScript mirrors together ([conventions](docs/knowledge/conventions.md#mirrored-constants)). Nothing catches drift.
8. Never change `YayCheckoutPluginAdapter::get_plugin_slug()`: it prefixes customers' license options.

## Tests
- `yayh test run all` runs the unit (`tests/php/unit/`, Brain Monkey), js (`tests/js/`, Vitest) and wp-env integration (`tests/php/integration/`) layers. Integration needs Docker and `yayh e2e env start`. Use Node 22.
- The older integration suite lives in `tests/Integration/` (WordPress test library + WooCommerce, a Local MySQL database), run by `composer test`. Pro tests are in `tests/Integration/Pro/` and skip on Free.
- Run one with `vendor/bin/phpunit -c tests/php/unit.xml.dist --filter <TestName>` (unit) or `vendor/bin/phpunit --filter <TestName>` (Local suite).
- Browser specs: `yayh e2e run`, flows in [docs/knowledge/e2e.md](docs/knowledge/e2e.md).
- CI runs only `php -l`, the boundary check, `tsc` and ESLint.
- Layers, stubs, traps and the Coverage table: [testing.md](docs/knowledge/testing.md). Local suite setup: [release.md § Tests](docs/knowledge/release.md#tests).

## Read when…
- adding any function, hook or setting → [docs/knowledge/helpers.md](docs/knowledge/helpers.md) (reuse first)
- changing boot, editions, storage, REST or public hooks → [docs/knowledge/architecture.md](docs/knowledge/architecture.md)
- changing what the storefront prints, a block, the design CSS or a template → [docs/knowledge/storefront.md](docs/knowledge/storefront.md)
- changing the admin SPA → [docs/knowledge/admin.md](docs/knowledge/admin.md)
- building, releasing, testing or touching CI → [docs/knowledge/release.md](docs/knowledge/release.md)
- writing code in a new place, or adding a block, setting or route → [docs/knowledge/conventions.md](docs/knowledge/conventions.md)
- touching gateway, WooCommerce-surface or other-plugin compatibility → [docs/knowledge/integrations.md](docs/knowledge/integrations.md)
- writing or fixing a test → [docs/knowledge/testing.md](docs/knowledge/testing.md)
- writing or fixing a browser spec → [docs/knowledge/e2e.md](docs/knowledge/e2e.md)
- something looks wrong or a check fails → [docs/knowledge/known-issues.md](docs/knowledge/known-issues.md)
- asking "why is it built this way" → [docs/knowledge/decisions.md](docs/knowledge/decisions.md)
<!-- yay:project:end -->
