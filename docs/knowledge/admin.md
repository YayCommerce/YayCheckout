---
area: admin
updated: 2026-11-12
updated_ref: c7ee247
covers: [apps/admin/, includes/Engine/Admin/, includes/Engine/Register/, includes/Helpers/LocalizeHelper.php]
---
# Admin SPA

> Update when: the SPA's routing, data layer, bootstrap globals, build output, dev-server contract or Free/Pro gating changes.

`apps/admin/` is `@yaycheckout/admin-page`: React 18 + TypeScript, Vite, Tailwind 4, shadcn-style `components/ui/` (Radix + CVA + `cn()`), TanStack Query, react-hook-form + zod, and a `ky` client.

## Shape

- **Entry and routing.** `apps/admin/src/main.tsx` mounts into `#yaycheckout`. `apps/admin/src/router.tsx` uses a **hash router**, because everything stays on one wp-admin page (`yaycommerce_page_yaycheckout`). The routes are `layouts`, `tools`, `settings/:subMenu` and `compatibility`.
- **The block editor is not in the SPA.** The Layouts page opens `post.php?yc-embed=1` in an iframe (`Engine\Admin\LayoutEditorEmbed`), driven by `?edit=<id>&view=…`.
- **Bootstrap globals.** `Engine\Admin\Settings` localizes two globals, built by `Helpers\LocalizeHelper`:
  - `window.yayCheckoutMeta`: `wpMeta` with `restBase`, `restRoot` and the `wp_rest` nonce, plus `checkoutMeta`.
  - `window.yayCheckoutAdmin`: settings, integrations, templates, and in Pro `store`.
  - The TypeScript mirror is `apps/admin/src/types/localize.type.ts`.
- **API.** `apps/admin/src/lib/api/api.ts` sends `X-WP-Nonce`, handles plain permalinks (`?rest_route=`) and reloads on `rest_cookie_invalid_nonce`. Never use raw `fetch`. `wordpressApi` targets `wp/v2`.
- **Data, per domain:**

| File | What it holds |
|---|---|
| `lib/schema/<x>.schema.ts` | zod wire schema (snake_case, `.strict()`) and the camelCase app type |
| `lib/api/<x>.api.ts` | the calls, plus `fromWire` / `toWire`, which `.parse()` every response |
| `lib/queries/<x>.queries.ts` | query keys, `queryOptions` and hooks |

  Copy `settings.*` for a small domain and `layout.*` for a list.
- **Error policy.** It's set in `apps/admin/src/lib/query-client.ts`:
  - queries use `retry:false` and a 5-minute `staleTime`;
  - a query error toasts only on a background refetch;
  - mutations always toast;
  - opt out with `meta: { skipGlobalError: true }`;
  - use `getErrorMsg()` (`lib/helpers/response.helper.ts`) in catches.

## Free and Pro

- **The plan is decided at build time.** `__YAYCHECKOUT_PLAN__` comes from `YAYCHECKOUT_PLAN` (`apps/admin/vite.config.ts`).
  - The defaults are `pro` for `vite serve` and `free` for a build, so an unflagged build can't ship Pro UI. `release --plan` sets the variable.
  - Read the plan only through `apps/admin/src/lib/plan.ts` (`isPro`, `useIsPro`).
- **`ProGate`, `ProOnly` and `FreeOnly` are cosmetic.** Their children still ship in the Free bundle. Real Pro code goes behind `{isPro && <X/>}`, so the bundler drops it.
- `window.yayCheckoutAdmin.store` only exists in Pro, so the assignment code must stay behind `isPro`.

## Dev vs built

- **Dev.** `YAYCHECKOUT_IS_DEVELOPMENT` defaults to `true` in `yaycheckout.php`, so `Engine\Register\RegisterDev` loads `http://localhost:3040/main.tsx`.
  - Vite's `strictPort` is 3040.
  - **With no dev server, the admin page is blank.**
- **Built.** `RegisterProd` loads `assets/dist/admin/js/main.js` and `assets/dist/admin/style.css`.
  - The release flips the flag and deletes `RegisterDev.php`.
- **Externals.** In the build, React and `@wordpress/*` map to WordPress globals. The externals list must stay in sync with `PAGE_SETTINGS_DEPS` in `includes/Engine/Register/ScriptName.php`.
- **Translations.** JS translations are built from the `.mo` file by `RegisterFacade::use_mo_file_for_script_translations`, for the handles in its list only. A new script handle must be added there.
- **Tailwind scoping.** Utilities only apply under `.yay-ui`, which `Engine\Admin\Settings::admin_body_class` adds to the page body.

## Recipes

- **Add a setting:**
  1. Add the key to `Models\Settings::schema()`.
  2. Add it to both schemas in `lib/schema/settings.schema.ts`.
  3. Map it in `settingsFromWire` and `settingsToWire` (`lib/api/settings.api.ts`).
  4. Add a `FormField` + `SettingRow` in the tab, copying `pages/settings/components/GeneralTab.tsx`.

  The wire schema is `.strict()`, so a PHP/JS key mismatch throws on page load.
- **Add a settings tab:** add the slug to `pages/settings/settings-sub-menus.ts`, plus a `case` in `tabLabel` and a `Tabs.Content` in `SettingsPage.tsx`.
- **Add a page:** create `pages/<name>/<Name>Page.tsx` inside `PageLayout`, a route in `router.tsx` and a `NavLink` in `components/layout/HeaderMenu.tsx`. `pages/tools/ToolsPage.tsx` is a small example to copy.

## Traps

- **Don't lower the settings query's `gcTime`.** The settings query is seeded from the bootstrap with infinite `staleTime` and `gcTime`. If it were collected, it would reseed with the pre-save values.
- **Templates and integrations are bootstrap-only.** A change needs a page reload.
- **Some settings are hidden on purpose.** Safe mode and Share usage data stay in the schema without UI (`GeneralTab.tsx` says so). Don't add the missing UI.
- **Shared code reaches both bundles.** `@blocks-shared/*` is `packages/blocks/src/shared/`, so a change there affects the SPA and the blocks.
