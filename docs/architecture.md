# Architecture

**Purpose:** how YayCheckout's parts fit together: boot, the admin-shell sync, the server layers and who writes what, how a layout is stored and picked, the storefront, preview links, blocks and the embedded editor, the Free/Pro split, how the admin SPA reaches WordPress, the compatibility scan, release, and tests. Each claim names the file that decides it; read that file for the details.

**Update when:** a change moves a boundary described here: boot order or autoloading, the admin-shell sync, a layer or a writer, a stored option or post field, how the storefront picks or renders a layout, the preview rules, how blocks register or which editor offers them, the Free/Pro seam or its checks, how the SPA loads or calls WordPress, the compatibility scan, the release steps, or the test harness. A feature that stays inside an existing layer needs no update.

Commands and hard rules live in [CLAUDE.md](../CLAUDE.md). Citations give a path and a symbol, not a line number: search for the symbol.

## Boot and autoload

- `yaycheckout.php` is the entry point. Each of its constants is defined only when not already defined, so `wp-config.php` can override any of them. `YAYCHECKOUT_IS_DEVELOPMENT` is the one a site sets (see [SPA and WordPress](#spa-and-wordpress)).
- Two autoloaders run. `composer.json` classmaps only the scoped admin-shell in `vendor-prefixed/src/` and `YayCheckoutPluginAdapter.php`, loaded through `vendor/autoload.php`, which `yaycheckout.php` requires unconditionally. Everything in the `YayCheckout\` namespace goes through the loader in `yaycheckout.php`, which maps it onto `includes/`, requires a file only if it exists, and uses `require_once` because a doubled backslash in a class name maps to the same file.
- That `file_exists` check lets one tree run with parts missing: Free has no `includes/Pro/`, and release zips have no `RegisterDev.php`, so code probes those classes with `class_exists` or `is_readable` instead of naming them.
- Boot runs on `plugins_loaded`:
  - priority 5: admin-shell boots and registers the adapter (see [the next section](#admin-shell-sync-and-scoping));
  - priority 10: `plugin_init()` shows a notice and stops if WooCommerce is missing. Otherwise it declares HPOS compatibility (`Engine\ActDeact::before_woocommerce_init`), builds the `YayCheckout` singleton and loads translations.
- `includes/YayCheckout.php` wires everything by hand in its constructor, with no container. `boot_pro()` runs first so `Support\Plan::is_pro()` is settled before any component reads it; then REST, asset registration, the admin page, blocks, the post type, the layout guards, the editor embed, and the storefront renderers.
- Activation stores nothing: settings and design derive from their schemas on read. `uninstall.php` deletes layouts and `yaycheckout_*` options only when the merchant turned on `general.delete_data_on_uninstall`; it runs without the autoloader, so it repeats those keys as literals.

## Admin-shell sync and scoping

- Admin-shell is a private Composer package shared by the YayCommerce plugins. It draws the shared YayCommerce admin menu and its common pages, and adds this plugin's submenu entry.
- Every plugin ships its own copy, so php-scoper rewrites the package into `vendor-prefixed/` under the `YayCheckoutScoped` namespace (`scoper.inc.php`). [The decision record](decisions/2026-10-05-admin-shell-scoped-with-php-scoper.md) says why. With several YayCommerce plugins active, the newest copy draws the shared menu.
- `YayCheckoutPluginAdapter.php` joins the two. It is a global class that implements the scoped menu-adapter interface and stays out of scoping. It names the page slug `yaycheckout` and the capability `manage_woocommerce`, and its page callback prints the SPA's mount point, `<div id="yaycheckout">`.
- The sync lives in the package's own scripts. `./scripts/run.sh dev` runs a full `composer update`, then `vendor/bin/yaycommerce-update`, which deletes `vendor-prefixed/`, updates admin-shell, requires php-scoper and thecodingmachine/safe as dev dependencies (which can rewrite `composer.json`), regenerates `scoper.inc.php` from the package's template keeping only the prefix and the adapter's name, then scopes the package and dumps the classmap.
- `./scripts/run.sh release` runs the `yaycommerce-prerelease` variant, which ends with `composer install --no-dev`. A plain `composer install` never builds `vendor-prefixed/`.

## Server layers and write ownership

- REST lives under `BaseRestController::REST_NAMESPACE` (yaycheckout/v1). `Engine\RestAPI` builds the Free controllers on `rest_api_init`; `Pro\ProServiceProvider::rest_endpoints()` builds the Pro ones, so the Free build simply has no such route. Every route, ours and core's, requires `LayoutPostType::CAPABILITY` (`manage_woocommerce`).
- Callbacks run through `BaseRestController`: `exec_read`, `exec_write_wp` for WordPress API writes, and `exec_write`, which wraps a SQL transaction, for custom tables. There are no custom tables, so nothing uses `exec_write`. WordPress writes go through the object cache, and a `ROLLBACK` would leave a persistent cache holding rolled-back data; ordering the writes so a partial failure leaves the previous state is the substitute. A thrown exception becomes a 500 and is logged by `Helpers\Logger::exception()`.
- The layers under `includes/`: `Models/` are plain values that own their storage-key constants and schemas; `Repositories/` hold every post, meta and option call and the invariants; `Services/` hold computation that spans repositories or needs no WordPress (`LayoutMarkupReader`, `LayoutAssignment`, `RequiredFieldOverlay`); `Controllers/` are thin; `Renderer/` hooks the storefront and only reads; `Engine/` wires boot, assets, the admin page and the layout guards.
- Writers: `LayoutRepository` writes layouts and the active set; `SettingsRepository` writes `yaycheckout_settings` and its flags (`yaycheckout_settings_updated_at`, `yaycheckout_reviewed`, `yaycheckout_setup_done`); `DesignRepository` writes `yaycheckout_design`; core's REST API (wp/v2) writes `post_content` from the editor; `CompatibilityCatalogueRepository` caches the catalogue in a transient. Preview links store nothing.
- `Models\Settings::schema()` and `Models\Design::schema()` are the one description of each key, its type and default. `from_option()` projects what is stored through the schema, so a missing, stale or invalid value reads as its default and the SPA never meets an unknown shape; `sanitize()` checks a write against the same schema. Built-in design presets live in code; merchant presets are stored in `yaycheckout_design`.
- `POST /setup` (`Controllers\SetupRestController::complete_setup()`) validates the template and design before its first write, then creates the draft, saves the design and marks setup done. Once setup is done it answers 409, so a retry cannot add a second draft. `LayoutTemplateCatalogue::assert_available()` decides which templates a plan may create from.

## Layouts: one post, a status and an active set

- A layout is one `yay_checkout_layout` post (`PostTypes\LayoutPostType`): `post_content` holds the block markup, and `post_status` says whether shoppers may see it. [The decision record](decisions/2026-10-05-layout-is-one-post.md) says why. Publishing is a status change, the same one the editor's Publish button and cron's `publish_future_post` make; the wire `status` is `post_status` (`Enums\LayoutStatus`).
- The post type maps every capability to `manage_woocommerce` with `map_meta_cap => false`. `Controllers\LayoutCoreRestController` is its `rest_controller_class`: it requires the capability for reads too, because core answers a logged-out read of a published post, and its `prepare_item_for_database()` refuses a wp/v2 save that would leave a layout published while `LayoutMarkupReader::publish_error()` objects.
- `LayoutRepository::publish()` and `activate()` also refuse on `publish_error()`, and refuse an archived layout, since setting its status would restore it from the trash. `publish()` goes through `wp_publish_post()`, so a scheduled or pending layout goes live at once; `activate()` publishes first unless the layout already is.
- The active set is the `Layout::OPTION_ACTIVE` option, an ordered list of ids. Free keeps one (`set_active()` replaces it); Pro keeps several, most recently activated last.
- Guards keep the set honest (`includes/Engine/`): `LayoutActiveGuard` refuses to trash or delete an active layout and drops one whose status moves to draft or pending; `LayoutTrashSync` removes WordPress's trash timestamp, so an archived layout is never purged by cron; `LayoutCapGuard` enforces the Free limit (`LayoutRepository::FREE_LIMIT`) on core's insert, because any shop manager can POST to core's wp/v2 route directly. `LayoutRepository::archive()` deactivates before it trashes; `restore()` doesn't check the limit.
- Layout lists are paged server-side (`Models\LayoutQuery::PER_PAGE`), and search matches titles only (`LayoutRepository::match_search_to_title()`), since `post_content` is markup.

## Storefront

- `Renderer\CheckoutPage` takes over the checkout page on `wp` when safe mode is off and a layout resolves, endpoints such as order-received included. Otherwise WooCommerce is untouched. A preview link resolves its own layout first (see [Preview links](#preview-links)).
- **Which layout:** `LayoutRepository::active()` gets the shopper facts that `CheckoutPage` reads (roles, logged in, `wp_is_mobile()`). Free serves its one active layout; Pro hands the set to `Services\LayoutAssignment::pick()`, which serves the first layout whose rules match (groups ORed, conditions ANDed; no rules matches everyone). No match leaves WooCommerce's checkout.
- Every request picks afresh, AJAX and the order submit included, with no pin. That is safe only because each `Enums\ConditionType` reads a fact that holds for the life of a form: the required-field overlay and extra fields validate against the served layout, so a fact the shopper can change mid-form, such as billing country or cart, must never become a condition.
- **Rendering:** `template_include` at `PHP_INT_MAX` swaps in `templates/checkout-page.php`, which renders the layout before `wp_head()` (block themes print the import map there), then the theme's header and footer around it. The root `checkout` block is the form. `Renderer\DesignStyles` prints the design as `--yaycheckout-*` custom properties, only for sections the merchant turned on.
- **Validation:** `Renderer\CheckoutRequiredFields` filters `woocommerce_checkout_fields` at 9999 through `Services\RequiredFieldOverlay::apply()`, so a field is required exactly when its block says so; email is always required. The filter isn't gated on the checkout page, because the checkout POST validates before `is_page()` works. `assets/js/checkout-field-requirements.js` re-applies flags and labels after WooCommerce's `address-i18n.js` resets them on a country change.
- **Extra fields and fragments:** `Renderer\CheckoutExtraFields` reads the served layout's extra fields (`LayoutMarkupReader::extra_fields()`), checks them with `Services\ExtraFieldSubmission::collect()` and stores them as order meta on `woocommerce_checkout_create_order`. A Key must match `LayoutMarkupReader::EXTRA_FIELD_KEY_PATTERN`, because a leading underscore reaches WooCommerce's internal order setters. `Renderer\CheckoutFragments` swaps the shipping-rate and payment fragments only for sections `LayoutMarkupReader::slots()` finds in the layout.

## Preview links

- `POST /layouts/{id}/preview-link` returns the checkout URL with `?yaycheckout_preview=TOKEN`. `Helpers\PreviewToken` signs the layout, blog and expiry with an HMAC keyed to this purpose and blog, so the token is its own record and nothing is stored. Anyone with the link can view it; per-link revocation isn't built.
- `Renderer\PreviewRequest` reads the token on `init` and applies its rules on `wp` at 5, before `CheckoutPage`. A token that doesn't resolve answers 410 instead of falling back to the live layout. A preview wins over safe mode. Hooks attach only on the checkout page or a wc-ajax call. Ordering is refused whenever the argument is present, valid or not; an empty cart gets sample products marked with `PreviewRequest::CART_ITEM_FLAG`, which every non-preview request strips.

## Blocks and the embedded editor

- Blocks are dynamic: each `packages/blocks/src/<block>/` has a `block.json` and a `render.php`, and `save()` returns nothing, so a renamed attribute needs a default in `render.php`, not a block deprecation. `@wordpress/scripts` copies both into `assets/dist/blocks/<block>/`.
- `Blocks\BlockRegistry::register_blocks()` registers every built directory it finds, in `BlockRegistry::ORDER` (the inserter's order), so with no build there are no blocks. `allowed_blocks()` keeps `yaycheckout/*` blocks in the layout editor only, and WooCommerce's Checkout block out of it. Placement rules live in each `block.json` (`parent`, `ancestor`, `supports.multiple`).
- One blocks bundle serves both editions: `BlockRegistry` passes `isPro`, the post type and the upgrade link as `window.yayCheckoutBlocks` (`packages/blocks/src/shared/editor-data.ts`).
- `packages/blocks/src/checkout/publish-guard.ts` locks saving on the same rules as `LayoutMarkupReader::publish_error()`, with a notice; the server refuses on them too, so the lock is not the only guard.
- The admin edits a layout in core's `post.php` editor inside a modal iframe with `?yc-embed=1`. `Engine\Admin\LayoutEditorEmbed` hides the wp-admin chrome and tags the editor's REST calls with `X-YayCheckout-Embed`, and `LayoutCoreRestController::delete_item_permissions_check()` refuses delete for them: after a trash, core navigates the frame to a screen the modal can't host. Drafts save through core's REST API, so autosave and revisions come free.

## Free and Pro

- One codebase, two zips: Free (`yaycheckout/`) without `includes/Pro/`, and Pro (`yaycheckout-pro/`) with it. Pro installs into its own folder, so a wordpress.org update for slug `yaycheckout` can't overwrite a paid install. [The decision record](decisions/2026-10-05-build-time-free-pro-split.md) says why the split is made at build time.
- PHP: `YayCheckout::boot_pro()` requires `includes/Pro/ProServiceProvider.php` when it is readable. The provider calls `Plan::mark_pro()` and attaches through hooks and filters only. `Plan::is_pro()` reports whether it booted, so it can't claim Pro while the files are missing; it describes the build, not a licence. `scripts/check-pro-boundary.sh`, run by `composer lint`, allows exactly one reference to `YayCheckout\Pro` in `includes/`, the one in `boot_pro()`.
- SPA: `YAYCHECKOUT_PLAN` becomes `__YAYCHECKOUT_PLAN__` in `apps/admin/vite.config.ts`; the dev server defaults to `pro` and builds to `free`. `apps/admin/src/lib/plan.ts` exposes it as `isPro`. `<ProGate>` ships its children to Free, blurred, as an upsell: safe only because Free's PHP has no matching endpoint.
- Free allows `LayoutRepository::FREE_LIMIT` layouts and Pro is unlimited (`Plan::layout_limit()`). `LayoutRepository::insert_draft()`, behind create, duplicate and import, and `LayoutCapGuard` both ask `Plan::layout_limit_reached()`.

## SPA and WordPress

- `Engine\Admin\Settings` enqueues the SPA only on the admin page's hook suffix. `Engine\Register\RegisterFacade` picks `RegisterDev` (Vite at `RegisterDev::DEV_SERVER`, port 3040) while `YAYCHECKOUT_IS_DEVELOPMENT` is true, which is the default, and `RegisterProd` (`assets/dist/admin/`) otherwise.
- `Helpers\LocalizeHelper` prints `window.yayCheckoutMeta` (REST root, nonce, site data) and `window.yayCheckoutAdmin`, each through a filter; Pro adds `store` to the admin data.
- The SPA is a hash-routed React app. `apps/admin/src/lib/api/api.ts` is the ky client, with the REST root and nonce from `yayCheckoutMeta`. Each resource has a zod schema in `apps/admin/src/lib/schema/`, its calls in `apps/admin/src/lib/api/` and its queries in `apps/admin/src/lib/queries/`; the conventions for that bridge are in [conventions.md](conventions.md).
- Production builds one entry into `assets/dist/admin/`, with React and `@wordpress/*` external. The SPA imports the blocks' shared code through the `@blocks-shared` alias.

## Compatibility scan

- `GET /compatibility` (`Controllers\CompatibilityRestController`) composes three live detections and a catalogue, and stores nothing but the catalogue transient.
- `Helpers\StoreEnvironmentHelper` reads versions, the theme and whether the store's checkout is the block checkout. `Helpers\GatewayCompatibilityHelper` lists enabled classic gateways, then active block payment methods with no classic gateway of the same id, flagged `block-only`, because YayCheckout renders the classic form. `Helpers\CheckoutHookScanHelper` resolves every callback on a fixed list of checkout hooks to a plugin, mu-plugin or theme by its file; it runs inside a REST request, so hooks added only on `wp` or `template_redirect` are invisible. A row is a hint that a plugin is involved, not proof that one isn't.
- `Repositories\CompatibilityCatalogueRepository` fetches the remote catalogue at `YAYCHECKOUT_COMPATIBILITY_CATALOGUE_URL` and falls back to the bundled `Support\CompatibilityCatalogue`. A failed fetch caches the bundled list for `CompatibilityCatalogue::TTL_FALLBACK`, so a dead endpoint costs one attempt per that period, not one per page view. `normalize()` is the only gate the remote document passes.

## Build and release

- `./scripts/run.sh build` type-checks and builds the SPA, then the blocks. Both must build for a release: without the blocks build, a zip ships an editor with no YayCheckout blocks.
- `./scripts/run.sh release` sets the version (`yaycheckout.php` header and `YAYCHECKOUT_VERSION`, and `Stable tag:` in `readme.txt`), runs the admin-shell prerelease sync, exports `YAYCHECKOUT_PLAN` and builds, then copies a fixed list into a folder named after the edition. Free deletes `includes/Pro/`; Pro renames the plugin in its header. It strips `RegisterDev.php`, source maps and `.gitkeep`, zips into `release/`, and checks the zip for `includes/Pro/` entries against the plan.
- The copy list is the release: a runtime file outside it is silently absent from the zip. The list and its strip step live in `cmd_release` in `scripts/run.sh`.

## Tests

- `composer test` runs PHPUnit 9 with `phpunit.xml.dist`: one suite, `tests/Integration/`, which holds no tests today.
- `tests/bootstrap.php` loads the WordPress test library from `vendor/wp-phpunit/`, then WooCommerce (the sibling `woocommerce/` plugin) and this plugin as must-use plugins, and reinstalls WooCommerce, because the test library recreates the database on every run. `tests/wp-tests-config.php` reads the database from the environment.
- `tests/Support/` holds fakes (gateways, a layout factory) and `tests/fixtures/` holds fake plugins and themes for the hook scan. When to add a test is in [CLAUDE.md](../CLAUDE.md).
