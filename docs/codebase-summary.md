# Codebase Summary

Orientation map. `system-architecture.md` explains why the design is what it is; `code-standards.md` covers conventions.

WooCommerce checkout builder. WordPress 6.5+, PHP 7.4+, WooCommerce 10+, HPOS compatible.

## Layout

```
yaycheckout.php          bootstrap: constants, autoloader, admin shell, duplicate guard
uninstall.php            opt-in data removal on plugin delete (no autoloader; literals mirror Model constants)
includes/                plugin PHP (~6,000 lines)
apps/admin/              React settings SPA (Vite)      → assets/dist/admin/
packages/blocks/         editor blocks (@wordpress/scripts) → assets/dist/blocks/
templates/               checkout page template (theme header/footer around the layout); the root block is the form
assets/js/               hand-written storefront scripts (checkout-field-requirements.js)
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
| `Enums/` | `LayoutStatus`, `ConditionType` | constants classes (no native enums on PHP 7.4); `LayoutStatus` holds the WP `post_status` values; `ConditionType` defines the assignment rule vocabulary — condition types, their operators and value kinds |
| `PostTypes/` | `LayoutPostType` | registration only: caps, template, revision policy |
| `Models/` | `Layout`, `LayoutQuery`, `Settings`, `Design` | dumb entities; **every storage key constant lives here**; zero WP calls. `Settings` and `Design` own `schema()` (the single description of settings/tokens), `from_option()` (read projection through the schema), and `sanitize()` (strict validation, unknown keys stripped). `LayoutQuery` is immutable value object for filtered/paginated queries. |
| `Repositories/` | `LayoutRepository`, `SettingsRepository`, `DesignRepository`, `CompatibilityCatalogueRepository` | all storage access: layouts (publish sequence, active-set), settings (get/save/reviewed), design (tokens, presets, CSS), catalogue (transient → remote JSON → fallback) |
| `Controllers/` | `BaseRestController`, `SettingsRestController`, `LayoutRestController`, `LayoutCoreRestController`, `LayoutTemplateCatalogue`, `DesignRestController`, `CompatibilityRestController` | routing, validation, permissions; `BaseRestController` owns `permission_callback()` (checks `LayoutPostType::CAPABILITY` = `manage_woocommerce`) and `id_args()`; every controller inherits them |
| `Services/` | `LayoutMarkupReader`, `ExtraFieldSubmission`, `RequiredFieldOverlay`, `RequiredIndicator`, `LayoutAssignment` | pure: no storage. `LayoutMarkupReader` flattens a layout's blocks and answers `slots()`, `fields()`, `extra_fields()`, `order_modes()` (always `[]` for now) and `publish_error()`. `ExtraFieldSubmission::collect()` turns a checkout POST into order rows and per-field error messages, reading only Keys from `extra_fields()` and accepting only values from `LayoutMarkupReader::options()`. `RequiredFieldOverlay::apply()` rewrites WooCommerce's billing/shipping fieldsets so the layout's Field blocks decide what is required; `RequiredIndicator` prints the required/optional label suffixes and the payload for `assets/js/checkout-field-requirements.js`. `LayoutAssignment::pick()` returns the first active layout whose assignment rules match the shopper facts it is given; `LayoutRepository::active()` calls it on Pro only. |
| `Blocks/` | `BlockRegistry` | registers every built block directory (inserter order from `ORDER`); `allowed_blocks()` on `allowed_block_types_all` keeps `yaycheckout/*` blocks in the layout editor only and `woocommerce/checkout` out of it |
| `Support/` | `Plan`, `CompatibilityCatalogue` | Free/Pro flag derived from provider boot; owns the layout limit and its message. Compatibility catalogue: status vocabulary, bundled list, `normalize()` for the remote JSON, transient key and TTLs — pure |
| `Pro/` | `ProServiceProvider`, `Controllers/LayoutExportRestController`, `Controllers/LayoutImportRestController`, `Controllers/LayoutRulesRestController`, `Controllers/DesignPresetRestController`, `Services/LayoutImportReview`, `Services/AssignmentStoreData` | **absent from the Free build**; the provider registers Pro REST controllers on `rest_api_init`, adds the Assignment pickers' `store` data through `yaycheckout_localize_admin_data`, so a Free build has no route behind the editor's layout Import/Export panel, the admin SPA's Export card, the Layouts page's Assignment column drawer, or the Design page's preset actions |
| `Helpers/` | `BlockMarkup`, `DateHelper`, `IntegrationsHelper`, `LocalizeHelper`, `Logger`, `PreviewToken`, `StoreEnvironmentHelper`, `GatewayCompatibilityHelper`, `CheckoutHookScanHelper`, `PluginHeaderHelper` | stateless: GMT storage → ISO 8601 wire timestamps, preview tokens, server-side block markup (seeds, templates), integration detection, SPA bootstrap payload, logging, compatibility detection, hook scans |
| `Renderer/` | `CheckoutScripts`, `CheckoutPage`, `DesignStyles`, `PreviewRequest`, `CheckoutFragments`, `CheckoutExtraFields`, `CheckoutRequiredFields` | Storefront takeover and AJAX; `CheckoutFragments` swaps payment/shipping fragments on `update_order_review` when the layout renders those blocks. `CheckoutExtraFields` validates the served layout's extra fields, stores them as order meta on `woocommerce_checkout_create_order`, and prints them on the order screens. `CheckoutRequiredFields` hands `woocommerce_checkout_fields` to `RequiredFieldOverlay` when a layout is served. |
| `Engine/` | `RestAPI`, `ActDeact`, `LayoutCapGuard`, `LayoutTrashSync`, `LayoutActiveGuard`, `Admin/` (Settings, LayoutEditorEmbed), `Register/` (RegisterFacade, RegisterDev, RegisterProd, ScriptName) | wiring, asset registration, the layout cap, trashed layouts kept out of WP's purge, active-set integrity, embedded-editor chrome and REST tagging, dev/prod asset registration |
| `Utils/` | `SingletonTrait` | |
| `Fallback.php` | duplicate-guard admin notice | shown when another YayCheckout copy is installed; wired in yaycheckout.php |
| `I18n.php` | textdomain loader | loaded via plugins_loaded, handles i18n with locale detection |

Most classes use `SingletonTrait` and are constructed from `YayCheckout::__construct()`.

### Entry points

- `yaycheckout.php` → `plugin_init()` on `plugins_loaded`, after a WooCommerce check; also registers `I18n::load_plugin_textdomain()` on `plugins_loaded`
- `YayCheckout::__construct()` boots in order: `boot_pro()`, `RestAPI`, `RegisterFacade`, `Admin\Settings`, `BlockRegistry`, `LayoutPostType`, `LayoutCapGuard`, `LayoutTrashSync`, `LayoutActiveGuard`, `Admin\LayoutEditorEmbed`, `Renderer\PreviewRequest`, `Renderer\CheckoutScripts`, `Renderer\CheckoutPage`, `Renderer\CheckoutFragments`, `Renderer\CheckoutExtraFields`, `Renderer\CheckoutRequiredFields`
- `Engine\RestAPI` instantiates controllers on `rest_api_init`; Free: `SettingsRestController`, `LayoutRestController`, `DesignRestController`, `CompatibilityRestController`. Pro's controllers (`DesignPresetRestController`, `LayoutExportRestController`, `LayoutImportRestController`) are registered by `ProServiceProvider` on the same hook, so the Free build has none
- `Engine\Admin\Settings` enqueues the SPA and localises `yayCheckoutMeta` / `yayCheckoutAdmin`, gated on the admin-shell hook suffix
- `Engine\Register\RegisterFacade` branches to RegisterDev or RegisterProd on `init`, conditionally based on `YAYCHECKOUT_IS_DEVELOPMENT`

### REST

| Route | Method | Purpose |
|---|---|---|
| `wp/v2/yay_checkout_layout` | GET/POST | core: draft save, autosave, revisions |
| `yaycheckout/v1/layouts` | GET | list one page; args: `status` (comma list), `search` (string), `page` (int ≥1); envelope `{ items, total, page, total_pages }`. A row carries `blocks` (count of `yaycheckout/*` blocks in its markup) and `size` (bytes of markup) so the Tools cards need no detail fetch; every item (and `Layout::to_array()` anywhere) carries the layout's `rules` (`[]` when none) |
| `yaycheckout/v1/layouts` | POST | create draft seeded with contact, billing, shipping, order-review; args: `name`, `source` (blank\|library), `template_slug` |
| `yaycheckout/v1/layouts/counts` | GET | per-tab totals: `listed` (everything not archived), `archived`, and store-wide `active` |
| `yaycheckout/v1/layouts/templates` | GET | library catalogue for "New layout → From library" |
| `yaycheckout/v1/layouts/{id}` | GET | single layout |
| `yaycheckout/v1/layouts/{id}` | DELETE | permanent delete with revisions and meta; archived, non-active layouts only (400 otherwise) |
| `yaycheckout/v1/layouts/{id}/publish` | POST | status → `publish`; a layout dated in the future goes live now, its date moved to now; refused when `LayoutMarkupReader::publish_error()` objects (no `checkout` block, or an `extra-field` Key empty or duplicated), as is `/activate` on any layout, published or not; core's `wp/v2` save is refused the same way (400 `yaycheckout_layout_unpublishable`) when it would leave the layout published or scheduled |
| `yaycheckout/v1/layouts/{id}/activate` | POST | publish first unless `post_status` is already `publish`, then add to the active set |
| `yaycheckout/v1/layouts/{id}/deactivate` | POST | remove from the active set; status untouched |
| `yaycheckout/v1/layouts/{id}/archive` | POST | move to the archive (trash), deactivating it first if it was active |
| `yaycheckout/v1/layouts/{id}/reset` | POST | replace `post_content` with `LayoutRepository::default_markup()`; title, status, active flag and meta stay, the old markup becomes a revision; 404 when missing |
| `yaycheckout/v1/layouts/{id}/preview-link` | POST | `{ url, expires_at }`: the checkout URL with a fresh token, lifetime from `advanced.preview_link_lifetime`; any status, nothing stored |
| `yaycheckout/v1/layouts/{id}/export` | GET | **Pro.** `{ format, plugin_version, exported_at, layouts: [{ title, content, rules }] }`: the portable envelope the Tools page and the editor's Import / Export panel download as .json; `rules` is the assignment rules as stored (absent in files exported before they travelled); status, active flag and dates stay behind |
| `yaycheckout/v1/layouts/{id}/rules` | GET, PUT | **Pro.** `{ rules }`: the assignment rules (`_yaycheckout_rules`: groups ORed, conditions ANDed, vocabulary in `Enums\ConditionType`); PUT body `{ rules }` replaces them (validated by `Layout::sanitize_rules()`, `[]` clears), writing meta only |
| `yaycheckout/v1/layouts/import` | POST | **Pro.** body `{ file: <export envelope> }` → 201 with the new draft (`Layout::to_array()`) plus `rules_import: { status }`: the file's rules are kept as the file holds them when readable (`imported`, even values this store lacks), dropped whole when not (`dropped`, never per condition: that would widen a group), `none` when absent; 400 for a foreign or multi-layout file, or no `yaycheckout/checkout` block anywhere in the tree; any other block passes |
| `yaycheckout/v1/layouts/import/review` | POST | **Pro.** body `{ file: <export envelope> }` → 200 with `{ title, content, blocks, has_rules }` (from `LayoutImportReview::from_envelope()`); stores nothing; the file's rules are never returned, `has_rules` only says it has some, validates only; same 400 refusals as `/layouts/import` |
| `yaycheckout/v1/design` | GET/POST | envelope `{ tokens, preset_id, custom_css, updated_at, presets }`; POST replaces the whole design (all three keys required) |
| `yaycheckout/v1/design/presets` | POST | `{ name, tokens }`; returns full design envelope; Pro only |
| `yaycheckout/v1/design/presets/{id}` | DELETE | drop merchant preset; returns full design envelope; Pro only |
| `yaycheckout/v1/compatibility` | GET | `{ store, gateways, plugins, catalogue: { source, fetched_at } }`; arg `refresh` (bool) drops the cached catalogue first |
| `yaycheckout/v1/settings` | GET/POST | envelope `{ settings, updated_at }`; POST merges over stored settings, strips unknown keys, rejects wrong types/enum values with 400 |
| `yaycheckout/v1/mark-reviewed` | POST | review prompt dismissed |

All require `LayoutPostType::CAPABILITY` (`manage_woocommerce`).

## Admin SPA (`apps/admin/`)

React 18 + TypeScript, Vite, hash router, TanStack Query, zod, Tailwind 4, shadcn/ui, ky. (~8,400 lines excluding `components/ui`).

Routes: `/` → `/layouts`, `/layouts`, `/design`, `/tools`, `/settings/{general|integrations|advanced}`, `/compatibility`, `*` (404).

```
src/
  main.tsx              root: QueryClientProvider, router, dev-only devtools
  router.tsx            hash routes; settings/:subMenu has tabs (general | integrations | advanced)
  AppLayout.tsx         chrome + Toaster
  PageLayout.tsx        common layout wrapper for pages
  pages/
    404.tsx  500.tsx    error pages
    layouts/            LayoutsPage (queries, filters, modals); assignment drawer and condition builder
      LayoutsPage.tsx   list with pagination, search, status tabs, Pro-only Assignment column (`assignment/AssignmentChips`, chips from each layout's `rules` wrapping onto more lines, "Everyone" when none); Edit/Preview/Publish/Set active/Deactivate/Archive/Restore/Delete for good real; Health placeholder DOM
      assignment/       AssignmentDrawer (Pro, `/layouts/{id}/rules`), ConditionGroup, AssignmentChips, OptionsMultiSelect, MultiSelectTrigger, condition-types.ts (vocabulary mirror and form shape)
      PreviewLinkModal.tsx  minted preview URL with Copy (select-text fallback on http) and Open
      SetActiveModal.tsx    Set active modal (real)
      DeactivateModal.tsx   Deactivate modal (real)
      ArchiveModal.tsx      Archive modal (real)
      DeleteModal.tsx       Delete-for-good modal: typed DELETE gate (real)
      LayoutStatusBadge.tsx status + active badge; shared with the Tools layout picker
      new-layout/       NewLayoutModal, SourceStep, LibraryStep, NameField, TemplateThumbnail + template-thumbnail-sketches (SVG sketch per catalogue slug)
    design/             Design tokens page (real)
      DesignPage.tsx    form with token groups, preset picker, custom CSS, Save/Reset actions
      components/       TokenGroups (summary + accordion), TokenField, PxInput; PresetsCard (built-in + merchant); ColoursGroup, TypographyGroup, SpacingGroup, ButtonsGroup, InputsGroup, CustomCssGroup; PreviewPane, DesignActions, SavePresetModal (Pro), ResetConfirmModal
    settings/           one form across tabs, Save in the panel footer, unsaved-changes guard
    compatibility/      CompatibilityPage: warning banner, Store / Payment gateways / Plugins touching checkout, Re-scan, catalogue footnote
      compatibility-warnings.ts   report → { blockOnly, unverifiedPlugins }
      components/       WarningBanner, StoreSection, GatewaysSection (block-only "Why?" expander + vendor request), PluginsSection, StatusBadge
    tools/              ToolsPage: Export + Import (Pro) or the ExportImportCard teaser (Free), and Reset (both builds)
      components/       ExportCard (download .json), ImportCard (drop/pick a .json, client-side parse + review), ExportImportCard (Free upsell), ResetCard + ResetModal (typed-phrase gate, warns on an active layout), LayoutSelect (searchable, infinite-scroll picker, starts empty)
      import-modal/     ImportModal with StepStrip, SourceStep, DraftStep: creates the draft from the parsed export envelope
  components/
    ui/                 shadcn primitives (kebab-case, CLI-generated)
    shared/             UnsavedChangesDialog, QueryDevtools, YayCheckoutToolTip, ConfirmPopover, TrashButton; used by multiple pages
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
    helpers/            error unwrapping, date formatting, clipboard with select-text fallback, form utilities, `infinite-list` (`loadMoreOnScroll()` for paged pickers)
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

Thirteen blocks: `checkout` (root, `multiple: false`), `contact`, `billing`, `shipping`, `field`, `order-review`, `extra-field`, `shipping-methods`, `express-pay`, `payment`, `order-summary`, `place-order`, `slot`. All dynamic, rendered by `render.php`: leaves save `null`; `checkout`, `billing` and `shipping` save only their `InnerBlocks` so children persist. Every block registers through `shared/register-dynamic-block.tsx`, which also sets its inserter icon from `shared/block-icons.tsx` (custom 24px SVGs keyed by block name). Built by `@wordpress/scripts`, which emits `index.asset.php` so `wp-*` dependencies are extracted. Placement comes from each `block.json` (`parent`/`ancestor`/`multiple`) and which editor offers the blocks from `BlockRegistry::allowed_blocks()`; see `system-architecture.md`.

The editor block bundle includes `checkout/import-export.tsx`, a `PluginDocumentSettingPanel` that registers only on the layout post type. It offers Import and Export buttons, locked in Free for looks only; the lock guards nothing, since Free has no `/export` or `/import/review` routes. Export uses `/layouts/{id}/export` (disabled while unsaved); Import picks a .json file, posts it to `/layouts/import/review` for validation, then replaces the editor's blocks unsaved (undoable). Content only: the layout keeps its own assignment rules, and a file that carries rules gets a notice pointing to Tools → Import.

`shared/export-file.ts` holds `filenameFromHeaders()` (reads the filename from `Content-Disposition`), `saveFile()` (downloads a blob), and `isJsonFileName()` (checks extension). It is shared with the admin SPA through the `@blocks-shared` alias (see `apps/admin/vite.config.ts`).

## Tooling

| Command | Does |
|---|---|
| `./scripts/run.sh dev` | admin shell update + Vite dev server on :3040 + blocks watcher (`wp-scripts start`) |
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
- Import/Export → `Pro/Controllers/LayoutImportRestController` (routes), `Pro/Services/LayoutImportReview` (validation), `checkout/import-export.tsx` (editor panel), `shared/export-file.ts` (download utilities); `Layout::block_count()` counts the exported blocks
- Adding a block → `packages/blocks/src/` (registered automatically once built; add it to `BlockRegistry::ORDER` for its inserter position, and give it an icon in `shared/block-icons.tsx`); a section block also goes in `LayoutMarkupReader::SECTION_SLOTS`
- Free/Pro → `includes/Support/Plan.php` and `scripts/check-pro-boundary.sh`; the editor's plan state comes from `window.yayCheckoutBlocks.isPro` printed by `BlockRegistry`; the SPA reads `upgradeUrl` from `yayCheckoutMeta.checkoutMeta.upgradeUrl`

## Not yet built

Telemetry is a stored setting with no consumer yet (data is not sent). The Integrations tab detects YayBoost and YayLoyalty but those companions do not yet declare checkout slots. The Compatibility page has no self-test, report modal or header warning chip yet, and its remote catalogue URL is a placeholder, so every install runs on the bundled list until it is published. The storefront prints design tokens as `--yaycheckout-*` CSS variables, but no block reads them yet.

`Controllers/LayoutTemplateCatalogue` is a fixed list of the 11 v1.1 templates (order matters: Free unlocks the first three), no database; the SPA draws each slug's layout sketch in `new-layout/template-thumbnail-sketches.tsx`, but `create_draft` still seeds the same default markup for every slug — repoint it at real template markup once that exists. The Health row action renders placeholder DOM but has no backend; Preview / Set active / Deactivate / Archive / Restore / Delete are wired to `/preview-link`, `/activate`, `/deactivate`, `/archive`, `/restore` and the delete endpoint; Edit, Publish, Search and pagination are fully functional. `restore()` deliberately does not re-check the Free-plan layout cap that `create()`/`duplicate()` enforce.

See the end of `system-architecture.md` for the full deferred list.
