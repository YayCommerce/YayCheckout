<!--
Purpose: what a session needs to know about YayCheckout that reading the code would not
tell it, or would cost ten reads to learn.
Update when: an invariant, a gotcha, the edition policy or the public-contract policy
changes. Structure is scout's; sections 4 and 6 are yours.
-->

# YayCheckout

<!-- yay:generated:identity -->
## 1. Identity and boundary

A WooCommerce checkout builder. A merchant assembles checkout layouts from blocks in an
embedded block editor, and the storefront renders the active layout in place of
WooCommerce's own checkout form. It is not a payment gateway and not a cart plugin: it
decides how the checkout looks and which fields appear, never how an order is paid or
priced.

**Must never:** proposed, not yet confirmed — no scope refusal is recorded anywhere in the
repo, so the second sentence above is derived from the code's reach, not from a decision.
Confirm or correct it before anything relies on it.

- **Slug:** `yaycheckout` · **main file:** `yaycheckout.php` · **text domain:** `yaycheckout`
- **Runs on:** PHP 7.4+, WP 6.5+, WC 10.0+
- **Editions:** `directory` — Free is Pro minus `includes/Pro/`. Not published to wp.org yet.
<!-- /yay:generated:identity -->

<!-- yay:generated:contract -->
## 2. Public contract

The contract is `.yay/api.yml`, not this file. 0 public, 0 internal, 37 unreviewed
(11 hooks, 25 REST routes, 1 option), plus the `yay_checkout_layout` post type.

Read it before any change that renames, removes or re-signatures a hook, filter, REST
route, option or table. `verify` blocks on removing a `public` entry and warns on
`unreviewed` (§5). An `unreviewed` count that is not falling is debt.

**Policy:** not decided. Nobody has said which of the 37 are a contract, so today every
one of them is both unremovable-in-practice and unprotected-in-theory. The 42 WooCommerce
filters this plugin re-fires are **not** its contract and must not be counted here.
<!-- /yay:generated:contract -->

<!-- yay:generated:map -->
## 3. Bootstrap and map

`yaycheckout.php` defines the constants and the autoloader, then `includes/YayCheckout.php`
boots. Pro is reached only through the guarded `boot_pro()`, which is the single place Free
names Pro; everything Pro adds attaches through `ProServiceProvider`'s hooks. The storefront
swap happens in `Renderer/ThemelessCheckout.php`, which is what replaces WooCommerce's form.

| Path | Holds | Read it when |
|---|---|---|
| `yaycheckout.php` | constants, autoloader, `YAYCHECKOUT_IS_DEVELOPMENT` | the admin is blank, or a constant is missing |
| `includes/YayCheckout.php` | boot, and the only mention of Pro in Free | touching boot order or the Pro split |
| `includes/Pro/ProServiceProvider.php` | every hook Pro attaches through | adding anything Pro |
| `includes/Models/Layout.php` | `POST_TYPE`, the layout's status vocabulary | anything about layout storage |
| `includes/Models/Settings.php` | `schema()` — the one declaration of every setting | adding or defaulting a setting |
| `includes/Models/LayoutDesign.php` | `schema()` for design tokens | adding a design token |
| `includes/Controllers/BaseRestController.php` | `REST_NAMESPACE`, the 500 logger | adding a route, or chasing a 500 |
| `includes/Renderer/ThemelessCheckout.php` | the storefront takeover | the checkout renders wrong |
| `uninstall.php` | storage keys as literals, no autoloader | renaming any stored key |
| `scripts/run.sh` | dev, build, release, 14 subcommands | any build or release question |
<!-- /yay:generated:map -->

## 4. Invariants

1. **Product PHP must run on PHP 7.4.** No enums, `match`, `readonly`, promoted
   constructor properties, `?->`, named arguments or union types. phpcs checks no PHP
   version and the dev tooling runs PHP 8, so **nothing mechanical catches a violation** —
   it ships and fataled on a customer's host.
2. **Never hand-edit generated output** (`vendor/`, `vendor-prefixed/`, `node_modules/`,
   `assets/dist/`, `release/`, the generated `preview.html` files). Change the input and
   rerun its build. The guard that enforces this only covers Claude's file tools: a shell
   write goes straight through, and because those directories are gitignored `git status`
   will not show it either — so the edit survives until the next build silently erases it.
3. **Free names Pro in exactly one place:** the guarded `boot_pro()`. Any other reference
   to `YayCheckout\Pro` in `includes/` fails `scripts/check-pro-boundary.sh`, which
   `composer lint` and CI both run. Breaking it means Free fatals on a missing class.
4. **Every storage key is a class constant on its owning class,** used at every call site.
   A mistyped key reads nothing and raises no error, so the store silently forgets its
   active layout; a mistyped constant is a fatal error you see at once. `uninstall.php` is
   the sole exception — it runs without the autoloader and repeats three keys as literals,
   so renaming a key means editing it too or uninstall leaves data behind.
