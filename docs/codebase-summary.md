# Codebase Summary

Orientation map. `system-architecture.md` explains why the design is what it is; `code-standards.md` covers conventions.

WooCommerce checkout builder. WordPress 6.5+, PHP 7.4+, WooCommerce 10+, HPOS compatible.

## Layout

```
yaycheckout.php          bootstrap: constants, autoloader, admin shell, duplicate guard
uninstall.php            opt-in data removal on plugin delete (no autoloader; literals mirror Model constants)
includes/                plugin PHP (~2,900 lines)
apps/admin/              React settings SPA (Vite)      → assets/dist/admin/
packages/blocks/         editor blocks (@wordpress/scripts) → assets/dist/blocks/
assets/dist/             build output, gitignored
scripts/run.sh           dev / build / release
docs/                    this
plans/                   implementation plans (gitignored)
```

Classes autoload `YayCheckout\Foo\Bar` → `includes/Foo/Bar.php`. The shared YayCommerce admin shell is scoped into `vendor-prefixed/` by php-scoper.

## PHP (`includes/`)

| Directory | Holds | Notes |
|---|---|---|
| `PostTypes/` | `LayoutPostType` | registration only: caps, template, revision policy |
| `Models/` | `Layout`, `LayoutQuery`, `Settings` | `Layout` and `Settings` are dumb entities; **every storage key constant lives here**; zero WP calls. `Settings` owns `schema()` (the single description of every setting), `from_option()` (read projection through the schema), and `sanitize()` (strict type and enum validation, unknown keys stripped). `LayoutQuery` is immutable value object for filtered/paginated queries. |
| `Repositories/` | `LayoutRepository`, `SettingsRepository` | all storage access: layouts (owns the publish sequence and the active-layout set — Free replaces, Pro appends, last id serves), settings (get/save/is_reviewed/mark_reviewed) |
| `Services/` | `DerivedCacheBuilder` | pure markup → derived caches |
| `Controllers/` | `BaseRestController`, `SettingsRestController`, `LayoutRestController` | routing, validation, permissions |
| `Blocks/` | `BlockRegistry` | registers built block directories |
| `Support/` | `Plan` | Free/Pro flag derived from provider boot; owns the layout limit and its message |
| `Pro/` | `ProServiceProvider` | **absent from the Free build** |
| `Helpers/` | `IntegrationsHelper`, `LocalizeHelper`, `Logger` | YayBoost/YayLoyalty detection (via defined constants + filter), SPA bootstrap payload, WooCommerce logging |
| `Renderer/` | `CheckoutScripts` | WooCommerce storefront hooks; today only force-enqueueing checkout scripts when `advanced.force_enqueue_checkout_scripts` is on |
| `Engine/` | `RestAPI`, `ActDeact`, `LayoutCapGuard`, `LayoutScheduleSync`, `Admin/`, `Register/` | wiring, asset registration, the layout cap, snapshot on cron publish |
| `Utils/` | `SingletonTrait` | |

Most classes use `SingletonTrait` and are constructed from `YayCheckout::__construct()`.

### Entry points

- `yaycheckout.php` → `plugin_init()` on `plugins_loaded`, after a WooCommerce check
- `YayCheckout::__construct()` boots Pro first (so `Plan` is settled), then REST, assets, admin, blocks, the post type, and the cap guard
- `Engine\RestAPI` instantiates controllers on `rest_api_init`
- `Engine\Admin\Settings` enqueues the SPA and localises `yayCheckoutMeta` / `yayCheckoutAdmin`, gated on the admin-shell hook suffix

### REST

| Route | Method | Purpose |
|---|---|---|
| `wp/v2/yay_checkout_layout` | GET/POST | core: draft save, autosave, revisions |
| `yaycheckout/v1/layouts` | GET | list one page; args: `status` (comma list), `search` (string), `page` (int ≥1); envelope `{ items, total, page, total_pages }` |
| `yaycheckout/v1/layouts` | POST | create draft; args: `name`, `source` (blank\|library), `template_slug` |
| `yaycheckout/v1/layouts/counts` | GET | per-tab totals: `listed` (everything not archived), `archived`, and store-wide `active` |
| `yaycheckout/v1/layouts/templates` | GET | library catalogue for "New layout → From library" |
| `yaycheckout/v1/layouts/{id}` | GET | single layout |
| `yaycheckout/v1/layouts/{id}/publish` | POST | draft → snapshot; a layout dated in the future goes live now, its date moved to now |
| `yaycheckout/v1/layouts/{id}/activate` | POST | publish first unless the layout already reads as Published, then add to the active set |
| `yaycheckout/v1/layouts/{id}/deactivate` | POST | remove from the active set; snapshot and status untouched |
| `yaycheckout/v1/settings` | GET/POST | envelope `{ settings, updated_at }`; POST merges over stored settings, strips unknown keys, rejects wrong types/enum values with 400 |
| `yaycheckout/v1/mark-reviewed` | POST | review prompt dismissed |

