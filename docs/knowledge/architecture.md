---
area: architecture
updated: 2026-11-12
updated_ref: 44fdc3b
covers: [yaycheckout.php, YayCheckoutPluginAdapter.php, editions/, uninstall.php, includes/YayCheckout.php, includes/Fallback.php, includes/Engine/, includes/Controllers/, includes/Repositories/, includes/Models/, includes/Services/, includes/Support/, includes/PostTypes/, includes/Enums/, includes/Utils/, includes/Pro/]
---
# Architecture

> Update when: the boot order, a storage key, a REST route, a public hook or the edition split changes.

The storefront side (renderer, blocks, design, templates) is in [storefront.md](storefront.md), the admin SPA in [admin.md](admin.md), build and release in [release.md](release.md).

## Boot

1. `yaycheckout.php` guards against a second copy and against Pro being active, before defining anything:
   - If `yaycheckout-pro/yaycheckout.php` is active, a Free copy loads `includes/Fallback.php` and deactivates itself on `admin_init`. It reads `active_plugins` (and `active_sitewide_plugins`) because `is_plugin_active()` isn't loaded on the storefront, and `yaycheckout/` sorts before `yaycheckout-pro/`.
   - A `function_exists( 'YayCheckout\plugin_init' )` check stops a duplicate copy before its autoloader redeclares classes.
