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

That switches asset registration from `RegisterProd` (built files) to `RegisterDev` (Vite dev server on :3030). Without it the dev server runs but WordPress still loads the built bundle.

## Commands

```bash
./scripts/run.sh dev       # update admin shell + Vite dev server on :3030
./scripts/run.sh build     # typecheck + build admin dashboard
./scripts/run.sh release   # version bump + release ZIP in release/
./scripts/run.sh info      # current version
./scripts/run.sh clean     # remove build artifacts

composer lint              # phpcs
composer lint:fix          # phpcbf
pnpm makepot               # regenerate languages/yaycheckout.pot
```

`release` copies a **fixed file list** — `assets/`, `includes/`, `languages/`, `vendor-prefixed/` and a few root files. Anything built outside those paths is silently absent from the ZIP. After changing the build, unzip the result and check rather than trusting the script.

## Documentation

- [`docs/code-standards.md`](docs/code-standards.md) — layering, naming, REST conventions
- `plans/` — implementation plans (gitignored)
