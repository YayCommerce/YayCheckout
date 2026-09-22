# Codebase Summary

Orientation map. `system-architecture.md` explains why the design is what it is; `code-standards.md` covers conventions.

WooCommerce checkout builder. WordPress 6.5+, PHP 7.4+, WooCommerce 10+, HPOS compatible.

## Layout

```
yaycheckout.php          bootstrap: constants, autoloader, admin shell, duplicate guard
uninstall.php            opt-in data removal on plugin delete (no autoloader; literals mirror Model constants)
includes/                plugin PHP (~4,000 lines)
apps/admin/              React settings SPA (Vite)      → assets/dist/admin/
packages/blocks/         editor blocks (@wordpress/scripts) → assets/dist/blocks/
templates/               checkout page template (theme header/footer around the layout); the root block is the form
assets/dist/             build output, gitignored
scripts/run.sh           dev / build / release
tests/                   PHPUnit integration suite (wp-phpunit, WooCommerce booted); fixtures/ holds fake plugins/themes for the hook scan
docs/                    this
plans/                   implementation plans (gitignored)
```

Classes autoload `YayCheckout\Foo\Bar` → `includes/Foo/Bar.php`. The shared YayCommerce admin shell is scoped into `vendor-prefixed/` by php-scoper.

## PHP (`includes/`)

| Directory | Holds | Notes |
|---|---|---|
| `Enums/` | `LayoutStatus` | constants class (no native enums on PHP 7.4); the WP `post_status` values, which are also the wire vocabulary |
| `PostTypes/` | `LayoutPostType` | registration only: caps, template, revision policy |
| `Models/` | `Layout`, `LayoutQuery`, `Settings`, `Design` | dumb entities; **every storage key constant lives here**; zero WP calls. `Settings` and `Design` own `schema()` (the single description of settings/tokens), `from_option()` (read projection through the schema), and `sanitize()` (strict validation, unknown keys stripped). `LayoutQuery` is immutable value object for filtered/paginated queries. |
| `Repositories/` | `LayoutRepository`, `SettingsRepository`, `DesignRepository`, `CompatibilityCatalogueRepository` | all storage access: layouts (publish sequence, active-set), settings (get/save/reviewed), design (tokens, presets, CSS), catalogue (transient → remote JSON → fallback) |
| `Controllers/` | `BaseRestController`, `SettingsRestController`, `LayoutRestController`, `LayoutCoreRestController`, `LayoutTemplateCatalogue`, `DesignRestController`, `CompatibilityRestController` | routing, validation, permissions; all require `manage_woocommerce` |
| `Services/` | `LayoutBlockIndex`, `ExtraFieldPostedCollector` | pure: no storage. `LayoutBlockIndex` reduces layout markup to `{ fields, extra_fields, slots, order_modes }`; blocks contribute through the `yaycheckout_block_contributors` filter. `ExtraFieldPostedCollector` validates and normalizes posted extra-field values against the index. |
| `Blocks/` | `BlockRegistry` | registers built block directories |
| `Support/` | `Plan`, `CompatibilityCatalogue` | Free/Pro flag derived from provider boot; owns the layout limit and its message. Compatibility catalogue: status vocabulary, bundled list, `normalize()` for the remote JSON, transient key and TTLs — pure |
| `Pro/` | `ProServiceProvider`, `Controllers/DesignPresetRestController` | **absent from the Free build**; preset management REST routes |
| `Helpers/` | `BlockMarkup`, `IntegrationsHelper`, `LocalizeHelper`, `Logger`, `PreviewToken`, `StoreEnvironmentHelper`, `GatewayCompatibilityHelper`, `CheckoutHookScanHelper`, `PluginHeaderHelper`, `ExtraFieldKeyHelper` | stateless: preview tokens, server-side block markup (seeds, templates), integration detection, SPA bootstrap payload, logging, compatibility detection, hook scans, extra-field key fallback naming |
| `Renderer/` | `CheckoutScripts`, `CheckoutPage`, `PreviewRequest`, `CheckoutFragments`, `CheckoutExtraFields` | Storefront takeover and AJAX; `CheckoutFragments` swaps payment/shipping fragments on `update_order_review` when the layout renders those blocks. `CheckoutExtraFields` validates and persists custom extra fields to order meta via hooks on checkout create/update. |
| `Engine/` | `RestAPI`, `ActDeact`, `LayoutCapGuard`, `LayoutTrashSync`, `LayoutActiveGuard`, `Admin/` (Settings, LayoutEditorEmbed), `Register/` (RegisterFacade, RegisterDev, RegisterProd, ScriptName) | wiring, asset registration, the layout cap, trashed layouts kept out of WP's purge, active-set integrity, embedded-editor chrome and REST tagging, dev/prod asset registration |
| `Utils/` | `SingletonTrait` | |
| `Fallback.php` | duplicate-guard admin notice | shown when another YayCheckout copy is installed; wired in yaycheckout.php |
| `I18n.php` | textdomain loader | loaded via plugins_loaded, handles i18n with locale detection |