2. It defines the `YAYCHECKOUT_*` constants and registers the `YayCheckout\` → `includes/` autoloader.
3. At `plugins_loaded` priority 5, it boots the scoped AdminShell (`YayCheckoutScoped\YayCommerce\AdminShell`) with `YayCheckoutPluginAdapter`. Priority 5 lets version election across coexisting Yay plugins happen before menus register. AdminShell, not the plugin, registers the admin page: slug `yaycheckout`, capability `manage_woocommerce`, mount `<div id="yaycheckout">`.
4. At `plugins_loaded` priority 10, `plugin_init` bails with a notice if WooCommerce is missing, declares HPOS compatibility (`Engine\ActDeact`), then calls `YayCheckout::initialize()`.
5. The `YayCheckout` constructor calls `boot_pro()` first, then `get_instance()` on every component. **A new component must be added to that list** (`includes/YayCheckout.php`). There's no container: components use `Utils\SingletonTrait` and add their hooks in the constructor. Repositories and controllers are created with `new`.

Activation and deactivation do nothing (`Engine\ActDeact`). There are no tables, no cron and no DB-version option, and settings come from `Settings::schema()` defaults on read.

## Editions

- **The tree is Pro.** `boot_pro()` requires `includes/Pro/ProServiceProvider.php` when it's readable. It's the only non-comment reference to `YayCheckout\Pro` outside Pro, and `scripts/check-pro-boundary.sh` enforces that.
- **`Support\Plan::is_pro()`** reports that the provider booted, not the license. An expired license is still Pro. It's the only edition check core code should make.
- **Pro attaches through hooks only** (`ProServiceProvider::boot()`):
  - five REST controllers (assignment options, export, import, rules, thank-you);
  - the `yaycheckout_built_blocks` filter for Pro blocks;
  - the `yaycheckout_localize_admin_data` filter, which adds `store`;
  - `ThankYouPage` and `OrderAgain`.
- **Core branches on `Plan` instead of forking files.** For example, on Free `LayoutRepository::active()` serves only the newest active layout and ignores assignment rules.
- **Free is produced at release:** delete the Pro paths and swap in `editions/YayCheckoutPluginAdapter.free.php`. See [release.md](release.md).

## Layers

| Layer | Path | Rule |
|---|---|---|
| Models | `includes/Models/` | Pure entities: `Layout`, `LayoutDesign`, `Settings`, `LayoutQuery` (no WordPress calls) |
| Repositories | `includes/Repositories/` | All storage access. `LayoutRepository` is the one writer of layout status and the active set |
| Services | `includes/Services/` | Pure helpers ("no storage, no hooks"): `LayoutAssignment`, `LayoutMarkupReader`, `Disclosure`, `ExtraFieldSubmission`… |
| Controllers | `includes/Controllers/` | REST, all extending `BaseRestController` |
| Engine | `includes/Engine/` | Hook glue: guards, admin enqueue, font install, the editor embed |
| Renderer | `includes/Renderer/` | Storefront ([storefront.md](storefront.md)) |

## Storage

| Key | Kind | What |
|---|---|---|
| `yaycheckout_settings` | option | `general.{safe_mode,share_usage_data,delete_data_on_uninstall}`, `advanced.{preview_link_lifetime,force_enqueue_checkout_scripts,custom_css}`; `Models\Settings::schema()` is the only source |
| `yaycheckout_settings_updated_at` | option | ISO 8601 UTC |
| `yaycheckout_reviewed`, `yaycheckout_setup_done` | option | bools |
| `yaycheckout_active_layout` | option | int[] of layout ids, oldest first (older installs stored a bare int; `active_ids()` accepts both) |
| `yay_checkout_layout` | post type | One layout. `post_content` holds the block markup. Not public; `show_in_rest` through `LayoutCoreRestController`; every capability maps to `manage_woocommerce` |
| `_yaycheckout_design` | post meta | `LayoutDesign`, sanitised by `LayoutDesign::from_meta()->to_meta()`, revisioned |
| `_yaycheckout_rules`, `_yaycheckout_template`, `_yaycheckout_logged_out_content`, `_yaycheckout_thank_you_content`, `_yaycheckout_self_test` | post meta | assignment rules (Pro), template slug for Reset, the extra views |
| `yaycheckout_compatibility_catalogue` | transient | Remote list for 12 h, or the bundled list for 1 h when the fetch fails |
| `yaycheckout_layout_pin` | WC session + cookie | The pinned layout when rules read the cart ([storefront.md](storefront.md#request-flow)) |
| `{slug}_license_key`, `{slug}_license_info` | option | AdminShell licensing, prefixed by `get_plugin_slug()` |

Preview links are stateless HMAC tokens (`Helpers\PreviewToken`, query arg `yaycheckout_preview`), with no per-link revocation.

**Layout lifecycle:**
- Archive is the trash. `Engine\LayoutTrashSync` deletes `_wp_trash_meta_time`, so archived layouts are never auto-purged.
- `Engine\LayoutActiveGuard` makes the active layout undeletable (`user_has_cap`, `pre_trash_post`, `pre_delete_post`), and moving it to a non-live status deactivates it.
- `LayoutRepository::deactivate()` returns the layout to draft. `forget_active()` only edits the option and is for hooks reacting to status changes.

## REST

- Namespace `yaycheckout/v1` (`BaseRestController::REST_NAMESPACE`). Controllers are created on `rest_api_init` by `Engine\RestAPI`.
- Core routes: `/settings`, `/mark-reviewed`, `/mark-setup-done`, `/setup`, `/compatibility`, `/fonts/{slug}`, `/layouts`, `/layouts/counts`, `/layouts/bulk`, `/layouts/{id}`, and `/layouts/{id}/{publish|activate|deactivate|archive|restore|preview-link|duplicate|reset}`.
- Pro adds its own controllers in `includes/Pro/`.
- Draft saves from the block editor go through core `wp/v2/yay_checkout_layout`.
- Every route uses `BaseRestController::permission_callback()`, which checks `manage_woocommerce`.

Core has no admin `wp_ajax_*` handlers. The storefront wc-ajax endpoints are listed in [storefront.md](storefront.md).

## Hooks the plugin exposes

| Filter | Where | For |
|---|---|---|
| `yaycheckout_built_blocks` | `Blocks\BlockRegistry` | add built block dirs (Pro does) |
| `yaycheckout_editor_views` | `Engine\Admin\LayoutEditorEmbed` | view → meta key (Pro adds thank-you) |
| `yaycheckout_localize_admin_data`, `yaycheckout_localize_meta` | `Helpers\LocalizeHelper` | SPA bootstrap data |
| `yaycheckout_integrations` | `Helpers\IntegrationsHelper` | companion plugins shown in the SPA |
| `yaycheckout_plugin_blocks` | `Helpers\PluginBlockCatalogue` | plugin placeholder spots |
| `yaycheckout_express_wallet_order_markers` | `Renderer\ExpressPay::is_wallet_order()` | POST keys that mark a gateway's wallet order |
| `yaycheckout_themeless_checkout` | `Renderer\ThemelessCheckout` | opt out of themeless (mu-plugin only: runs at `setup_theme`) |
| `yaycheckout_checkout_terms`, `yaycheckout_layout_renders_order_received`, `yaycheckout_live_values_source` | `includes/Renderer/` | renderer extension points |

## Uninstall

- `uninstall.php` does nothing unless `general.delete_data_on_uninstall` is true.
- It also stops if another `yaycheckout.php` copy remains, so swapping Free for Pro keeps the data.
- It runs without the autoloader, so it repeats `'yaycheckout_settings'`, `'delete_data_on_uninstall'` and `'yay_checkout_layout'` as literals. Keep them in sync.
- It deletes options before layouts, because `LayoutActiveGuard` would refuse to delete the active layout.
- It finds layouts with raw SQL, because `get_posts( 'any' )` skips the trash.