All require `manage_woocommerce`.

## Admin SPA (`apps/admin/`)

React 18 + TypeScript, Vite, hash router, TanStack Query, zod, Tailwind 4, shadcn/ui, ky.

```
src/
  main.tsx              root: QueryClientProvider, router, dev-only devtools
  router.tsx            hash routes; settings/:subMenu has tabs (general | integrations | advanced)
  AppLayout.tsx         chrome + Toaster
  pages/{page}/         route components; page-private components/ and hooks/ alongside
    settings/           one form across tabs, Save in the panel footer, unsaved-changes guard;
                        settings-sub-menus.ts is the tab list shared with router.tsx
  components/
    ui/                 shadcn primitives (kebab-case, CLI-generated)
    shared/             ours, used by two or more pages
    layout/  pro/  icons/
  lib/
    api/                ky calls per feature
    queries/            queryOptions + hooks per feature
    schema/             zod schemas mirroring PHP to_array()
    helpers/            error unwrapping
    logger.ts           namespaced console; error/warn kept in production
    plan.ts             Free/Pro, from the build-time constant
    query-client.ts     QueryClient + global error toasts
  types/                window globals, module augmentation
```

Three files per feature in `lib/` — `{feature}.schema.ts`, `.api.ts`, `.queries.ts`. `settings.*` and `layout.*` are the worked examples. `.schema.ts` holds both the strict snake_case `*WireSchema` and the camelCase app types; `.api.ts` is the only file that converts between them (see `code-standards.md`, REST). Companion detection (`useIntegrations()`) is read from the localize bootstrap, not a query — it cannot change without an admin reload.

The API client handles WordPress specifics: `X-WP-Nonce`, pretty vs. plain permalinks, and reloading on an expired nonce.

Query errors surface centrally — background refetch failures toast, first-load failures render inline, mutations always toast. `meta: { skipGlobalError: true }` opts out.

## Blocks (`packages/blocks/`)

One block so far: `yaycheckout/checkout`, the locked root of a layout. Dynamic (`save()` returns `null`), rendered by `render.php`. Built by `@wordpress/scripts`, which emits `index.asset.php` so `wp-*` dependencies are extracted rather than hand-listed.

## Tooling

| Command | Does |
|---|---|
| `./scripts/run.sh dev` | admin shell update + Vite dev server on :3040 |
| `./scripts/run.sh build` | typecheck + build SPA and blocks |
| `./scripts/run.sh release --plan=free\|pro` | versioned zip, then verifies the artifact matches the plan |
| `composer lint` | Pro boundary guard, then phpcs |
| `pnpm makepot` | regenerate the POT file |

`phpcs.xml` extends WordPress but encodes this repo's existing style (4-space indent, aligned array arrows, no Yoda, PascalCase filenames for the autoloader).

Requires `pnpm` and PHP 8.x locally. The root `dev:*` / `build:*` scripts shell out to a nested `pnpm`, which is why `run.sh` resolves a working one itself.

## Where to start

- Understanding storage → `includes/Models/Layout.php`, then `LayoutRepository::publish()`
- Adding a setting → `Models/Settings::schema()`, then `settings.schema.ts`, then a form field in `pages/settings/components/`
- Adding an endpoint → `LayoutRestController`, then the `lib/` triplet
- Adding a block → `packages/blocks/src/`, then `BlockRegistry`
- Free/Pro → `includes/Support/Plan.php` and `scripts/check-pro-boundary.sh`

## Not yet built

No tests. `Renderer/` holds only the force-enqueue hook — the plugin does not yet render layouts into WooCommerce's checkout, so published layouts are stored and rendered by `do_blocks()` but not wired into the storefront. Safe mode, telemetry opt-in and preview-link lifetime are stored settings with no consumer yet (telemetry data is not sent; preview links are not generated). The Integrations tab detects YayBoost and YayLoyalty but those companions do not yet declare checkout slots.

`Controllers/LayoutTemplateCatalogue` is a placeholder controller: a fixed template library, no database — repoint it at a real template source once one exists. Row actions Health, Preview, Restore and Delete render but have no backend yet; Set active / Deactivate are wired to `/activate` and `/deactivate`.

See the end of `system-architecture.md` for the full deferred list.
