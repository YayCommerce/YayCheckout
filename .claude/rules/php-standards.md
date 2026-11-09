---
paths:
  - "includes/**/*.php"
  - "packages/**/*.php"
  - "templates/**/*.php"
  - "YayCheckoutPluginAdapter.php"
  - "uninstall.php"
  - "yaycheckout.php"
---

<!--
Purpose: the PHP rules this repo's code cannot state about itself.
Ownership: anything below the `your rules` marker at the foot is this repo's and is
  never regenerated; everything above it comes from the kit.
Update when: the PHP floor moves, or a rule stops being true.
-->

- **Product PHP must run on PHP 7.4.** No syntax newer than that: check before
  using enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments
  or union types. The linter checks no PHP version and the dev tooling runs on a newer one,
  so only review catches this.
- **Escape at output, in the output's context** — `esc_html`, `esc_attr`, `esc_url`,
  `esc_textarea`, `wp_kses_post`, `wp_json_encode`. Read where a value came from before
  wrapping it: escaping something already escaped upstream double-escapes it.
- **Sanitize at entry**, with the most restrictive fitting function, and `wp_unslash` before
  reading request data.
- **Every handler reading `$_GET`, `$_POST`, `$_REQUEST` or `$_FILES` verifies a nonce**, and
  checks `current_user_can()` wherever the action is privileged.
- **`$wpdb` queries use `prepare()`.** Interpolation is not escaping.
- **Prefix everything with `yaycheckout_`** — functions, classes, globals, `define()`,
  options, transients, post meta, shortcodes, post types, menu pages, script handles and
  `wp_ajax_*`. Renaming an existing one is a breaking change, not a tidy-up: check
  `.yay/api.yml` first, and migrate rather than rename when the symbol is `public`.

<!-- --- your rules --- -->

<!-- Carried wholesale from this repo's own .yay/archive/old-harness/.claude/rules/php-standards.md when the kit was adopted. The kit
     wrote none of it and has not checked any of it. Trim what the rules above now cover;
     keep every line they do not — that is why this was kept rather than replaced. -->

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
- **Storage:** a new option, meta key or transient is a constant on the class that owns it, a new setting is one entry in `Settings::schema()`, then the zod schema in `apps/admin/src/lib/schema/`, and a new design token is one entry in `LayoutDesign::schema()`, then `DesignStyles::vars()` and its copy `design-css.ts`. `uninstall.php` deletes layouts and every option named `yaycheckout_*`; a key stored any other way stays behind unless you add it there.
- **Wire shapes:** a model's `to_array()` is its REST shape, snake_case, with dates through `Helpers\DateHelper::to_iso8601()`. Change the matching `*WireSchema` in `apps/admin/src/lib/schema/` in the same commit: it is strict, so the SPA rejects an unknown key.
- **Storefront:** `Renderer/` classes only read, with five exceptions: `CheckoutExtraFields` stores extra fields as order meta when the order is created, `PreviewRequest` fills and strips a preview's sample cart, `CartItemQuantity` changes or removes a cart item for the Items block's quantity stepper, `Pro\Renderer\OrderAgain` refills the cart from a past order for the Order Again Button, and `LayoutPin` pins the layout a checkout page load picked from the cart in the WooCommerce session and a hint cookie. A storefront hook stands down when `CheckoutPage::get_instance()->layout()` is null, so stock WooCommerce checkout stays untouched; `CheckoutScripts`, a support escape hatch behind a setting, is the exception.
