---
paths:
  - "includes/**/*.php"
  - "yaycheckout.php"
  - "YayCheckoutPluginAdapter.php"
  - "uninstall.php"
  - "templates/**/*.php"
  - "packages/blocks/src/**/*.php"
  - "tests/**/*.php"
---

<!--
Purpose: rules for writing the plugin's PHP: the house style, the parts of the PHP 7.4 floor that no tool checks, and the patterns new code follows. Loads when Claude reads a matching file.
Update when: phpcs.xml changes, the PHP or WordPress floor moves, or one of these patterns changes.
-->

# PHP

Layers and writers are in [docs/architecture.md § Server layers and write ownership](../../docs/architecture.md#server-layers-and-write-ownership). Storage keys, schemas, dates, logging and comments are in [docs/conventions.md](../../docs/conventions.md). Hard rules 2–4 in `CLAUDE.md` cover Pro and PHP 7.4.

## Style

- `phpcs.xml` applies the WordPress standard, with this repo's exceptions: 4-space indents, short arrays `[]`, no Yoda conditions, and PascalCase class files. The rest is WordPress style: spaces inside parentheses, aligned `=>` in multi-line arrays, the `yaycheckout` text domain, and the `yaycheckout_` prefix on globals and hooks.
- A class file in `includes/` opens with its namespace, its `use` lines, `defined( 'ABSPATH' ) || exit;` and the class docblock.
- Lint what you changed with `composer lint -- <files>`.

## PHP 7.4, beyond hard rule 4

- These are out too, and neither phpcs nor `php -l` on PHP 8 reports them: `mixed`, `static`, `never` and intersection types; a `catch` without a variable; `throw` as an expression; a trailing comma after the last parameter of a declaration; `new` in a default value; first-class callables such as `strlen(...)`; `$object::class`; and attributes.
- Use a PHP 8 function only where WordPress 6.5 polyfills it: `str_contains`, `str_starts_with`, `str_ends_with` and `array_is_list`.

## Patterns for new code

- **Layers:** a WordPress call that reads or writes state (`get_post`, `get_option`, `update_post_meta`, `wp_insert_post`) belongs in `Repositories/`, `Helpers/`, `Engine/`, `Renderer/` or `Blocks/`, never in `Models/` or `Services/`, which take already-fetched data. Translation and escaping functions are fine anywhere.
- **REST:** a controller extends `Controllers\BaseRestController`, uses `SingletonTrait`, registers its routes in its constructor, and is built in `Engine\RestAPI::checkout_endpoints()` (Pro: `Pro\ProServiceProvider::rest_endpoints()`). Its `permission_callback` requires `LayoutPostType::CAPABILITY`. Validate route parameters in `args` with a `validate_callback`, and when a route takes a post id, check the post type in the handler, or the route changes any post. Wrap the callback in `exec_read()` or `exec_write_wp()`; `exec_write()` opens a SQL transaction and is only for custom tables, which a rollback can't keep in step with the object cache. Answer expected failures with the `error_*()` helpers as a 4xx; a thrown exception becomes a logged 500.
- **Storage:** a new option, meta key or transient is a constant on the class that owns it, and a new setting or design token is one entry in its `schema()`, then the zod schema in `apps/admin/src/lib/schema/`. `uninstall.php` deletes layouts and every option named `yaycheckout_*`; a key stored any other way stays behind unless you add it there.
- **Wire shapes:** a model's `to_array()` is its REST shape, snake_case, with dates through `Helpers\DateHelper::to_iso8601()`. Change the matching `*WireSchema` in `apps/admin/src/lib/schema/` in the same commit: it is strict, so the SPA rejects an unknown key.
- **Storefront:** `Renderer/` classes only read. A storefront hook stands down when `CheckoutPage::get_instance()->layout()` is null, so stock WooCommerce checkout stays untouched; `CheckoutScripts`, a support escape hatch behind a setting, is the exception.