5. **Escape anything thrown that is not a plain literal** (`esc_html__()` or `esc_html()`).
   wordpress.org's Plugin Check fails an unescaped exception message as
   `ExceptionNotEscaped`, and it does not read `phpcs.xml`, so a local clean lint proves
   nothing about the submission.
6. **Free's upsell components ship to the Free build.** `ProBadge`, `UpgradeButton`, the
   locked mocks and `<ProGate>` are all present in Free, so real data or a real endpoint
   placed behind one ships to Free with it.

## 5. Edition boundary

<!-- yay:generated:edition -->
**Mechanism:** `directory` — Free's zip is Pro with `includes/Pro/` removed, the SPA built
as `free`, and `editions/YayCheckoutPluginAdapter.free.php` plus a Pro-free header staged
over the branch's files. `scripts/check-pro-boundary.sh` protects it, in `composer lint`
and in CI. The markers live in `harness.yml`; do not restate them here.
<!-- /yay:generated:edition -->

**Policy:** Pro features are withheld by PHP, not by the UI — the Free build has no Pro
REST routes and no Pro block or template code, and the limits and templates check
`Plan::is_pro()`. The SPA's `isPro` only decides what the SPA draws.

This is the right shape for a wp.org submission and it is not an accident: Guideline 5
fails a plugin that ships a feature present but disabled, so "ship it off" would not be an
option here. Because `directory` physically removes the code, Free has nothing locked to
find. Upsell badges and locked *mocks* are not the thing Guideline 5 prohibits — a
`available => false` flag guarding real shipped code is.

## 6. Gotchas and history

- **Three decision areas have no record in force.** An accepted record was set to
  `Superseded by` a record that is still `Proposed`, and the practice says a proposed record
  is one nothing acts on. Base styles and the heading font, Pro template previews in Free,
  and the plugin spot each sit between two records, neither of which is in force — while the
  code implements the proposed one. Reading either end alone gives a confident wrong answer.
- **The lockfiles are not tracked** and `run.sh` runs `composer update`, not `install`. Two
  machines resolve different versions, and every dev run can move every Composer package —
  so compare `composer show` and `pnpm list` with your teammate *before* debugging the code.
- **A release run ends with `composer install --no-dev`,** even when it failed. `composer
  lint` and `composer test` then fail because phpcs and PHPUnit are gone. Run
  `composer install` after any release.
- **`run.sh dev`, `run.sh release` and `composer shell-update` rewrite tracked files** —
  `composer.json`'s `require-dev` and `scoper.inc.php`, both regenerated by the admin-shell
  sync. Check `git status` before committing; a lasting `scoper.inc.php` change belongs in
  admin-shell's template, not here.
- **`YAYCHECKOUT_IS_DEVELOPMENT` defaults to true,** so the admin page is blank unless the
  Vite dev server on :3040 is running. It looks like a broken build and is not one.
- **Two checks already fail on `dev`:** `./scripts/check-harness.sh` (27
  `[backticked_paths] MISSING` lines, because it resolves every backticked path against the
  repo root) and the admin lint. Judge a change by not adding a line, not by a green run.
- **`composer test` drops and recreates its own tables** from `WP_TESTS_DB_*`. Never point
  it at a site's database.
- **One PHP/TypeScript mirror runs backwards.** Every other one names PHP as the owner;
  `packages/blocks/src/shared/address-fields.ts` owns the address-field list and PHP seeds
  from it. Changing the PHP side first is the wrong direction there.
- **The harness before this one is at the tag `harness-v1-final`.** It was replaced because
  its docs restated the code and drifted, code comments cited doc paths that broke silently,
  `.gitignore` hid `.claude/`, ~680 lines loaded every session, and an owner table reached
  24 rows and needed its own skill to police it. That is the failure this file is shaped to
  avoid: if it starts restating the code, it is becoming the thing that was deleted.

<!-- yay:generated:workflows -->
## 7. Workflows

Commands live in `harness.yml` — this file does not duplicate them, so they cannot drift.
`lint`, `test`, `build` and `dev` all exist, all through `scripts/run.sh` or Composer.

Expected to fail today: `./scripts/check-harness.sh` and
`pnpm --filter @yaycheckout/admin-page lint`, both recorded in `docs/known-issues.md` with
their cause. CI runs neither `composer install`, phpcs, nor `composer test`.
<!-- /yay:generated:workflows -->

---
<!-- yay:generated:footer -->
Derived by `scout` from yaycheckout at 2026-11-08. `scout --check` re-verifies; a claim here
that the code no longer supports is a bug in this file, not in the code (§8).
<!-- /yay:generated:footer -->