Most classes use `SingletonTrait` and are constructed from `YayCheckout::__construct()`.

### Entry points

- `yaycheckout.php` → `plugin_init()` on `plugins_loaded`, after a WooCommerce check; also registers `I18n::load_plugin_textdomain()` on `plugins_loaded`
- `YayCheckout::__construct()` boots in order: `boot_pro()`, `RestAPI`, `RegisterFacade`, `Admin\Settings`, `BlockRegistry`, `LayoutPostType`, `LayoutCapGuard`, `LayoutTrashSync`, `LayoutActiveGuard`, `Admin\LayoutEditorEmbed`, `Renderer\PreviewRequest`, `Renderer\CheckoutScripts`, `Renderer\CheckoutPage`, `Renderer\CheckoutFragments`, `Renderer\CheckoutExtraFields`
- `Engine\RestAPI` instantiates controllers on `rest_api_init`; Free: `SettingsRestController`, `LayoutRestController`, `DesignRestController`, `CompatibilityRestController`; Pro adds `DesignPresetRestController`
- `Engine\Admin\Settings` enqueues the SPA and localises `yayCheckoutMeta` / `yayCheckoutAdmin`, gated on the admin-shell hook suffix
- `Engine\Register\RegisterFacade` branches to RegisterDev or RegisterProd on `init`, conditionally based on `YAYCHECKOUT_IS_DEVELOPMENT`

### REST

| Route | Method | Purpose |
|---|---|---|
| `wp/v2/yay_checkout_layout` | GET/POST | core: draft save, autosave, revisions |
| `yaycheckout/v1/layouts` | GET | list one page; args: `status` (comma list), `search` (string), `page` (int ≥1); envelope `{ items, total, page, total_pages }` |
| `yaycheckout/v1/layouts` | POST | create draft seeded with contact, billing, shipping, order-review; args: `name`, `source` (blank\|library), `template_slug` |
| `yaycheckout/v1/layouts/counts` | GET | per-tab totals: `listed` (everything not archived), `archived`, and store-wide `active` |
| `yaycheckout/v1/layouts/templates` | GET | library catalogue for "New layout → From library" |
| `yaycheckout/v1/layouts/{id}` | GET | single layout |
| `yaycheckout/v1/layouts/{id}` | DELETE | permanent delete with revisions and meta; archived, non-active layouts only (400 otherwise) |
| `yaycheckout/v1/layouts/{id}/publish` | POST | status → `publish`; a layout dated in the future goes live now, its date moved to now |
| `yaycheckout/v1/layouts/{id}/activate` | POST | publish first unless `post_status` is already `publish`, then add to the active set |
| `yaycheckout/v1/layouts/{id}/deactivate` | POST | remove from the active set; status untouched |
| `yaycheckout/v1/layouts/{id}/archive` | POST | move to the archive (trash), deactivating it first if it was active |
| `yaycheckout/v1/layouts/{id}/preview-link` | POST | `{ url, expires_at }`: the checkout URL with a fresh token, lifetime from `advanced.preview_link_lifetime`; any status, nothing stored |
| `yaycheckout/v1/design` | GET/POST | envelope `{ tokens, preset_id, custom_css, updated_at, presets }`; POST replaces the whole design (all three keys required) |
| `yaycheckout/v1/design/presets` | POST | `{ name, tokens }`; returns full design envelope; Pro only |
| `yaycheckout/v1/design/presets/{id}` | DELETE | drop merchant preset; returns full design envelope; Pro only |
| `yaycheckout/v1/compatibility` | GET | `{ store, gateways, plugins, catalogue: { source, fetched_at } }`; arg `refresh` (bool) drops the cached catalogue first |
| `yaycheckout/v1/settings` | GET/POST | envelope `{ settings, updated_at }`; POST merges over stored settings, strips unknown keys, rejects wrong types/enum values with 400 |
| `yaycheckout/v1/mark-reviewed` | POST | review prompt dismissed |

