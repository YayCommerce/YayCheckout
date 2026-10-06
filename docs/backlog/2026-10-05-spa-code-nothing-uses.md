# The SPA carries components, helpers and localized values that nothing uses

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

- **Pro gates:** no file renders `ProGate`, `ProOnly`, `FreeOnly`, `ProBadge` or `UpgradePrompt` from `apps/admin/src/components/pro/ProGate.tsx`; `ProBadge` and `UpgradePrompt` are used only inside `ProGate`. Free's upsells use `ProTag` and `UpgradeButton` from the same file. `isFree`, `useIsPro` and the `Plan` type in `apps/admin/src/lib/plan.ts` have no callers.
- **UI:** nothing imports `attachment`, `avatar`, `bulk-actions`, `button-group`, `checkbox`, `date-picker`, `date-range-picker`, `scroll-area` or `tabs` from `apps/admin/src/components/ui/`, and `calendar` is used only by the two pickers. Only those files import `date-fns`, `react-day-picker`, `@radix-ui/react-avatar`, `@radix-ui/react-checkbox` and `@radix-ui/react-scroll-area`. `@radix-ui/react-tabs` is still needed: `apps/admin/src/pages/settings/SettingsPage.tsx` imports it directly.
- **Data layer:** `wordpressApi` in `apps/admin/src/lib/api/api.ts`, `useLayoutQuery` and `LAYOUT_QUERIES.detail` in `apps/admin/src/lib/queries/layout.queries.ts`, and `getLayout` in `apps/admin/src/lib/api/layout.api.ts` have no callers, so the SPA never calls `GET /layouts/{id}`. The docblock of `useDeleteLayoutMutation` still describes a mounted detail query.
- **Localized values:** `LocalizeHelper::get_meta()` (`includes/Helpers/LocalizeHelper.php`) sends `wpMeta.siteUrl`, `wpMeta.ajaxUrl`, `wpMeta.isBlockTheme`, `checkoutMeta.pluginUrl` and `checkoutMeta.version`, and the `Meta` type in `apps/admin/src/types/localize.type.ts` declares them, but no SPA code reads them.

Vite drops unused modules, so the bundle doesn't grow. Each name was checked with `git grep`. Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

A reader takes unused code for the pattern to follow: `.claude/rules/react-admin.md` still describes `<ProGate>`, `<ProOnly>` and `wordpressApi` as the way to do things. Unused dependencies still get audited and upgraded, and unused localized values look like a contract PHP must keep.

## Suggested fix

Delete what nothing uses, and the dependencies only it uses. Keep `ProTag` and `UpgradeButton`, in a file named after what it exports. Then update what describes the deleted parts: hard rule 3 in `CLAUDE.md`, the Pro and data-layer bullets in `.claude/rules/react-admin.md`, the `ProGate.tsx` example in `docs/conventions.md`, the Free and Pro section of `docs/architecture.md`, and the "SPA's ProGate honest" docblocks in `includes/Pro/Controllers/LayoutExportRestController.php` and `includes/Pro/Controllers/LayoutImportRestController.php`. If the team keeps `<ProGate>` for a coming Pro screen, drop the boxes that remove it.

## Done when

- [ ] `git grep -nwE 'ProGate|ProOnly|FreeOnly|ProBadge|UpgradePrompt|isFree|useIsPro' -- apps/admin/src` prints nothing.
- [ ] None of the nine unused `apps/admin/src/components/ui/` files above, nor `calendar.tsx`, exists.
- [ ] `apps/admin/package.json` lists none of `date-fns`, `react-day-picker`, `@radix-ui/react-avatar`, `@radix-ui/react-checkbox` and `@radix-ui/react-scroll-area`.
- [ ] `git grep -nwE 'wordpressApi|useLayoutQuery|getLayout' -- apps/admin/src .claude/rules` prints nothing.
- [ ] `LocalizeHelper::get_meta()` sends only keys the SPA reads, and the `Meta` type declares only those.
- [ ] `git grep -n ProGate -- CLAUDE.md .claude/rules docs/architecture.md docs/conventions.md includes` prints nothing.
