# YayCheckout

WooCommerce checkout builder — customize checkout fields, layout and design.

Requires WordPress 6.5+, PHP 7.4+, WooCommerce 10.0+. HPOS compatible.

## Layout

```
yaycheckout.php          plugin bootstrap: constants, autoloader, admin shell
includes/                plugin PHP (see docs/code-standards.md for layering)
apps/admin/              React settings SPA (Vite) → assets/dist/admin/
packages/blocks/         checkout blocks (@wordpress/scripts) → assets/dist/blocks/
assets/dist/             build output, gitignored
scripts/run.sh           dev / build / release
docs/                    project documentation
```

Classes autoload as `YayCheckout\Foo\Bar` → `includes/Foo/Bar.php`. The shared YayCommerce admin shell is scoped into `vendor-prefixed/` by php-scoper.

## Setup

```bash
pnpm install          # also runs composer install
```

For the admin SPA with hot reload, add to `wp-config.php`:

```php
define( 'YAYCHECKOUT_IS_DEVELOPMENT', true );
```

That switches asset registration from `RegisterProd` (built files) to `RegisterDev` (Vite dev server on :3040). Without it the dev server runs but WordPress still loads the built bundle.

## Commands

```bash
./scripts/run.sh dev       # update admin shell + Vite dev server on :3040
./scripts/run.sh build     # typecheck + build admin dashboard
./scripts/run.sh release                 # Free ZIP in release/ (default)
./scripts/run.sh release --plan=pro      # Pro ZIP
./scripts/run.sh info      # current version
./scripts/run.sh clean     # remove build artifacts

composer lint              # phpcs
composer lint:fix          # phpcbf
pnpm makepot               # regenerate languages/yaycheckout.pot
```

## Editions

One codebase, two artifacts. `includes/Pro/` holds all pro PHP and is
removed from the Free zip, so the Free build genuinely has no pro code —
not merely disabled code. Nothing outside that directory may name a
`YayCheckout\Pro` class; `composer lint` runs
`scripts/check-pro-boundary.sh` to keep that true.

| | Folder | Plugin Name |
|---|---|---|
| Free | `yaycheckout/` | YayCheckout |
| Pro | `yaycheckout-pro/` | YayCheckout Pro |

Pro installs into its own directory rather than replacing the Free one: if the
Free plugin is ever listed on wordpress.org, an update for slug `yaycheckout`
would otherwise overwrite a paid Pro install. Both being active at once is
handled — the plugin deactivates a second copy of itself.

The SPA plan comes from `__YAYCHECKOUT_PLAN__`, resolved in `vite.config.ts`.
The dev server defaults to `pro`; builds default to `free`; the release script
sets `YAYCHECKOUT_PLAN` explicitly. Note the SPA gate is cosmetic — pro
components ship in the Free bundle blurred. The Free build's PHP is what
actually withholds the features.

`release` copies a **fixed file list** — `assets/`, `includes/`, `languages/`, `vendor-prefixed/` and a few root files. Anything built outside those paths is silently absent from the ZIP. After changing the build, unzip the result and check rather than trusting the script.

## Documentation

- [`docs/codebase-summary.md`](docs/codebase-summary.md) — orientation map of the codebase structure
- [`docs/system-architecture.md`](docs/system-architecture.md) — design decisions and data flow
- [`docs/code-standards.md`](docs/code-standards.md) — layering, naming, and conventions
- `plans/` — implementation plans (gitignored)