All require `manage_woocommerce`.

## Admin SPA (`apps/admin/`)

React 18 + TypeScript, Vite, hash router, TanStack Query, zod, Tailwind 4, shadcn/ui, ky. (~3,700 lines excluding `components/ui`).

Routes: `/` → `/layouts`, `/layouts`, `/design`, `/tools`, `/settings/{general|integrations|advanced}`, `/compatibility`, `*` (404).

```
src/
  main.tsx              root: QueryClientProvider, router, dev-only devtools
  router.tsx            hash routes; settings/:subMenu has tabs (general | integrations | advanced)
  AppLayout.tsx         chrome + Toaster
  PageLayout.tsx        common layout wrapper for pages
  pages/
    404.tsx  500.tsx    error pages
    layouts/            LayoutsPage (queries, filters, modals)
      LayoutsPage.tsx   list with pagination, search, status tabs; Edit/Preview/Publish/Set active/Deactivate/Archive/Restore/Delete for good real; Health placeholder DOM
      PreviewLinkModal.tsx  minted preview URL with Copy (select-text fallback on http) and Open
      SetActiveModal.tsx    Set active modal (real)
      DeactivateModal.tsx   Deactivate modal (real)
      ArchiveModal.tsx      Archive modal (real)
      DeleteModal.tsx       Delete-for-good modal: typed DELETE gate (real)
      new-layout/       NewLayoutModal, SourceStep, LibraryStep, NameField, TemplateThumbnail + template-thumbnail-sketches (SVG sketch per catalogue slug)
    design/             Design tokens page (real)
      DesignPage.tsx    form with token groups, preset picker, custom CSS, Save/Reset actions
      components/       TokenGroups (summary + accordion), TokenField, PxInput; PresetsCard (built-in + merchant); ColoursGroup, TypographyGroup, SpacingGroup, ButtonsGroup, InputsGroup, CustomCssGroup; PreviewPane, DesignActions, SavePresetModal (Pro), ResetConfirmModal
    settings/           one form across tabs, Save in the panel footer, unsaved-changes guard
    compatibility/      CompatibilityPage: warning banner, Store / Payment gateways / Plugins touching checkout, Re-scan, catalogue footnote
      compatibility-warnings.ts   report → { blockOnly, unverifiedPlugins }
      components/       WarningBanner, StoreSection, GatewaysSection (block-only "Why?" expander + vendor request), PluginsSection, StatusBadge
    tools/              placeholder route component
  components/
    ui/                 shadcn primitives (kebab-case, CLI-generated)
    shared/             UnsavedChangesDialog, QueryDevtools, YayCheckoutToolTip; used by multiple pages
    layout/             Header, Footer, HeaderMenu (chrome)
    pro/                ProGate, upsell surfaces
    icons/
  hooks/
    useRouteLeaveGuard  react-router useBlocker for unsaved-changes guard on data router
    useUncontrolled     mantine-style controlled/uncontrolled pattern
    useCombinedRefs     ref combining utility
  lib/
    api/                ky calls per feature
    queries/            queryOptions + hooks per feature
    schema/             zod schemas mirroring PHP to_array()
    helpers/            error unwrapping, date formatting, clipboard with select-text fallback, form utilities
    logger.ts           namespaced console; error/warn kept in production
    plan.ts             Free/Pro, from the build-time constant
    query-client.ts     QueryClient + global error toasts
    design.* / settings.* / layout.* / template.* / compatibility.* (triplets)
  types/                window globals, module augmentation
```

