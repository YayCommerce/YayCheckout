# The SPA departs from its own data-layer and placement conventions

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

Measured against `docs/conventions.md` and `.claude/rules/react-admin.md`:

- **Wire data outside the api layer:** `apps/admin/src/lib/queries/design.queries.ts` and `apps/admin/src/lib/queries/settings.queries.ts` parse `window.yayCheckoutAdmin` with the wire schemas and convert it with `*FromWire()` themselves. `apps/admin/src/pages/layouts/assignment/condition-types.ts` holds `groupsFromWire()` and `groupsToWire()`, and its `storeData()` reads `window.yayCheckoutAdmin.store` with no schema. `LayoutSummary.rules` in `apps/admin/src/lib/schema/layout.schema.ts` is typed `LayoutRulesWire`, so pages handle the wire shape.
- **Schemas:** `templateSchema` and `templateLibrarySchema` (`apps/admin/src/lib/schema/template.schema.ts`) are neither strict nor named `*WireSchema`. `layoutRulesWireSchema` isn't strict. `layoutCountsSchema` and `layoutCountsResponseSchema` are strict but unsuffixed. `integrationWireSchema` is loose on purpose, and its comment says why.
- **Calls outside the query layer:** `markReviewed()` and `markSetupDone()` in `apps/admin/src/lib/api/settings.api.ts` return `json<boolean>()` with no schema. `apps/admin/src/components/layout/Footer.tsx` and `apps/admin/src/components/setup-wizard/SetupWizard.tsx` call them directly, so a failure skips the mutation cache and is only `logger.warn`ed. A failed Skip setup brings the wizard back on the next load.
- **Placement:** `apps/admin/src/components/shared/TrashButton.tsx` has one user, `apps/admin/src/pages/layouts/assignment/ConditionGroup.tsx`. `apps/admin/src/pages/layouts/` has no components folder; its modals sit beside the page. `LayoutStatusBadge` lives in `apps/admin/src/pages/layouts/`, but the Tools page imports it too.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

Each exception is small, but each is a second pattern the next change may copy. A loose schema lets a new or misspelled PHP key through unnoticed, and the direct calls skip the one place that toasts and logs failures. A shared component in the wrong folder hides who depends on it.

## Suggested fix

Move the bootstrap parse into `apps/admin/src/lib/api/design.api.ts` and `apps/admin/src/lib/api/settings.api.ts`, beside `getDesign` and `getSettings`. Move the rule conversion and the store read into `apps/admin/src/lib/api/layout.api.ts`, and give `LayoutSummary.rules` an app type. Make the template and rules schemas strict, and suffix all four names. Turn the two mark calls into mutations in `apps/admin/src/lib/queries/settings.queries.ts`, with a reply schema. Move `TrashButton` beside its user, `LayoutStatusBadge` into `apps/admin/src/components/shared/`, and the Layouts page's own components into a components folder. Where the team keeps an exception instead, write it into `docs/conventions.md` and drop its box.

## Done when

- [ ] `git grep -nE 'WireSchema|FromWire|ToWire' -- apps/admin/src/lib/queries apps/admin/src/pages apps/admin/src/components` prints nothing.
- [ ] `git grep -n yayCheckoutAdmin -- apps/admin/src/lib/queries apps/admin/src/pages apps/admin/src/components` prints nothing.
- [ ] `LayoutSummary.rules` in `apps/admin/src/lib/schema/layout.schema.ts` has an app type, not `LayoutRulesWire`.
- [ ] Every schema in `apps/admin/src/lib/schema/` that parses a reply is named `*WireSchema` and is `.strict()`, except `integrationWireSchema`.
- [ ] `markReviewed()` and `markSetupDone()` parse their reply with a zod schema.
- [ ] `apps/admin/src/components/layout/Footer.tsx` and `apps/admin/src/components/setup-wizard/SetupWizard.tsx` import nothing from `apps/admin/src/lib/api/settings.api.ts`.
- [ ] `TrashButton.tsx` sits under `apps/admin/src/pages/layouts/`, beside its one user.
- [ ] `LayoutStatusBadge.tsx` sits under `apps/admin/src/components/shared/`.
- [ ] The Layouts page's own components sit in a components folder under `apps/admin/src/pages/layouts/`, as on the other pages.