Three files per feature in `lib/` — `{feature}.schema.ts`, `.api.ts`, `.queries.ts`. `settings.*`, `layout.*`, and `design.*` are the worked examples. `.schema.ts` holds both the strict snake_case `*WireSchema` and the camelCase app types; `.api.ts` is the only file that converts between them (see `code-standards.md`, REST). Companion detection (`useIntegrations()`) is read from the localize bootstrap, not a query — it cannot change without an admin reload.

The API client handles WordPress specifics: `X-WP-Nonce`, pretty vs. plain permalinks, and reloading on an expired nonce.

Query errors surface centrally — background refetch failures toast, first-load failures render inline, mutations always toast. `meta: { skipGlobalError: true }` opts out.

## Blocks (`packages/blocks/`)

Thirteen blocks: `checkout` (root, `multiple: false`), `contact`, `billing`, `shipping`, `field`, `order-review`, `extra-field`, `shipping-methods`, `express-pay`, `payment`, `order-summary`, `place-order`, `slot`. All dynamic (`save()` returns `null`), rendered by `render.php`. Built by `@wordpress/scripts`, which emits `index.asset.php` so `wp-*` dependencies are extracted. The insert guard enforces: YayCheckout blocks only on the layout CPT; leaves (`contact`, `billing`, `shipping`, `order-review`, `shipping-methods`, `express-pay`, `payment`, `order-summary`, `place-order`) directly under `checkout`; `field` under address sections only; `extra-field` and `slot` anywhere under `checkout`.

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

- Understanding storage → `includes/Models/Layout.php`, then `LayoutRepository::publish()` and `activate()`
- Adding a setting → `Models/Settings::schema()`, then `settings.schema.ts`, then a form field in `pages/settings/components/`
- Adding a design token → `Models/Design::schema()`, then `design.schema.ts`, then a component in `pages/design/components/`
- Adding an endpoint → `LayoutRestController`, then the `lib/` triplet
- Compatibility statuses → the bundled list in `Support/CompatibilityCatalogue`, or the remote JSON at `YAYCHECKOUT_COMPATIBILITY_CATALOGUE_URL`; new checkout hooks → `CheckoutHookScanHelper::HOOKS`
- Adding a block → `packages/blocks/src/`, then `BlockRegistry`, then contribute to `LayoutBlockIndex` via the filter
- Free/Pro → `includes/Support/Plan.php` and `scripts/check-pro-boundary.sh`

## Not yet built

Telemetry is a stored setting with no consumer yet (data is not sent). The Integrations tab detects YayBoost and YayLoyalty but those companions do not yet declare checkout slots. The Compatibility page has no self-test, report modal or header warning chip yet, and its remote catalogue URL is a placeholder, so every install runs on the bundled list until it is published. The storefront does not yet render design tokens in CSS.

`Controllers/LayoutTemplateCatalogue` is a fixed list of the 11 v1.1 templates (order matters: Free unlocks the first three), no database; the SPA draws each slug's layout sketch in `new-layout/template-thumbnail-sketches.tsx`, but `create_draft` still seeds the same default markup for every slug — repoint it at real template markup once that exists. The Health row action renders placeholder DOM but has no backend; Preview / Set active / Deactivate / Archive / Restore / Delete are wired to `/preview-link`, `/activate`, `/deactivate`, `/archive`, `/restore` and the delete endpoint; Edit, Publish, Search and pagination are fully functional. `restore()` deliberately does not re-check the Free-plan layout cap that `create()`/`duplicate()` enforce.

See the end of `system-architecture.md` for the full deferred list.
